-- #!mysql
-- #{ deathinventorylog

-- #  { init
-- #    { create_table
CREATE TABLE IF NOT EXISTS death_inventory_log(
  id INT UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
  uuid BINARY(16) NOT NULL,
  time TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
  inventory BLOB NOT NULL,
  armor_inventory BLOB NOT NULL,
  KEY uuid_idx(uuid)
);
-- #    }
-- #    { index_uuid
ALTER TABLE death_inventory_log ADD INDEX uuid_idx(uuid);
-- #    }
-- #  }

-- #  { retrieve
-- #    :id int
SELECT id, uuid, UNIX_TIMESTAMP(time) AS time, inventory, armor_inventory FROM death_inventory_log WHERE id=:id;
-- #  }

-- #  { retrieve_player
-- #    :uuid string
-- #    :offset int
-- #    :length int
SELECT id, uuid, UNIX_TIMESTAMP(time) AS time, inventory, armor_inventory FROM death_inventory_log WHERE uuid=:uuid ORDER BY id DESC LIMIT :offset, :length;
-- #  }

-- #  { save
-- #    :uuid string
-- #    :inventory string
-- #    :armor_inventory string
INSERT INTO death_inventory_log(uuid, inventory, armor_inventory) VALUES(FROM_BASE64(:uuid), FROM_BASE64(:inventory), FROM_BASE64(:armor_inventory));
-- #  }

-- #  { purge
-- #    :time int
DELETE FROM death_inventory_log WHERE time <= FROM_UNIXTIME(:time);
-- #  }

-- #}