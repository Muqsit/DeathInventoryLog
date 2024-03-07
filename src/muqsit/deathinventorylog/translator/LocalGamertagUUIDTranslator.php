<?php

declare(strict_types=1);

namespace muqsit\deathinventorylog\translator;

use Generator;
use muqsit\deathinventorylog\Loader;
use poggit\libasynql\DataConnector;
use poggit\libasynql\libasynql;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use function strtolower;

final class LocalGamertagUUIDTranslator implements GamertagUUIDTranslator{
	use AsyncToCallbackGamertagUUIDTranslatorTrait;

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
			"uuid" => $uuid->toString(),
			"gamertag" => $gamertag
		]);
	}

	public function translateUuidsAsync(array $uuids) : Generator{
		$rows = yield from $this->connector->asyncSelect("deathinventorylog.translate_uuids", [
			"uuids" => "'" . implode("', '", array_map(static fn(UuidInterface $uuid) : string => $uuid->toString(), $uuids)) . "'"
		]);
		$result = [];
		foreach($rows as $row){
			$result[Uuid::fromString($row["uuid"])->getBytes()] = $row["gamertag"];
		}
		return $result;
	}

	public function translateGamertagsAsync(array $gamertags) : Generator{
		$rows = yield from $this->connector->asyncSelect("deathinventorylog.translate_gamertags", [
			"gamertags" => implode("', '", array_map(strtolower(...), $gamertags))
		]);
		$result = [];
		foreach($rows as $row){
			$result[strtolower($row["gamertag"])] = Uuid::fromString($row["uuid"])->getBytes();
		}
		return $result;
	}

	public function close() : void{
		$this->connector->close();
	}
}