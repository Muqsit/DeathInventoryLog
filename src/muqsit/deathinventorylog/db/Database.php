<?php

declare(strict_types=1);

namespace muqsit\deathinventorylog\db;

use Closure;
use muqsit\deathinventorylog\Loader;
use Ramsey\Uuid\UuidInterface;

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
	 * @param UuidInterface $player
	 * @param DeathInventory $inventory
	 * @param Closure $callback
	 *
	 * @phpstan-param Closure(int) : void $callback
	 */
	public function store(UuidInterface $player, DeathInventory $inventory, Closure $callback) : void;

	/**
	 * @param int $id
	 * @param Closure $callback
	 *
	 * @phpstan-param Closure(?DeathInventoryLog) : void $callback
	 */
	public function retrieve(int $id, Closure $callback) : void;

	/**
	 * @param UuidInterface $player
	 * @param int $offset
	 * @param int $length
	 * @param Closure $callback
	 *
	 * @phpstan-param Closure(DeathInventoryLog[]) : void $callback
	 */
	public function retrievePlayer(UuidInterface $player, int $offset, int $length, Closure $callback) : void;

	/**
	 * @param int $older_than_timestamp
	 * @param Closure $callback
	 *
	 * @phpstan-param Closure(int) : void $callback
	 */
	public function purge(int $older_than_timestamp, Closure $callback) : void;

	public function close() : void;
}