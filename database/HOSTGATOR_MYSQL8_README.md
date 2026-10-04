# BloodHub na HostGator — MySQL 8.0.46

## Arquivo de instalação

Este arquivo registra o workaround histórico usado na investigação. Para instalações oficiais novas, use `database/bloodhub_clean_install.sql`, agora nativamente compatível com MySQL 8. O arquivo `database/bloodhub_hostgator_mysql8.sql` também foi alinhado para não manter uma ação referencial incompatível.

O instalador não contém `CREATE DATABASE`, `USE`, `DROP`, `DELETE` ou `TRUNCATE`. Selecione previamente, no phpMyAdmin, o banco vazio de destino e importe o arquivo em UTF-8.

## Causa identificada

A auditoria das definições não encontrou incompatibilidade lógica ou de tipo na relação reportada:

- `blood_components.id` é `BIGINT UNSIGNED NOT NULL`, chave primária e `AUTO_INCREMENT`;
- `cpaf_yield_classification_rules.blood_component_id` é `BIGINT UNSIGNED NOT NULL`;
- as duas tabelas são InnoDB e usam `utf8mb4_unicode_ci` como collation padrão;
- a collation não participa da compatibilidade desta FK porque as colunas são numéricas;
- o registro mestre preservado da regra usa `blood_component_id = 14`, e o hemocomponente `blood_components.id = 14` existe;
- as FKs de autoria, atualização, autorreferência e a FK de `cpaf_yield_classifications.rule_id` continuam estruturalmente válidas.

O erro `#1215` tem causa estrutural: no MySQL 8, uma foreign key sobre uma coluna-base de uma coluna gerada `STORED` não pode usar `CASCADE`, `SET NULL` ou `SET DEFAULT` como ação referencial. Neste caso, `blood_component_id` é usado por `active_component_guard`, portanto `fk_cpaf_yield_component` deve usar `ON DELETE RESTRICT`.

## Tratamento aplicado

A integridade física foi preservada. A FK não foi removida:

1. `fk_cpaf_yield_component` foi retirada apenas do `CREATE TABLE` de `cpaf_yield_classification_rules`;
2. foi criado o índice simples e explícito `idx_cpaf_yield_blood_component_id (blood_component_id)`;
3. todas as tabelas, PKs, demais índices, demais FKs e dados configuracionais são criados/carregados primeiro;
4. depois do `COMMIT`, ainda com `FOREIGN_KEY_CHECKS = 1`, a FK é criada por `ALTER TABLE`, no fim do script, com `ON DELETE RESTRICT`;
5. somente após o `ALTER TABLE` a configuração anterior de `FOREIGN_KEY_CHECKS` é restaurada.

O índice simples é intencional, mesmo existindo outros índices compostos iniciados pela mesma coluna: ele elimina qualquer dependência da escolha automática de índice pelo InnoDB durante o `ALTER TABLE`.

## Diferenças em relação ao clean install

As únicas diferenças funcionais no instalador HostGator são o índice explícito e a criação tardia da FK problemática. As demais estruturas e todos os `INSERT`s são iguais aos de `bloodhub_clean_install.sql`.

A política da base limpa foi mantida: somente dados mestres/configuracionais, um único usuário Administrador e nenhuma amostra, resultado ou movimentação operacional de desenvolvimento. Nenhum arquivo PHP foi alterado.

## Validações após a importação

Execute as consultas abaixo no banco de destino.

### Versão e tabelas

```sql
SELECT VERSION();

SELECT TABLE_NAME, ENGINE, TABLE_COLLATION
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN (
    'blood_components',
    'cpaf_yield_classification_rules',
    'cpaf_yield_classifications'
  );
```

As três tabelas devem aparecer como InnoDB.

### Tipos das colunas relacionadas

```sql
SELECT TABLE_NAME, COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_KEY
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND (
    (TABLE_NAME = 'blood_components' AND COLUMN_NAME = 'id')
    OR
    (TABLE_NAME = 'cpaf_yield_classification_rules'
      AND COLUMN_NAME = 'blood_component_id')
  );
```

Ambas devem ter `COLUMN_TYPE = bigint unsigned`.

### Índice explícito e FK final

```sql
SHOW INDEX FROM cpaf_yield_classification_rules
WHERE Key_name = 'idx_cpaf_yield_blood_component_id';

SELECT CONSTRAINT_NAME, TABLE_NAME, REFERENCED_TABLE_NAME, DELETE_RULE
FROM information_schema.REFERENTIAL_CONSTRAINTS
WHERE CONSTRAINT_SCHEMA = DATABASE()
  AND CONSTRAINT_NAME = 'fk_cpaf_yield_component';
```

Deve existir uma linha para o índice e uma para a FK, com tabela referenciada `blood_components` e `DELETE_RULE = RESTRICT`.

### Ausência de órfãos

```sql
SELECT COUNT(*) AS orphan_rules
FROM cpaf_yield_classification_rules AS r
LEFT JOIN blood_components AS b ON b.id = r.blood_component_id
WHERE b.id IS NULL;

SELECT COUNT(*) AS orphan_classification_rules
FROM cpaf_yield_classifications AS c
LEFT JOIN cpaf_yield_classification_rules AS r ON r.id = c.rule_id
WHERE c.rule_id IS NOT NULL AND r.id IS NULL;
```

Os dois resultados devem ser zero.

### Perfil da instalação limpa

```sql
SELECT COUNT(*) AS users_total FROM users;
SELECT id, name, email, status FROM users;

SELECT COUNT(*) AS samples_total FROM samples;
SELECT COUNT(*) AS sample_tests_total FROM sample_tests;
SELECT COUNT(*) AS test_results_total FROM test_results;
SELECT COUNT(*) AS cpaf_classifications_total FROM cpaf_yield_classifications;
SELECT COUNT(*) AS shipments_total FROM sample_shipments;
```

Deve existir exatamente um usuário, o Administrador já definido no clean install. Todas as contagens operacionais acima devem ser zero.

## Se o `ALTER TABLE` final ainda retornar #1215

Não ignore a falha nem considere a instalação concluída. Execute imediatamente, na mesma sessão se possível:

```sql
SHOW WARNINGS;
SHOW ENGINE INNODB STATUS;
SHOW CREATE TABLE blood_components;
SHOW CREATE TABLE cpaf_yield_classification_rules;
```

Guarde a seção `LATEST FOREIGN KEY ERROR`. Ela informa o motivo interno que o phpMyAdmin normalmente resume como `#1215`.

Somente se a HostGator confirmar uma limitação irremovível do serviço deve-se operar excepcionalmente sem a FK física. Nesse cenário, o índice `idx_cpaf_yield_blood_component_id` permanece e as validações existentes da aplicação continuam ativas, mas essa exceção deve ser registrada operacionalmente e a consulta de órfãos deve fazer parte das verificações periódicas. O instalador entregue não adota esse fallback automaticamente: por segurança, ele tenta e exige a criação da FK.
