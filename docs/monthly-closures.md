# Fechamento Mensal do Controle de Qualidade

O módulo registra o aceite formal de um período por unidade de processamento sem duplicar o Dashboard Global. O Dashboard permanece como visão operacional atual; o fechamento preserva como o período estava no momento do aceite.

## Período e fontes

- Chave corrente: `unit_id + year + month`.
- Produção: `production_records.production_date`.
- Amostras, análises, bacteriologia, notificações e correções: mês de `samples.production_date`.
- Amostragem: `SamplingScheduleService`, incluindo fonte de produção e regra efetiva do período.
- Ausência de registro de produção é `null`; produção informada igual a zero permanece `0`.

## Pendências

- Bloqueantes: produção esperada não informada e inconsistência estrutural.
- Justificáveis: amostragem abaixo do mínimo, CQ em andamento, resultado obrigatório incompleto, bacteriologia pendente e notificação aberta.
- Pendência justificável nunca fecha automaticamente. O usuário com `monthly_closure.close` deve informar uma justificativa.

## Ciclo e histórico

`OPEN`, `READY`, `CLOSED` e `REOPENED` são apresentados como Em aberto, Pronto para fechamento, Fechado e Reaberto. O mês atual permanece Em aberto por padrão. Cada fechamento cria uma linha imutável em `qc_monthly_closure_versions`; reabrir não apaga a versão anterior. O comprovante usa exclusivamente o JSON dessa versão.

Edições operacionais comuns de Produção e CQ são recusadas enquanto o período estiver `CLOSED`. Bacteriologia tardia, notificações e Correções Administrativas permanecem disponíveis e auditáveis.

## Verificação

Execute:

```powershell
php scripts/check_monthly_closure.php
```

O teste integrado usa uma unidade temporária, valida produção zero, fechamento, snapshot, reabertura e segunda versão, e remove os dados temporários ao final.
