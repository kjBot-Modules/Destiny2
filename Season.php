<?php
namespace kjBotModule\kj415j45\Destiny2;

use kjBot\Framework\Module;
use kjBot\Framework\Event\MessageEvent;
use kjBot\SDK\CQCode;

class Season extends Module{
    public function process(array $args, MessageEvent $event){
        return $event->sendBack(
            CQCode::Image('https://www.bungie.net/pubassets/pkgs/133/133107/Destiny_SoW_Calendar_ZH_TW.jpg')
        );
    }
}
