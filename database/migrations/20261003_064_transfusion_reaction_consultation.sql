INSERT INTO permissions(permission_key,name,module,status)
VALUES('transfusion_reaction_consultation.view','Consultar reações transfusionais','transfusion_reaction_consultation','active')
ON DUPLICATE KEY UPDATE name=VALUES(name),module=VALUES(module),status='active';

INSERT IGNORE INTO role_permissions(role_id,permission_id)
SELECT r.id,p.id FROM roles r JOIN permissions p ON p.permission_key='transfusion_reaction_consultation.view'
WHERE r.slug IN ('administrador','lcqh','gestao','agencia-transfusional');

-- O perfil cliente existente é Agência Transfusional. Ele consulta, mas não opera o fluxo do LCQH.
DELETE rp FROM role_permissions rp JOIN roles r ON r.id=rp.role_id JOIN permissions p ON p.id=rp.permission_id
WHERE r.slug='agencia-transfusional' AND p.permission_key IN ('transfusion_reactions.view','transfusion_reactions.edit','transfusion_reactions.manage');
