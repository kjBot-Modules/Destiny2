<?php
namespace kjBotModule\kj415j45\Destiny2;

use kjBot\Framework\Module;
use kjBot\Framework\Event\MessageEvent;
use kjBot\SDK\CQCode;

class Season extends Module{
    public function process(array $args, MessageEvent $event){
        return $event->sendBack(
            CQCode::Image('https://www.bungie.net/7/ca/destiny/bgs/season11/S11_Calendar_Calendar_zh-chs.jpg', 0)
        );
    }
}
