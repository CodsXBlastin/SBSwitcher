<?php

declare(strict_types=1);

namespace THXC\SBSwitcher;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\entity\projectile\Snowball;
use pocketmine\event\entity\EntityDamageByChildEntityEvent;
use pocketmine\event\entity\ProjectileHitEntityEvent;
use pocketmine\event\entity\ProjectileLaunchEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\item\Item;
use pocketmine\item\ItemFactory;
use pocketmine\level\Position;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\ByteTag;
use pocketmine\network\mcpe\protocol\PlaySoundPacket;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;

class Main extends PluginBase implements Listener {
	protected $thrownSwitchers = [];
	/** @var Config */
	protected $config;

	public function onEnable(): void {
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		$this->config = new Config($this->getDataFolder() . "config.yml", Config::YAML, [
			"customName" => "Switch-A-Roo",
			"lore" => [
				"Throw at another player to switch spots"
			],
			"sound" => "mob.endermite.hit"
		]);
	}

	public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool {
		if($command->getName() == "gsb"){
			if(count($args) == 2){
				if(is_numeric($args[0])){
					$args[0] = (int)$args[0];

					$t = $sender->getServer()->getPlayer($args[1]);
					if($t instanceof Player){
						$i = ItemFactory::get(Item::SNOWBALL, 0, $args[0]);
						$i->setNamedTagEntry(new ByteTag("switcher", 0));
						$i->setCustomName((string)$this->config->get("customName"));
						$i->setLore($this->config->get("lore"));
						$t->getInventory()->addItem($i);
						$sender->sendMessage("Given " . $t->getName() . " x" . $args[0] . " of switcher snowballs");
					} else {
						$sender->sendMessage("Player is offline");
					}
				} else {
					$sender->sendMessage("Usage: " . $command->getUsage());
				}
			} else {
				$sender->sendMessage("Usage: " . $command->getUsage());
			}
		}
		return true;
	}

	public function onInteract(PlayerInteractEvent $ev): void {
		if($ev->getAction() == PlayerInteractEvent::RIGHT_CLICK_AIR && ($item = $ev->getItem())->getId() == Item::SNOWBALL) {
			if($item->getNamedTagEntry("switcher") !== null) {
				$this->thrownSwitchers[$ev->getPlayer()->getName()]["throwing"] = 1;
			}
		}
	}

	public function onThrow(ProjectileLaunchEvent $ev): void {
		$proj = $ev->getEntity();
		if($proj instanceof Snowball) {
			$owner = $proj->getOwningEntity();
			if($owner instanceof Player) {
				if($this->thrownSwitchers[$owner->getName()]["throwing"] === 1) {
					$this->thrownSwitchers[$owner->getName()][$proj->getId()] = 1;
					$this->thrownSwitchers[$owner->getName()]["throwing"] = 0;
				}
			}
		}
	}

	/**
	 * @param EntityDamageByChildEntityEvent $ev
	 *
	 * @priority HIGHEST
	 * @ignoreCancelled true
	 */
	public function onProjectileDamage(EntityDamageByChildEntityEvent $ev): void {
		$proj = $ev->getChild();
		if($proj instanceof Snowball) {
			$owner = $proj->getOwningEntity();
			if($owner instanceof Player && isset($this->thrownSwitchers[$owner->getName()][$proj->getId()])) {
				unset($this->thrownSwitchers[$owner->getName()][$proj->getId()]);
				$oPos = clone $owner->getPosition();
				$owner->teleport(($hit = $ev->getEntity()));
				self::playSound($hit, ($s = (string)$this->config->get("sound")));
				$hit->teleport($oPos);
				self::playSound($oPos, $s);

				unset($this->thrownSwitchers[$owner->getName()][$proj->getId()]);
			}
		}
	}

	protected static function playSound(Position $pos, string $soundName):void {
		$sPk = new PlaySoundPacket();
		$sPk->soundName = $soundName;
		$sPk->x = $pos->x;
		$sPk->y = $pos->y;
		$sPk->z = $pos->z;
		$sPk->volume = $sPk->pitch = 1;
		$pos->getLevel()->broadcastPacketToViewers($pos, $sPk);
	}
}
