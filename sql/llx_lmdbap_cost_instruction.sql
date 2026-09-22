-- Explicit analytical snapshot: never update the product or its supplier tariffs.
CREATE TABLE IF NOT EXISTS llx_lmdbap_cost_instruction (
 rowid integer AUTO_INCREMENT PRIMARY KEY,
 entity integer DEFAULT 1 NOT NULL,
 fk_project integer NOT NULL,
 fk_product integer NOT NULL,
 fk_unit integer NOT NULL DEFAULT 0,
 snapshot_unit_ht double(24,8) NOT NULL,
 currency varchar(3) NOT NULL,
 source_code varchar(32) NOT NULL,
 fk_source integer DEFAULT NULL,
 fk_source_history integer DEFAULT NULL,
 source_date datetime DEFAULT NULL,
 date_creation datetime NOT NULL,
 fk_user_author integer NOT NULL,
 request_key varchar(64) NOT NULL
) ENGINE=innodb;
