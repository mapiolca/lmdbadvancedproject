-- Complement to an immutable shipment revision, following its owning entity.
CREATE TABLE IF NOT EXISTS llx_lmdbap_cost_fallback (
 rowid integer AUTO_INCREMENT PRIMARY KEY,
 entity integer DEFAULT 1 NOT NULL,
 fk_shipment_cost integer NOT NULL,
 snapshot_unit_ht double(24,8) NOT NULL,
 currency varchar(3) NOT NULL,
 source_code varchar(32) NOT NULL,
 fk_source integer DEFAULT NULL,
 fk_source_history integer DEFAULT NULL,
 source_date datetime NOT NULL
) ENGINE=innodb;
