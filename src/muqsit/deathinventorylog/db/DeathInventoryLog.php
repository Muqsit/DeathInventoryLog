<?php

declare(strict_types=1);

namespace muqsit\deathinventorylog\db;

use Ramsey\Uuid\UuidInterface;

final class DeathInventoryLog {

    public int $id;
    public UuidInterface $uuid;
    public DeathInventory $inventory;
    public int $timestamp;

    public function __construct(int $id, UuidInterface $uuid, DeathInventory $inventory, int $timestamp) {
        $this->id = $id;
        $this->uuid = $uuid;
        $this->inventory = $inventory;
        $this->timestamp = $timestamp;
    }

}
