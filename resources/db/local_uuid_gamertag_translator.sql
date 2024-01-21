-- #!sqlite
-- #{ deathinventorylog

-- #  { init_translator
CREATE TABLE IF NOT EXISTS death_inventory_log_uuid_gamertag(
  player_uuid BINARY(16) NOT NULL PRIMARY KEY,
  gamertag VARCHAR(16) NOT NULL
);
-- #  }

-- #  { translate_uuids
-- #    :uuids string
SELECT player_uuid, gamertag FROM death_inventory_log_uuid_gamertag WHERE player_uuid IN (:uuids);
-- #  }

-- #  { translate_gamertags
-- #    :gamertags string
SELECT player_uuid, gamertag FROM death_inventory_log_uuid_gamertag WHERE LOWER(gamertag) IN (:gamertags);
-- #  }

-- #  { store_translation
-- #    :player_uuid string
-- #    :gamertag string
INSERT OR REPLACE INTO death_inventory_log_uuid_gamertag(player_uuid, gamertag) VALUES(:player_uuid, :gamertag);
-- #  }

-- #}