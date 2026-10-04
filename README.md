# BloodHub v0.1 — Fundação

**BloodHub — Sistema de Controle de Qualidade de Hemocomponentes**

Esta primeira base técnica inicia o desenvolvimento do sistema em PHP + MySQL/MariaDB, visando implantação em HostGator/cPanel.

## Objetivo desta versão
- estrutura de diretórios;
- roteamento simples;
- conexão PDO;
- autenticação por sessão;
- proteção CSRF;
- base para RBAC (perfis e permissões);
- página de login;
- layout inicial com sidebar;
- dashboard inicial de placeholder;
- schema SQL inicial para usuários, perfis, permissões, clientes e unidades;
- tabelas-base preparadas para amostras, testes, insumos e auditoria.

## Requisitos
- PHP 8.1+
- MySQL 8+ ou MariaDB equivalente
- PDO MySQL habilitado
- Apache com mod_rewrite (recomendado)

## Instalação rápida
1. Crie um banco no cPanel.
2. Importe `database/schema.sql`.
3. Copie `config/database.example.php` para `config/database.php`.
4. Preencha host, banco, usuário e senha.
5. Aponte o Document Root do domínio/subdomínio para `public`.
6. Acesse pelo navegador.

## Criação do primeiro administrador

Depois de importar o schema e configurar a conexão com o banco de dados, execute na raiz do projeto:

```bash
php scripts/create_admin.php
```

Informe o nome, o e-mail e uma senha com pelo menos 8 caracteres quando solicitado. O script busca o perfil `Administrador`, impede e-mails duplicados e armazena a senha somente como hash.

## Módulo futuro reservado: Faturamento
Em etapa futura, o BloodHub deverá consolidar:
- quantidade de testes executados por processamento;
- quantidade por tipo de teste;
- consolidação por período;
- estimativa de consumo de reagentes e insumos;
- eventual precificação/custo por teste.

## Administração e RBAC

Para sincronizar as permissões-padrão dos perfis iniciais (o comando pode ser reexecutado com segurança), execute na raiz do projeto:

```bash
php scripts/sync_rbac.php
```

### Administração > Insumos

URL: `/admin/supplies`

O módulo permite cadastrar e editar insumos, controlar seus lotes e quantidades, acompanhar validades e visualizar a situação operacional de cada lote (Vencido, Crítico, Atenção ou Válido). Todas as rotas exigem a permissão `admin.supplies.manage`.

Após entrar com o perfil Administrador, estão disponíveis:

- `/admin/users`: listagem, cadastro e edição de usuários;
- `/admin/roles`: listagem, cadastro e edição de perfis;
- `/admin/permissions`: matriz de permissões por perfil.
- `/admin/clients`: listagem, cadastro e edição de clientes internos e externos;
- `/admin/units`: listagem, cadastro e edição de unidades, com vínculo opcional a clientes.
- `/admin/blood-components`: listagem, cadastro e edição de hemocomponentes, incluindo ativação e inativação.
- `/admin/tests`: listagem, cadastro e edição de testes/ensaios, com tipo de resultado, unidade de medida, uso como teste avulso e vínculos com hemocomponentes.
- `/admin/supplies`: listagem, cadastro e edição de insumos, lotes e validades.

As rotas de Clientes, Unidades, Hemocomponentes, Testes e Insumos exigem sessão autenticada e, respectivamente, as permissões `admin.clients.manage`, `admin.units.manage`, `admin.blood_components.manage`, `admin.tests.manage` e `admin.supplies.manage`. O sincronizador garante essas permissões para o perfil Administrador, sem concedê-las aos demais perfis. A criação inicial do administrador continua sendo feita com `php scripts/create_admin.php`.

### Administração > Testes > Insumos necessários

Na edição de um teste (`/admin/tests/edit?id=...`), a seção **Insumos necessários** permite vincular insumos ativos, informar a quantidade necessária por execução e definir se cada item é obrigatório. A tabela apresenta também a situação atual dos lotes: Sem lote, Vencido, Crítico, Atenção ou Válido.

Os vínculos podem ser adicionados, editados e removidos sem excluir o cadastro do insumo. As operações exigem `admin.tests.manage`. Nesta etapa a disponibilidade é informativa: não há consumo de estoque nem bloqueio efetivo da execução de testes.

## HubChat

O HubChat fica disponível em `/chat` e pelo ícone da top bar, com o subtítulo **Comunicação interna**. Ele oferece a conversa Geral, grupos automáticos por unidade e conversas privadas únicas entre dois usuários, com mensagens persistentes, indicador de não lidas e polling sem WebSocket (5 segundos na conversa aberta e 15 segundos para listas/badge).

Cada unidade ativa possui no máximo um grupo. Os participantes são sincronizados a partir de `user_units`, considerando usuários, unidades, perfis e permissões ativos. As permissões são `chat.view`, `chat.send`, `chat.private` e `chat.group`.

Para atualizar uma instalação existente, execute na raiz do projeto:

```bash
php scripts/migrate.php database/migrations/20260902_005_internal_chat.sql
php scripts/migrate.php database/migrations/20260902_006_hubchat_unit_groups.sql
php scripts/migrate.php database/migrations/20260902_007_hubchat_sectors_archive.sql
```

O comando é idempotente. O `schema.sql` já contém a estrutura para instalações novas.
