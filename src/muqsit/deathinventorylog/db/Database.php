<?php

declare(strict_types=1);

namespace muqsit\deathinventorylog\db;

use Closure;
use muqsit\deathinventorylog\Loader;
use pocketmine\utils\VersionString;
use Ramsey\Uuid\UuidInterface;

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

	public function close() : void;
}