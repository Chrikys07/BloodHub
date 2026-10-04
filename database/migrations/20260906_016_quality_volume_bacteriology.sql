-- Volume calculado com snapshot e fila configurável de Bacteriologia.
ALTER TABLE sample_weight_results
    ADD COLUMN IF NOT EXISTS density_used DECIMAL(8,4) NULL AFTER net_weight,
    ADD COLUMN IF NOT EXISTS volume_ml DECIMAL(12,4) NULL AFTER density_used;

-- Código estável do ensaio, preservando eventual cadastro existente.
UPDATE tests SET code='BACTERIOLOGY'
 WHERE code IS NULL AND LOWER(name) IN ('bacteriológico','bacteriologico')
   AND NOT EXISTS (SELECT 1 FROM (SELECT id FROM tests WHERE code='BACTERIOLOGY') stable WHERE stable.id<>tests.id);
INSERT INTO tests(code,name,result_type,unit,allows_ad_hoc,status)
SELECT 'BACTERIOLOGY','Bacteriológico','positive_negative',NULL,0,'active'
 WHERE NOT EXISTS(SELECT 1 FROM tests WHERE code='BACTERIOLOGY');

-- Remove duplicatas históricas antes de impor a idempotência no banco.
DELETE newer FROM sample_tests newer
JOIN sample_tests older ON older.sample_id=newer.sample_id AND older.test_id=newer.test_id AND older.id<newer.id
WHERE newer.test_id IS NOT NULL
  AND NOT EXISTS(SELECT 1 FROM test_results tr WHERE tr.sample_test_id=newer.id);
SET @uk_exists=(SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sample_tests' AND INDEX_NAME='uk_sample_tests_sample_test');
SET @uk_sql=IF(@uk_exists=0,'ALTER TABLE sample_tests ADD UNIQUE KEY uk_sample_tests_sample_test (sample_id,test_id)','SELECT 1');
PREPARE stmt FROM @uk_sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
