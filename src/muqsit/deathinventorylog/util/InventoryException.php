<?php

declare(strict_types=1);

namespace muqsit\deathinventorylog\util;

use Exception;

final class InventoryException extends Exception{

	public const ERR_PLAYER_DISCONNECTED = 100001;
	public const ERR_INVENTORY_CLOSED = 100002;
	public const ERR_INVENTORY_NOT_SENT = 100003;
}