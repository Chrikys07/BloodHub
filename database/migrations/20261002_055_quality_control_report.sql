-- Relatório Gerencial de Controle de Qualidade (somente leitura).
INSERT INTO permissions(permission_key,name,description,status)
SELECT 'reports.quality_control.view','Visualizar relatório gerencial de CQ','Consulta e exportação dos resultados vigentes de Controle de Qualidade.','active'
WHERE NOT EXISTS(SELECT 1 FROM permissions WHERE permission_key='reports.quality_control.view');

INSERT IGNORE INTO role_permissions(role_id,permission_id)
SELECT r.id,p.id FROM roles r JOIN permissions p ON p.permission_key='reports.quality_control.view'
WHERE r.slug IN ('administrador','gestao','lcqh');

-- Cobre finalidade, período regente, escopo e agrupamento mais usados pelo relatório.
CREATE INDEX idx_samples_qc_report ON samples(purpose,received_at,origin_unit_id,blood_component_id,status);
CREATE INDEX idx_samples_lcqh_code ON samples(lcqh_code);
