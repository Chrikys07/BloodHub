-- Completa instalações que já aplicaram a configuração inicial sem sobrescrever preferências salvas.
INSERT INTO system_settings(setting_key,setting_value)
VALUES
 ('report_eligibility_external_quality_control','1'),
 ('report_eligibility_external_transfusion_reaction','1'),
 ('report_eligibility_internal_quality_control','0'),
 ('report_eligibility_internal_transfusion_reaction','1')
ON DUPLICATE KEY UPDATE setting_key=VALUES(setting_key);
