-- Há combinações históricas duplicadas; não criar UNIQUE antes do saneamento autorizado.
-- Diagnóstico executado em 2026-09-27: 1 combinação duplicada.
ALTER TABLE samples
    ADD INDEX idx_samples_donation_component (donation_number, blood_component_id);
