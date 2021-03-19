<?php

declare(strict_types=1);

namespace muqsit\deathinventorylog\db;

use muqsit\deathinventorylog\Loader;

final class DatabaseFactory{

	/**
	 * @var string[]|Database[]
	 *
	 * @phpstan-var array<string, class-string<Database>>
	 */
	private array $databases = [];

	public function __construct(){
		$this->register("mysql", MySQLDatabase::class);
		$this->register("sqlite", SQLite3Database::class);
	}

	/**
	 * @param string $identifier
	 * @param string $class
	 *
	 * @phpstan-param class-string<Database> $class
	 */
	public function register(string $identifier, string $class) : void{
		$this->databases[$identifier] = $class;
	}

	/**
	 * @param Loader $loader
	 * @param string $identifier
	 * @param array $configuration
	 * @return Database
	 *
	 * @phpstan-param array<string, mixed> $configuration
	 */
	public function create(Loader $loader, string $identifier, array $configuration) : Database{
		return $this->databases[$identifier]::create($loader, $configuration);
	}
}