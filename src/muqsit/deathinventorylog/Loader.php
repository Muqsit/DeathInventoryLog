<?php

declare(strict_types=1);

namespace muqsit\deathinventorylog;

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
use muqsit\deathinventorylog\util\WeakCommandSender;
use muqsit\invmenu\InvMenu;
use muqsit\invmenu\InvMenuHandler;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\event\EventPriority;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\inventory\ArmorInventory;
use pocketmine\item\VanillaItems;
use pocketmine\permission\Permission;
use pocketmine\permission\PermissionManager;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\ClosureTask;
use pocketmine\utils\TextFormat;
use pocketmine\utils\VersionString;
use Ramsey\Uuid\Uuid;
use RuntimeException;
use SOFe\AwaitGenerator\Await;
use SOFe\AwaitGenerator\Mutex;
use Symfony\Component\Filesystem\Path;
use function assert;
use function count;
use function current;
use function file_exists;
use function file_get_contents;
use function file_put_contents;
use function gmdate;
use function is_numeric;
use function max;
use function spl_object_id;
use function strtolower;

final class Loader extends PluginBase{

	private Database $database;
	private LogPurgerFactory $log_purger_factory;
	private LogPurger $log_purger;
	private ?GamertagUUIDTranslator $translator = null;

	private Permission $permission_read_history_self;
	private Permission $permission_read_history_others;
	private Permission $permission_retrieve_contents;

	/** @var array<int, Mutex> */
	private array $command_locks = [];

	protected function onLoad() : void{
		$this->log_purger = NullLogPurger::instance();
		$this->log_purger_factory = new LogPurgerFactory($this);

		$manager = PermissionManager::getInstance();
		$this->permission_read_history_self = $manager->getPermission("deathinventorylog.read.self") ?? throw new RuntimeException("Could not find permission");
		$this->permission_read_history_others = $manager->getPermission("deathinventorylog.read.other") ?? throw new RuntimeException("Could not find permission");
		$this->permission_retrieve_contents = $manager->getPermission("deathinventorylog.retrieve.contents") ?? throw new RuntimeException("Could not find permission");
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

		$this->getServer()->getPluginManager()->registerEvent(PlayerQuitEvent::class, function(PlayerQuitEvent $event) : void{
			unset($this->command_locks[spl_object_id($event->getPlayer())]);
		}, EventPriority::MONITOR, $this);
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

	public function onCommand(CommandSender $sender, Command $command, string $label, array $args) : bool{
		$lock = $this->command_locks[spl_object_id($sender)] ??= new Mutex();
		if(!$lock->isIdle()){
			$sender->sendMessage(TextFormat::GRAY . "You are executing this command too quickly. Please wait a moment before retrying.");
			return true;
		}

		switch($args[0] ?? ""){
			case "history":
				if($sender instanceof Player && count($args) === 1){ // dil history
					$gamertag = $sender->getName();
					$page = 1;
				}elseif($sender instanceof Player && count($args) > 1 && is_numeric($args[1])){ // dil history 2
					$gamertag = $sender->getName();
					$page = max(1, (int) $args[1]);
				}elseif(count($args) > 1 && !is_numeric($args[1])){ // dil history steve [page=1]
					$gamertag = $args[1];
					$page = max(1, (int) ($args[2] ?? 1));
				}else{
					break;
				}
				if(!$sender->hasPermission($this->permission_read_history_self) && strtolower($gamertag) === strtolower($sender->getName())){
					break;
				}
				if(!$sender->hasPermission($this->permission_read_history_others) && strtolower($gamertag) !== strtolower($sender->getName())){
					break;
				}
				assert($page > 0);
				Await::g2c($lock->run($this->sendHistory(new WeakCommandSender($sender), $gamertag, $page)));
				return true;
			case "retrieve":
				if(!isset($args[1])){
					break;
				}
				if(!($sender instanceof Player)){
					$sender->sendMessage(TextFormat::RED . "This command can only be executed in-game.");
					return true;
				}
				Await::g2c($lock->run($this->sendContents(new WeakCommandSender($sender), (int) $args[1])));
				return true;
		}

		$choices = [];
		if($sender->hasPermission($this->permission_read_history_self)) $choices[] = TextFormat::WHITE . "/{$label} history [page=1] " . TextFormat::GRAY . "List your own death log history";
		if($sender->hasPermission($this->permission_read_history_others)) $choices[] = TextFormat::WHITE . "/{$label} history <player> [page=1] " . TextFormat::GRAY . "List a player's death log history";
		if($sender->hasPermission($this->permission_read_history_self) || $sender->hasPermission($this->permission_read_history_others)) $choices[] = TextFormat::WHITE . "/{$label} retrieve <id> " . TextFormat::GRAY . "Retrieve inventory contents of a specific death log";
		if(count($choices) === 0){
			$sender->sendMessage(TextFormat::RED . "You do not have permission to use this command.");
		}else{
			$sender->sendMessage(TextFormat::BOLD . TextFormat::RED . "Death Inventory Log Commands" . TextFormat::RESET . TextFormat::EOL . implode(TextFormat::EOL, $choices));
		}
		return true;
	}

	/**
	 * @return Generator<mixed, Await::RESOLVE|Await::REJECT, void, void>
	 */
	private function sleep() : Generator{
		yield from Await::promise(fn($resolve) => $this->getScheduler()->scheduleDelayedTask(new ClosureTask($resolve), 20));
	}

	/**
	 * @param WeakCommandSender $sender
	 * @param string $gamertag
	 * @param positive-int $page
	 * @return Generator<mixed, Await::RESOLVE|Await::REJECT, void, void>
	 */
	private function sendHistory(WeakCommandSender $sender, string $gamertag, int $page) : Generator{
		static $per_page = 10;
		$offset = ($page - 1) * $per_page;
		$translation = current(yield from $this->getTranslator()->translateGamertagsAsync([$gamertag]));
		if($translation !== false){
			$entries = yield from $this->database->retrievePlayerAsync(Uuid::fromBytes($translation), $offset, $per_page);
		}else{
			$entries = [];
		}

		/** @var DeathInventoryLog[] $entries */
		if(count($entries) === 0){
			$sender->sendMessage($page === 1 ? TextFormat::RED . "No logs found for that player." : TextFormat::RED . "No logs found on page {$page} for that player.");
		}else{
			$message = TextFormat::BOLD . TextFormat::RED . "Death Entries Page {$page}" . TextFormat::RESET . TextFormat::EOL;
			foreach($entries as $entry){
				$message .= TextFormat::BOLD . TextFormat::RED . ++$offset . ". " . TextFormat::RESET . TextFormat::WHITE . "#{$entry->id} " . TextFormat::GRAY . "logged on " . gmdate("Y-m-d H:i:s", $entry->timestamp) . TextFormat::EOL;
			}
			$sender->sendMessage($message);
		}
		yield from $this->sleep();
	}

	/**
	 * @param WeakCommandSender<Player> $sender
	 * @param int $id
	 * @return Generator<mixed, Await::RESOLVE|Await::REJECT, void, void>
	 */
	private function sendContents(WeakCommandSender $sender, int $id) : Generator{
		$log = yield from $this->database->retrieveAsync($id);
		$sender_player = $sender->get();
		if(!($sender_player instanceof Player)){
			return;
		}

		if(!($log instanceof DeathInventoryLog)){
			$sender->sendMessage(TextFormat::RED . "No death log with the id #{$id} could be found.");
			yield from $this->sleep();
			return;
		}

		if(!$sender_player->hasPermission($this->permission_read_history_self) && $log->uuid->equals($sender_player->getUniqueId())){
			$sender->sendMessage(TextFormat::RED . "You do not have permission to read this log.");
			yield from $this->sleep();
			return;
		}

		if(!$sender_player->hasPermission($this->permission_read_history_others) && !$log->uuid->equals($sender_player->getUniqueId())){
			$sender->sendMessage(TextFormat::RED . "You do not have permission to read this log.");
			yield from $this->sleep();
			return;
		}

		$items = $log->inventory->inventory_contents;
		$armor_inventory = $log->inventory->armor_contents;
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

		$items[53] = $log->inventory->offhand_contents[0] ?? VanillaItems::AIR();

		$menu = InvMenu::create(InvMenu::TYPE_DOUBLE_CHEST);
		if(!$sender_player->hasPermission($this->permission_retrieve_contents)){
			$menu->setListener(InvMenu::readonly());
		}
		$menu->setName("Death Inventory Log #{$log->id}");
		$menu->getInventory()->setContents($items);

		$sender->sendMessage(TextFormat::GRAY . "Retrieved death inventory log #{$log->id}");
		yield from Await::promise(static fn($resolve) => $menu->send($sender_player, null, $resolve));
		yield from $this->sleep();
	}
}