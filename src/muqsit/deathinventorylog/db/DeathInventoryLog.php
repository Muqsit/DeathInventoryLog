<?php

declare(strict_types=1);

namespace muqsit\deathinventorylog\db;

use Ramsey\Uuid\UuidInterface;

final class DeathInventoryLog{

	/** @var int */
	private $id;

	/** @var UuidInterface */
	private $uuid;

	/** @var DeathInventory */
	private $inventory;

	/** @var int */
	private $timestamp;

	public function __construct(int $id, UuidInterface $uuid, DeathInventory $inventory, int $timestamp){
		$this->id = $id;
		$this->uuid = $uuid;
		$this->inventory = $inventory;
		$this->timestamp = $timestamp;
	}

	public function getId() : int{
		return $this->id;
	}

	public function getUuid() : UuidInterface{
		return $this->uuid;
	}

	public function getInventory() : DeathInventory{
		return $this->inventory;
	}

	public function getUnixTimestamp() : int{
		return $this->timestamp;
	}
}