<?php
namespace kjBotModule\kj415j45\Destiny2;

use kjBot\SDK\CQCode;

class Definition{
    const Bungie = 'https://www.bungie.net';

    static function Parse($array){
        $image = $array['displayProperties']['hasIcon']?CQCode::Image(Definition::Bungie.$array['displayProperties']['icon']):'';
        return <<<EOT
{$image}【{$array['displayProperties']['name']}】：{$array['displayProperties']['description']}
EOT;
    }
}