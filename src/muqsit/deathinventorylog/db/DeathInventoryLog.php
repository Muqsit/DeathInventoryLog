<?php

declare(strict_types=1);

namespace muqsit\deathinventorylog\db;

use Ramsey\Uuid\UuidInterface;

final class DeathInventoryLog{

	public function __construct(
		private int $id,
		private UuidInterface $uuid,
		private DeathInventory $inventory,
		private int $timestamp
	){}

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