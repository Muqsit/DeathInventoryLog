<?php

declare(strict_types=1);

namespace muqsit\deathinventorylog\db;

use Closure;
use muqsit\deathinventorylog\Loader;
use muqsit\deathinventorylog\util\InventorySerializer;
use pocketmine\utils\VersionString;
use poggit\libasynql\DataConnector;
use poggit\libasynql\libasynql;
use poggit\libasynql\SqlError;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

final class SQLite3Database implements Database{

	private const VERSION_UPGRADE_MAPPING = [
		"0.2.0" => "deathinventorylog.upgrade.impl_offhand_inventory"
	];

	/**
	 * @param Loader $plugin
	 * @param array{file: string} $configuration
	 * @return self
	 */
	public static function create(Loader $plugin, array $configuration) : self{
		$connector = libasynql::create($plugin, [
			"type" => "sqlite",
			"sqlite" => ["file" => $configuration["file"]]
		], ["sqlite" => "db/sqlite.sql"]);
		$connector->executeGeneric("deathinventorylog.init.create_table");
		$connector->executeGeneric("deathinventorylog.init.index_uuid");
		$connector->waitAll();
		return new self($connector);
	}

	private function __construct(
		private DataConnector $connector
	){}

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
			"uuid" => $player->getBytes(),
			"time" => time(),
			"inventory" => InventorySerializer::serialize($inventory->inventory_contents),
			"armor_inventory" => InventorySerializer::serialize($inventory->armor_contents),
			"offhand_inventory" => InventorySerializer::serialize($inventory->offhand_contents)
		], static function(int $insert_id, int $affected_rows) use($callback) : void{ $callback($insert_id); });
	}

	public function retrieve(int $id, Closure $callback) : void{
		$this->connector->executeSelect("deathinventorylog.retrieve", ["id" => $id], static function(array $rows) use($callback) : void{
			$row = current($rows);
			if($row !== false){
				$callback(new DeathInventoryLog(
					$row["id"],
					Uuid::fromBytes($row["uuid"]),
					new DeathInventory(
						InventorySerializer::deSerialize($row["inventory"]),
						InventorySerializer::deSerialize($row["armor_inventory"]),
						InventorySerializer::deSerialize($row["offhand_inventory"])
					),
					$row["time"]
				));
			}else{
				$callback(null);
			}
		});
	}

	public function retrievePlayer(UuidInterface $player, int $offset, int $length, Closure $callback) : void{
		$this->connector->executeSelect("deathinventorylog.retrieve_player", [
			"uuid" => $player->getBytes(),
			"offset" => $offset,
			"length" => $length
		], static function(array $rows) use($callback) : void{
			$result = [];
			foreach($rows as $row){
				$result[] = new DeathInventoryLog(
					$row["id"],
					Uuid::fromBytes($row["uuid"]),
					new DeathInventory(
						InventorySerializer::deSerialize($row["inventory"]),
						InventorySerializer::deSerialize($row["armor_inventory"]),
						InventorySerializer::deSerialize($row["offhand_inventory"])
					),
					$row["time"]
				);
			}
			$callback($result);
		});
	}

	public function purge(int $older_than_timestamp, Closure $callback) : void{
		$this->connector->executeChange("deathinventorylog.purge", ["time" => $older_than_timestamp], static function(int $affectedRows) use($callback) : void{
			$callback($affectedRows);
		});
	}

	public function close() : void{
		$this->connector->close();
	}
}