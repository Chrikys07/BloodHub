ALTER TABLE sample_shipments
    MODIFY COLUMN purpose ENUM('quality_control','validation','single_assessment') NOT NULL DEFAULT 'quality_control';

ALTER TABLE samples
    MODIFY COLUMN purpose ENUM('quality_control','validation','single_assessment','transfusion_reaction','other') NOT NULL;

INSERT INTO units (client_id,name,unit_type,code,status)
SELECT NULL,'Laboratório de Controle de Qualidade de Hemocomponentes','lcqh','LCQH','active'
WHERE NOT EXISTS (SELECT 1 FROM units WHERE unit_type='lcqh' AND status='active');
