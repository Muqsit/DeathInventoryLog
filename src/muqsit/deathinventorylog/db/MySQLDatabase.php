<?php

declare(strict_types=1);

namespace muqsit\deathinventorylog\db;

use Closure;
use muqsit\deathinventorylog\Loader;
use muqsit\deathinventorylog\util\InventorySerializer;
use poggit\libasynql\DataConnector;
use poggit\libasynql\libasynql;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

final class MySQLDatabase implements Database{

	/**
	 * @param Loader $plugin
	 * @param mixed[] $configuration
	 * @return self
	 *
	 * @phpstan-param array{host: string, username: string, password: string, schema: string} $configuration
	 */
	public static function create(Loader $plugin, array $configuration) : self{
		return new self($plugin, $configuration["host"], $configuration["username"], $configuration["password"], $configuration["schema"]);
	}

	/** @var DataConnector */
	private $connector;

	private function __construct(Loader $plugin, string $host, string $username, string $password, string $schema){
		$this->connector = libasynql::create($plugin, [
			"type" => "mysql",
			"mysql" => [
				"host" => $host,
				"username" => $username,
				"password" => $password,
				"schema" => $schema
			]
		], ["mysql" => "db/mysql.sql"]);
		$this->connector->executeGeneric("deathinventorylog.init");
		$this->connector->waitAll();
	}

	public function store(UuidInterface $player, DeathInventory $inventory, Closure $callback) : void{
		$this->connector->executeInsert("deathinventorylog.save", [
			"uuid" => base64_encode($player->getBytes()),
			"inventory" => base64_encode(InventorySerializer::serialize($inventory->getInventoryContents())),
			"armor_inventory" => base64_encode(InventorySerializer::serialize($inventory->getArmorContents()))
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
						InventorySerializer::deSerialize($row["armor_inventory"])
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
						InventorySerializer::deSerialize($row["armor_inventory"])
					),
					$row["time"]
				);
			}
			$callback($result);
		});
	}

	public function close() : void{
		$this->connector->close();
	}
}