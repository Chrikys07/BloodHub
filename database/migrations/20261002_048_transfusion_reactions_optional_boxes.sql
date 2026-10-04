ALTER TABLE sample_shipments
    MODIFY COLUMN purpose ENUM('quality_control','validation','single_assessment','transfusion_reaction') NOT NULL DEFAULT 'quality_control';
ALTER TABLE samples ADD COLUMN IF NOT EXISTS patient_name VARCHAR(255) NULL AFTER purpose;
ALTER TABLE sample_shipment_thermal_boxes MODIFY COLUMN box_code VARCHAR(80) NULL;
INSERT INTO permissions(permission_key,name,module,status) VALUES
('transfusion_reactions.view','Visualizar reacoes transfusionais','quality_results','active'),
('transfusion_reactions.edit','Registrar bacteriologico de reacoes transfusionais','quality_results','active')
ON DUPLICATE KEY UPDATE name=VALUES(name),module=VALUES(module),status='active';
INSERT IGNORE INTO role_permissions(role_id,permission_id)
SELECT r.id,p.id FROM roles r JOIN permissions p ON p.permission_key IN ('transfusion_reactions.view','transfusion_reactions.edit')
WHERE r.slug IN ('administrador','lcqh');
