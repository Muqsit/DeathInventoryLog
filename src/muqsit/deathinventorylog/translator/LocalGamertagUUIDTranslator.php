<?php

declare(strict_types=1);

namespace muqsit\deathinventorylog\translator;

use Closure;
use muqsit\deathinventorylog\Loader;
use poggit\libasynql\DataConnector;
use poggit\libasynql\libasynql;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

final class LocalGamertagUUIDTranslator implements GamertagUUIDTranslator{

	public static function create(Loader $plugin) : self{
		$connector = libasynql::create($plugin, [
			"type" => "sqlite",
			"sqlite" => ["file" => "uuid_gamertag_translations.sqlite"]
		], ["sqlite" => "db/local_uuid_gamertag_translator.sql"]);
		$connector->executeGeneric("deathinventorylog.init_translator");
		$connector->waitAll();
		return new self($connector);
	}

	public function __construct(
		private DataConnector $connector
	){}

	public function store(UuidInterface $uuid, string $gamertag) : void{
		$this->connector->executeInsert("deathinventorylog.store_translation", [
			"player_uuid" => $uuid->toString(),
			"gamertag" => $gamertag
		]);
	}

	public function translateUuids(array $uuids, Closure $callback) : void{
		$this->connector->executeSelect("deathinventorylog.translate_uuids", [
			"uuids" => "'" . implode("', '", array_map(static fn(UuidInterface $uuid) : string => $uuid->toString(), $uuids)) . "'"
		], static function(array $rows) use($callback) : void{
			$result = [];
			foreach($rows as $row){
				$result[Uuid::fromString($row["player_uuid"])->getBytes()] = $row["gamertag"];
			}
			$callback($result);
		});
	}

	public function translateGamertags(array $gamertags, Closure $callback) : void{
		$this->connector->executeSelect("deathinventorylog.translate_gamertags", [
			"gamertags" => implode("', '", array_map(strtolower(...), $gamertags))
		], static function(array $rows) use($callback) : void{
			$result = [];
			foreach($rows as $row){
				$result[strtolower($row["gamertag"])] = Uuid::fromString($row["player_uuid"])->getBytes();
			}
			$callback($result);
		});
	}

	public function close() : void{
		$this->connector->close();
	}
}