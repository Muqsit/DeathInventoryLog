<?php

declare(strict_types=1);

namespace muqsit\deathinventorylog\db;

use Generator;
use muqsit\deathinventorylog\Loader;
use muqsit\deathinventorylog\util\InventorySerializer;
use pocketmine\utils\VersionString;
use poggit\libasynql\DataConnector;
use poggit\libasynql\libasynql;
use poggit\libasynql\SqlError;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use function current;
use function time;

final class SQLite3Database implements Database{
	use AsyncToCallbackDatabaseTrait;

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

	public function storeAsync(UuidInterface $player, DeathInventory $inventory) : Generator{
		[$insert_id, ] = yield from $this->connector->asyncInsert("deathinventorylog.save", [
			"uuid" => $player->getBytes(),
			"time" => time(),
			"inventory" => InventorySerializer::serialize($inventory->inventory_contents),
			"armor_inventory" => InventorySerializer::serialize($inventory->armor_contents),
			"offhand_inventory" => InventorySerializer::serialize($inventory->offhand_contents)
		]);
		return $insert_id;
	}

	public function retrieveAsync(int $id) : Generator{
		$row = current(yield from $this->connector->asyncSelect("deathinventorylog.retrieve", ["id" => $id]));
		return $row === false ? null : new DeathInventoryLog(
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

	public function retrievePlayerAsync(UuidInterface $player, int $offset, int $length) : Generator{
		$rows = yield from $this->connector->asyncSelect("deathinventorylog.retrieve_player", [
			"uuid" => $player->getBytes(),
			"offset" => $offset,
			"length" => $length
		]);
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
		return $result;
	}

	public function purgeAsync(int $older_than_timestamp) : Generator{
		return yield from $this->connector->asyncChange("deathinventorylog.purge", ["time" => $older_than_timestamp]);
	}

	public function close() : void{
		$this->connector->close();
	}
}