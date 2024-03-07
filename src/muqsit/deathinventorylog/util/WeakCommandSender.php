<?php

declare(strict_types=1);

namespace muqsit\deathinventorylog\util;

use pocketmine\command\CommandSender;
use pocketmine\player\Player;

final class WeakCommandSender{

	public function __construct(
		readonly private CommandSender $sender
	){}

	public function get() : ?CommandSender{
		if($this->sender instanceof Player && !$this->sender->isConnected()){
			return null;
		}
		return $this->sender;
	}

	public function sendMessage(string $message) : void{
		$this->get()?->sendMessage($message);
	}
}