<?php

declare(strict_types=1);

namespace muqsit\deathinventorylog\purger;

use muqsit\deathinventorylog\Loader;

final class NullLogPurger implements LogPurger{

	public static function fromConfiguration(Loader $loader, array $data) : self{
		return self::instance();
	}

	public static function instance() : self{
		static $instance = null;
		return $instance ??= new self();
	}

	private function __construct(){
	}

	public function close() : void{
	}
}