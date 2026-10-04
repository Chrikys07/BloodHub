-- Tentativas bacteriologicas individuais auditaveis, compartilhadas entre CQ e RT.
ALTER TABLE bacteriology_results
 MODIFY result ENUM('negative','positive') NULL,
 ADD COLUMN test_sequence SMALLINT UNSIGNED NOT NULL DEFAULT 1 AFTER sample_id,
 ADD COLUMN test_kind ENUM('initial','additional','retest','pool') NOT NULL DEFAULT 'initial' AFTER test_sequence,
 ADD INDEX idx_bact_result_attempt (sample_id,test_sequence,test_kind);

UPDATE bacteriology_results
SET test_kind=CASE
 WHEN source_type='retest' THEN 'retest'
 WHEN source_type='pool' THEN 'pool'
 ELSE 'initial'
END;
