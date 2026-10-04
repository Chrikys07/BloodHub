-- Identificacao bacteriana por etapa e estado operacional do resultado.
-- A coluna legada identified_bacteria dos registros positivos e preservada.
ALTER TABLE bacteriology_results
 ADD COLUMN identified_bacteria VARCHAR(255) NULL AFTER result,
 ADD COLUMN workflow_status ENUM('registered','retest_pending','identification_pending','completed') NOT NULL DEFAULT 'registered' AFTER bacteriological_conformity,
 ADD INDEX idx_bact_result_workflow (workflow_status,stage,result);

ALTER TABLE bacteriology_positive_sample_records
 ADD COLUMN identified_bacteria_initial VARCHAR(255) NULL AFTER retest_result,
 ADD COLUMN identified_bacteria_retest VARCHAR(255) NULL AFTER identified_bacteria;

UPDATE bacteriology_positive_sample_records
SET identified_bacteria_retest=identified_bacteria
WHERE retest_result='positive' AND identified_bacteria_retest IS NULL AND identified_bacteria IS NOT NULL;

UPDATE bacteriology_positive_sample_records
SET identified_bacteria_initial=identified_bacteria
WHERE bacteriology_result='positive' AND identified_bacteria_initial IS NULL
 AND identified_bacteria IS NOT NULL AND (retest_result IS NULL OR retest_result<>'positive');

UPDATE bacteriology_results
SET workflow_status=CASE
 WHEN is_final=1 THEN 'completed'
 WHEN stage='individual_initial' AND result='positive' THEN 'retest_pending'
 ELSE 'registered'
END;
