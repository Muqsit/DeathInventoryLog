<?php

declare(strict_types=1);

namespace muqsit\deathinventorylog\db;

use muqsit\deathinventorylog\Loader;

final class DatabaseFactory{

	/** @var array<string, class-string<Database>> */
	private array $databases = [];

	public function __construct(){
		$this->register("mysql", MySQLDatabase::class);
		$this->register("sqlite", SQLite3Database::class);
	}

	/**
	 * @param string $identifier
	 * @param class-string<Database> $class
	 */
	public function register(string $identifier, string $class) : void{
		$this->databases[$identifier] = $class;
	}

	/**
	 * @param Loader $loader
	 * @param string $identifier
	 * @param array<string, mixed> $configuration
	 * @return Database
	 */
	public function create(Loader $loader, string $identifier, array $configuration) : Database{
		return $this->databases[$identifier]::create($loader, $configuration);
	}
}