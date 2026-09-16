-- Native run_sql ignores already-existing key names on module reactivation.
ALTER TABLE llx_lmdbap_tariff_history ADD UNIQUE KEY uk_lmdbap_th_fingerprint (fingerprint);
ALTER TABLE llx_lmdbap_tariff_history ADD KEY idx_lmdbap_th_product (entity, fk_product, date_effective);
ALTER TABLE llx_lmdbap_tariff_history ADD KEY idx_lmdbap_th_price (fk_supplier_price);
ALTER TABLE llx_lmdbap_tariff_history ADD INDEX idx_lmdbap_th_soc (fk_soc);
ALTER TABLE llx_lmdbap_tariff_history ADD INDEX idx_lmdbap_th_author (fk_user_author);
