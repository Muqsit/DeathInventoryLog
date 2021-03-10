<?php

declare(strict_types=1);

namespace muqsit\deathinventorylog\translator;

use Closure;
use muqsit\deathinventorylog\Loader;
use pocketmine\uuid\UUID;
use poggit\libasynql\DataConnector;
use poggit\libasynql\libasynql;

final class LocalGamertagUUIDTranslator implements GamertagUUIDTranslator{

	/** @var DataConnector */
	private $connector;

	public function __construct(Loader $plugin){
		$this->connector = libasynql::create($plugin, [
			"type" => "sqlite",
			"sqlite" => ["file" => "uuid_gamertag_translations.sqlite"]
		], ["sqlite" => "db/local_uuid_gamertag_translator.sql"]);
		$this->connector->executeGeneric("deathinventorylog.init_translator");
		$this->connector->waitAll();
	}

	public function store(UUID $uuid, string $gamertag) : void{
		$this->connector->executeInsert("deathinventorylog.store_translation", [
			"uuid" => $uuid->toString(),
			"gamertag" => $gamertag
		]);
	}

	public function translateUuids(array $uuids, Closure $callback) : void{
		$this->connector->executeSelect("deathinventorylog.translate_uuids", [
			"uuids" => "'" . implode("', '", array_map(static function(UUID $uuid) : string{ return $uuid->toString(); }, $uuids)) . "'"
		], static function(array $rows) use($callback) : void{
			$result = [];
			foreach($rows as $row){
				$result[UUID::fromString($row["uuid"])->toBinary()] = $row["gamertag"];
			}
			$callback($result);
		});
	}

	public function translateGamertags(array $gamertags, Closure $callback) : void{
		$this->connector->executeSelect("deathinventorylog.translate_gamertags", [
			"gamertags" => implode("', '", array_map("strtolower", $gamertags))
		], static function(array $rows) use($callback) : void{
			$result = [];
			foreach($rows as $row){
				$result[strtolower($row["gamertag"])] = UUID::fromString($row["uuid"])->toBinary();
			}
			$callback($result);
		});
	}

	public function close() : void{
		$this->connector->close();
	}
}