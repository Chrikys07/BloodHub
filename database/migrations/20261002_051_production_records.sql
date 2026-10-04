CREATE TABLE IF NOT EXISTS production_records (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 unit_id BIGINT UNSIGNED NOT NULL,
 production_date DATE NOT NULL,
 blood_component_id BIGINT UNSIGNED NOT NULL,
 quantity INT NOT NULL,
 created_by BIGINT UNSIGNED NULL,
 updated_by BIGINT UNSIGNED NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 CONSTRAINT fk_production_unit FOREIGN KEY(unit_id) REFERENCES units(id) ON DELETE RESTRICT,
 CONSTRAINT fk_production_component FOREIGN KEY(blood_component_id) REFERENCES blood_components(id) ON DELETE RESTRICT,
 CONSTRAINT fk_production_created_by FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL,
 CONSTRAINT fk_production_updated_by FOREIGN KEY(updated_by) REFERENCES users(id) ON DELETE SET NULL,
 UNIQUE KEY uk_production_unit_date_component(unit_id,production_date,blood_component_id),
 INDEX idx_production_period(unit_id,production_date),
 INDEX idx_production_component_period(blood_component_id,production_date),
 CONSTRAINT chk_production_quantity CHECK(quantity >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE production_records MODIFY quantity INT NOT NULL;

INSERT INTO permissions(permission_key,name,module,status) VALUES
 ('production.view','Visualizar producao','production','active'),
 ('production.create','Registrar producao','production','active'),
 ('production.edit','Editar producao','production','active'),
 ('production.admin','Administrar producao','production','active')
ON DUPLICATE KEY UPDATE name=VALUES(name),module=VALUES(module),status='active';

INSERT IGNORE INTO role_permissions(role_id,permission_id)
SELECT r.id,p.id FROM roles r JOIN permissions p
WHERE (r.slug='administrador' OR LOWER(r.name)='administrador')
  AND p.permission_key IN ('production.view','production.create','production.edit','production.admin');

INSERT IGNORE INTO role_permissions(role_id,permission_id)
SELECT r.id,p.id FROM roles r JOIN permissions p
WHERE (r.slug='processamento' OR LOWER(r.name)='processamento')
  AND p.permission_key IN ('production.view','production.create','production.edit');
