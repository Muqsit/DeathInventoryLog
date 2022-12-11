<?php

declare(strict_types=1);

namespace muqsit\deathinventorylog\db;

use pocketmine\item\Item;

final class DeathInventory{

	/**
	 * @param array<int, Item> $inventory_contents
	 * @param array<int, Item> $armor_contents
	 */
	public function __construct(
		/** @readonly */ public array $inventory_contents,
		/** @readonly */ public array $armor_contents
	){}
}