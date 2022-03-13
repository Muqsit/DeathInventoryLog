<?php

declare(strict_types=1);

namespace muqsit\deathinventorylog\util;

use InvalidArgumentException;
use pocketmine\item\Item;
use pocketmine\nbt\BigEndianNbtSerializer;
use pocketmine\nbt\NBT;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\nbt\TreeRoot;

final class InventorySerializer{

	private const TAG_NAME = "contents";

	/**
	 * @param Item[] $contents
	 * @return string
	 *
	 * @phpstan-param array<int, Item> $contents
	 */
	public static function serialize(array $contents) : string{
		$contents_tag = [];
		foreach($contents as $slot => $item){
			$contents_tag[] = $item->nbtSerialize($slot);
		}
		return (new BigEndianNbtSerializer())->write(new TreeRoot(CompoundTag::create()->setTag(self::TAG_NAME, new ListTag($contents_tag, NBT::TAG_Compound))));
	}

	/**
	 * @param string $string
	 * @return Item[]
	 *
	 * @phpstan-return array<int, Item>
	 */
	public static function deSerialize(string $string) : array{
		$tag = (new BigEndianNbtSerializer())->read($string)->mustGetCompoundTag()->getListTag(self::TAG_NAME) ?? throw new InvalidArgumentException("Invalid serialized string specified");

		$contents = [];
		/** @var CompoundTag $value */
		foreach($tag as $value){
			$contents[$value->getByte("Slot")] = Item::nbtDeserialize($value);
		}
		return $contents;
	}
}