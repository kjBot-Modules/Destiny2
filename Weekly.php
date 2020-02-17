<?php
namespace kjBotModule\kj415j45\Destiny2;

use DateTime;
use DateTimeZone;
use Exception;
use kjBot\Framework\DataStorage;
use kjBot\Framework\Module;
use kjBot\Framework\Event\MessageEvent;
use kjBotModule\kj415j45\CoreModule\AccessControl;
use kjBotModule\kj415j45\CoreModule\AccessLevel;

class Weekly extends Module{
    private $publicMilestones; //公共里程碑列表
    private $activitiesList; //可用活动列表
    private $manifest;

    protected $flashPoint; //闪点
    protected $strike; //日落严酷考验
    protected $nightfall; //老日落
    protected $nightmare; //梦魇狩猎
    protected $raid; //周RAID挑战
    protected $crucible; //熔炉竞技场轮转列表
    protected $clanRaid; //公会RAID挑战
    protected $dreamingCity; //TODO 梦城状态
    protected $menagerie; //动物园
    //protected $reckoning; //大清算

    public function process(array $args, MessageEvent $event){
        $forceUpdate = false;
        if(($args[1] == '--force-update')
        && (new AccessControl($event))->hasLevel(AccessLevel::Developer)){
            $forceUpdate = true;
        }
        try{
            $msg = $this->doRealThings($forceUpdate);
        }catch(Exception $e){
            return [
                $event->sendBack('无法更新周报'),
                notifyMaster($e->getMessage()),
            ];
        }
        return $event->sendBack($msg);
    }

    private function doRealThings($forceUpdate = false){
        if(!$forceUpdate && !$this->needUpdate()) {
            return $this->readWeeklyFromSave();
        }

        $this->getLatestManifest();
        $this->getPublicMilestones();

        $weekly = '';

        $weekly.= $this->getRaid();
        $weekly.= $this->getFlashpoint();
        
        $weekly.= $this->getActivities();
        //TODO

        $this->saveWeekly(trim($weekly));
        $this->setNextExpireTime();
        return trim($weekly);
    }

    private function getLatestManifest(){
        $this->manifest = new Manifest(Config('bungieAPIkey'));
        $this->manifest->getLatestManifest();
    }

    private function getActivities($image = false){
        $elementBurn = ''; //周常先锋元素焦灼
        $anotherElementBurn = ''; //英雄隐藏本焦灼
        $nightmareStr = '';
        $nightmares = []; //周轮换梦魇狩猎
        $crucible = ''; //熔炉竞技场轮转列表
        $strike = []; //日落严酷考验
        $nightfalls = []; //老日落
        $championsModifiers = [605585258, 882588556, 1930311099, 2687456355, 2834348323, 3605663348, 3933343183];
        $championsType = [605585258, 882588556, 3933343183];

        $activitiesList = $this->getActivitiesList();

        foreach($activitiesList as $activity){
            $activityInfo = $this->manifest->findID($activity['activityHash'])['DestinyActivityDefinition']; //获取正在轮询活动的信息
            if($elementBurn === '' && $activity['activityHash'] == 352668024){ //以动物园普通难度为筛选目标
                $elementBurn = $this->manifest->findID($activity['modifierHashes'][0]) //FIXME 直接取首位修改器的写法有风险
                               ['DestinyActivityModifierDefinition']['displayProperties']['name'];
                continue;
            }

            if($anotherElementBurn === '' && $activity['activityHash'] == 2731208666){ //以全面爆发为筛选目标
                $anotherElementBurn = $this->manifest->findID($activity['modifierHashes'][0])
                               ['DestinyActivityModifierDefinition']['displayProperties']['name'];
                continue;
            }

            if(preg_match('/梦魇狩猎：(\S+): 大师/', $activityInfo['displayProperties']['name'], $matches) >= 1){ //匹配梦魇狩猎
                $nightmares[]= [
                    'name' => $matches[1],
                    'info' => $activityInfo,
                    'inList' => $activity,
                ];
                continue;
            }
    
            if($activityInfo['displayProperties']['name'] === '日落：严酷考验: 大师'){ //匹配日落严酷考验
                $strike = [
                    'info' => $activityInfo,
                    'inList' => $activity,
                ];
                continue;
            }

            if(preg_match('/日落: (\S+)/', $activityInfo['displayProperties']['name'], $matches) >= 1){ //匹配老日落
                $nightfalls[]= [
                    'name' => $matches[1],
                    'info' => $activityInfo,
                    'inList' => $activity,
                ];
                continue;
            }

            if(2777041980 == $activityInfo['destinationHash'] //匹配熔炉竞技场
            && 4088006058 == $activityInfo['placeHash']){ //虽然说一个匹配规则就够了
                switch($activityInfo['hash']){
                    case 1859507212: //私人比赛（熔炉）
                    case 3176544780: //占领
                    case 2304691867: //经典混合
                    case 1478171612: //灭绝
                    case 135537449:  //生存
                    case 740891329:  //生存：自由竞技
                    case 914148167:  //混战
                        continue; //排除掉六个核心列表和私人比赛
                    default: //剩余的都是轮转列表（包括铁旗）
                        $crucible .= $activityInfo['displayProperties']['name'].' ';
                }
            }

            if($activity['activityHash'] == 2509539864){ //英雄动物园（阿鲁纳）
                $menagerie = '【奇珍异兽园（阿鲁纳）】 烈日焦灼 团灭 饥荒 掷雷手';
                continue;
            }
            if($activity['activityHash'] == 2509539865){ //英雄动物园（帕格里）
                $menagerie = '【奇珍异兽园（帕格里）】 电弧焦灼 团灭 连连看 羸弱';
                continue;
            }
            if($activity['activityHash'] == 2509539867){ //英雄动物园（哈萨匹克）
                $menagerie = '【奇珍异兽园（哈萨匹克）】 虚空焦灼 团灭 钢铁意志 管制';
                continue;
            }

            /* 修改器为每日变更，待移动到Daily
            if($activity['activityHash'] == 1446606128){ //大清算三阶
                $reckoningModifiersStr = '';
                foreach($activity['modifierHashes'] as $modifier){
                    $reckoningModifiersStr.= ' '.$this->manifest->findID($modifier)['DestinyActivityModifierDefinition']['displayProperties']['name'];
                }
                $reckoningStr = '【大清算】'.ltrim($reckoningModifiersStr);
                continue;
            }
            */
        }

        //梦魇狩猎相关开始
        $nightmareCommonModifier = "[公共修改器]";
        $nightmareCommonModifiers = array_diff( //排除掉勇士类修改器
            array_intersect( //三个梦魇的共同修改器
                $nightmares[0]['inList']['modifierHashes'], 
                $nightmares[1]['inList']['modifierHashes'], 
                $nightmares[2]['inList']['modifierHashes']
            ),
            $championsModifiers, [2821775453] //排除掉“大师难度修改器”
        );
        foreach($nightmareCommonModifiers as $modifier){
            $nightmareCommonModifier.= ' '.$this->manifest->findID($modifier)['DestinyActivityModifierDefinition']['displayProperties']['name'];
        }
        $nightmareStr.= $nightmareCommonModifier."\n";

        foreach($nightmares as $nightmare){
            $nightmaresStr = "【{$nightmare['name']}】";
            $champions = array_intersect( //取得该难度的勇士类型
                $championsType, 
                $nightmare['inList']['modifierHashes']
            );

            $nightmaresStr.= $this->championFilterToString($champions);

            $nightmareStr.= $nightmaresStr."\n";
        }
        //梦魇狩猎相关结束

        //日落：严酷考验相关开始
        $strikeStr = "【日落：严酷考验】";

        $strikeCommonModifiersStr = '[修改器]';
        $strikeCommonModifiers = array_diff( //排除掉勇士类修改器
            $strike['inList']['modifierHashes'],
            $championsModifiers, [2821775453] //排除掉“大师难度修改器”
        );
        foreach($strikeCommonModifiers as $modifier){
            $strikeCommonModifiersStr.= ' '.$this->manifest->findID($modifier)['DestinyActivityModifierDefinition']['displayProperties']['name'];
        }

        $champions = array_intersect( //取得该难度的勇士类型
            $championsType, 
            $strike['inList']['modifierHashes']
        );
        $strikeStr.= "{$strike['info']['displayProperties']['description']}(".trim($this->championFilterToString($champions)).")\n";
        $strikeStr.= $strikeCommonModifiersStr;
        //日落：严酷考验相关结束

        //老日落相关开始
        $nightfallStr = '【日落】';
        foreach($nightfalls as $nightfall){
            if(!isset($nightfall['inList']['modifierHashes'])){ //排除掉指导模式的老日落
                continue;
            }

            $nightfallStr .= $nightfall['name'].' ';
        }
        $nightfallStr = rtrim($nightfallStr);
        //老日落相关结束

        return static::GenerateWeeklyFragment("游戏列表", [
            "{$elementBurn} 正作用于英雄难度探险、英雄剧情任务、奇珍异兽园普通难度和先锋打击。",
            "{$anotherElementBurn} 正作用于英雄难度冥冥低语和行动时刻。",
            '【熔炉竞技场】'.rtrim($crucible),
            $menagerie,
            //$reckoningStr,
            $nightfallStr,
            $strikeStr,
            static::GenerateWeeklyFragment('梦魇狩猎', [
                $nightmareStr,
            ]),
        ]);
    }

    private function championFilterToString(array $champions): string{
        $championsStr = '';
        foreach($champions as $champion){
            switch($champion){
                case 605585258:
                    $championsStr.= ' 屏障勇士';
                    break;
                case 882588556:
                    $championsStr.= ' 超载勇士';
                    break;
                case 3933343183:
                    $championsStr.= ' 势不可挡勇士';
                    break;
                default:
                    $championsStr.= ' 未知勇士'.$champion;
            }
        }
        return $championsStr;
    }

    private function getActivitiesList(){
        $json = file_get_contents('https://www.bungie.net/Platform/Destiny2/3/Profile/4611686018489331546/Character/2305843009507084169/?components=CharacterActivities', false, stream_context_create([
            'http' => [
                'header' => 'X-API-Key: '.Config('bungieAPIkey'),
            ]
        ]));

        if(false === $json){
            throw new Exception('Can not get activities list');
        }

        DataStorage::SetData('Destiny2.Weekly/ActivitiesList.json', $json);
        $activitiesList = json_decode($json, true, 512, JSON_BIGINT_AS_STRING)['Response']['activities']['data']['availableActivities'];
        $this->activitiesList = $activitiesList;

        return $activitiesList;
    }

    private function getFlashpoint($image = false){
        $flashpointInfo = $this->publicMilestones['463010297'];
        $flashpoints = [];
        foreach($flashpointInfo['availableQuests'] as $flashpoint){
            $flashpointQuest = $this->manifest->findID($flashpoint['questItemHash'])['DestinyInventoryItemDefinition'];
            $flashpointName = $flashpointQuest['displayProperties']['name'];
            $flashpointDescription = $this->manifest->findID($flashpointQuest['setData']['itemList'][0]['itemHash'])['DestinyInventoryItemDefinition']['displayProperties']['description'];
            $flashpointReward = $this->manifest->findID($flashpointQuest['value']['itemValue'][0]['itemHash'])['DestinyInventoryItemDefinition']['displayProperties']['name'];
            $flashpoints[]= "【{$flashpointName}】{$flashpointDescription}\n奖励：{$flashpointReward}";
        }

        $flashpointStr = implode("\n", $flashpoints);

        if(!$image){
            return static::GenerateWeeklyFragment('闪点', [
                $flashpointStr,
            ]);
        }else{

            return null; //TODO
        }
    }

    private function getRaid($image = false){
        $raid = '';
        $raid.= trim($this->getLewiathan($image))."\n";
        $raid.= trim($this->getGardenOfSalvation($image))."\n";
        $raid.= trim($this->getClanRAID($image));

        return static::GenerateWeeklyFragment('RAID', [
            $raid,
        ]);
    }

    private function getRefreshedToken($refreshToken){
        $bungieClientId = Config('bungieCID');
        $bungieClientSecret = Config('bungieCS');
        $web = file_get_contents('https://www.bungie.net/platform/app/oauth/token/', false, stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => 'Content-Type: application/x-www-form-urlencoded',
                'content' => "grant_type=refresh_token&client_id={$bungieClientId}&client_secret={$bungieClientSecret}&refresh_token={$refreshToken}",
            ]
        ]));
        
        if(false == $web){
            throw new Exception('Can not refresh oauth token');
        }

        $json = json_decode($web, true);
        if(null == $json){
            throw new Exception('Something wrong with refresh token or bungie down');
        }
        return $json;
    }

    private function getHawthorneQuests(){
        $quests = [];
        $bungieOAuth = json_decode(DataStorage::GetData('Destiny2.Weekly/token.json'), true);
        $bungieOAuth = $this->getRefreshedToken($bungieOAuth['refresh_token']);
        DataStorage::SetData('Destiny2.Weekly/token.json', json_encode($bungieOAuth));
        $apikey = Config('bungieAPIkey');

        $web = file_get_contents('https://www.bungie.net/Platform/Destiny2/3/Profile/4611686018489331546/Character/2305843009507084169/Vendors/3347378076/?components=VendorSales', false, stream_context_create([
            'http' => [
                'header' => "X-API-Key: {$apikey}\nAuthorization: {$bungieOAuth['token_type']} {$bungieOAuth['access_token']}",
            ]
        ]));

        if(false === $web){
            throw new Exception('Can not fetch clan raid challenge');
        }
        $json = json_decode($web, true);
        if(null == $json){
            throw new Exception('Something wrong with token or bungie down');
        }

        $datas = $json['Response']['sales']['data'];

        return $datas;
    }

    private function getClanRAID($image = false){
        $clanRaid = '';

        $challenges = $this->getHawthorneQuests();

        foreach($challenges as $challenge){
            if($challenge['costs'][0]['itemHash'] == 3159615086 && $challenge['costs'][0]['quantity'] == 1000){ //FIXME 这个取法可能存在风险
                switch($challenge['itemHash']){
                    //sotp
                    case 1348944144:
                        $quests[]= '【往日之苦】死守防线：伯特扎市阶段（老一）地图倒计时不能低于一半';
                    break;
                    case 3415614992:
                        $quests[]= '【往日之苦】姐妹齐心：获取保险库权限阶段（老三）每个人都要取得一次不同的相位辐射';
                    break;
                    case 1381881897:
                        $quests[]= '【往日之苦】各有所好：暴动首领阶段（老四）每个人只能打破一个弱点';
                    break;
                    //cos
                    case 2459033425:
                        $quests[]= '【忧愁王冠】有限祝福：邪魔族仪式阶段（老一）同时不能有超过两名守护者拥有女巫的祝福';
                    break;
                    case 2459033426:
                        $quests[]= '【忧愁王冠】大获全胜：消灭幻影阶段（老三）击杀幻影的那轮输出阶段击破护盾五次';
                    break;
                    case 2459033427:
                        $quests[]= '【忧愁王冠】全力以赴：消灭加尔兰阶段（老四）输出阶段每个人只能参与打一次手';
                    break;
                    //lw
                    case 2836954349:
                        $quests[]= '【最后一愿】召唤仪式：击败卡莉阶段（老一）占满⑨个台子，杀死⑨个骑士，并在输出卡莉之前消灭全部虫瘤';
                    break;
                    case 1250327262:
                        $quests[]= '【最后一愿】哪位女巫：击败秋露·知阶段（老二）不能被Boss攻击命中';
                    break;
                    case 3871581136:
                        $quests[]= '【最后一愿】永恒战斗：击败摩格斯阶段（老三）不能打死小虫瘤';
                    break;
                    case 1568895666:
                        $quests[]= '【最后一愿】请勿入内：保险库阶段（老四）不能让骑士进入中央大厅';
                    break;
                    case 4007940282:
                        $quests[]= '【最后一愿】记忆的力量：击败魅痕阶段（老五）不能打同一个眼睛两次';
                    break;
                }
            }else{
                continue;
            }
        }

        
        if(!$image){
            return static::GenerateWeeklyFragment('公会挑战', [
                implode("\n", $quests),
            ]);
        }else{
            //TODO
        }
    }

    private function getGardenOfSalvation($image = false){
        $gos = $this->publicMilestones['2712317338'];

        $challenges = [];
        foreach($gos['activities'][0]['modifierHashes'] as $challenge){
            switch($challenge){
                case 2095683347:
                    $challenges[]= '【剩余物】避开崇圣首脑阶段（老一）Boss所在区域召唤的炮台不能打，在前三个区域共六个';
                    break;
                case 405180260:
                    $challenges[]= '【枷锁的一环】召唤崇圣首脑阶段（老二）六人必须同时刷新启蒙Buff，可以不在同一个继电器处';
                    break;
                case 2472478405:
                    $challenges[]= '【登顶】消灭崇圣首脑阶段（老三）每次存入荧光必须是十个';
                    break;
                case 4080157289:
                    $challenges[]= '【从零到一百】消灭圣洁首脑阶段（老四）每个继电器都必须在十秒内存满';
                    break;
                default:
                    $challenges[]= '尚未记录该挑战：'.$challenge;
                    break;
            }
        }

        $challengesStr = implode("\n", $challenges);

        if(!$image){
            return static::GenerateWeeklyFragment('救赎花园', [
                $challengesStr,
            ]);
        }else{
            //TODO
        }
    }

    private function getLewiathan($image = false){
        $lewithan = '';
        $lewithan.= $this->getBaseLewiathan($image);
        //$lewithan.= $this->getWorldEater($image);
        //$lewithan.= $this->getStarsSpire($image); //TODO 似乎世吞和星塔是一个巅峰挑战
        return $lewithan;
    }

    //FIXME 写法有问题，棒鸡API返回也有问题
    private function getWorldEater($image = false){
        $worldEater = $this->publicMilestones['2986584050'];

        $modifiers = [];

        foreach($worldEater['activities'][2]['modifierHashes'] as $modifier){ //FIXME 直接取第3个活动的方式存在风险
            $modifierInfo = $this->manifest->findID($modifier)['DestinyActivityModifierDefinition'];
            $modifiers[]= "【{$modifierInfo['displayProperties']['name']}】{$modifierInfo['displayProperties']['description']}";
        }

        $modifiersStr = implode("\n", $modifiers);

        if(!$image){
            return static::GenerateWeeklyFragment('世界吞噬者/星之塔：巅峰', [ //TODO 似乎世吞和星塔是一个巅峰挑战
                $modifiersStr,
            ]);
        }else{
            //TODO
        }
    }

    private function getBaseLewiathan($image = false){
        $lewiathan = $this->publicMilestones['3660836525'];

        $phaseOrder = [];
        foreach($lewiathan['activities'][0]['phaseHashes'] as $phase){
            switch($phase){
                case 1431486395:
                    $phaseOrder[]= '铁拳厅';
                    break;
                case 2188993306:
                    $phaseOrder[]= '皇家水池';
                    break;
                case 3847906370:
                    $phaseOrder[]= '欢愉花园';
                    break;
                case 4231923662:
                    $phaseOrder[]= '王座';
                    break;
                default:
                    $phaseOrder[]= '真的有其他阶段吗？？？'.$phase;
                    break;
            }
        }

        $challenges = [];
        foreach($lewiathan['activities'][0]['modifierHashes'] as $challenge){
            switch($challenge){
                case 2863316929:
                    $challenges[]= '【铁拳厅挑战】不能站同一个台子两次';
                    break;
                case 3296085675:
                    $challenges[]= '【皇家水池挑战】必须有一位玩家全程站在中央水池中';
                    break;
                case 871205855:
                    $challenges[]= '【欢愉花园挑战】每个棱镜只能点亮一朵花';
                    break;
                case 2770077977:
                    $challenges[]= '【王座挑战】输出阶段必须同时站上四个台子';
                    break;
                default:
                    $challenges[]= '真的有其他挑战吗？？？'.$challenge;
                    break;
            }
        }

        $phaseOrderStr = implode(' ', $phaseOrder);
        $challengesStr = implode("\n", $challenges);
        if(!$image){
            return static::GenerateWeeklyFragment('利维坦', [
                $phaseOrderStr,
                $challengesStr,
            ]);
        }else{

            return null; //TODO
        }
    }

    protected function setNextExpireTime(){
        $expireTime = new DateTime('Wednesday next week 01:00:00', new DateTimeZone('Asia/Shanghai'));
        DataStorage::SetData('Destiny2.Weekly/expireTime', $expireTime->format('c'));
    }

    protected function needUpdate(){
        $_expireTime = DataStorage::GetData('Destiny2.Weekly/expireTime');
        if($_expireTime === false) return true;
        $expireTime = new DateTime($_expireTime, new DateTimeZone('Asia/Shanghai'));
        $now = new DateTime('now', new DateTimeZone('Asia/Shanghai'));
        $refreshTime = new DateTime('Wednesday 01:00:00', new DateTimeZone('Asia/Shanghai'));

        if( ( ($expireTime->format('W') < $now->format('W')) || ($now->format('W') == 1) ) //不同周，或是本周是新年第一周
            || ($now > $refreshTime) //或已经过了本周的刷新时间
        )return true;
        else return false;
    }

    protected function getPublicMilestones(){
        $json = file_get_contents('https://www.bungie.net/Platform/Destiny2/Milestones/', false, stream_context_create([
            'http' => [
                'header' => 'X-API-Key: '.Config('bungieAPIkey'),
            ]
        ]));
        if(false === $json){
            throw new Exception('Can not get public milestones');
        }
        DataStorage::SetData('Destiny2.Weekly/PublicMilestones.json', $json);
        $publicMilestones = json_decode($json, true, 512, JSON_BIGINT_AS_STRING)['Response'];
        $this->publicMilestones = $publicMilestones;

        return $publicMilestones;
    }

    private static function GenerateWeeklyFragment(string $fragmentName, array $strs): string{
        return "\n『{$fragmentName}』\n".implode("\n", $strs)."\n";
    }

    protected function readWeeklyFromSave(){
        return DataStorage::GetData('Destiny2.Weekly/weekly.txt');
    }

    protected function saveWeekly($weekly){
        return DataStorage::SetData('Destiny2.Weekly/weekly.txt', $weekly);
    }
}
