# Faturamento — PDLAB005 Rev.14

O módulo usa exclusivamente `tests.is_final_result`, a classificação gerencial já consumida pelo Dashboard Global e pelo Relatório Gerencial. Um resultado automático exige teste final ativo, amostra não cancelada, `sample_tests.status = completed`, data de conclusão na competência e mapping ativo para serviço/contexto/hemocomponente.

A unidade conceitual é `sample_id + test_id + billing_service_id`. A consulta escolhe o último `test_results.id`, portanto retificações não multiplicam quantidades. Para bacteriologia, escolhe o último `bacteriology_results` com `is_final = 1`; preliminares, pools e retestes técnicos não são contados separadamente.

A matriz parte do produto cartesiano de todos os centros e serviços ativos. Lançamentos complementares atendem fontes externas. O fechamento só ocorre sem resultados finais sem mapping ou unidades sem centro, e preserva a matriz completa, inclusive zeros, em JSON. A exportação SpreadsheetML é entregue como `.xls` e mantém códigos e ordem institucional.

## Instalação

```powershell
php scripts/migrate.php database/migrations/20261002_055_billing_module.sql
php scripts/check_billing.php
```
