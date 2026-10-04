-- Relacionamentos normalizados para rastreabilidade, preservando as colunas textuais históricas.
ALTER TABLE bacteriology_positive_samples
 ADD COLUMN client_id BIGINT UNSIGNED NULL AFTER sample_id,
 ADD CONSTRAINT fk_bact_positive_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE SET NULL,
 ADD INDEX idx_bact_positive_client (client_id);

UPDATE bacteriology_positive_samples ps
JOIN samples s ON s.id=ps.sample_id
SET ps.client_id=s.client_id
WHERE ps.client_id IS NULL;

ALTER TABLE bacteriology_positive_sample_records
 ADD COLUMN blood_component_id BIGINT UNSIGNED NULL AFTER donation_number,
 ADD COLUMN storage_unit_id BIGINT UNSIGNED NULL AFTER situation,
 ADD CONSTRAINT fk_bact_positive_record_component FOREIGN KEY (blood_component_id) REFERENCES blood_components(id) ON DELETE SET NULL,
 ADD CONSTRAINT fk_bact_positive_record_storage FOREIGN KEY (storage_unit_id) REFERENCES units(id) ON DELETE SET NULL,
 ADD INDEX idx_bact_positive_record_component (blood_component_id),
 ADD INDEX idx_bact_positive_record_storage (storage_unit_id);

UPDATE bacteriology_positive_sample_records r
JOIN blood_components bc ON bc.name=r.hemocomponent
SET r.blood_component_id=bc.id
WHERE r.blood_component_id IS NULL;

UPDATE bacteriology_positive_sample_records r
JOIN bacteriology_positive_samples ps ON ps.id=r.positive_sample_id
JOIN units u ON u.name=r.storage_location AND u.client_id=ps.client_id
SET r.storage_unit_id=u.id
WHERE r.storage_unit_id IS NULL;
