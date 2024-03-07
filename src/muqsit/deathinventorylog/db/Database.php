<?php

declare(strict_types=1);

namespace muqsit\deathinventorylog\db;

use Closure;
use Generator;
use muqsit\deathinventorylog\Loader;
use pocketmine\utils\VersionString;
use Ramsey\Uuid\UuidInterface;
use SOFe\AwaitGenerator\Await;

interface Database{

	/**
	 * @param Loader $plugin
	 * @param array<string, mixed> $configuration
	 * @return static
	 */
	public static function create(Loader $plugin, array $configuration) : self;

	public function upgrade(VersionString $previous, VersionString $current) : void;

	/**
	 * @param UuidInterface $player
	 * @param DeathInventory $inventory
	 * @param Closure(int) : void $callback
	 */
	public function store(UuidInterface $player, DeathInventory $inventory, Closure $callback) : void;

	/**
	 * @param int $id
	 * @param Closure(?DeathInventoryLog) : void $callback
	 */
	public function retrieve(int $id, Closure $callback) : void;

	/**
	 * @param UuidInterface $player
	 * @param int $offset
	 * @param int $length
	 * @param Closure(DeathInventoryLog[]) : void $callback
	 */
	public function retrievePlayer(UuidInterface $player, int $offset, int $length, Closure $callback) : void;

	/**
	 * @param int $older_than_timestamp
	 * @param Closure(int) : void $callback
	 */
	public function purge(int $older_than_timestamp, Closure $callback) : void;

	/**
	 * @param UuidInterface $player
	 * @param DeathInventory $inventory
	 * @return Generator<mixed, Await::RESOLVE|Await::REJECT, void, int>
	 */
	public function storeAsync(UuidInterface $player, DeathInventory $inventory) : Generator;

	/**
	 * @param int $id
	 * @return Generator<mixed, Await::RESOLVE|Await::REJECT, void, DeathInventoryLog|null>
	 */
	public function retrieveAsync(int $id) : Generator;

	/**
	 * @param UuidInterface $player
	 * @param int $offset
	 * @param int $length
	 * @return Generator<mixed, Await::RESOLVE|Await::REJECT, void, DeathInventoryLog[]>
	 */
	public function retrievePlayerAsync(UuidInterface $player, int $offset, int $length) : Generator;

	/**
	 * @param int $older_than_timestamp
	 * @return Generator<mixed, Await::RESOLVE|Await::REJECT, void, int>
	 */
	public function purgeAsync(int $older_than_timestamp) : Generator;

	public function close() : void;
}