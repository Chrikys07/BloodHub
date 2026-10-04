# Taras por referência de bolsa

Uma referência em `bag_brands` pode possuir vários perfis em `bag_brand_tares`. Os hemocomponentes associados a cada perfil ficam em `bag_brand_tare_components`. A coluna legada `bag_brands.tare_weight` permanece intacta; a migration copia seu valor uma única vez como **Tara principal**, sem criar vínculos incertos.

`BagTareResolver::resolve($bagBrandId, $bloodComponentId)` usa diretamente a referência armazenada na amostra, mesmo que ela tenha sido inativada depois. Retorna a configuração de tara ativa daquela referência ou `null` quando não há tara. Se dados inconsistentes produzirem mais de uma resolução, lança `DomainException` e nunca escolhe arbitrariamente.

## Uso futuro em resultados

Ao concluir o cálculo, o módulo de resultados deverá, na mesma transação:

1. resolver a tara a partir de `sample.bag_brand_id` e `sample.blood_component_id`;
2. armazenar `bag_brand_tare_id` e `tare_weight_used` como snapshot do resultado;
3. preencher `bag_brand_tares.locked_at`, impedindo alterações retroativas;
4. calcular `peso líquido = peso bruto - tare_weight_used`.

Não se deve armazenar a tara na amostra antes da conclusão analítica, pois correções do hemocomponente precisam provocar uma nova resolução.

## Atualização

```bash
php scripts/migrate.php database/migrations/20260906_011_multiple_bag_tares.sql
php scripts/check_bag_tares.php
```
