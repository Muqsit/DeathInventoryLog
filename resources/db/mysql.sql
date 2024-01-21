-- #!mysql

-- #{ deathinventorylog

-- #  { init
-- #    { create_table
CREATE TABLE IF NOT EXISTS death_inventory_log (
                                                   id INT UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
                                                   player_uuid BINARY(16) NOT NULL,
                                                   log_time TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
                                                   item_inventory BLOB NOT NULL,
                                                   armor_inventory BLOB NOT NULL,
                                                   offhand_inventory BLOB NOT NULL,
                                                   KEY uuid_time_idx(player_uuid, log_time)
    );
-- #    }
-- #  }
-- #  { retrieve
-- #    :id int
SELECT id, player_uuid, UNIX_TIMESTAMP(log_time) AS log_time, item_inventory, armor_inventory, offhand_inventory FROM death_inventory_log WHERE id = :id;
-- #  }

-- #  { retrieve_player
-- #    :player_uuid string
-- #    :offset int
-- #    :length int
SELECT id, player_uuid, UNIX_TIMESTAMP(log_time) AS log_time, item_inventory, armor_inventory, offhand_inventory FROM death_inventory_log WHERE player_uuid = :player_uuid ORDER BY id DESC LIMIT :offset, :length;
-- #  }

-- #  { save
-- #    :player_uuid string
-- #    :item_inventory string
-- #    :armor_inventory string
-- #    :offhand_inventory string
INSERT INTO death_inventory_log(player_uuid, item_inventory, armor_inventory, offhand_inventory) VALUES(FROM_BASE64(:player_uuid), FROM_BASE64(:item_inventory), FROM_BASE64(:armor_inventory), FROM_BASE64(:offhand_inventory));
-- #  }

-- #  { purge
-- #    :log_time int
DELETE FROM death_inventory_log WHERE log_time <= FROM_UNIXTIME(:log_time);
-- #  }

-- #  { upgrade
-- #    { impl_offhand_inventory
-- ALTER TABLE death_inventory_log ADD COLUMN offhand_inventory BLOB DEFAULT "";
-- Elimina la línea anterior si solo necesitas una columna offhand_inventory.
-- #    }
-- #  }
-- #}
