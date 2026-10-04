-- Regras institucionais de elegibilidade para emissão de laudos.
INSERT INTO system_settings(setting_key,setting_value)
VALUES
 ('report_eligibility_external_quality_control','1'),
 ('report_eligibility_external_transfusion_reaction','1'),
 ('report_eligibility_internal_quality_control','0'),
 ('report_eligibility_internal_transfusion_reaction','1')
ON DUPLICATE KEY UPDATE setting_key=VALUES(setting_key);
