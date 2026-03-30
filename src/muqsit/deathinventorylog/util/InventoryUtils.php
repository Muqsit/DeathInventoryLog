<?php

declare(strict_types=1);

namespace muqsit\deathinventorylog\util;

use muqsit\deathinventorylog\db\DeathInventoryLog;
use pocketmine\inventory\ArmorInventory;
use pocketmine\inventory\transaction\action\SlotChangeAction;
use pocketmine\inventory\transaction\InventoryTransaction;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\network\mcpe\convert\TypeConverter;
use pocketmine\utils\TextFormat;
use function array_push;
use function arsort;
use function count;
use function gmdate;

final class InventoryUtils{

	/**
	 * @param Item[] $contents
	 * @return list<string>
	 */
	public static function stackedSummary(array $contents) : array{
		$stacked = [];
		foreach($contents as $item){
			$name = $item->getName();
			$stacked[$name] ??= 0;
			$stacked[$name] += $item->getCount();
		}
		arsort($stacked);
		$lore = [];
		foreach($stacked as $name => $count){
			$lore[] = TextFormat::RESET . TextFormat::GRAY . "- " . TextFormat::WHITE . TextFormat::clean($name) . TextFormat::GRAY . " (x{$count})";
		}
		return $lore;
	}

	public static function asDisplayItem(DeathInventoryLog $log) : Item{
		$lore = [TextFormat::RESET . TextFormat::GRAY . gmdate("Y-m-d H:i:s (T)", $log->timestamp)];

		if(count($log->inventory->offhand_contents) > 0){
			$lore[] = "";
			$lore[] = TextFormat::RESET . TextFormat::BOLD . TextFormat::WHITE . "Offhand:";
			array_push($lore, ...self::stackedSummary($log->inventory->offhand_contents));
		}

		if(count($log->inventory->armor_contents) > 0){
			$lore[] = "";
			$lore[] = TextFormat::RESET . TextFormat::BOLD . TextFormat::WHITE . "Armor:";
		}
		foreach([
			[ArmorInventory::SLOT_HEAD, "Helmet"],
			[ArmorInventory::SLOT_CHEST, "Chestplate"],
			[ArmorInventory::SLOT_LEGS, "Leggings"],
			[ArmorInventory::SLOT_FEET, "Boots"]
		] as [$slot, $name]){
			if(isset($log->inventory->armor_contents[$slot])){
				$lore[] = TextFormat::RESET . TextFormat::GRAY . "- {$name}: " . TextFormat::WHITE . TextFormat::clean($log->inventory->armor_contents[$slot]->getName());
			}
		}

		if(count($log->inventory->inventory_contents) > 0){
			$lore[] = "";
			$lore[] = TextFormat::RESET . TextFormat::BOLD . TextFormat::WHITE . "Inventory:";
			array_push($lore, ...self::stackedSummary($log->inventory->inventory_contents));
		}

		return VanillaItems::NETHER_STAR()
			->setCustomName(TextFormat::RESET . TextFormat::RED . TextFormat::BOLD . "Log #{$log->id}")
			->setLore($lore);
	}

	public static function buildDisplayContents(DeathInventoryLog $log) : array{
		$items = $log->inventory->inventory_contents;
		$armor_inventory = $log->inventory->armor_contents;
		foreach([
			ArmorInventory::SLOT_HEAD => 47,
			ArmorInventory::SLOT_CHEST => 48,
			ArmorInventory::SLOT_LEGS => 50,
			ArmorInventory::SLOT_FEET => 51
		] as $armor_inventory_slot => $menu_slot){
			if(isset($armor_inventory[$armor_inventory_slot])){
				$items[$menu_slot] = $armor_inventory[$armor_inventory_slot];
			}
		}
		$items[53] = $log->inventory->offhand_contents[0] ?? VanillaItems::AIR();
		return $items;
	}

	public static function syncSlots(InventoryTransaction $transaction) : void{
		foreach($transaction->getActions() as $action){
			if($action instanceof SlotChangeAction){
				$inventory = $action->getInventory();
				$slot = $action->getSlot();
				$item = TypeConverter::getInstance()->coreItemStackToNet($inventory->getItem($slot));
				$transaction->getSource()->getNetworkSession()->getInvManager()->syncSlot($inventory, $slot, $item);
			}
		}
	}
}