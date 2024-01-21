<?php

declare(strict_types=1);

namespace muqsit\deathinventorylog\db;

use Closure;
use muqsit\deathinventorylog\Loader;
use muqsit\deathinventorylog\util\InventorySerializer;
use pocketmine\utils\VersionString;
use pocketmine\VersionInfo;
use poggit\libasynql\DataConnector;
use poggit\libasynql\libasynql;
use poggit\libasynql\SqlError;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

final class MySQLDatabase implements Database{

	private const VERSION_UPGRADE_MAPPING = [
		"0.2.0" => "deathinventorylog.upgrade.impl_offhand_inventory"
	];

	/**
	 * @param Loader $plugin
	 * @param array{host: string, username: string, password: string, schema: string} $configuration
	 * @return self
	 */
	public static function create(Loader $plugin, array $configuration) : self{
		$connector = libasynql::create($plugin, [
			"type" => "mysql",
			"mysql" => [
				"host" => $configuration["host"],
				"username" => $configuration["username"],
				"password" => $configuration["password"],
				"schema" => $configuration["schema"]
			]
		], ["mysql" => "db/mysql.sql"]);
		$connector->executeGeneric("deathinventorylog.init.create_table");
		$connector->waitAll();
		return new self($connector);
	}

	private function __construct(
		private DataConnector $connector
	){}

	private function handleError() : Closure{
		return static function(SqlError $error) : void{
			throw $error;
		};
	}

	public function upgrade(VersionString $previous, VersionString $current) : void{
		foreach(self::VERSION_UPGRADE_MAPPING as $version => $query_name){
			$version = new VersionString($version);
			if($version->compare($previous) < 0 && $version->compare($current) >= 0){
				$this->connector->executeGeneric($query_name, [], null, function(SqlError $_) : void{});
			}
		}
	}

	public function store(UuidInterface $player, DeathInventory $inventory, Closure $callback) : void{
		$this->connector->executeInsert("deathinventorylog.save", [
			"player_uuid" => base64_encode($player->getBytes()),
			"item_inventory" => base64_encode(InventorySerializer::serialize($inventory->inventory_contents)),
			"armor_inventory" => base64_encode(InventorySerializer::serialize($inventory->armor_contents)),
			"offhand_inventory" => base64_encode(InventorySerializer::serialize($inventory->offhand_contents))
		], static function(int $insert_id, int $affected_rows) use($callback) : void{ $callback($insert_id); }, $this->handleError());
	}

	public function retrieve(int $id, Closure $callback) : void{
		$this->connector->executeSelect("deathinventorylog.retrieve", ["id" => $id], static function(array $rows) use($callback) : void{
			$row = current($rows);
			if($row !== false){
				$callback(new DeathInventoryLog(
					$row["id"],
					Uuid::fromBytes($row["player_uuid"]),
					new DeathInventory(
						InventorySerializer::deSerialize($row["item_inventory"]),
						InventorySerializer::deSerialize($row["armor_inventory"]),
						InventorySerializer::deSerialize($row["offhand_inventory"])
					),
					$row["log_time"]
				));
			}else{
				$callback(null);
			}
		}, $this->handleError());
	}

	public function retrievePlayer(UuidInterface $player, int $offset, int $length, Closure $callback) : void{
		$this->connector->executeSelect("deathinventorylog.retrieve_player", [
			"player_uuid" => $player->getBytes(),
			"offset" => $offset,
			"length" => $length
		], static function(array $rows) use($callback) : void{
			$result = [];
			foreach($rows as $row){
				$result[] = new DeathInventoryLog(
					$row["id"],
					Uuid::fromBytes($row["player_uuid"]),
					new DeathInventory(
						InventorySerializer::deSerialize($row["item_inventory"]),
						InventorySerializer::deSerialize($row["armor_inventory"]),
						InventorySerializer::deSerialize($row["offhand_inventory"])
					),
					$row["log_time"]
				);
			}
			$callback($result);
		}, $this->handleError());
	}

	public function purge(int $older_than_timestamp, Closure $callback) : void{
		$this->connector->executeChange("deathinventorylog.purge", ["log_time" => $older_than_timestamp], static function(int $affectedRows) use($callback) : void{
			$callback($affectedRows);
		}, $this->handleError());
	}

	public function close() : void{
		$this->connector->close();
	}
}