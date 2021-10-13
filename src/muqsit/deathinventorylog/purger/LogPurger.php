<?php

declare(strict_types=1);

namespace muqsit\deathinventorylog\purger;

use muqsit\deathinventorylog\Loader;

interface LogPurger{

	/**
	 * @param Loader $loader
	 * @param mixed[] $data
	 * @return self
	 */
	public static function fromConfiguration(Loader $loader, array $data) : self;

	public function close() : void;
}