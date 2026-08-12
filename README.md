# Gerenciamento de Pedidos

Estou desenvolvendo este projeto para o teste técnico fullstack. Meu objetivo é
criar uma API de pedidos em PHP, uma interface em React e preparar todo o
ambiente para rodar com Docker.

## Estado atual

Até agora, deixei a API e a primeira versão funcional do frontend prontas:

- API executando com PHP e Apache;
- MySQL isolado na rede do Docker;
- variáveis de ambiente obrigatórias e booleanas validadas;
- conexão PDO centralizada e validada com o MySQL;
- criptografia autenticada implementada e testada com Libsodium;
- hash protegido para consultas de dados criptografados;
- migrations versionadas para usuários, pedidos, tokens e limite de login;
- entidade, perfis e validação do cadastro de usuários;
- persistência de usuários com PDO e proteção dos dados sensíveis;
- service de usuários com validação de duplicidade e hash de senha;
- backend de autenticação com verificação isolada de credenciais;
- configuração de autenticação validada para desenvolvimento e produção;
- emissão e validação de access e refresh tokens JWT;
- rotação transacional de refresh tokens com detecção de reutilização;
- blacklist persistente e idempotente para tokens JWT;
- login com emissão de tokens e vínculo CSRF;
- renovação com rotação do refresh token e novo vínculo CSRF;
- logout com revogação do refresh e blacklist do access token;
- camada HTTP com request, response e tratamento centralizado de erros;
- roteador para rotas estáticas e parametrizadas;
- logs estruturados de requisições e exceções com Monolog;
- serviço de cookies de autenticação com atributos de segurança testados;
- controller de autenticação para login, refresh e logout;
- proteção CSRF por cookie e header nos endpoints sensíveis;
- vínculo do CSRF com a sessão registrada no access token;
- limite persistente de tentativas de login por credencial/IP e por IP;
- limite de 1 MB e `Content-Type` obrigatório nos corpos JSON;
- headers HTTP de segurança e versões do Apache/PHP ocultadas;
- CORS restrito à origem configurada para o frontend;
- preflight `OPTIONS` validado sem exigir autenticação;
- rotas HTTP de login, refresh e logout conectadas;
- autenticação de rotas pelo access token armazenado em cookie;
- consulta da blacklist e do usuário ativo em cada requisição protegida;
- autorização por perfil `ADMIN` e `OPERADOR`;
- rota autenticada para consultar a sessão atual;
- listagem de usuários restrita ao perfil `ADMIN`;
- criação de usuários restrita ao perfil `ADMIN` e protegida por CSRF;
- atualização de usuários restrita ao perfil `ADMIN` e protegida por CSRF;
- exclusão lógica de usuários restrita ao perfil `ADMIN` e protegida por CSRF;
- proteção contra desativação, exclusão ou remoção do perfil da própria conta;
- revogação dos refresh tokens ao trocar a senha, desativar ou excluir um usuário;
- criação, listagem, consulta e atualização de pedidos;
- acesso aos pedidos permitido para usuários `ADMIN` e `OPERADOR` autenticados;
- nome do cliente e descrição do pedido criptografados no banco;
- validação dos três status permitidos para pedidos;
- comando de terminal para criar administradores sem cadastro público;
- healthchecks configurados para a API e o banco;
- rota `GET /health` disponível para verificar a API;
- testes unitários e de integração configurados com PHPUnit;
- frontend React executando com Vite e Material UI;
- login conectado à API por cookies, CSRF e renovação automática da sessão;
- rotas protegidas e menu condicionado aos perfis `ADMIN` e `OPERADOR`;
- layout ERP responsivo inspirado na navegação lateral do AgraTeste;
- tela inicial e páginas unificadas para administração de acessos e pedidos;
- busca local e paginação de dez registros nas listas de acessos e pedidos;
- estados de carregamento, lista vazia, sucesso, validação e erro no frontend;
- cliente HTTP, componentes e fluxos do frontend testados com Vitest;
- build de produção validado pelo TypeScript e Vite;
- contrato OpenAPI 3.1 publicado e conferido automaticamente com as rotas.

A refatoração do código legado também está entregue separadamente em
`/refatoracao`, com prepared statement, camadas, logs e testes próprios.

## Estrutura

```text
PedidosFull/
|-- api/
|   |-- bootstrap/app.php                  # Montagem da aplicação
|   |-- bin/
|   |   |-- create-admin.php              # Criação segura de administrador
|   |   `-- migrate.php                   # Comando de migrations
|   |-- database/migrations/              # Alterações versionadas do banco
|   |-- docker/apache/                    # VirtualHost do Apache
|   |-- public/
|   |   |-- index.php                    # Ponto de entrada da API
|   |   `-- openapi.json                 # Contrato HTTP em OpenAPI 3.1
|   |-- routes/api.php                    # Registro das rotas HTTP
|   |-- src/
|   |   |-- Config/
|   |   |   |-- AuthConfig.php             # Configuração segura da autenticação
|   |   |   |-- CorsConfig.php             # Origem permitida no CORS
|   |   |   `-- Environment.php            # Leitura e validação do ambiente
|   |   |-- Console/
|   |   |   `-- CreateAdminCommand.php     # Regra do comando administrativo
|   |   |-- Controller/
|   |   |   |-- AuthenticationController.php
|   |   |   |-- OrderController.php
|   |   |   `-- UserController.php
|   |   |-- Database/
|   |   |   |-- ConnectionFactory.php     # Criação da conexão PDO
|   |   |   `-- MigrationRunner.php       # Execução das migrations
|   |   |-- Dto/
|   |   |   |-- AuthenticatedUser.php       # Usuário e token da requisição
|   |   |   |-- AuthenticationResult.php   # Resultado do login e refresh
|   |   |   |-- CreateUserInput.php        # Dados validados do cadastro
|   |   |   |-- IssuedToken.php            # Token emitido e seus metadados
|   |   |   |-- OrderInput.php              # Dados validados do pedido
|   |   |   |-- TokenClaims.php            # Claims validadas do token
|   |   |   `-- UpdateUserInput.php        # Dados validados da atualização
|   |   |-- Entity/
|   |   |   |-- Order.php                   # Entidade de pedido
|   |   |   |-- OrderStatus.php             # Status permitidos
|   |   |   |-- TokenRevocationReason.php  # Motivos de revogação
|   |   |   |-- TokenType.php              # Tipos access e refresh
|   |   |   |-- User.php                   # Entidade de usuário
|   |   |   `-- UserProfile.php            # Perfis de acesso
|   |   |-- Exception/
|   |   |   |-- InvalidCsrfTokenException.php
|   |   |   |-- InvalidJsonBodyException.php
|   |   |   |-- InvalidTokenException.php
|   |   |   |-- InvalidCredentialsException.php
|   |   |   |-- MethodNotAllowedException.php
|   |   |   |-- OrderNotFoundException.php
|   |   |   |-- RefreshTokenNotActiveException.php
|   |   |   |-- RefreshTokenReuseException.php
|   |   |   |-- RouteNotFoundException.php
|   |   |   |-- UserNotFoundException.php
|   |   |   `-- ValidationException.php
|   |   |-- Http/                         # Entrada e saída HTTP da aplicação
|   |   |-- Logging/                      # Configuração do Monolog
|   |   |-- Middleware/                   # Autenticação e autorização
|   |   |-- Repository/
|   |   |   |-- AuthenticationRepository.php
|   |   |   |-- PdoAuthenticationRepository.php
|   |   |   |-- OrderRepository.php         # Contrato de pedidos
|   |   |   |-- PdoOrderRepository.php      # Persistência criptografada
|   |   |   |-- PdoRefreshTokenRepository.php
|   |   |   |-- PdoTokenBlacklistRepository.php
|   |   |   |-- RefreshTokenRepository.php
|   |   |   |-- TokenBlacklistRepository.php
|   |   |   |-- UserRepository.php         # Contrato de persistência
|   |   |   `-- PdoUserRepository.php      # Implementação com PDO
|   |   |-- Security/
|   |   |   |-- CsrfTokenService.php       # Geração e validação de CSRF
|   |   |   |-- DataCipher.php             # Criptografia autenticada
|   |   |   |-- LoginRateLimiter.php        # Contrato do limite de login
|   |   |   |-- LookupHasher.php            # Hash protegido para consultas
|   |   |   `-- PdoLoginRateLimiter.php     # Limite persistente no MySQL
|   |   |-- Routing/                      # Registro e resolução de rotas
|   |   `-- Service/
|   |       |-- AuthenticationService.php   # Regras de autenticação
|   |       |-- CreateUserInputValidator.php
|   |       |-- JwtService.php              # Emissão e validação de JWT
|   |       |-- OrderInputValidator.php
|   |       |-- OrderService.php             # Regras de pedidos
|   |       |-- PasswordPolicy.php           # Política compartilhada de senha
|   |       |-- UpdateUserInputValidator.php
|   |       `-- UserService.php             # Regras de usuários
|   |-- tests/
|   |   |-- Unit/                         # Testes isolados
|   |   |-- Integration/                  # Testes com serviços reais
|   |   `-- Smoke/                        # Verificação do ambiente completo
|   |-- composer.json
|   |-- composer.lock
|   |-- phpunit.xml
|   |-- .dockerignore
|   `-- Dockerfile
|-- frontend/
|   |-- public/                           # Marca e arquivos públicos
|   |-- src/
|   |   |-- app/                          # Rotas e tema do Material UI
|   |   |-- components/                   # Shell ERP e componentes comuns
|   |   |-- contexts/                     # Estado da autenticação
|   |   |-- lib/                          # Cliente HTTP e renovação da sessão
|   |   |-- pages/                        # Login, início, acessos e pedidos
|   |   `-- types/                        # Contratos TypeScript da API
|   |-- Dockerfile
|   |-- package.json
|   `-- vite.config.ts
|-- refatoracao/                          # Parte 2 entregue separadamente
|   |-- database/schema.sql               # Modelo mínimo do exercício legado
|   |-- public/index.php                  # Entrada HTTP refatorada
|   |-- src/                              # Banco, HTTP, logs, repository e service
|   |-- tests/Unit/                       # Testes isolados da inserção e das camadas
|   |-- composer.json
|   |-- phpunit.xml
|   `-- README.md                         # Decisões e execução da Parte 2
|-- .env.example
|-- compose.yaml
`-- README.md
```

## Tecnologias

Estas são as versões que validei no ambiente Docker atual:

- PHP 8.2.33 (`php:8.2-apache-bookworm`)
- Apache 2.4
- Composer 2.10.2
- MySQL 8.4.11 (`mysql:8.4`)
- PDO MySQL
- Libsodium
- Node.js 22.23.2 (`node:22-alpine`)
- React 18.3.1
- React Router 7.18
- TypeScript 5.9
- Vite 8.2
- Material UI 7.3
- Vitest 4.1
- Docker Compose

Dependências instaladas na API:

- PHPUnit 11.5
- Firebase PHP-JWT 7.1
- Monolog 3.10

No frontend, uso os componentes do Material UI, os ícones oficiais do pacote
`@mui/icons-material` e carregamento sob demanda das páginas.

## Como executar

Para executar o projeto, é necessário ter Docker Engine e Docker Compose
instalados.

Crie o arquivo local de configuração:

```bash
cp .env.example .env
```

Gere valores diferentes para `MYSQL_PASSWORD` e `MYSQL_ROOT_PASSWORD`:

```bash
openssl rand -hex 24
```

Execute o comando duas vezes e coloque os resultados no `.env`. Esse arquivo é
ignorado pelo Git e não deve ser enviado ao repositório.

Gere separadamente as duas chaves de 32 bytes usadas na proteção dos dados:

```bash
openssl rand -base64 32
```

Execute o comando duas vezes. Coloque um resultado em `DATA_ENCRYPTION_KEY` e o
outro em `DATA_LOOKUP_KEY` no `.env`.

A primeira chave protege a criptografia reversível dos dados. A segunda protege
os hashes determinísticos usados nas consultas. As chaves não devem ser iguais
nem enviadas ao repositório.

Gere mais duas chaves com o mesmo comando para `JWT_ACCESS_SECRET` e
`JWT_REFRESH_SECRET`. O access token possui duração de 15 minutos e o refresh
token de um dia. As chaves dos tokens também devem ser diferentes entre si e
das chaves usadas para proteger os dados.

Configure em `FRONTEND_ORIGIN` a origem exata do frontend, sem barra no final:

```dotenv
FRONTEND_ORIGIN=http://localhost:5174
VITE_API_URL=http://localhost:18081
```

Em produção, essa origem deve utilizar HTTPS. Como a autenticação usa cookies,
o frontend deverá enviar `credentials: 'include'` nas requisições HTTP.

O limite de login usa os seguintes valores padrão, que podem ser ajustados no
`.env`:

```dotenv
AUTH_LOGIN_MAX_ATTEMPTS=5
AUTH_LOGIN_IP_MAX_ATTEMPTS=20
AUTH_LOGIN_WINDOW=900
AUTH_LOGIN_BLOCK=900
```

O primeiro limite considera a combinação usuário/IP e o segundo evita a
distribuição de tentativas entre vários usuários no mesmo IP. As chaves desses
registros são protegidas com HMAC antes de serem persistidas.

Suba o frontend, a API e o MySQL:

```bash
docker compose up -d --build --wait --wait-timeout 180
```

Confira os containers:

```bash
docker compose ps
```

Execute as migrations:

```bash
docker compose exec api php bin/migrate.php
```

O comando cria as tabelas pendentes e registra cada versão em
`schema_migrations`. Execuções seguintes ignoram migrations já aplicadas.

Crie o primeiro administrador pelo terminal:

```bash
docker compose exec api php bin/create-admin.php
```

O comando solicita nome, e-mail, usuário, senha e confirmação. A senha fica
oculta durante a digitação, não é recebida como argumento do processo e é
armazenada somente como hash. A senha deve possuir mais de oito caracteres e
conter pelo menos uma letra maiúscula, uma minúscula, um número e um caractere
especial. O perfil é sempre definido como `ADMIN`. O mesmo comando pode criar
outro administrador quando necessário, mas exige acesso ao servidor ou ao
contêiner e não fica disponível como rota pública.

Deixei a API disponível em:

```text
http://localhost:18081
```

Deixei o frontend disponível em:

```text
http://localhost:5174
```

É possível alterar as portas por `API_PORT` e `FRONTEND_PORT` no `.env`. A
variável `VITE_API_URL` informa ao navegador onde a API está publicada. Mantive
o MySQL na porta `3306` apenas dentro da rede Docker, sem publicá-la na máquina.

## Rotas atuais

| Método | Rota | Descrição |
|---|---|---|
| `GET` | `/` | Confirma que o serviço está ativo |
| `GET` | `/health` | Healthcheck da API |
| `POST` | `/auth/login` | Autentica e cria os cookies da sessão |
| `POST` | `/auth/refresh` | Rotaciona os tokens com proteção CSRF |
| `POST` | `/auth/logout` | Revoga a sessão com proteção CSRF |
| `GET` | `/auth/me` | Retorna o usuário autenticado |
| `GET` | `/usuarios` | Lista usuários; exige perfil `ADMIN` |
| `POST` | `/auth/register` | Cria usuário; exige `ADMIN` e proteção CSRF |
| `PUT` | `/usuarios/{id}` | Atualiza usuário; exige `ADMIN` e proteção CSRF |
| `DELETE` | `/usuarios/{id}` | Exclui usuário logicamente; exige `ADMIN` e proteção CSRF |
| `GET` | `/pedidos` | Lista pedidos; exige autenticação |
| `POST` | `/pedidos` | Cria pedido; exige autenticação e proteção CSRF |
| `GET` | `/pedidos/{id}` | Detalha pedido; exige autenticação |
| `PUT` | `/pedidos/{id}` | Atualiza pedido; exige autenticação e proteção CSRF |

Não implementei `DELETE /pedidos`, conforme solicitado no teste técnico.

Mantive o cadastro de acesso completo e controlado pelo administrador. A rota
usa o nome `POST /auth/register`, conforme o teste técnico, mas continua
exigindo uma sessão `ADMIN` e proteção CSRF. O administrador informa nome,
e-mail, usuário, senha e o perfil `ADMIN` ou `OPERADOR` do novo acesso.

## Contrato OpenAPI

O contrato completo da API está versionado em `api/public/openapi.json` e fica
disponível com a aplicação em execução:

```text
http://localhost:18081/openapi.json
```

O arquivo usa OpenAPI 3.1 e descreve rotas, corpos JSON, campos obrigatórios,
respostas, códigos HTTP, cookies de autenticação, proteção CSRF e permissões
administrativas. Ele pode ser aberto diretamente ou importado em ferramentas
compatíveis, como Swagger Editor, Postman e Insomnia.

OpenAPI é o padrão que define o contrato legível por pessoas e programas.
Swagger é o conjunto de ferramentas que pode editar ou apresentar esse
contrato. O projeto publica o contrato OpenAPI, mas não incorpora Swagger UI,
evitando adicionar scripts e dependências desnecessárias à API.

Teste rápido:

```bash
curl -sS -w '\nHTTP %{http_code}\n' http://localhost:18081/health
```

Resposta esperada:

```text
{"service":"pedidos-api","status":"ok"}
HTTP 200
```

## Como executar os testes

Com a API e o MySQL em execução, rodo:

```bash
docker compose exec api composer test
```

Também posso executar cada camada separadamente:

```bash
docker compose exec api composer test:unit
docker compose exec api composer test:integration
docker compose exec api composer test:smoke
```

O smoke test acessa a API pelo Apache, valida as respostas HTTP 200, 204, 403,
404, 405 e 415, incluindo o cabeçalho `Allow`, e confirma que essas respostas não
criam cookies. Também confirma que o contrato OpenAPI está publicado como JSON.
Ele consulta o MySQL com PDO, confirma que não há
migrations pendentes, verifica as tabelas obrigatórias e executa um logout real
em uma transação temporária para validar a revogação e a blacklist. O smoke
também cria usuários temporários para confirmar autenticação obrigatória,
bloqueio de token inválido ou revogado, acesso comum do `OPERADOR`, bloqueio do
`OPERADOR` em rota administrativa e acesso permitido ao `ADMIN`. Ele também
valida a criação administrativa de usuário, política de senha, proteção CSRF e
duplicidade de e-mail e usuário. Também valida atualização, troca de senha,
exclusão lógica, revogação da sessão e proteção da própria conta do
administrador. Para pedidos, testa autenticação, CSRF, validação, criação por
`OPERADOR`, listagem, detalhe, atualização, resposta `404` e a ausência do
endpoint `DELETE`. Os registros temporários, pedidos e tokens são removidos ao
final da execução.

O smoke também envia requisições como um navegador: confirma a origem
permitida, o preflight `OPTIONS`, cookies habilitados, cache do preflight e o
bloqueio de origens e headers não autorizados. Também verifica os headers de
segurança, a ocultação das versões do servidor, `Content-Type` JSON e rejeição
de um token CSRF pertencente a outra sessão.

Para executar PHPUnit e o smoke test em sequência:

```bash
docker compose exec api composer check
```

Para executar os testes e o build do frontend:

```bash
docker compose exec frontend npm test
docker compose exec frontend npm run build
```

Para instalar as dependências e executar separadamente os testes da refatoração:

```bash
docker compose run --rm --no-deps \
    --user "$(id -u):$(id -g)" \
    -e COMPOSER_HOME=/tmp/composer \
    -v "$PWD/refatoracao:/var/www/html" \
    api \
    composer install --no-interaction --prefer-dist

docker compose run --rm --no-deps \
    -v "$PWD/refatoracao:/var/www/html" \
    api \
    composer test
```

A Parte 2 possui documentação própria em `refatoracao/README.md`. Seus testes
confirmam a separação em camadas, validação, respostas HTTP, logs e o uso de
prepared statement, incluindo uma tentativa de SQL Injection tratada somente
como dado.

Os testes do frontend confirmam o envio de cookies, o cabeçalho CSRF nas
operações de escrita, a renovação automática após `401`, o tratamento dos
erros de validação e a consolidação de requisições simultâneas em uma única
rotação do refresh token. Também validam a busca sem diferença entre acentos
e letras maiúsculas, a filtragem das listas e a paginação fixa de dez registros
nas telas de acessos e pedidos. Os testes de componentes cobrem login,
visibilidade da senha, restauração e proteção da sessão, autorização por perfil,
navegação do menu, confirmação de logout, cadastro e exclusão de acessos,
estados das listagens, criação, validação, carregamento e atualização de pedidos.

Atualmente, a suíte possui testes unitários para o contrato OpenAPI, variáveis
de ambiente, criptografia autenticada, hashes de consulta, entidades, validação
e services de usuários e pedidos, JWT, CSRF, login, renovação de tokens,
request, response, router, logout, autenticação do access token, autorização por
perfil, tratamento de erros, comando de criação de administrador, aplicação
HTTP, logging e cookies de autenticação.
Os testes de cookies verificam atributos `HttpOnly`, `Secure`, `SameSite`,
escopo, expiração, remoção e codificação contra injeção. Os testes de integração
validam a conexão PDO, a persistência e a autenticação com um MySQL real,
incluindo limite de login, registro, rotação, revogação, reutilização e limpeza de refresh
tokens, além de inserção, consulta e limpeza da blacklist. A persistência de
pedidos é testada com o MySQL real e inclui verificação de que nome do cliente
e descrição não são armazenados em texto puro.

Resultado atual:

```text
OK (276 tests, 1068 assertions)
```

O frontend possui atualmente:

```text
40 tests passed
```

A refatoração do código legado possui:

```text
OK (10 tests, 32 assertions)
```

Para encerrar os containers sem apagar os dados do banco:

```bash
docker compose down
```

Para reiniciar o banco do zero, removendo o volume e todos os dados:

```bash
docker compose down -v
```

## Decisões do projeto

Escolhi desenvolver a API em PHP puro, sem Laravel ou Symfony. Vou separar o
código em controllers, services, repositories e entities, com autoload PSR-4
pelo Composer.

Vou acessar o MySQL com PDO e prepared statements.

As mudanças estruturais do banco são aplicadas por migrations versionadas. O
runner executa os arquivos em ordem e registra cada versão concluída na tabela
`schema_migrations`.

As senhas serão armazenadas com hash irreversível utilizando `password_hash()`
e verificadas com `password_verify()`. Senhas não serão criptografadas de forma
reversível.

Para os dados pessoais que realmente precisarem de criptografia reversível,
implementei um serviço com Libsodium e XChaCha20-Poly1305. A chave fica somente
nas variáveis de ambiente e nunca será armazenada no banco ou enviada ao
repositório.

Campos criptografados que precisarem de busca, como o e-mail, terão um hash de
consulta separado, gerado com HMAC-SHA256 e uma chave exclusiva. Isso permite
validar a unicidade e localizar registros sem pesquisar pelo texto original ou
pela criptografia aleatória.

Essa criptografia será uma das medidas de segurança do projeto, junto com
controle de acesso por perfil, exclusão lógica de usuários e logs sem
informações sensíveis.

Nos pedidos, criptografo o nome do cliente e a descrição antes de persistir e
descriptografo somente ao montar as entidades retornadas pela API. Todas as
operações de escrita usam prepared statements. O campo `criado_por` registra o
usuário autenticado que criou o pedido, enquanto `ADMIN` e `OPERADOR` podem
acessar as quatro rotas exigidas no teste.

Para a autenticação, escolhi separar access e refresh tokens e armazená-los em
cookies `HttpOnly`. A configuração exige cookies `Secure`, debug desativado e
CSRF ativo em produção. A emissão e a validação criptográfica dos dois tipos de
token já estão implementadas. Os refresh tokens são persistidos com seus
identificadores protegidos, rotacionados em transações e têm reutilizações
detectadas com revogação da família. A blacklist persistente também está pronta
para bloquear tokens ainda válidos. O login já autentica credenciais, gera os
tokens, vincula o CSRF ao access token e registra o refresh. A rotação já faz
parte do service de autenticação: ela valida o refresh atual,
consulta se o usuário continua ativo, preserva a família, invalida o token
usado e emite um novo par de tokens com um novo CSRF. O logout já revoga a
família do refresh e bloqueia o access token ainda válido. Login, refresh e
logout já estão conectados à camada HTTP com cookies `HttpOnly`, `SameSite` e
validação CSRF por double-submit. Nas operações protegidas, o hash do cookie
também precisa corresponder ao claim CSRF do access token, impedindo o uso de
um CSRF emitido para outra sessão. Nas rotas protegidas, o middleware valida o
access token, consulta a blacklist e carrega o usuário atual do banco. Assim,
desativação e mudança de perfil têm efeito sem esperar o JWT expirar. A
autorização administrativa compara o perfil atual com `ADMIN`.

As falhas de login são limitadas no MySQL por credencial/IP e por IP. Esse
armazenamento funciona entre processos e futuras réplicas da API. Usuários
inexistentes também passam por uma verificação de hash de senha fictício para
reduzir diferenças de tempo que poderiam revelar quais contas existem. Corpos
JSON exigem `application/json` e possuem limite de 1 MB tanto no PHP quanto no
Apache.

As respostas recebem `Cache-Control: no-store`, proteção contra MIME sniffing,
frames, envio de referência e uma política restritiva de conteúdo. O Apache e
o PHP não expõem suas versões. HSTS é enviado apenas quando `APP_ENV` é
`production`, porque ativá-lo em HTTP local prejudicaria o desenvolvimento.

## Preparação para produção

O Compose atual executa Vite e inclui ferramentas de desenvolvimento para
facilitar a implementação. Antes da publicação, vou criar uma configuração de
produção separada com estes pontos:

- TLS/HTTPS encerrado por um proxy reverso;
- `APP_ENV=production`, `APP_DEBUG=false` e `AUTH_COOKIE_SECURE=true`;
- `FRONTEND_ORIGIN` e `VITE_API_URL` com os domínios HTTPS reais;
- segredos gerenciados pela plataforma, sem arquivo `.env` na imagem;
- frontend compilado por `npm run build` e servido como arquivos estáticos;
- API instalada com `composer install --no-dev --classmap-authoritative`;
- banco e backups fora da rede pública, com migrations executadas na entrega;
- observabilidade, retenção de logs e alertas sem dados pessoais ou segredos.

Na administração de usuários, uma troca de senha, desativação ou exclusão
lógica revoga os refresh tokens do usuário afetado. O access token continua
limitado à sua duração de 15 minutos, enquanto usuários desativados ou
excluídos são bloqueados imediatamente pela consulta ao banco feita em cada
requisição protegida. Também impeço o
administrador autenticado de desativar ou excluir a própria conta e de remover
o próprio perfil `ADMIN`, evitando que ele interrompa acidentalmente o próprio
acesso administrativo.

O CORS devolve `Access-Control-Allow-Origin` somente quando o header `Origin`
corresponde exatamente a `FRONTEND_ORIGIN`. Também habilita credenciais para os
cookies `HttpOnly`, aceita apenas os métodos usados pela API e limita os
headers enviados pelo frontend a `Content-Type` e `X-CSRF-Token`. Não uso o
curinga `*`, pois ele não deve ser combinado com cookies de autenticação.

No frontend, concentro as requisições no cliente HTTP de `src/lib/api.ts`. Ele
sempre usa `credentials: 'include'`, lê somente o cookie público de CSRF e
renova a sessão quando uma rota protegida retorna `401`. Os JWTs continuam
inacessíveis ao JavaScript por serem cookies `HttpOnly`. O contexto de
autenticação mantém o usuário atual e as rotas React impedem a navegação sem
sessão. O menu de acessos aparece apenas para `ADMIN`, mas essa condição visual
não substitui a autorização do backend, que continua validando o perfil em
cada rota administrativa.

As listas de acessos e pedidos possuem busca instantânea e paginação de dez
registros. A API devolve os registros autorizados e o frontend filtra os dados
já descriptografados em memória, sem realizar uma nova consulta a cada tecla.
Por isso, essa busca não utiliza os índices do MySQL. Uma futura paginação no
servidor será necessária caso o volume de registros cresça significativamente.

## Limitações atuais

A aplicação atende aos fluxos principais do teste, mas ainda possui limitações:

- estão expostas as rotas técnicas, de autenticação, o cadastro administrativo
  completo de usuários e as quatro rotas obrigatórias de pedidos;
- a busca e a paginação atuais são locais e ainda não foram movidas para o
  servidor, o que seria necessário para volumes elevados de registros.

## Próximas etapas

As três partes obrigatórias do teste estão implementadas. Como melhorias
futuras, vou preparar uma configuração separada para produção e mover a busca e
a paginação para o servidor quando o volume de registros justificar essa
mudança.
