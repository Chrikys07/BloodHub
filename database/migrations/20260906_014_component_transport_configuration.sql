-- Configuração física dos hemocomponentes e snapshot térmico das caixas.
-- Campos permanecem nullable para preservar registros históricos existentes.
ALTER TABLE blood_components
    ADD COLUMN IF NOT EXISTS density DECIMAL(8,4) NULL AFTER name,
    ADD COLUMN IF NOT EXISTS transport_temperature_min DECIMAL(5,2) NULL AFTER density,
    ADD COLUMN IF NOT EXISTS transport_temperature_max DECIMAL(5,2) NULL AFTER transport_temperature_min;

ALTER TABLE sample_shipment_thermal_boxes
    ADD COLUMN IF NOT EXISTS transport_temp_min_snapshot DECIMAL(5,2) NULL AFTER box_code,
    ADD COLUMN IF NOT EXISTS transport_temp_max_snapshot DECIMAL(5,2) NULL AFTER transport_temp_min_snapshot;
