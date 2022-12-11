<?php

declare(strict_types=1);

namespace muqsit\deathinventorylog\db;

use Ramsey\Uuid\UuidInterface;

final class DeathInventoryLog{

	public function __construct(
		/** @readonly */ public int $id,
		/** @readonly */ public UuidInterface $uuid,
		/** @readonly */ public DeathInventory $inventory,
		/** @readonly */ public int $timestamp
	){}
}