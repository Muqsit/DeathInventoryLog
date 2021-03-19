<?php

declare(strict_types=1);

namespace muqsit\deathinventorylog\db;

use pocketmine\item\Item;

final class DeathInventory{

	/**
	 * @var Item[]
	 *
	 * @phpstan-var array<int, Item>
	 */
	private array $inventory_contents;

	/**
	 * @var Item[]
	 *
	 * @phpstan-var array<int, Item>
	 */
	private array $armor_contents;

	/**
	 * @param Item[] $inventory_contents
	 * @param Item[] $armor_contents
	 *
	 * @phpstan-param array<int, Item> $inventory_contents
	 * @phpstan-param array<int, Item> $armor_contents
	 */
	public function __construct(array $inventory_contents, array $armor_contents){
		$this->inventory_contents = $inventory_contents;
		$this->armor_contents = $armor_contents;
	}

	/**
	 * @return Item[]
	 *
	 * @phpstan-return array<int, Item>
	 */
	public function getInventoryContents() : array{
		return $this->inventory_contents;
	}

	/**
	 * @return Item[]
	 *
	 * @phpstan-return array<int, Item>
	 */
	public function getArmorContents() : array{
		return $this->armor_contents;
	}
}