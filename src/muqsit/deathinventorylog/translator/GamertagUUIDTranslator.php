<?php

declare(strict_types=1);

namespace muqsit\deathinventorylog\translator;

use Closure;
use pocketmine\uuid\UUID;

interface GamertagUUIDTranslator{

	public function store(UUID $uuid, string $gamertag) : void;

	/**
	 * @param UUID[] $uuids
	 * @param Closure $callback
	 *
	 * @phpstan-param Closure(array<string, string>) : void $callback
	 */
	public function translateUuids(array $uuids, Closure $callback) : void;

	/**
	 * @param string[] $gamertags
	 * @param Closure $callback
	 *
	 * @phpstan-param Closure(array<string, string>) : void $callback
	 */
	public function translateGamertags(array $gamertags, Closure $callback) : void;

	public function close() : void;
}