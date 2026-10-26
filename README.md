# CampusEncontra

Aplicação web para consulta e publicação de objectos perdidos e encontrados no campus. O projecto tem uma API em Laravel, autenticação por token Sanctum e uma SPA em Vue 3 + Vuetify, servida pelo Laravel em `/app/`.

## Estado da entrega

- Branch de trabalho: `fase-8-admin`, criada a partir de `fase-7-entrega-final`.
- Baseline preservada: commit `8776578e8fb132a7ac6990636f34e65309e426c5` e tag anotada `fase-6-concluida` (commit `d810e70957a80f3a492f255ae7a3743477c1d3a6`).
- Suite Laravel: **158 testes / 810 assertions** em SQLite e numa base PostgreSQL isolada.
- Suite frontend: **12 testes** com o test runner nativo do Node.
- Build de produção gerado em `public/app` com Vite.
- Migrações da Fase 8 aplicadas à base local de referência `campus_encontra`; são aditivas e não eliminaram dados existentes.
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
- Denúncia autenticada de anúncios ou comportamentos inadequados, com motivos e estados de tratamento.
- Painel administrativo em `/app/admin` com dashboard, utilizadores, anúncios, solicitações, denúncias e auditoria.
- Suspensão e reactivação de contas, com invalidação imediata dos tokens da conta suspensa.
- Moderação de anúncios sem eliminação: retirada de publicação com razão, reposição e histórico.
- Registo de acções administrativas em `admin_actions`, sem palavras-passe, tokens ou conversas privadas.
- Paginação das listas pessoais: sem parâmetros mantém o formato antigo em array; com `page`/`per_page` devolve o envelope paginado.
- Fotografias e dados pessoais tratados sem expor email, telefone ou credenciais.
- SPA com deep links e fallback no Laravel.

## Papéis e permissões

| Papel | Permissões principais |
| --- | --- |
| Visitante | Consultar anúncios públicos e detalhes de anúncios não retirados. |
| `student` (estudante) | Gerir os próprios anúncios, reclamações e mensagens; denunciar anúncios ou comportamentos. |
| `admin` (administrador) | Tudo o que um estudante pode fazer, mais o painel e as operações administrativas. |

O papel é guardado na coluna `users.role`, com `student` por omissão. `role`, `suspended_at` e `suspension_reason` **não** são atribuíveis por pedidos normais de registo ou actualização; só a API administrativa protegida e o comando CLI de configuração inicial os alteram.

A autorização é validada no backend por dois middlewares:

- `active`: rejeita contas suspensas, mesmo que apresentem um token antigo.
- `admin`: exige `role = admin` na base de dados para todas as rotas `/api/admin/*`.

Esconder o link no frontend é apenas uma melhoria de experiência; não substitui esta validação.

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

## Administração e primeiro administrador

O projecto **não** cria administradores por omissão, não usa contas públicas de demonstração e não contém credenciais codificadas. O primeiro administrador é configurado por CLI, a partir de uma conta já existente:

1. Cria uma conta normal em `/app/registar` ou usa uma conta existente.
2. No servidor, executa:

```powershell
php artisan admin:promote utilizador@exemplo.pt
```

3. Inicia sessão com essa conta e abre `/app/admin`. Se a conta já estava autenticada, actualiza a página ou termina e inicia sessão novamente para o frontend receber o papel actualizado.

Regras do comando:

- Só promove contas existentes; não cria contas nem palavras-passe.
- Em produção exige confirmação explícita: `php artisan admin:promote utilizador@exemplo.pt --force`.
- Um administrador suspenso não pode ser promovido enquanto a suspensão não for removida.
- `php artisan admin:promote utilizador@exemplo.pt --demote` remove o papel, mas recusa deixar a plataforma sem administrador activo.
- As alterações de papel são registadas em `admin_actions` quando já existe um administrador activo.

Depois de existir um administrador, a gestão normal de papéis, suspensões, moderação e denúncias é feita no painel. A CLI é apenas o procedimento de arranque e recuperação controlada.

### Painel administrativo

| Secção | Endereço | Conteúdo |
| --- | --- | --- |
| Dashboard | `/app/admin/dashboard` | Totais de utilizadores, anúncios, perdidos/encontrados, reclamações, devoluções, denúncias e retirados; últimos registos de auditoria. |
| Utilizadores | `/app/admin/utilizadores` | Pesquisa, filtros por papel/estado, detalhe, suspensão, reactivação e alteração de papel. |
| Anúncios | `/app/admin/anuncios` | Pesquisa, filtros, detalhe, retirada de publicação com razão e reposição. |
| Solicitações | `/app/admin/solicitacoes` | Consulta de reclamações, intervenientes, datas e estados, sem carregar a conversa privada. |
| Denúncias | `/app/admin/denuncias` | Filtros, análise, estados `pending`, `reviewing`, `resolved` e `dismissed`, nota de acompanhamento/decisão e auditoria. |

O painel usa os mesmos padrões de loading, erro, sucesso, confirmação, pesquisa, paginação e deep links da restante SPA. `/app/admin` é redireccionado para o dashboard. O Laravel serve o `index.html` em qualquer deep link `/app/...`, pelo que actualizar a página funciona.

A moderação **não elimina** o anúncio. O anúncio retirado:

- desaparece da listagem pública;
- devolve `404` a terceiros no detalhe;
- continua visível para o autor e para administradores;
- não aceita novas reclamações;
- guarda a razão, a data e o administrador responsável;
- pode ser reposto, ficando novamente visível.

As denúncias usam os motivos `inappropriate`, `spam`, `harassment`, `fraud` e `other`. A conversa privada de uma reclamação não é carregada pela área administrativa; o administrador vê apenas os dados da reclamação e a mensagem inicial, não o histórico de `claim_messages`.

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
| `POST` | `/api/reports` | Denunciar um anúncio (`target_type=item`) ou um utilizador (`target_type=user`) |

A denúncia aceita `reason` (`inappropriate`, `spam`, `harassment`, `fraud` ou `other`) e `details` opcional. Para denunciar um comportamento, envia `target_type=user`, `target_id` do utilizador e, quando aplicável, `item_id` como contexto. O servidor valida o alvo, impede auto-denúncias e rejeita denúncias duplicadas ainda em aberto. Esta rota tem limite de **10 pedidos por minuto**.

Rotas administrativas (`Authorization: Bearer <token>`, conta activa e `role=admin`):

| Método | Rota | Descrição |
| --- | --- | --- |
| `GET` | `/api/admin/dashboard` | Totais e estatísticas por estado |
| `GET` | `/api/admin/users` | Listar/pesquisar utilizadores (`q`, `role`, `status`) |
| `GET` | `/api/admin/users/{user}` | Detalhe de utilizador e contadores |
| `PATCH` | `/api/admin/users/{user}/suspend` | Suspender conta com `reason` |
| `PATCH` | `/api/admin/users/{user}/reactivate` | Reactivar conta |
| `PATCH` | `/api/admin/users/{user}/role` | Alterar papel (`student`/`admin`) |
| `GET` | `/api/admin/items` | Listar/pesquisar anúncios (`q`, `type`, `status`, `moderation`) |
| `GET` | `/api/admin/items/{item}` | Detalhe de anúncio e moderação |
| `PATCH` | `/api/admin/items/{item}/moderate` | Retirar de publicação com `reason` |
| `PATCH` | `/api/admin/items/{item}/restore` | Repor anúncio com nota opcional |
| `GET` | `/api/admin/claims` | Acompanhar solicitações (`q`, `status`) |
| `GET` | `/api/admin/claims/{claim}` | Detalhe da solicitação sem conversa privada |
| `GET` | `/api/admin/reports` | Listar/pesquisar denúncias (`q`, `status`, `target_type`) |
| `GET` | `/api/admin/reports/{report}` | Detalhe da denúncia |
| `PATCH` | `/api/admin/reports/{report}` | Actualizar estado e nota de decisão |
| `GET` | `/api/admin/audit-logs` | Histórico de acções administrativas |

As respostas administrativas são paginadas e não incluem palavras-passe, tokens nem o conteúdo de `claim_messages`. As acções de suspensão, alteração de papel, moderação e decisão de denúncias são registadas em `admin_actions`.

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

Resultado actual: **158 testes, 810 assertions**, todos aprovados. A suite usa SQLite em memória por omissão (`phpunit.xml`), pelo que não toca na base de desenvolvimento. Inclui testes de acesso anónimo/estudante/administrador, dashboard, gestão de utilizadores, moderação, solicitações, denúncias, auditoria, comando de primeiro administrador e tentativas de elevação de privilégios.

### Frontend

```powershell
cd frontend
npm test
```

Resultado actual: **12 testes**, todos aprovados. Os testes cobrem utilitários de tipos, estados, reclamações, denúncias, moderação, papéis, erros da API, redireccionamentos seguros, formatação e truncagem.

### PostgreSQL

A suite completa também foi executada numa base temporária PostgreSQL isolada (**158 testes, 810 assertions**), criada especificamente para o efeito, com as migrações aplicadas e removida no fim. A base `campus_encontra` não foi alterada.

Para repetir, usa uma base separada (nunca a base principal) e define as variáveis `DB_CONNECTION=pgsql`, `DB_DATABASE`, `DB_HOST`, `DB_PORT`, `DB_USERNAME`, `DB_PASSWORD` e `DB_SSLMODE` para essa base antes de executar `php artisan test`. Em PHPUnit, garante que os valores têm precedência sobre o `phpunit.xml` (por exemplo, com um ficheiro de configuração temporário e `force="true"`). Remove a base de teste no fim.

## Segurança

- Autenticação por token Sanctum; o frontend envia `Authorization: Bearer`.
- Expiração de token configurável em `SANCTUM_EXPIRATION` (por omissão, 7 dias) e prefixo `SANCTUM_TOKEN_PREFIX`.
- Rate limiting:
  - API: 120 pedidos por minuto por utilizador/IP.
  - Login/registo: 5 tentativas por minuto por email+IP e 20 por minuto por IP.
  - Mensagens: 30 por minuto.
  - Denúncias: 10 por minuto.
- CORS configurável por `CORS_ALLOWED_ORIGINS`. Em produção não há fallback para origens locais: define explicitamente os domínios autorizados.
- Cabeçalhos `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy` e HSTS quando o pedido é HTTPS.
- CSP activa apenas em produção, aplicada às respostas web; a API fica fora para não bloquear clientes JSON.
- Validação de autoria, estados, transições e uploads no servidor.
- Respostas de objectos/reclamações expõem apenas `id` e `name` dos utilizadores envolvidos, nunca email, telefone ou tokens.
- Mensagens de reclamação só são acessíveis ao reclamante e ao dono do objecto.
- Todas as rotas `/api/admin/*` exigem autenticação, conta activa e papel `admin` validado na base de dados; esconder o link no frontend não é suficiente.
- `role`, `suspended_at` e `suspension_reason` não são atribuíveis por registo nem por actualização normal de perfil.
- A suspensão invalida os tokens da conta e o login é bloqueado; o middleware `active` também rejeita tokens emitidos antes da suspensão.
- Um administrador não pode suspender a própria conta, alterar o próprio papel nem remover/suspender o último administrador activo.
- A moderação preserva o anúncio e o histórico; não elimina dados. O conteúdo retirado deixa de ser público e não aceita novas reclamações.
- As acções administrativas são registadas em `admin_actions` sem palavras-passe, tokens ou conversas privadas.
- O painel de solicitações não carrega `claim_messages`; mostra apenas os dados da reclamação e a mensagem inicial.
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
6. Criar o primeiro administrador a partir de uma conta existente: `php artisan admin:promote email@exemplo.pt --force`. Não criar administradores por seed nem credenciais públicas.
7. Executar `php artisan storage:link` e garantir permissões de escrita em `storage/` e `bootstrap/cache/`.
8. Construir a SPA: `cd frontend && npm ci && npm run build`.
9. Optimizar: `php artisan optimize` (config, rotas e views). Depois de alterações, `php artisan optimize:clear` e repetir.
10. Configurar HTTPS, redireccionamento de HTTP para HTTPS e compressão/cache de assets no web server.
11. Confirmar `GET /up` e abrir `/app/`, testando login, publicação, foto, reclamação, mensagens, denúncia, acesso administrativo e deep link. Confirmar que um estudante recebe `403` em `/api/admin/dashboard`.

Não publicar `.env`, `vendor/`, `node_modules/`, chaves, tokens ou dados reais no repositório.

## Limitações conhecidas

- A publicação online não foi executada: não houve domínio, alojamento nem credenciais reais nesta fase.
- As migrações da Fase 8 foram aplicadas localmente e testadas numa base PostgreSQL isolada. Em produção, continuam por aplicar até à publicação.
- O envio de email está em `log`; não há verificação de email nem recuperação de palavra-passe.
- O armazenamento de fotos está preparado para disco local público ou S3, mas o S3 não foi configurado nem testado com credenciais reais. Se usares S3, ajusta a CSP `img-src`; se a API estiver noutro domínio, ajusta também `connect-src`.
- Não existem testes E2E de browser automatizados; os testes cobrem API, regras de negócio, uploads, segurança, autorização administrativa e utilitários do frontend.
- O painel administrativo não mostra a conversa privada de uma solicitação; essa decisão de privacidade é intencional.
- A moderação retira anúncios de publicação, mas não os elimina. Não há eliminação definitiva pelo painel.
- O primeiro administrador e a remoção controlada de papel são feitos por CLI; não há ecrã de bootstrap administrativo.
- Não há autenticação de dois factores nem gestão de sessões administrativas para além da expiração normal dos tokens.
- O servidor built-in do PHP serve `public/app/index.html` directamente em `/app/`; para produção usa Apache/Nginx com o rewrite configurado.
- `public/app` é um artefacto de build e tem de ser regenerado após alterações ao frontend.

## Estrutura principal

```text
app/
  Console/Commands/       admin:promote (primeiro administrador)
  Http/Controllers/Api/   Auth, itens, reclamações, mensagens, denúncias
  Http/Controllers/Api/Admin/  Dashboard, utilizadores, anúncios, solicitações, denúncias, auditoria
  Http/Middleware/        Cabeçalhos de segurança, conta activa e autorização admin
  Http/Resources/         Respostas públicas e administrativas
  Models/                 User, Item, Claim, ClaimMessage, Report, AdminAction
routes/
  api.php                 API pública, autenticada e administrativa
  web.php                 SPA, raiz e fallback de deep links
database/migrations/      Esquema existente + papéis, moderação, denúncias e auditoria
frontend/src/             SPA Vue/Vuetify, incluindo pages/AdminPage.vue e components/admin/
frontend/tests/           Testes Node
public/app/               Build de produção da SPA
tests/Feature/            API, uploads, mensagens, segurança, administração, moderação, denúncias e SPA
```

## Licença

Projecto académico CampusEncontra.
