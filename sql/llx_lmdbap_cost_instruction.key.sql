ALTER TABLE llx_lmdbap_cost_instruction ADD UNIQUE KEY uk_lmdbap_ci_pair (entity, fk_project, fk_product);
ALTER TABLE llx_lmdbap_cost_instruction ADD INDEX idx_lmdbap_ci_product (fk_product);
