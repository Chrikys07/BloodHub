# Dashboard Global

O Dashboard Global é uma camada de leitura institucional. Ele não reavalia limites laboratoriais.

## Definições

- **Produzido:** soma de `production_records.quantity`, pela unidade, hemocomponente e data de produção. A fonte herdada é resolvida por `sampling_production_sources` no `SamplingScheduleService`. A ausência de linhas é `null` (produção não informada); uma linha cuja quantidade é zero é produção real igual a zero.
- **Testado (CQ):** `COUNT DISTINCT` de amostras com `purpose = quality_control`, não canceladas, com `status = completed`, usando `production_date`. Amostras recebidas, em análise ou com resultados parciais não entram nesse KPI.
- **Amostragem:** total testado / total produzido × 100. Sem produção informada ou quando o total produzido é zero, o percentual não é calculado.
- **Mínimo:** calculado por `SamplingScheduleService::calculateRequired()` com a regra efetiva do mês (`sampling_rules`). A situação institucional também exige o cumprimento em cada unidade/período; excesso em uma unidade não compensa falta em outra.
- **Conformidade por teste:** usa apenas `test_result_spec_evaluations.conformity_status` de testes concluídos. `NO_SPECIFICATION`, `NOT_EVALUATED`, pendentes, ausentes e cancelados ficam fora do denominador. O último `test_results.id` de cada `sample_test` é o vigente; correções administrativas alteram esse registro e sua avaliação sem gerar dupla contagem.
- **Resultado final:** cards, gráfico, colunas dinâmicas, PDF e conformidade global consideram apenas testes ativos com `tests.is_final_result = 1`. Inputs auxiliares permanecem normalmente nas telas e cálculos laboratoriais.
- **Meta agregada:** a cor vem de `dashboard_conformity_targets`, por hemocomponente e teste, usando a vigência válida no fim do período consultado. Sem meta ou sem avaliados, o indicador permanece neutro; nenhuma porcentagem é presumida.
- **Bacteriológico:** usa exclusivamente o registro mais recente de `bacteriology_results` marcado como final. Apenas `bacteriological_conformity` conforme/não conforme entra no denominador. O filtro de propósito da amostra impede mistura com reação transfusional.
- **Conformidade global:** considera somente amostras CQ concluídas cujos testes obrigatórios atribuídos possuem resultado final avaliável. A amostra é conforme apenas quando todos esses resultados são conformes. Testes opcionais ausentes não são falha. Amostra com obrigatório pendente ou não avaliável não entra no denominador global e permanece como atenção operacional.

Agregações percentuais usam `total conformes / total avaliados`; percentuais de unidades nunca são promediados.

## RBAC e escopo

A rota exige `dashboard.global.view`. Administrador, Gestão, LCQH ou perfis com `samples.scope.global` veem processamentos globais. Os demais perfis com acesso veem somente unidades associadas em `users.primary_unit_id` ou `user_units`.

## Relatório

`/dashboard-global/report` recebe exatamente os mesmos filtros da tela e renderiza template A4 independente, sem sidebar. O botão de impressão permite selecionar “Salvar como PDF” no navegador.
