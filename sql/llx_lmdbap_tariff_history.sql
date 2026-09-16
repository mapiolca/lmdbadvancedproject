-- Net supplier price snapshots: native price logs omit historical discounts.
CREATE TABLE IF NOT EXISTS llx_lmdbap_tariff_history (
 rowid integer AUTO_INCREMENT PRIMARY KEY,
 entity integer DEFAULT 1 NOT NULL,
 fk_product integer NOT NULL,
 fk_supplier_price integer NOT NULL,
 fk_soc integer NOT NULL,
 date_effective datetime NOT NULL,
 date_capture datetime NOT NULL,
 snapshot_unit_ht double(24,8) DEFAULT NULL,
 snapshot_min_qty double(24,8) NOT NULL,
 currency varchar(3) NOT NULL,
 price_status varchar(64) NOT NULL,
 deleted integer NOT NULL DEFAULT 0,
 fingerprint varchar(64) NOT NULL,
 fk_user_author integer NOT NULL
) ENGINE=innodb;
