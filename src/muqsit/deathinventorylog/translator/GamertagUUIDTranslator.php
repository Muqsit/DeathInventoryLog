<?php

declare(strict_types=1);

namespace muqsit\deathinventorylog\translator;

use Closure;
use Generator;
use Ramsey\Uuid\UuidInterface;
use SOFe\AwaitGenerator\Await;

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

	/**
	 * @param UuidInterface[] $uuids
	 * @return Generator<mixed, Await::RESOLVE, void, array<string, string>>
	 */
	public function translateUuidsAsync(array $uuids) : Generator;

	/**
	 * @param string[] $gamertags
	 * @return Generator<mixed, Await::RESOLVE, void, array<string, string>>
	 */
	public function translateGamertagsAsync(array $gamertags) : Generator;

	public function close() : void;
}