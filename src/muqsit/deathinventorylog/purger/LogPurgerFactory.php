<?php

declare(strict_types=1);

namespace muqsit\deathinventorylog\purger;

use InvalidArgumentException;
use muqsit\deathinventorylog\Loader;

final class LogPurgerFactory{

	private Loader $plugin;

	/** @var array<string, class-string<LogPurger>>|LogPurger[] */
	private array $purgers = [];

	public function __construct(Loader $plugin){
		$this->plugin = $plugin;
		$this->register("none", NullLogPurger::class);
		$this->register("periodic", PeriodicLogPurger::class);
	}

	/**
	 * @param string $identifier
	 * @param class-string<LogPurger> $class
	 */
	public function register(string $identifier, string $class) : void{
		$this->purgers[$identifier] = $class;
	}

	/**
	 * @param string $identifier
	 * @param mixed[] $data
	 * @return LogPurger
	 */
	public function create(string $identifier, array $data) : LogPurger{
		if(!isset($this->purgers[$identifier])){
			throw new InvalidArgumentException("Invalid log purger type: {$identifier}");
		}

		return $this->purgers[$identifier]::fromConfiguration($this->plugin, $data);
	}
}