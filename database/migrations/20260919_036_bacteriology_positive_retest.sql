-- Resultado do reteste dos registros adicionais de uma ocorrência positiva.
-- bacteriology_result permanece como o resultado inicial para compatibilidade legada.
ALTER TABLE bacteriology_positive_sample_records
 ADD COLUMN retest_result ENUM('negative','positive') NULL AFTER bacteriology_result,
 ADD INDEX idx_bact_positive_record_retest (retest_result);
