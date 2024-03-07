<?php

declare(strict_types=1);

namespace muqsit\deathinventorylog\db;

use Closure;
use Generator;
use Ramsey\Uuid\UuidInterface;
use SOFe\AwaitGenerator\Await;

trait AsyncToCallbackDatabaseTrait{

	public function store(UuidInterface $player, DeathInventory $inventory, Closure $callback) : void{
		Await::f2c(function() use($player, $inventory, $callback) : Generator{
			$callback(yield from $this->storeAsync($player, $inventory));
		});
	}

	public function retrieve(int $id, Closure $callback) : void{
		Await::f2c(function() use($id, $callback) : Generator{
			$callback(yield from $this->retrieveAsync($id));
		});
	}

	public function retrievePlayer(UuidInterface $player, int $offset, int $length, Closure $callback) : void{
		Await::f2c(function() use($player, $offset, $length, $callback) : Generator{
			$callback(yield from $this->retrievePlayerAsync($player, $offset, $length));
		});
	}

	public function purge(int $older_than_timestamp, Closure $callback) : void{
		Await::f2c(function() use($older_than_timestamp, $callback) : Generator{
			$callback(yield from $this->purgeAsync($older_than_timestamp));
		});
	}
}