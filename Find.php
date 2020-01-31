<?php
namespace kjBotModule\kj415j45\Destiny2;

use Exception;
use kjBot\Framework\Module;
use kjBot\Framework\Event\MessageEvent;
use kjBotModule\kj415j45\CoreModule\AccessControl;
use kjBotModule\kj415j45\CoreModule\AccessLevel;

class Find extends Module{
    public function process(array $args, MessageEvent $event){
        (new AccessControl($event))->requireLevel(AccessLevel::Supporter);
        $manifest = new Manifest(Config('bungieAPIkey'));
        try{
            $manifest->getLatestManifest();
        }catch(Exception $e){
            return [
                $event->sendBack('无法更新数据库，已通知master'),
                notifyMaster($e->getMessage()),
            ];
        }
        $result = $manifest->findID((int)$args[1]);
        $count = count($result);
        $msg = "共找到 {$count} 个定义";
        if($args[2] !== '--debug'){
            foreach($result as $name => $value){
                $msg.= "\n[{$name}]\n".Definition::Parse($value)."\n";
            }
        }else{
            foreach($result as $name => $value){
                $msg.= "\n[{$name}]\n".var_export($value, true)."\n";
            }
        }
        return $event->sendBack($msg);
    }
}