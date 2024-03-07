<?php

declare(strict_types=1);

namespace muqsit\deathinventorylog\translator;

use Closure;
use Generator;
use SOFe\AwaitGenerator\Await;

trait AsyncToCallbackGamertagUUIDTranslatorTrait{

	public function translateUuids(array $uuids, Closure $callback) : void{
		Await::f2c(function() use($uuids, $callback) : Generator{
			$callback(yield from $this->translateUuidsAsync($uuids));
		});
	}

	public function translateGamertags(array $gamertags, Closure $callback) : void{
		Await::f2c(function() use($gamertags, $callback) : Generator{
			$callback(yield from $this->translateGamertagsAsync($gamertags));
		});
	}
}