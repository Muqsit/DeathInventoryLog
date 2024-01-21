<?php

declare(strict_types=1);

namespace muqsit\deathinventorylog;

use Closure;
use Generator;
use muqsit\deathinventorylog\db\Database;
use muqsit\deathinventorylog\db\DatabaseFactory;
use muqsit\deathinventorylog\db\DeathInventory;
use muqsit\deathinventorylog\db\DeathInventoryLog;
use muqsit\deathinventorylog\purger\LogPurger;
use muqsit\deathinventorylog\purger\LogPurgerFactory;
use muqsit\deathinventorylog\purger\NullLogPurger;
use muqsit\deathinventorylog\translator\LocalGamertagUUIDTranslator;
use muqsit\deathinventorylog\translator\GamertagUUIDTranslator;
use muqsit\invmenu\InvMenu;
use muqsit\invmenu\InvMenuHandler;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\event\EventPriority;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\inventory\ArmorInventory;
use pocketmine\item\VanillaItems;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\Server;
use pocketmine\utils\TextFormat;
use pocketmine\utils\VersionString;
use Ramsey\Uuid\Uuid;
use SOFe\AwaitGenerator\Await;
use Symfony\Component\Filesystem\Path;
use function count;
use function current;
use function file_exists;
use function file_get_contents;
use function file_put_contents;
use function gmdate;

final class Loader extends PluginBase{

	private Database $database;
	private LogPurgerFactory $log_purger_factory;
	private LogPurger $log_purger;
	private ?GamertagUUIDTranslator $translator = null;

	protected function onLoad() : void{
		$this->log_purger = NullLogPurger::instance();
		$this->log_purger_factory = new LogPurgerFactory($this);
	}

	protected function onEnable() : void{
		$version_file = Path::join($this->getDataFolder(), ".version");
		if(!file_exists($version_file)){
			$version = file_exists(Path::join($this->getDataFolder(), "config.yml")) ? "0.1.1" : $this->getDescription()->getVersion();
			file_put_contents($version_file, $version);
			$previous_version = $current_version = new VersionString($version);
		}else{
			$previous_version = new VersionString(file_get_contents($version_file));
			$current_version = new VersionString($this->getDescription()->getVersion());
		}

		if(!InvMenuHandler::isRegistered()){
			InvMenuHandler::register($this);
		}

		$factory = new DatabaseFactory();
		$db_identifier = $this->getConfig()->getNested("database.type");
		$this->database = $factory->create($this, $db_identifier, $this->getConfig()->getNested("database.{$db_identifier}"));
		if($previous_version->compare($current_version) === 1){
			$this->getLogger()->notice("Upgrading from v{$previous_version->getFullVersion()} to v{$current_version->getFullVersion()}");
			$this->database->upgrade($previous_version, $current_version);
			file_put_contents($version_file, $current_version->getFullVersion());
		}

		if($this->log_purger instanceof NullLogPurger){ // don't set purger from configuration in case another plugin set a log purger already
			$purge_config = $this->getConfig()->get("auto-purge");
			if($purge_config === false){
				$purge_config = ["type" => "none"];
			}
			$this->setLogPurger($this->log_purger_factory->create($purge_config["type"], $purge_config[$purge_config["type"]] ?? []));
		}

		$this->getServer()->getPluginManager()->registerEvent(PlayerDeathEvent::class, function(PlayerDeathEvent $event) : void{
			$player = $event->getPlayer();
			$uuid = $player->getUniqueId();
			$name = $player->getName();
			$this->getTranslator()->store($uuid, $name);
			$this->database->store($uuid, new DeathInventory(
				$player->getInventory()->getContents(),
				$player->getArmorInventory()->getContents(),
				$player->getOffHandInventory()->getContents()
			), function(int $id) use($name, $uuid) : void{
				$this->getLogger()->debug("Stored death inventory [#{$id}] of {$name} [{$uuid->toString()}]");
			});
		}, EventPriority::LOWEST, $this);
	}

	protected function onDisable() : void{
		$this->setLogPurger(NullLogPurger::instance());
		$this->database->close();
		$this->getTranslator()->close();
	}

	public function setLogPurger(LogPurger $purger) : void{
		$this->log_purger->close();
		$this->log_purger = $purger;
	}

	public function getTranslator() : GamertagUUIDTranslator{
		return $this->translator ??= LocalGamertagUUIDTranslator::create($this);
	}

	public function setTranslator(GamertagUUIDTranslator $translator) : void{
		$this->translator = $translator;
	}

	public function getDatabase() : Database{
		return $this->database;
	}

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool
    {
        if (isset($args[0])) {
            switch ($args[0]) {
                case "history":
                    if (!isset($args[1])) {
                        $sender->sendMessage(TextFormat::RED . "/{$label} {$args[0]} <player> [page=1]");
                        return true;
                    }

                    $page = max(1, isset($args[2]) ? (int)$args[2] : 1);
                    $gamertags = [$args[1]];

                    Await::f2c(function() use($page, $sender, $gamertags) : Generator {
                        $per_page = 10;
                        $offset = ($page - 1) * $per_page;

                        $translation = yield from Await::promise(function(Closure $resolve) use($gamertags) : void {
                            $this->getTranslator()->translateGamertags($gamertags, $resolve);
                        });

                        if (empty($translation))
                        {
                            $sender->sendMessage("no results");
                            return false;
                        }
                        $entries = yield from Await::promise(function(Closure $resolve) use($offset, $per_page, $translation, $gamertags) : void {
                            $this->database->retrievePlayer(Uuid::fromBytes($translation[strtolower($gamertags[0])]), $offset, $per_page, $resolve);
                        });

                        if(count($entries) === 0){
                            $sender->sendMessage($page === 1 ? TextFormat::RED . "No logs found for that player." : TextFormat::RED . "No logs found on page {$page} for that player.");
                            return false;
                        }

                        if($sender instanceof Player && !$sender->isConnected()){
                            return false;
                        }

                        $message = TextFormat::BOLD . TextFormat::RED . "Death Entries Page {$page}" . TextFormat::RESET . TextFormat::EOL;
                        foreach($entries as $entry){
                            $message .= TextFormat::BOLD . TextFormat::RED . ++$offset . ". " . TextFormat::RESET . TextFormat::WHITE . "#{$entry->id} " . TextFormat::GRAY . "logged on " . gmdate("Y-m-d h:i:s", $entry->timestamp) . TextFormat::EOL;
                        }
                        $sender->sendMessage($message);
                    });
                    return true;
                case "restore":
                    return $this->executeRestoreCommand($sender, $label, $args);

                case "view":
                    return $this->executeViewCommand($sender,$label, $args);
            }
        }

        $sender->sendMessage(
            TextFormat::BOLD . TextFormat::RED . "Death Inventory Log Commands" . TextFormat::RESET . TextFormat::EOL .
            TextFormat::WHITE . "/{$label} history <player> [page=1] " . TextFormat::GRAY . "Retrieve a player's death log history" . TextFormat::EOL .
            TextFormat::WHITE . "/{$label} restore <player> <id> " . TextFormat::GRAY . "Retrieve inventory contents of a specific death log" . TextFormat::EOL .
            TextFormat::WHITE . "/{$label} view <id> " . TextFormat::GRAY . "Retrieve inventory contents of a specific death log"
        );
        return true;
    }

    private function executeRestoreCommand(CommandSender $sender,string $label, array $args): bool
    {
        if (!($sender instanceof Player)) {
            $sender->sendMessage(TextFormat::RED . "This command can only be executed in-game.");
            return true;
        }

        if (!isset($args[1]) || !isset($args[2]) || !is_numeric($args[2])) {
            $sender->sendMessage(TextFormat::RED . "/{$label} {$args[0]} <player> <id>");
            return true;
        }

        $player = Server::getInstance()->getPlayerByPrefix($args[1]);
        if (!$player instanceof Player) {
            $sender->sendMessage("§cPlayer offline");
            return false;
        }

        $id = (int)$args[2];
        $this->retrieveAndApplyLog($id, $sender, $player);
        return true;
    }

    private function executeViewCommand(CommandSender $sender, string $label, array $args): bool
    {
        if (!($sender instanceof Player)) {
            $sender->sendMessage(TextFormat::RED . "This command can only be executed in-game.");
            return true;
        }

        if (!isset($args[1]) || !is_numeric($args[1])) {
            $sender->sendMessage(TextFormat::RED . "/{$label} {$args[0]} <id>");
            return true;
        }

        $id = (int)$args[1];
        $this->retrieveAndDisplayLog($id, $sender, $args[0]);
        return true;
    }

    private function retrieveAndApplyLog(int $id, CommandSender $sender, Player $player): void
    {
        $this->database->retrieve($id, function (?DeathInventoryLog $log) use ($sender, $id, $player): void {
            if (!$player->isConnected()) {
                return;
            }

            if ($log === null) {
                $sender->sendMessage(TextFormat::RED . "No death log with the id #{$id} could be found.");
                return;
            }

            $sender->sendMessage(TextFormat::GRAY . "Retrieved death inventory log #{$log->id} to {$player->getName()}");
            $this->applyLogToPlayer($log, $player);
        });
    }

    private function retrieveAndDisplayLog(int $id, CommandSender $sender, string $command): void
    {
        $this->database->retrieve($id, function (?DeathInventoryLog $log) use ($sender, $id, $command): void {
            if ($log === null) {
                $sender->sendMessage(TextFormat::RED . "No death log with the id #{$id} could be found.");
                return;
            }

            $this->displayLogToSender($log, $sender, $command);
        });
    }

    private function applyLogToPlayer(DeathInventoryLog $log, Player $player): void
    {
        $items = $log->inventory->inventory_contents;
        $armor_inventory = $log->inventory->armor_contents;

        foreach ([
                     ArmorInventory::SLOT_HEAD => 47,
                     ArmorInventory::SLOT_CHEST => 48,
                     ArmorInventory::SLOT_LEGS => 50,
                     ArmorInventory::SLOT_FEET => 51,
                 ] as $armor_inventory_slot => $menu_slot) {
            if (isset($armor_inventory[$armor_inventory_slot])) {
                $items[$menu_slot] = $armor_inventory[$armor_inventory_slot];
            }
        }

        $items[53] = $log->inventory->offhand_contents[0] ?? VanillaItems::AIR();
        $player->getInventory()->setContents($items);
    }

    private function displayLogToSender(DeathInventoryLog $log, CommandSender $sender, string $command): void
    {
        if (!$sender instanceof Player) return;
        $items = $log->inventory->inventory_contents;
        $armor_inventory = $log->inventory->armor_contents;

        foreach ([
                     ArmorInventory::SLOT_HEAD => 47,
                     ArmorInventory::SLOT_CHEST => 48,
                     ArmorInventory::SLOT_LEGS => 50,
                     ArmorInventory::SLOT_FEET => 51,
                 ] as $armor_inventory_slot => $menu_slot) {
            if (isset($armor_inventory[$armor_inventory_slot])) {
                $items[$menu_slot] = $armor_inventory[$armor_inventory_slot];
            }
        }

        $items[53] = $log->inventory->offhand_contents[0] ?? VanillaItems::AIR();

        if ($command === "view") {
            $menu = InvMenu::create(InvMenu::TYPE_DOUBLE_CHEST);
            $menu->setName("Death Inventory Log #{$log->id}");
            $menu->getInventory()->setContents($items);
            $menu->send($sender);
        }
    }


}