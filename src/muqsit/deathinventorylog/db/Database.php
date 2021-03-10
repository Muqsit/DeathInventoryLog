<?php

declare(strict_types=1);

namespace muqsit\deathinventorylog\db;

use Closure;
use muqsit\deathinventorylog\Loader;
use pocketmine\uuid\UUID;

interface Database{

	/**
	 * @param Loader $plugin
	 * @param mixed[] $configuration
	 * @return static
	 *
	 * @phpstan-param array<string, mixed> $configuration
	 */
	public static function create(Loader $plugin, array $configuration) : self;

	/**
	 * @param UUID $player
	 * @param DeathInventory $inventory
	 * @param Closure $callback
	 *
	 * @phpstan-param Closure(int) : void $callback
	 */
	public function store(UUID $player, DeathInventory $inventory, Closure $callback) : void;

	/**
	 * @param int $id
	 * @param Closure $callback
	 *
	 * @phpstan-param Closure(?DeathInventoryLog) : void $callback
	 */
	public function retrieve(int $id, Closure $callback) : void;

	/**
	 * @param UUID $player
	 * @param int $offset
	 * @param int $length
	 * @param Closure $callback
	 *
	 * @phpstan-param Closure(DeathInventoryLog[]) : void $callback
	 */
	public function retrievePlayer(UUID $player, int $offset, int $length, Closure $callback) : void;

	public function close() : void;
}