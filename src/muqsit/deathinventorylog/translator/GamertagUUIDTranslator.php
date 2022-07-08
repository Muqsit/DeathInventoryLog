<?php

declare(strict_types=1);

namespace muqsit\deathinventorylog\translator;

use Closure;
use Ramsey\Uuid\UuidInterface;

interface GamertagUUIDTranslator{

	public function store(UuidInterface $uuid, string $gamertag) : void;

	/**
	 * @param UuidInterface[] $uuids
	 * @param Closure(array<string, string>) : void $callback
	 */
	public function translateUuids(array $uuids, Closure $callback) : void;

	/**
	 * @param string[] $gamertags
	 * @param Closure(array<string, string>) : void $callback
	 */
	public function translateGamertags(array $gamertags, Closure $callback) : void;

	public function close() : void;
}