<?php

namespace TeleMarket;

use pocketmine\block\Block;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\item\Item;
use pocketmine\Player;
use pocketmine\Server;
use pocketmine\tile\ItemFrame;

class EventListener implements Listener {

    public function __construct(TeleMarket $plugin) {
        $this->plugin = $plugin;
    }

    public function onTouch(PlayerInteractEvent $ev) {
        $block = $ev->getBlock();
        $x = $block->getFloorX();
        $y = $block->getFloorY();
        $z = $block->getFloorZ();
        $level = $block->getLevel();
        $player = $ev->getPlayer();
        $key = "{$x}:{$y}:{$z}:{$level->getFolderName()}";
        if ($ev->getAction() == PlayerInteractEvent::RIGHT_CLICK_BLOCK) {
            if ($this->plugin->isMode($ev->getPlayer())) {
                if ($block->getId() == Block::ITEM_FRAME_BLOCK) {
                    if ($this->plugin->isMarket($key)) {
                        $ev->setCancelled(true);
                        $this->plugin->FixMarketBlock($block);
                        $player->sendMessage("{$this->plugin->pre} 해당 위치에 상점이 이미 존재합니다.");
                        return false;
                    } else {
                        if ($block->getId() == Block::AIR) {
                            $player->sendMessage("{$this->plugin->pre} 공기는 판매할 수 없습니다.");
                            return false;
                        }
                        $tile = $level->getTile($block);
                        $item = $player->getInventory()->getIteminHand();
                        $item->setCount(1);
                        $this->plugin->setMarket($key, $item);
                        $tile->setItem($this->plugin->settingMarketItem($item));
                        $ev->setCancelled(true);
                        $player->sendMessage("{$this->plugin->pre} 성공적으로 상점을 설치하였습니다.");
                    }
                }
            } else {
                if ($this->plugin->isMarket($key)) {
                    $ev->setCancelled(true);
                    $this->plugin->FixMarketBlock($block);
                    $this->plugin->MarketUI($player, $this->plugin->getMargetItem($key));
                }
            }
        } else {
            if ($this->plugin->isMarket($key)) {
                $ev->setCancelled(true);
                if ($player->isOp()) {
                    $tile = $level->getTile($block);
                    $tile->setItem(new Item(0, 0));
                    $this->plugin->delMarket($key);
                    $player->sendMessage("{$this->plugin->pre} 성공적으로 상점을 제거하였습니다.");
                }
            }
        }
    }

}
