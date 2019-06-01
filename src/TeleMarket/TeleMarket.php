<?PHP

namespace TeleMarket;

use pocketmine\block\Block;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\item\Item;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;
use TeleMoney\TeleMoney;
use UiLibrary\UiLibrary;

class TeleMarket extends PluginBase {

    private static $instance = null;
    public $pre = "§e•";
    //public $pre = "§l§e[ §f시스템 §e]§r§e";
    public $count = 0;

    public static function getInstance() {
        return self::$instance;
    }

    public function onLoad() {
        self::$instance = $this;
    }

    public function onEnable() {
        $this->getServer()->getPluginManager()->registerEvents(new EventListener($this), $this);
        @mkdir($this->getDataFolder());
        $this->market = new Config($this->getDataFolder() . "market.yml", Config::YAML, ["count" => 0, "market" => []]);
        $this->mdata = $this->market->getAll();
        $this->item = new Config($this->getDataFolder() . "item.yml", Config::YAML, ["id" => [], "item" => []]);
        $this->idata = $this->item->getAll();
        $this->price = new Config($this->getDataFolder() . "price.yml", Config::YAML);
        $this->pdata = $this->price->getAll();
        $this->count = $this->mdata["count"];
        $this->ui = UiLibrary::getInstance();
        $this->money = TeleMoney::getInstance();
    }

    public function onDisable() {
        $this->save();
    }

    public function save() {
        $this->mdata["count"] = $this->count;
        $this->market->setAll($this->mdata);
        $this->market->save();
        $this->item->setAll($this->idata);
        $this->item->save();
        $this->price->setAll($this->pdata);
        $this->price->save();
    }

    public function isMode(Player $player) {
        return isset($this->mode[$player->getName()]);
    }

    public function setMarket(string $key, Item $item) {
        if ($this->isMarket($key))
            return false;
        if (!$this->isRegisterItem($item))
            $this->registerItem($item);
        $itemId = $this->getIdToItem($item);
        $this->mdata["market"][$key] = $itemId;
        return true;
    }

    public function isMarket(string $key) {
        return isset($this->mdata["market"][$key]);
    }

    public function isRegisterItem(Item $item) {
        $data = $this->ConvertItemToData($item);
        return isset($this->idata["id"][$data]);
    }

    public function ConvertItemToData(Item $item) {
        $data = "{$item->getId()}/|/{$item->getDamage()}/|/" . base64_encode($item->getCompoundTag());
        return $data;
    }

    public function registerItem(Item $item) {
        if ($this->isRegisterItem($item))
            return false;
        $data = $this->ConvertItemToData($item);
        $this->count++;
        $this->idata["id"][$data] = $this->count;
        $this->idata["item"][$this->count] = $data;
        $this->pdata[$this->count] = "-1:-1";
        return true;
    }

    public function getIdToItem(Item $item) {
        if (!$this->isRegisterItem($item))
            return null;
        $data = $this->ConvertItemToData($item);
        return $this->idata["id"][$data];
    }

    public function delMarket(string $key) {
        unset($this->mdata["market"][$key]);
    }

    public function FixMarketBlock(Block $block) {
        $key = "{$block->getFloorX()}:{$block->getFloorY()}:{$block->getFloorZ()}:{$block->getLevel()->getFolderName()}";
        if ($block->getId() == Block::ITEM_FRAME_BLOCK && $this->isMarket($key)) {
            $item = $this->getMargetItem($key);
            $tile = $block->getLevel()->getTile($block);
            $tile->setItem($this->settingMarketItem($item));
        }
    }

    public function getMargetItem($key) {
        if (!$this->isMarket($key))
            return null;
        $itemId = $this->getMargetId($key);
        return $this->getItemToId($itemId);
    }

    public function getMargetId($key) {
        if (!$this->isMarket($key))
            return null;
        return $this->mdata["market"][$key];
    }

    public function getItemToId(int $id) {
        if (!isset($this->idata["item"][$id]))
            return null;
        $data = $this->idata["item"][$id];
        return $this->ConvertDataToItem($data);
    }

    public function ConvertDataToItem(string $data) {
        $data = explode("/|/", $data);
        return Item::get($data[0], $data[1], 1, base64_decode($data[2]));
    }

    public function settingMarketItem(Item $item) {
        if ($item->hasCompoundTag())
            $itemName = $item->getCustomName();
        else
            $itemName = $item->getName();
        if ($this->getBuyPrice($this->getIdToItem($item)) < 0)
            $buyprice = "§c구매 불가";
        else
            $buyprice = $this->getBuyPrice($this->getIdToItem($item));
        if ($this->getSellPrice($this->getIdToItem($item)) < 0)
            $sellprice = "§c판매 불가";
        else
            $sellprice = $this->getSellPrice($this->getIdToItem($item));
        $itemName .= "\n§r§l§c▶ §r§f구매 : {$buyprice}";
        $itemName .= "\n§r§l§c▶ §r§f판매 : {$sellprice}";
        $item->clearNamedTag();
        $item->setCustomName($itemName);
        return $item;
    }

    public function getBuyPrice(int $id) {
        if (!isset($this->pdata[$id]))
            return null;
        return explode(":", $this->pdata[$id])[0];
    }

    public function getSellPrice(int $id) {
        if (!isset($this->pdata[$id]))
            return null;
        return explode(":", $this->pdata[$id])[1];
    }

    public function MarketUI(Player $player, Item $item) {
        if (!$this->isRegisterItem($item))
            return false;
        $itemId = $this->getIdToItem($item);
        $buyprice = $this->getBuyPrice($itemId);
        $sellprice = $this->getSellPrice($itemId);
        $playerMoney = $this->money->getMoney($player->getName());
        if ($buyprice < 0)
            $buyCount = "§c구매 불가§f";
        else
            $buyCount = (int) (floor($playerMoney / $buyprice));
        if ($sellprice < 0)
            $sellCount = "§c판매 불가§f";
        else {
            if (!$player->getInventory()->contains($item)) {
                $sellCount = 0;
            } else {
                $count = 0;
                foreach ($player->getInventory()->all($item) as $key => $value) {
                    if ($player->getInventory()->getSize() <= $key) {
                        continue;
                    }
                    $count += $value->getCount();
                }
                $sellCount = $count;
            }
        }
        $this->Market[$player->getId()] = $item;
        if ($player instanceof Player) {
            $form = $this->ui->CustomForm(function (Player $player, array $data) {
                $item = $this->Market[$player->getId()];
                $itemId = $this->getIdToItem($item);
                unset($this->Market[$player->getId()]);
                if (!isset($data[1])) return false;
                if (!is_numeric($data[1]) || $data[1] <= 0) {
                    $player->sendMessage("{$this->pre} 갯수는 양수여야합니다.");
                    return false;
                }
                $item->setCount($data[1]);
                if ($data[2] == true) {
                    if ($this->getSellPrice($itemId) < 0) {
                        $player->sendMessage("{$this->pre} 판매가 불가능한 아이템입니다.");
                        return false;
                    } else {
                        if (!$player->getInventory()->contains($item)) {
                            $player->sendMessage("{$this->pre} 판매할 아이템 갯수가 부족합니다.");
                            return false;
                        }
                    }
                    $price = $this->getSellPrice($itemId) * $data[1];
                    $this->check($player, $item, "판매");
                } else {
                    if ($this->getBuyPrice($itemId) < 0) {
                        $player->sendMessage("{$this->pre} 구매가 불가능한 아이템입니다.");
                        return false;
                    }
                    $price = $this->getBuyPrice($itemId) * $data[1];
                    if ($this->money->getMoney($player->getName()) < $price) {
                        $player->sendMessage("{$this->pre} 테나가 부족합니다.");
                        return false;
                    }
                    if (!$player->getInventory()->canAddItem($item)) {
                        $player->sendMessage("{$this->pre} 인벤토리의 공간이 부족합니다.");
                        return false;
                    }
                    $this->check($player, $item, "구매");
                }
            });
            $form->setTitle("Tele Market");
            $form->addLabel("§l§c▶ §r§f상점을 이용합니다.\n  아래 입력창에 갯수를 입력하고, 상점 모드를 선택한후,\n  아래의 버튼을 선택해주세요.");
            $form->addInput("§l§a▶ §r§f소지한 테나 : {$playerMoney}테나\n  구매 가능한 갯수 : {$buyCount}\n  판매 가능한 갯수 : {$sellCount}", "아이템 갯수");
            $form->addToggle("상점 모드 ( 구매 / 판매 )", false);
            $form->sendToPlayer($player);
        }
    }

    private function check(Player $player, Item $item, string $type) {
        if (!$this->isRegisterItem($item))
            return false;
        if ($type == "구매") {
            if ($item->hasCompoundTag())
                $itemName = $item->getCustomName();
            else
                $itemName = $item->getName();
            $this->Market[$player->getId()] = $item;
            $form = $this->ui->ModalForm(function (Player $player, array $data) {
                $item = $this->Market[$player->getId()];
                $itemId = $this->getIdToItem($item);
                if ($item->hasCompoundTag())
                    $itemName = $item->getCustomName();
                else
                    $itemName = $item->getName();
                unset($this->Market[$player->getId()]);
                if ($data[0] == true) {
                    $price = $this->getBuyPrice($itemId) * $item->getCount();
                    $this->money->reduceMoney($player->getName(), $price);
                    $player->getInventory()->addItem($item);
                    $player->sendMessage("{$this->pre} {$price}테나로 {$itemName} §r§e{$item->getCount()}개를 구매하였습니다.");
                } else {
                    return false;
                }
            });
            $form->setTitle("Tele Market");
            $form->setContent("\n§r§l§a▶ §r§f{$itemName} §r§f{$item->getCount()}개를 구매하시겠습니까?");
            $form->setButton1("§l§8[예]");
            $form->setButton2("§l§8[아니오]");
            $form->sendToPlayer($player);
        } elseif ($type == "판매") {
            if ($item->hasCompoundTag())
                $itemName = $item->getCustomName();
            else
                $itemName = $item->getName();
            $this->Market[$player->getId()] = $item;
            $form = $this->ui->ModalForm(function (Player $player, array $data) {
                $item = $this->Market[$player->getId()];
                $itemId = $this->getIdToItem($item);
                if ($item->hasCompoundTag())
                    $itemName = $item->getCustomName();
                else
                    $itemName = $item->getName();
                unset($this->Market[$player->getId()]);
                if ($data[0] == true) {
                    $price = $this->getSellPrice($itemId) * $item->getCount();
                    $this->money->addMoney($player->getName(), $price);
                    $player->getInventory()->removeItem($item);
                    $player->sendMessage("{$this->pre} {$price}테나로 {$itemName} §r§e{$item->getCount()}개를 판매하였습니다.");
                } else {
                    return false;
                }
            });
            $form->setTitle("Tele Market");
            $form->setContent("\n§r§l§a▶ §r§f{$itemName} §r§f{$item->getCount()}개를 판매하시겠습니까?");
            $form->setButton1("§l§8[예]");
            $form->setButton2("§l§8[아니오]");
            $form->sendToPlayer($player);
        }
    }

    public function onCommand(CommandSender $sender, Command $command, $label, $args): bool {
        if ($command->getName() == "상점생성") {
            if ($sender->isOp()) {
                if (isset($this->mode[$sender->getName()])) {
                    unset($this->mode[$sender->getName()]);
                    $sender->sendMessage("{$this->pre} 상점생성을 중단하였습니다.");
                } else {
                    $this->mode[$sender->getName()] = true;
                    $sender->sendMessage("{$this->pre} 상점생성을 시작합니다.");
                    $sender->sendMessage("{$this->pre} 아이템액자나 표지판을 터치하면 상점이 생성됩니다.");
                }
                return true;
            }
            return false;
        } elseif ($command->getName() == "구매가") {
            if (!isset($args[0]) || !is_numeric($args[0])) {
                $sender->sendMessage("{$this->pre} 구매가는 숫자여야합니다.");
                return false;
            }
            if (($item = $sender->getInventory()->getIteminHand())->getId() == Block::AIR) {
                $sender->sendMessage("{$this->pre} 손에 아이템을 들고있지 않습니다.");
                return false;
            }
            if (!$this->isRegisterItem($item))
                $this->registerItem($item);
            $itemId = $this->getIdToItem($item);
            $this->setBuyPrice($itemId, $args[0]);
            if ($args[0] < 0)
                $price = "구매 불가로";
            else
                $price = $args[0] . "테나로";
            if ($item->hasCompoundTag())
                $itemName = $item->getCustomName();
            else
                $itemName = $item->getName();
            $sender->sendMessage("{$this->pre} {$itemName}§r§e의 구매가격을 {$price} 설정하였습니다.");
            return true;
        } elseif ($command->getName() == "판매가") {
            if (!isset($args[0]) || !is_numeric($args[0])) {
                $sender->sendMessage("{$this->pre} 판매가는 숫자여야합니다.");
                return false;
            }
            if (($item = $sender->getInventory()->getIteminHand())->getId() == Block::AIR) {
                $sender->sendMessage("{$this->pre} 손에 아이템을 들고있지 않습니다.");
                return false;
            }
            if (!$this->isRegisterItem($item))
                $this->registerItem($item);
            $itemId = $this->getIdToItem($item);
            $this->setSellPrice($itemId, $args[0]);
            if ($args[0] < 0)
                $price = "판매 불가로";
            else
                $price = $args[0] . "테나로";
            if ($item->hasCompoundTag())
                $itemName = $item->getCustomName();
            else
                $itemName = $item->getName();
            $sender->sendMessage("{$this->pre} {$itemName}§r§e의 판매 가격을 {$price} 설정하였습니다.");
            return true;
        }
    }

    public function setBuyPrice(int $id, int $amount) {
        if (!isset($this->pdata[$id]))
            return false;
        $price = explode(":", $this->pdata[$id]);
        if ($amount < 0)
            $amount = -1;
        $this->pdata[$id] = "{$amount}:{$price[1]}";
    }

    public function setSellPrice(int $id, int $amount) {
        if (!isset($this->pdata[$id]))
            return false;
        $price = explode(":", $this->pdata[$id]);
        if ($amount < 0)
            $amount = -1;
        $this->pdata[$id] = "{$price[0]}:{$amount}";
    }
}
