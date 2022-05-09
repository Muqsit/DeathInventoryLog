# DeathInventoryLog
[![](https://poggit.pmmp.io/shield.state/DeathInventoryLog)](https://poggit.pmmp.io/p/DeathInventoryLog)

A plugin that logs players' inventory contents on death for future retrieval.<br>
Handy for server administrators who may want to refund items that players have lost to hackers, bugs, and the likes.

## How it works?
When a player dies, their inventory and armor inventory contents are logged and are assigned an ID.
The player's personal death inventory logs list can be accessed by running `/dil history <player> [page=1]`.
```
> dil history BoxierChimera37
Death Entries Page 1
1. #5 logged on 2021-10-15 02:43:20
2. #2 logged on 2021-10-15 02:18:58
3. #1 logged on 2021-10-15 02:17:36
```
The number to right of number sign (`#`) is the ID of their death's inventory log.
Inventory and armor inventory contents linked to a player's specific death can be accessed by running `/dil retrieve <id>`
```
> dil retrieve 5
This command can only be executed in-game.
```
This command can be used only in-game because it opens an inventory GUI containing contents of the player's inventory during their time of death.
Items can be added and removed from this inventory GUI, however the death inventory log per se is **unmodifyable**. When the log is retrieved again, you'll notice
items in the log inventory GUI laid out exactly the way they originally were, despite items being taken out of that inventory GUI.


## Integrating with DeathInventoryLog
For plugins that would like to integrate with DeathInventoryLog to log manually handled player deaths,
such as logging deaths from "Combat Logger NPCs", there isn't a sane method to do so _yet_ other than directly accessing the database
interface of this plugin in this way (PRs are welcome!):
```php
/** @var Loader $plugin */
$plugin = $server->getPluginManager()->getPlugin("DeathInventoryLog");

/** @var Item[] $inventory */		// = Living::getInventory()->getContents()
/** @var Item[] $armor_inventory */	// = Living::getArmorInventory()->getContents()
/** @var UuidInterface $uuid */		// = Player::getUniqueId()
$plugin->getDatabase()->store($uuid, new DeathInventory($inventory, $armor_inventory), function(int $id) : void{
	echo "Logged death inventory, use '/dil history {$id}' to access this log";
});
```


## UUID to Gamertag (and vice-versa) Translation
DeathInventoryLog stores players' death inventory logs indexed by their UUIDs.
By default, DeathInventoryLog maintains a UUID => Gamertag mapping in a local database (located at `plugin_data/DeathInventoryLog/uuid_gamertag_translations.sqlite`).
This mapping is used to translate player gamertag specified during `/dil history <player>` to a UUID. This UUID is then seached in the database containing all death inventory logs.

If you maintain a UUID <-> Gamertag mapping already and do not like how there's this another database being maintained by this plugin and causing data redundancy,
and you also happen to have some knowledge in the field of programming plugins for PocketMine-MP, you can write your own `GamertagUUIDTranslator` implementation and set it to
DeathInventoryLog in the following way:
```php
/** @var Loader $plugin */
$plugin = $server->getPluginManager()->getPlugin("DeathInventoryLog");

/** @var GamertagUUIDTranslator $my_custom_translator */ 
$plugin->setTranslator($my_custom_translator);
```
`GamertagUUIDTranslator` is asynchronous-friendly, so you can translate between uuids and gamertags without necessarily blocking the main thread while at it.
