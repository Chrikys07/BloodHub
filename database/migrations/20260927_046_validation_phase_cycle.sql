-- Ciclo completo das etapas de validação, sem perda do histórico existente.
ALTER TABLE validation_phases
 ADD COLUMN IF NOT EXISTS outcome ENUM('satisfactory','unsatisfactory','inconclusive') NULL AFTER status,
 ADD COLUMN IF NOT EXISTS conclusion TEXT NULL AFTER outcome;

ALTER TABLE validations
 ADD COLUMN IF NOT EXISTS final_conclusion TEXT NULL AFTER actual_end_date;

-- Fases ativas antigas passam a ter uma data de início rastreável.
UPDATE validation_phases vp
JOIN validations v ON v.id=vp.validation_id
SET vp.started_at=COALESCE(vp.started_at,CONCAT(v.start_date,' 00:00:00'))
WHERE vp.status='active' AND vp.started_at IS NULL;

-- Novas etapas iniciadas pelo fluxo legado também recebem início automaticamente.
ALTER TABLE validation_phases
 MODIFY started_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP;
