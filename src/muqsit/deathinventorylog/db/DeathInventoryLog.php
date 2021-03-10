<?php

declare(strict_types=1);

namespace muqsit\deathinventorylog\db;

use pocketmine\uuid\UUID;

final class DeathInventoryLog{

	/** @var int */
	private $id;

	/** @var UUID */
	private $uuid;

	/** @var DeathInventory */
	private $inventory;

	/** @var int */
	private $timestamp;

	public function __construct(int $id, UUID $uuid, DeathInventory $inventory, int $timestamp){
		$this->id = $id;
		$this->uuid = $uuid;
		$this->inventory = $inventory;
		$this->timestamp = $timestamp;
	}

	public function getId() : int{
		return $this->id;
	}

	public function getUuid() : UUID{
		return $this->uuid;
	}

	public function getInventory() : DeathInventory{
		return $this->inventory;
	}

	public function getUnixTimestamp() : int{
		return $this->timestamp;
	}
}