# BloodHub em producao (EasyPanel/Docker)

## Arquitetura auditada

- Aplicacao PHP sem framework e sem dependencias Composer.
- Entrada unica: `public/index.php`; reescrita de rotas por `public/.htaccess`.
- Document root: `/var/www/html/public`. Nunca publique a raiz do repositorio.
- Runtime recomendado: PHP 8.3 + Apache, porta interna `80`.
- Banco: MySQL 8 em servico separado. O container da aplicacao nao contem MySQL.
- PHP minimo: 8.1 (inclui uso do tipo de retorno `never`, alem de `match`, union types, `str_starts_with` e `mixed`); o projeto sera executado em 8.3.
- Extensoes usadas: `pdo_mysql`, `mbstring`, `fileinfo`, `iconv`, `json`, `openssl`, `zip` e `gd`. As extensoes necessarias que nao fazem parte do core da imagem sao instaladas pelo Dockerfile.

## Build e criacao no EasyPanel

1. Envie o repositorio ao GitHub sem os arquivos locais ignorados.
2. No EasyPanel, crie um projeto independente e um servico App a partir do repositorio.
3. Selecione build por Dockerfile (arquivo `Dockerfile` na raiz).
4. Configure a porta interna HTTP como `80` e associe `bloodhub.com.br` ao servico. O Traefik/EasyPanel termina o TLS.
5. Crie separadamente um servico MySQL 8, com volume persistente, banco e usuario exclusivos para o BloodHub.
6. Use o nome DNS interno do servico MySQL em `DB_HOST`; nao use `localhost` (dentro do container, localhost e a propria aplicacao).

O build pode ser validado fora do EasyPanel com `docker build -t bloodhub .`. Nao e necessario Composer.

## Variaveis de ambiente

Cadastre os valores reais somente na area de Environment do EasyPanel:

| Variavel | Producao |
|---|---|
| `DB_HOST` | DNS interno do servico MySQL |
| `DB_PORT` | `3306` |
| `DB_DATABASE` | nome do banco BloodHub |
| `DB_USERNAME` | usuario exclusivo do BloodHub |
| `DB_PASSWORD` | segredo forte |
| `APP_URL` | `https://bloodhub.com.br` |
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` |
| `MAIL_HOST` | host SMTP |
| `MAIL_PORT` | normalmente `587` para STARTTLS ou `465` para SSL |
| `MAIL_USERNAME` | usuario SMTP |
| `MAIL_PASSWORD` | senha/App Password SMTP |
| `MAIL_ENCRYPTION` | `tls`, `ssl` ou `none` |
| `MAIL_FROM_ADDRESS` | remetente autorizado |
| `MAIL_FROM_NAME` | `BloodHub` |

O arquivo `.env.example` e apenas um inventario sem segredos. A aplicacao usa variaveis de ambiente primeiro. Para desenvolvimento local, `config/database.php` e `config/mail.php` continuam aceitos e ignorados pelo Git. Sem `config/database.php`, os defaults seguros de desenvolvimento em `config/database.example.php` sao usados.

## Volumes e permissoes

Crie dois volumes persistentes na aplicacao:

| Caminho no container | Conteudo | Obrigatorio |
|---|---|---|
| `/var/www/html/storage` | PDFs de laudos e sessoes PHP | Sim |
| `/var/www/html/public/uploads` | fotos de perfil enviadas por usuarios | Sim |

Os diretorios devem pertencer ao usuario/grupo `www-data` e permitir escrita pelo processo Apache (o build cria-os com modo `0770`). Ao montar um volume vazio, confirme no console do container: `chown -R www-data:www-data /var/www/html/storage /var/www/html/public/uploads` e `chmod -R 0770` nesses caminhos. Nao monte todo `/var/www/html`, pois isso ocultaria o codigo da imagem.

Os laudos sao gravados em `storage/reports/AAAA/MM`; fotos ficam em `public/uploads/avatars`; sessoes ficam em `storage/sessions`. Esses dados nao entram na imagem ou no Git e sobrevivem a redeploys pelos volumes.

## Banco, schema e migrations

Antes da primeira ativacao, faça backup do banco e importe `database/bloodhub_clean_install.sql` (instalacao nova) ou o schema aprovado pela equipe. Para uma instalacao existente, execute somente migrations ainda nao aplicadas, em ordem, usando:

```bash
php scripts/migrate.php database/migrations/NOME_DA_MIGRATION.sql
```

Nao execute automaticamente todas as migrations durante o start do container: isso pode deixar codigo e schema em versoes incompatíveis durante rollback. Faça snapshot/backup do volume MySQL antes de cada mudanca estrutural e teste a migration em homologacao. O deploy da aplicacao nunca deve recriar nem remover o servico/volume MySQL.

## Health check

O Dockerfile consulta `GET /login` em `127.0.0.1:80`. No EasyPanel, pode-se configurar o mesmo caminho `/login`, intervalo de 30 s, timeout de 5 s e 3 tentativas. Esse endpoint comprova Apache, reescrita e bootstrap PHP sem depender de autenticacao.

## Atualizacoes via GitHub

1. Faça backup/snapshot do MySQL e confirme os volumes da aplicacao.
2. Revise migrations novas e aplique-as de forma controlada conforme a compatibilidade da versao.
3. Faça merge na branch configurada pelo EasyPanel e acione rebuild/redeploy.
4. Aguarde o health check, valide login, envio de e-mail, upload de avatar e leitura/download de um laudo.
5. Em rollback, volte a imagem/commit anterior; nunca remova os volumes. Se uma migration nao for retrocompativel, restaure o snapshot seguindo o plano aprovado.

## Seguranca e itens locais

- `config/mail.php` contem credencial SMTP local e deve permanecer fora do versionamento. Revogue/rotacione a credencial antes da producao se ela ja foi compartilhada ou commitada em qualquer historico.
- `config/database.php` e configuracao local e tambem permanece ignorado.
- `database/backups/`, dumps, `storage/`, `tmp/` e uploads reais sao ignorados e excluidos do contexto Docker.
- O dump local e os arquivos de desenvolvimento nao sao apagados por esta preparacao.
- Se algum segredo ja tiver sido commitado no passado, adicionar ao `.gitignore` nao limpa o historico: remova-o do indice/historico com procedimento revisado e rotacione o segredo.

## Checklist de aceite

- DNS de `bloodhub.com.br` apontando para a VPS e HTTPS ativo no Traefik.
- Document root confirmado como `/var/www/html/public`; porta interna `80`.
- Todas as variaveis cadastradas sem aspas extras e `APP_DEBUG=false`.
- MySQL acessivel pelo DNS interno e com volume persistente independente.
- Volumes de `storage` e `public/uploads` montados, gravaveis e incluídos no backup.
- Schema/migrations, primeiro administrador, SMTP, upload e laudos validados.
