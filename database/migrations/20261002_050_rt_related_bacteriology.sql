-- Testes individuais dos componentes relacionados de uma amostra positiva.
-- Os componentes continuam em bacteriology_positive_sample_records: nenhuma sample e nenhum pool são criados.
ALTER TABLE bacteriology_positive_sample_records
 ADD COLUMN IF NOT EXISTS perform_bacteriology TINYINT(1) NOT NULL DEFAULT 1 AFTER reaction,
 ADD COLUMN IF NOT EXISTS operational_notes TEXT NULL AFTER identified_bacteria_retest;

CREATE TABLE IF NOT EXISTS bacteriology_positive_record_tests (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 positive_record_id BIGINT UNSIGNED NOT NULL,
 sequence SMALLINT UNSIGNED NOT NULL,
 attempt_type ENUM('initial','retest') NOT NULL DEFAULT 'initial',
 status ENUM('pending','completed') NOT NULL DEFAULT 'pending',
 result ENUM('negative','positive') NULL,
 identified_bacteria VARCHAR(255) NULL,
 tested_at DATETIME NULL,
 notes TEXT NULL,
 performed_by BIGINT UNSIGNED NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 CONSTRAINT fk_bact_positive_test_record FOREIGN KEY (positive_record_id) REFERENCES bacteriology_positive_sample_records(id) ON DELETE CASCADE,
 CONSTRAINT fk_bact_positive_test_user FOREIGN KEY (performed_by) REFERENCES users(id) ON DELETE SET NULL,
 UNIQUE KEY uk_bact_positive_test_attempt (positive_record_id,sequence),
 KEY idx_bact_positive_test_status (positive_record_id,status,attempt_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Converte registros RT legados sem apagar os campos históricos usados pelo CQ.
INSERT IGNORE INTO bacteriology_positive_record_tests(positive_record_id,sequence,attempt_type,status,result,identified_bacteria,tested_at)
SELECT r.id,1,'initial',IF(r.bacteriology_result IN('negative','positive'),'completed','pending'),
       IF(r.bacteriology_result IN('negative','positive'),r.bacteriology_result,NULL),r.identified_bacteria_initial,
       IF(r.bacteriology_date IS NULL,NULL,CONCAT(r.bacteriology_date,' 00:00:00'))
FROM bacteriology_positive_sample_records r
JOIN bacteriology_positive_samples ps ON ps.id=r.positive_sample_id
JOIN samples s ON s.id=ps.sample_id AND s.purpose='transfusion_reaction'
WHERE r.position>1 AND r.perform_bacteriology=1;

INSERT IGNORE INTO bacteriology_positive_record_tests(positive_record_id,sequence,attempt_type,status,result,identified_bacteria,tested_at)
SELECT r.id,2,'retest',IF(r.retest_result IN('negative','positive'),'completed','pending'),r.retest_result,r.identified_bacteria_retest,
       IF(r.retest_result IS NULL,NULL,r.updated_at)
FROM bacteriology_positive_sample_records r
JOIN bacteriology_positive_samples ps ON ps.id=r.positive_sample_id
JOIN samples s ON s.id=ps.sample_id AND s.purpose='transfusion_reaction'
WHERE r.position>1 AND r.perform_bacteriology=1 AND r.bacteriology_result='positive';
