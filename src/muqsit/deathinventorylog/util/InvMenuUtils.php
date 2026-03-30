<?php

declare(strict_types=1);

namespace muqsit\deathinventorylog\util;

use Closure;
use Generator;
use muqsit\invmenu\InvMenu;
use muqsit\invmenu\transaction\DeterministicInvMenuTransaction;
use pocketmine\inventory\Inventory;
use pocketmine\player\Player;
use SOFe\AwaitGenerator\Await;
use function assert;

final class InvMenuUtils{

	/**
	 * @param InvMenu $menu
	 * @param Player $player
	 * @param string|null $name
	 * @return Generator<mixed, Await::RESOLVE|Await::REJECT, void, void>
	 * @throws InventoryException
	 */
	public static function waitSend(InvMenu $menu, Player $player, ?string $name = null) : Generator{
		$player->isConnected() || throw new InventoryException("Failed to send menu to player", InventoryException::ERR_PLAYER_DISCONNECTED);
		if($player->getCurrentWindow() === $menu->getInventory()){
			return;
		}
		/** @var bool $sent */
		$sent = yield from Await::promise(static function(Closure $resolve) use ($menu, $player, $name) : void{
			$menu->send($player, $name, $resolve);
		});
		$sent || throw new InventoryException("Failed to send menu to player", InventoryException::ERR_INVENTORY_NOT_SENT);
	}

	/**
	 * @param InvMenu $menu
	 * @param Player $player
	 * @return Generator<mixed, Await::RESOLVE, void, null>
	 */
	public static function waitClose(InvMenu $menu, Player $player) : Generator{
		if($player->getCurrentWindow() !== $menu->getInventory()){
			return null;
		}
		$listener = $menu->getInventoryCloseListener();
		try{
			yield from Await::promise(static function(Closure $resolve) use ($menu, $listener) : void{
				$menu->setInventoryCloseListener(static function(Player $player, Inventory $inventory) use ($resolve, $listener) : void{
					if($listener !== null){
						$listener($player, $inventory);
					}
					$resolve(null);
				});
			});
		}finally{
			$menu->setInventoryCloseListener($listener);
		}
	}

	/**
	 * @param InvMenu $menu
	 * @return Generator<mixed, Await::RESOLVE|Await::REJECT, void, DeterministicInvMenuTransaction>
	 */
	private static function waitReadonlyTransaction(InvMenu $menu) : Generator{
		$listener = $menu->getListener();
		try{
			return yield from Await::promise(static function(Closure $resolve) use ($menu) : void{
				$menu->setListener(InvMenu::readonly($resolve));
			});
		}finally{
			$menu->setListener($listener);
		}
	}
	/**
	 * @param InvMenu $menu
	 * @param Player $player
	 * @param string|null $name
	 * @return Generator<mixed, Await::RESOLVE|Await::REJECT, void, DeterministicInvMenuTransaction>
	 * @throws InventoryException
	 */
	public static function waitTransaction(InvMenu $menu, Player $player, ?string $name = null) : Generator{
		yield from self::waitSend($menu, $player, $name);
		[$k, $v] = yield from Await::safeRace([self::waitClose($menu, $player), self::waitReadonlyTransaction($menu)]);
		$k !== 0 || throw new InventoryException("Player closed the menu", InventoryException::ERR_INVENTORY_CLOSED);
		assert($v instanceof DeterministicInvMenuTransaction);
		return $v;
	}
}