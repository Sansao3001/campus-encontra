# CampusEncontra

Aplicação web para consulta e publicação de objectos perdidos e encontrados no campus. O projecto tem uma API em Laravel, autenticação por token Sanctum e uma SPA em Vue 3 + Vuetify, servida pelo Laravel em `/app/`.

## Estado da entrega

- Branch de trabalho: `fase-7-entrega-final`.
- Baseline preservada: tag `fase-6-concluida` e commit `d810e70957a80f3a492f255ae7a3743477c1d3a6`.
- Suite Laravel: **116 testes / 553 assertions** em SQLite e em PostgreSQL isolado.
- Suite frontend: **8 testes** com o test runner nativo do Node.
- Build de produção gerado em `public/app` com Vite.
- Publicação online, domínio, HTTPS real e credenciais de alojamento **não** foram configurados nesta entrega.

## Funcionalidades

- Registo, login, logout e sessão por token Bearer.
- Listagem pública de objectos com pesquisa, filtros por tipo/estado e paginação.
- Detalhe de objecto com fotografia.
- CRUD de objectos do próprio utilizador, com validação no cliente e no servidor.
- Upload, substituição e remoção de fotografia (JPEG, PNG ou WebP, até 5 MB).
- Reclamações para objectos de outros utilizadores.
- Aceitação, rejeição, cancelamento, confirmação de devolução e reabertura do anúncio.
- Mensagens privadas entre reclamante e responsável, associadas à reclamação.
- Paginação das listas pessoais: sem parâmetros mantém o formato antigo em array; com `page`/`per_page` devolve o envelope paginado.
- Fotografias e dados pessoais tratados sem expor email, telefone ou credenciais.
- SPA com deep links e fallback no Laravel.

## Stack

- PHP 8.3 / Laravel 13
- Laravel Sanctum 4
- PostgreSQL (referência) ou SQLite (testes rápidos)
- Vue 3, Vue Router, Vuetify, Axios
- Vite 8
- PHPUnit 12 e Node test runner

## Requisitos

- PHP 8.3 ou superior com extensões usuais do Laravel (`pdo_pgsql` para PostgreSQL).
- Composer 2.
- Node.js 22.18+ ou 24.12+ e npm.
- PostgreSQL 14+ para reproduzir o ambiente de referência.
- Laragon (Windows), XAMPP, Docker ou outro ambiente equivalente.

### Laragon / Windows

Se `php` e `composer` não estiverem no `PATH`, usa o terminal do Laragon ou acrescenta temporariamente ao `PATH`:

```powershell
$env:Path = "C:\laragon\bin\php\php-8.3.33-Win32-vs16-x64;" + $env:Path
php -v
php C:\laragon\bin\composer\composer.phar -V
```

Os caminhos podem variar conforme a instalação. Nos comandos seguintes, `php` representa o binário do Laragon ou outro PHP 8.3+.

## Instalação local

1. Instalar dependências e criar o ficheiro de ambiente:

```powershell
composer install
copy .env.example .env
php artisan key:generate
```

2. Criar a base PostgreSQL e configurar o `.env`:

```sql
CREATE DATABASE campus_encontra;
```

```dotenv
APP_NAME=CampusEncontra
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=campus_encontra
DB_USERNAME=postgres
DB_PASSWORD=
DB_SSLMODE=prefer
```

3. Aplicar as migrações existentes (sem `migrate:fresh` nem `db:wipe`):

```powershell
php artisan migrate
```

4. Criar o link público das fotografias:

```powershell
php artisan storage:link
```

5. Arrancar a API:

```powershell
php artisan serve
```

6. Noutro terminal, arrancar o frontend em modo de desenvolvimento:

```powershell
cd frontend
npm install
npm run dev
```

A SPA fica em `http://localhost:5173` e o proxy do Vite encaminha `/api` para `http://127.0.0.1:8000`.

Para usar a SPA já compilada, abre `http://localhost:8000/app/` depois de executar o build. A raiz `http://localhost:8000/` encaminha para `/app/`.

### Utilizador de demonstração opcional

O seeder não cria contas com palavras-passe conhecidas por omissão e nunca cria utilizadores em produção. Para criar uma conta local de demonstração:

```dotenv
SEED_DEMO_USER=true
SEED_DEMO_EMAIL=demo@campus-encontra.test
SEED_DEMO_PASSWORD=uma-palavra-passe-com-pelo-menos-8-caracteres
```

```powershell
php artisan db:seed
```

## Frontend e build de produção

```powershell
cd frontend
npm ci
npm run build
```

O build é escrito em `public/app`. O Vite usa `/app/` como base e o Laravel serve o `index.html` para `/app/{any?}`.

Variáveis relevantes em `frontend/.env`:

```dotenv
# Vazio ou /api para same-origin; URL absoluta se a API estiver noutro domínio.
VITE_API_URL=/api

# /app/ para ser servido pelo Laravel; / para alojamento separado.
VITE_BASE_PATH=/app/

# Destino do proxy de desenvolvimento.
VITE_DEV_API_TARGET=http://127.0.0.1:8000
```

O build é um artefacto: sempre que alterares código em `frontend/src`, executa novamente `npm run build`.

### Deep links

O Laravel tem duas camadas de suporte:

- `GET /app/{any?}` devolve `public/app/index.html`.
- Um `Route::fallback` cobre o caso em que o servidor built-in do PHP reescreve `/app/<rota>` para `/app/index.html` durante `php artisan serve`.

Em produção, o web server deve enviar pedidos inexistentes para `public/index.php`. Exemplo Nginx:

```nginx
root /var/www/campus-encontra/public;
index index.php;

location / {
    try_files $uri $uri/ /index.php?$query_string;
}
```

O document root deve ser sempre a pasta `public`, nunca a raiz do projecto.

## API

Rotas públicas:

| Método | Rota | Descrição |
| --- | --- | --- |
| `POST` | `/api/register` | Criar conta e devolver token |
| `POST` | `/api/login` | Iniciar sessão e devolver token |
| `GET` | `/api/items` | Listar objectos com `q`, `type`, `status`, `page`, `per_page` |
| `GET` | `/api/items/{item}` | Consultar um objecto |

Rotas autenticadas (`Authorization: Bearer <token>`):

| Método | Rota | Descrição |
| --- | --- | --- |
| `POST` | `/api/logout` | Revogar o token actual |
| `GET` | `/api/user` | Utilizador autenticado |
| `GET` | `/api/my-items` | Objectos do utilizador |
| `POST` | `/api/items` | Criar objecto (`multipart/form-data` com `photo` opcional) |
| `PUT/PATCH` | `/api/items/{item}` | Actualizar objecto |
| `DELETE` | `/api/items/{item}` | Eliminar objecto |
| `GET` | `/api/my-claims` | Reclamações enviadas |
| `GET` | `/api/received-claims` | Reclamações recebidas |
| `GET` | `/api/items/{item}/claims` | Reclamações de um objecto (só o dono) |
| `POST` | `/api/items/{item}/claims` | Criar reclamação |
| `PATCH` | `/api/claims/{claim}/cancel` | Cancelar reclamação pendente |
| `PATCH` | `/api/claims/{claim}/accept` | Aceitar reclamação |
| `PATCH` | `/api/claims/{claim}/reject` | Rejeitar reclamação |
| `PATCH` | `/api/claims/{claim}/reopen` | Reabrir anúncio aceite |
| `PATCH` | `/api/claims/{claim}/confirm-return` | Confirmar devolução |
| `GET` | `/api/claims/{claim}/messages` | Ler mensagens privadas |
| `POST` | `/api/claims/{claim}/messages` | Enviar mensagem privada |

Notas de upload:

- O cliente envia o ficheiro no campo `photo`; `photo_path` é proibido.
- Para remover uma fotografia numa actualização: `remove_photo=1`.
- Como o PHP só processa ficheiros em pedidos `POST`, o frontend usa `POST` com `_method=PUT` para actualizações com fotografia.
- O disco é configurável por `ITEM_PHOTOS_DISK` (`public` por omissão).

## Testes

### Laravel

```powershell
php artisan test
```

Resultado actual: **116 testes, 553 assertions**, todos aprovados. A suite usa SQLite em memória por omissão (`phpunit.xml`), pelo que não toca na base de desenvolvimento.

### Frontend

```powershell
cd frontend
npm test
```

Resultado actual: **8 testes**, todos aprovados. Os testes cobrem utilitários de tipos, estados, reclamações, erros da API, redireccionamentos seguros, formatação e truncagem.

### PostgreSQL

A suite completa também foi executada numa base temporária PostgreSQL isolada (116 testes, 553 assertions), com as migrações aplicadas e removida no fim. A base `campus_encontra` não foi alterada.

Para repetir, usa uma base separada (nunca a base principal) e define as variáveis `DB_CONNECTION=pgsql`, `DB_DATABASE`, `DB_HOST`, `DB_PORT`, `DB_USERNAME`, `DB_PASSWORD` e `DB_SSLMODE` para essa base antes de executar `php artisan test`. Em PHPUnit, garante que os valores têm precedência sobre o `phpunit.xml` (por exemplo, com um ficheiro de configuração temporário e `force="true"`). Remove a base de teste no fim.

## Segurança

- Autenticação por token Sanctum; o frontend envia `Authorization: Bearer`.
- Expiração de token configurável em `SANCTUM_EXPIRATION` (por omissão, 7 dias) e prefixo `SANCTUM_TOKEN_PREFIX`.
- Rate limiting:
  - API: 120 pedidos por minuto por utilizador/IP.
  - Login/registo: 5 tentativas por minuto por email+IP e 20 por minuto por IP.
  - Mensagens: 30 por minuto.
- CORS configurável por `CORS_ALLOWED_ORIGINS`. Em produção não há fallback para origens locais: define explicitamente os domínios autorizados.
- Cabeçalhos `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy` e HSTS quando o pedido é HTTPS.
- CSP activa apenas em produção, aplicada às respostas web; a API fica fora para não bloquear clientes JSON.
- Validação de autoria, estados, transições e uploads no servidor.
- Respostas de objectos/reclamações expõem apenas `id` e `name` dos utilizadores envolvidos, nunca email, telefone ou tokens.
- Mensagens de reclamação só são acessíveis ao reclamante e ao dono do objecto.
- O seeder de demonstração é desactivado em produção e requer `SEED_DEMO_USER=true` e uma palavra-passe válida.

### HTTPS em produção

Define:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://o-teu-dominio.pt
APP_FORCE_HTTPS=true
TRUSTED_PROXIES=*
```

`TRUSTED_PROXIES` só deve ser usado quando existe um proxy/load balancer à frente da aplicação. A terminação TLS deve ser feita no proxy ou no web server.

## Publicação

Checklist mínimo:

1. Configurar `.env` de produção com `APP_KEY`, `APP_URL`, base de dados, `CORS_ALLOWED_ORIGINS`, `SANCTUM_EXPIRATION` e `ITEM_PHOTOS_DISK`.
2. Instalar dependências sem dependências de desenvolvimento: `composer install --no-dev --optimize-autoloader`.
3. Garantir que `document root` aponta para `public`.
4. Fazer backup da base de dados antes de migrar.
5. Executar `php artisan migrate --force` (nunca `migrate:fresh`, `db:wipe` ou `db:seed` de demonstração em produção).
6. Executar `php artisan storage:link` e garantir permissões de escrita em `storage/` e `bootstrap/cache/`.
7. Construir a SPA: `cd frontend && npm ci && npm run build`.
8. Optimizar: `php artisan optimize` (config, rotas e views). Depois de alterações, `php artisan optimize:clear` e repetir.
9. Configurar HTTPS, redireccionamento de HTTP para HTTPS e compressão/cache de assets no web server.
10. Confirmar `GET /up` e abrir `/app/`, testando login, publicação, foto, reclamação, mensagens e deep link.

Não publicar `.env`, `vendor/`, `node_modules/`, chaves, tokens ou dados reais no repositório.

## Limitações conhecidas

- A publicação online não foi executada: não houve domínio, alojamento nem credenciais reais nesta fase.
- O envio de email está em `log`; não há verificação de email nem recuperação de palavra-passe.
- O armazenamento de fotos está preparado para disco local público ou S3, mas o S3 não foi configurado nem testado com credenciais reais. Se usares S3, ajusta a CSP `img-src`; se a API estiver noutro domínio, ajusta também `connect-src`.
- Não existem testes E2E de browser automatizados; os testes cobrem API, regras de negócio, uploads, segurança e utilitários do frontend.
- O servidor built-in do PHP serve `public/app/index.html` directamente em `/app/`; para produção usa Apache/Nginx com o rewrite configurado.
- `public/app` é um artefacto de build e tem de ser regenerado após alterações ao frontend.

## Estrutura principal

```text
app/
  Http/Controllers/Api/   Auth, itens, reclamações e mensagens
  Http/Middleware/        Cabeçalhos de segurança
  Http/Resources/         Respostas públicas sem dados pessoais
  Models/                 User, Item, Claim, ClaimMessage
routes/
  api.php                 API
  web.php                 SPA, raiz e fallback de deep links
database/migrations/      Esquema existente + claim_messages
frontend/src/             SPA Vue/Vuetify
frontend/tests/           Testes Node
public/app/               Build de produção da SPA
tests/Feature/            API, uploads, mensagens, segurança, paginação e SPA
```

## Licença

Projecto académico CampusEncontra.
