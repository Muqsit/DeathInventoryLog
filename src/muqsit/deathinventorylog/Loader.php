<?php

declare(strict_types=1);

namespace muqsit\deathinventorylog;

use muqsit\deathinventorylog\db\Database;
use muqsit\deathinventorylog\db\DatabaseFactory;
use muqsit\deathinventorylog\db\DeathInventory;
use muqsit\deathinventorylog\db\DeathInventoryLog;
use muqsit\deathinventorylog\translator\LocalGamertagUUIDTranslator;
use muqsit\deathinventorylog\translator\GamertagUUIDTranslator;
use muqsit\invmenu\InvMenu;
use muqsit\invmenu\InvMenuHandler;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\inventory\ArmorInventory;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\TextFormat;
use Ramsey\Uuid\Uuid;

final class Loader extends PluginBase implements Listener{

	private Database $database;
	private ?GamertagUUIDTranslator $translator = null;

	protected function onEnable() : void{
		if(!InvMenuHandler::isRegistered()){
			InvMenuHandler::register($this);
		}

		$factory = new DatabaseFactory();
		$db_identifier = $this->getConfig()->getNested("database.type");
		$this->database = $factory->create($this, $db_identifier, $this->getConfig()->getNested("database.{$db_identifier}"));

		$this->getServer()->getPluginManager()->registerEvents($this, $this);
	}

	protected function onDisable() : void{
		$this->database->close();
		$this->getTranslator()->close();
	}

	/**
	 * @param PlayerDeathEvent $event
	 * @priority LOWEST
	 */
	public function onPlayerDeath(PlayerDeathEvent $event) : void{
		$player = $event->getPlayer();
		$uuid = $player->getUniqueId();
		$name = $player->getName();
		$this->getTranslator()->store($uuid, $name);
		$this->database->store($uuid, new DeathInventory(
			$player->getInventory()->getContents(),
			$player->getArmorInventory()->getContents()
		), function(int $id) use($name, $uuid) : void{
			$this->getLogger()->debug("Stored death inventory [#{$id}] of {$name} [{$uuid->toString()}]");
		});
	}

	public function getTranslator() : GamertagUUIDTranslator{
		return $this->translator ??= new LocalGamertagUUIDTranslator($this);
	}

	public function setTranslator(GamertagUUIDTranslator $translator) : void{
		$this->translator = $translator;
	}

	public function getDatabase() : Database{
		return $this->database;
	}

	public function onCommand(CommandSender $sender, Command $command, string $label, array $args) : bool{
		if(isset($args[0])){
			switch($args[0]){
				case "history":
					if(!isset($args[1])){
						$sender->sendMessage(TextFormat::RED . "/{$label} {$args[0]} <player> [page=1]");
						return true;
					}

					$page = isset($args[2]) ? max(1, (int) $args[2]) : 1;
					$this->getTranslator()->translateGamertags([$args[1]], function(array $translations) use($page, $sender) : void{
						if($sender instanceof Player && !$sender->isConnected()){
							return;
						}

						$translation = current($translations);
						if($translation === false){
							$sender->sendMessage(TextFormat::RED . "No logs found for that player.");
							return;
						}

						static $per_page = 10;
						$offset = ($page - 1) * $per_page;
						$this->database->retrievePlayer(Uuid::fromBytes($translation), $offset, $per_page, static function(array $entries) use($offset, $page, $sender) : void{
							/** @var DeathInventoryLog[] $entries */
							if(count($entries) === 0){
								$sender->sendMessage($page === 1 ? TextFormat::RED . "No logs found for that player." : TextFormat::RED . "No logs found on page {$page} for that player.");
								return;
							}

							$message = TextFormat::BOLD . TextFormat::RED . "Death Entries Page {$page}" . TextFormat::RESET . TextFormat::EOL;
							foreach($entries as $entry){
								$message .= TextFormat::BOLD . TextFormat::RED . ++$offset . ". " . TextFormat::RESET . TextFormat::WHITE . "#{$entry->getId()} " . TextFormat::GRAY . "logged on " . gmdate("Y-m-d h:i:s", $entry->getUnixTimestamp()) . TextFormat::EOL;
							}
							$sender->sendMessage($message);
						});
					});
					return true;
				case "retrieve":
					if(!($sender instanceof Player)){
						$sender->sendMessage(TextFormat::RED . "This command can only be executed in-game.");
						return true;
					}

					if(!isset($args[1])){
						$sender->sendMessage(TextFormat::RED . "/{$label} {$args[0]} <id>");
						return true;
					}

					$id = (int) $args[1];
					$this->database->retrieve($id, static function(?DeathInventoryLog $log) use ($sender, $id) : void{
						if(!$sender->isConnected()){
							return;
						}

						if($log === null){
							$sender->sendMessage(TextFormat::RED . "No death log with the id #{$id} could be found.");
							return;
						}

						$sender->sendMessage(TextFormat::GRAY . "Retrieved death inventory log #{$log->getId()}");

						$items = $log->getInventory()->getInventoryContents();

						$armor_inventory = $log->getInventory()->getArmorContents();
						foreach([
							ArmorInventory::SLOT_HEAD => 47,
							ArmorInventory::SLOT_CHEST => 48,
							ArmorInventory::SLOT_LEGS => 50,
							ArmorInventory::SLOT_FEET => 51
						] as $armor_inventory_slot => $menu_slot){
							if(isset($armor_inventory[$armor_inventory_slot])){
								$items[$menu_slot] = $armor_inventory[$armor_inventory_slot];
							}
						}

						$menu = InvMenu::create(InvMenu::TYPE_DOUBLE_CHEST);
						$menu->setName("Death Inventory Log #{$log->getId()}");
						$menu->getInventory()->setContents($items);
						$menu->send($sender);
					});
					return true;
			}
		}

		$sender->sendMessage(
			TextFormat::BOLD . TextFormat::RED . "Death Inventory Log Commands" . TextFormat::RESET . TextFormat::EOL .
			TextFormat::WHITE . "/{$label} history <player> [page=1] " . TextFormat::GRAY . "Retrieve a player's death log history" . TextFormat::EOL .
			TextFormat::WHITE . "/{$label} retrieve <id> " . TextFormat::GRAY . "Retrieve inventory contents of a specific death log"
		);
		return true;
	}
}