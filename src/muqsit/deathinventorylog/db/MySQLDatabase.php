<?php

declare(strict_types=1);

namespace muqsit\deathinventorylog\db;

use Closure;
use muqsit\deathinventorylog\Loader;
use muqsit\deathinventorylog\util\InventorySerializer;
use poggit\libasynql\DataConnector;
use poggit\libasynql\libasynql;
use poggit\libasynql\SqlError;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

final class MySQLDatabase implements Database{

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
		$connector->executeGeneric("deathinventorylog.init.index_uuid", [], null, static function(SqlError $error) : void{
			if($error->getMessage() === "SQL EXECUTION error: Duplicate key name 'uuid_idx', for query ALTER TABLE death_inventory_log ADD INDEX uuid_idx(uuid); | []"){
				// TODO: compare error message against an SQL error code instead (SqlError::getCode() seems to always return 0 here)
				return;
			}
			throw $error;
		});
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

	public function store(UuidInterface $player, DeathInventory $inventory, Closure $callback) : void{
		$this->connector->executeInsert("deathinventorylog.save", [
			"uuid" => base64_encode($player->getBytes()),
			"inventory" => base64_encode(InventorySerializer::serialize($inventory->getInventoryContents())),
			"armor_inventory" => base64_encode(InventorySerializer::serialize($inventory->getArmorContents()))
		], static function(int $insert_id, int $affected_rows) use($callback) : void{ $callback($insert_id); }, $this->handleError());
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
						InventorySerializer::deSerialize($row["armor_inventory"])
					),
					$row["time"]
				));
			}else{
				$callback(null);
			}
		}, $this->handleError());
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
						InventorySerializer::deSerialize($row["armor_inventory"])
					),
					$row["time"]
				);
			}
			$callback($result);
		}, $this->handleError());
	}

	public function purge(int $older_than_timestamp, Closure $callback) : void{
		$this->connector->executeChange("deathinventorylog.purge", ["time" => $older_than_timestamp], static function(int $affectedRows) use($callback) : void{
			$callback($affectedRows);
		}, $this->handleError());
	}

	public function close() : void{
		$this->connector->close();
	}
}