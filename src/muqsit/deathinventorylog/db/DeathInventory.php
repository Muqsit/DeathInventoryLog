<?php

declare(strict_types=1);

namespace muqsit\deathinventorylog\db;

use pocketmine\item\Item;

class DeathInventory {

    /** @var array<int, Item> */
    public array $inventory_contents;

    /** @var array<int, Item> */
    public array $armor_contents;

    /** @var array<int, Item> */
    public array $offhand_contents;

    /**
     * @param array<int, Item> $inventory_contents
     * @param array<int, Item> $armor_contents
     * @param array<int, Item> $offhand_contents
     */
    public function __construct(array $inventory_contents, array $armor_contents, array $offhand_contents) {
        $this->inventory_contents = $inventory_contents;
        $this->armor_contents = $armor_contents;
        $this->offhand_contents = $offhand_contents;
    }

}
