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
		private array $inventory_contents,
		private array $armor_contents
	){}

	/**
	 * @return array<int, Item>
	 */
	public function getInventoryContents() : array{
		return $this->inventory_contents;
	}

	/**
	 * @return array<int, Item>
	 */
	public function getArmorContents() : array{
		return $this->armor_contents;
	}
}