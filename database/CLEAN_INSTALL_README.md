# BloodHub — instalação limpa de homologação

## Escopo

`bloodhub_clean_install.sql` cria, em um banco **vazio**, as 82 tabelas atualmente existentes no BloodHub. A estrutura foi obtida do catálogo do banco local apenas por consultas de leitura e reconciliada com `database/schema.sql`, as migrations e o dump pré-homologação.

O script não contém `CREATE DATABASE`, `USE`, `DROP`, `DELETE` ou `TRUNCATE`. Portanto, ele não seleciona nem altera o banco local `bloodhub`; o banco de destino deve ser selecionado explicitamente pela ferramenta de importação.

## Dados preservados

Foram preservados somente dados mestres/configuracionais:

| Tabela | Registros | Finalidade |
|---|---:|---|
| `roles` | 5 | Perfis do sistema |
| `permissions` | 88 | Permissões vigentes |
| `role_permissions` | 184 | Matriz perfil/permissão |
| `clients` | 1 | Cadastro institucional necessário |
| `units` | 3 | Unidades institucionais necessárias |
| `users` | 1 | Primeiro acesso administrativo |
| `user_units` | 1 | Vínculo indispensável do administrador |
| `blood_components` | 13 | Hemocomponentes |
| `tests` | 20 | Testes laboratoriais |
| `test_blood_components` | 71 | Aplicabilidade teste/hemocomponente |
| `supplies` | 1 | Cadastro mestre de insumo |
| `bag_brands` | 5 | Marcas/referências técnicas de bolsas |
| `bag_brand_tares` | 8 | Taras técnicas |
| `bag_brand_tare_components` | 14 | Aplicabilidade das taras |
| `preservatives` | 2 | Preservativos |
| `blood_component_preservative_shelf_lives` | 13 | Regras de validade |
| `blood_component_test_specifications` | 34 | Especificações e regras vigentes/históricas necessárias à rastreabilidade |
| `cpaf_yield_classification_rules` | 1 | Regra CPAF |
| `billing_cost_centers` | 3 | Centros de custo configurados |
| `billing_services` | 27 | Serviços de faturamento |
| `billing_service_mappings` | 14 | Mapeamentos de faturamento |
| `sampling_rules` | 12 | Regras de amostragem |
| `sampling_production_sources` | 2 | Mapeamento técnico de origem da produção |
| `indicators` | 2 | Definições de indicadores |
| `indicator_targets` | 2 | Metas configuradas |
| `indicator_responsibles` | 1 | Responsabilidade vinculada ao administrador preservado |
| `system_settings` | 4 | Regras de elegibilidade/configuração de laudos |

As tabelas configuracionais `test_supplies`, `dashboard_conformity_targets`, `laboratory_equipment` e `test_equipment_assignments` também são criadas e estão atualmente sem registros na origem.

## Usuário inicial

Permanece apenas o usuário **Cristiano** (ID 1), com:

- papel Administrador e permissões associadas ao papel;
- e-mail usado como login pela aplicação;
- hash de senha já existente, sem senha em texto puro e sem geração de nova senha;
- nome profissional, conselho e registro profissional necessários à liberação de laudos;
- unidade primária e o único vínculo indispensável em `user_units`;
- caminho da foto de perfil já existente (o arquivo correspondente deve ser publicado junto com a aplicação).

Nenhum outro usuário ou vínculo de usuário é inserido.

## Tabelas vazias

As tabelas abaixo são criadas com estrutura, índices, `UNIQUE`s e foreign keys, mas sem dados:

- amostras/remessas/recebimentos: `samples`, `sample_shipments`, `sample_shipment_thermal_boxes`, `sample_shipment_thermal_box_components`, `sample_tests`;
- resultados: `test_results`, `test_result_parameters`, `test_result_supplies`, `test_result_spec_evaluations`, `test_result_equipment`, `sample_weight_results`, `cryoprecipitate_results`, `washed_red_cell_results`, `hemolysis_imports`;
- bacteriologia: `bacteriology_pools`, `bacteriology_pool_members`, `bacteriology_results`, `bacteriology_retests`, `bacteriology_positive_samples`, `bacteriology_positive_sample_records`, `bacteriology_positive_record_tests`;
- fator VIII e CPAF operacional: `factor_viii_pools`, `factor_viii_pool_samples`, `factor_viii_sample_results`, `cpaf_yield_classifications`;
- validações: `validations`, `validation_phases`, `validation_blood_components`, `validation_tests`;
- notificações: `qc_notifications`, `qc_notification_events`, `qc_notification_email_logs`, `qc_notification_analyses`, `qc_notification_analysis_items`, `qc_notification_analysis_events`, `qc_notification_actions`;
- produção e cronogramas: `production_records`, `sampling_schedule_days`;
- faturamento operacional: `billing_periods`, `billing_manual_entries`;
- fechamentos: `qc_monthly_closures`, `qc_monthly_closure_versions`;
- laudos emitidos: `laboratory_reports`;
- indicadores operacionais: `indicator_analyses`, `indicator_actions`;
- correções e auditoria: `administrative_corrections`, `audit_logs`;
- chat: `chat_conversations`, `chat_participants`, `chat_messages`;
- estoque/lotes: `supply_lots`;
- configurações sem cadastro atual: `test_supplies`, `dashboard_conformity_targets`, `laboratory_equipment`, `test_equipment_assignments`.

## Instalação

1. Crie no painel da HostGator um banco MySQL 8.0.46 **novo e vazio**, com usuário próprio e privilégios nesse banco.
2. Selecione explicitamente esse banco no phpMyAdmin e importe `database/bloodhub_clean_install.sql`; ou use um cliente MySQL, informando interativamente a senha:

   ```bash
   mysql --default-character-set=utf8mb4 -h HOST -u USUARIO -p BANCO_HOMOLOGACAO < database/bloodhub_clean_install.sql
   ```

3. Não aplique `schema.sql` nem as migrations antes ou depois: o arquivo limpo já representa a estrutura consolidada atual.
4. Configure as credenciais da homologação somente no arquivo/configuração de ambiente do servidor. O SQL não contém credenciais de banco ou SMTP.
5. Publique a aplicação e o avatar referenciado pelo administrador; depois faça o primeiro login e troque a senha por fluxo autenticado, se a política da homologação exigir.

O script mantém `FOREIGN_KEY_CHECKS=1`, cria os pais antes dos filhos e insere os dados na mesma ordem topológica. Assim, uma dependência inválida interrompe a importação em vez de ser silenciosamente aceita.

## Compatibilidade MariaDB → MySQL 8.0.46

- todas as tabelas foram normalizadas para `ENGINE=InnoDB`;
- charset/collation foram normalizados para `utf8mb4`/`utf8mb4_unicode_ci`, ambos suportados no MySQL 8;
- valores de `AUTO_INCREMENT` do desenvolvimento foram removidos das definições; IDs mestres explícitos continuam preservados e o próximo valor é calculado pelo MySQL;
- não foram levados cabeçalhos, locks, comandos de seleção de banco ou desativação de foreign keys do dump MariaDB;
- colunas geradas, constraints `CHECK`, `DATETIME(6)` e validações `json_valid` existentes foram mantidas em sintaxe aceita pelo MySQL 8.0.46;
- a FK `fk_cpaf_yield_component` usa `ON DELETE RESTRICT`: no MySQL 8, uma foreign key sobre `blood_component_id`, coluna-base da coluna gerada `STORED` `active_component_guard`, não pode usar `CASCADE`, `SET NULL` ou `SET DEFAULT` como ação referencial. A restrição preserva a FK, impede a exclusão de hemocomponentes com regras CPAF e mantém o histórico e a unicidade da única classificação ativa por hemocomponente;
- os tipos com largura de exibição, como `BIGINT(20)` e `TINYINT(1)`, foram mantidos por compatibilidade estrutural; no MySQL 8 a largura é apenas legada e não altera o armazenamento;
- não há views, triggers, procedures, functions ou events no catálogo atual.

## Verificações pós-importação

Execute no banco de homologação:

```sql
SELECT VERSION();
SELECT COUNT(*) AS total_tabelas
FROM information_schema.tables
WHERE table_schema = DATABASE() AND table_type = 'BASE TABLE';

SELECT id, name, email, role_id, primary_unit_id, status
FROM users;

SELECT r.name AS role_name, COUNT(rp.permission_id) AS permissions
FROM users u
JOIN roles r ON r.id = u.role_id
LEFT JOIN role_permissions rp ON rp.role_id = r.id
WHERE u.id = 1
GROUP BY r.id, r.name;

SELECT
  (SELECT COUNT(*) FROM samples) AS samples,
  (SELECT COUNT(*) FROM sample_shipments) AS shipments,
  (SELECT COUNT(*) FROM test_results) AS test_results,
  (SELECT COUNT(*) FROM audit_logs) AS audit_logs,
  (SELECT COUNT(*) FROM chat_messages) AS chat_messages,
  (SELECT COUNT(*) FROM laboratory_reports) AS laboratory_reports;
```

Resultados esperados: MySQL `8.0.46`, 82 tabelas, exatamente um usuário (`Cristiano`) e zero em todas as contagens operacionais acima. Também se recomenda testar login, menus administrativos, cadastro de uma amostra descartável de homologação e geração controlada de laudo; esses testes ocorrerão já na base nova e não fazem parte do SQL inicial.

## Integridade e segurança revisadas

- os registros preservados foram verificados contra todas as foreign keys e não deixam referências órfãs;
- não há inserts das tabelas operacionais listadas acima;
- a única credencial de usuário é um hash reconhecido pelo PHP; não há senha em texto puro;
- não há segredo SMTP, host/usuário/senha do banco local ou credencial de infraestrutura;
- o banco local `bloodhub` não foi alterado durante a geração ou validação.
