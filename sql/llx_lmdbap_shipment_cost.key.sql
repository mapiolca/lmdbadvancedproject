-- Native run_sql ignores already-existing key names on module reactivation.
ALTER TABLE llx_lmdbap_shipment_cost ADD UNIQUE KEY uk_lmdbap_sc_revision (entity, fk_expeditiondet, revision);
ALTER TABLE llx_lmdbap_shipment_cost ADD KEY idx_lmdbap_sc_shipment (entity, fk_expedition, active);
ALTER TABLE llx_lmdbap_shipment_cost ADD KEY idx_lmdbap_sc_product (fk_product);
ALTER TABLE llx_lmdbap_shipment_cost ADD INDEX idx_lmdbap_sc_supplier_price (fk_supplier_price);
ALTER TABLE llx_lmdbap_shipment_cost ADD INDEX idx_lmdbap_sc_tariff_history (fk_tariff_history);
ALTER TABLE llx_lmdbap_shipment_cost ADD INDEX idx_lmdbap_sc_unit (fk_unit);
ALTER TABLE llx_lmdbap_shipment_cost ADD INDEX idx_lmdbap_sc_user_author (fk_user_author);
