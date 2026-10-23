# Segurança da autenticação

## Situação actual do MVP

- O token Sanctum é guardado em `sessionStorage`, apenas para esta sessão do separador.
- Nenhuma palavra-passe é guardada no navegador.
- O utilizador autenticado é mantido apenas em memória e é novamente confirmado através de `GET /api/user`.
- O interceptor Axios só envia `Authorization: Bearer <token>` quando o pedido se destina ao origin da API configurada em `VITE_API_URL`.
- Uma resposta `401` remove o token local e encaminha rotas protegidas para `/entrar`.

A configuração actual do Sanctum mantém `expiration => null`. O frontend trata qualquer `401` como sessão inválida ou expirada, mas a expiração automática dos tokens deve ser revista antes de produção.

## Risco de XSS

`sessionStorage` **não é imune a XSS**. Qualquer script executado na origem da aplicação pode ler o token e usá-lo enquanto este for válido. O token não deve ser considerado uma protecção contra XSS.

Antes de produção, é necessário avaliar uma autenticação SPA baseada em cookies `HttpOnly`, `Secure` e `SameSite`, com o fluxo CSRF do Sanctum, CORS restrito e uma Política de Segurança de Conteúdo (CSP). Também deve ser revista a expiração e rotação dos tokens, a revogação de sessões e a utilização obrigatória de HTTPS.

## Autorização

A protecção de rotas no frontend é apenas uma melhoria de experiência. A autorização real é sempre imposta pelo backend Laravel, incluindo `auth:sanctum` e a verificação de autoria nas operações sobre objectos.
