# Gerenciamento de Pedidos

Estou desenvolvendo este projeto para o teste técnico fullstack. Meu objetivo é
criar uma API de pedidos em PHP, uma interface em React e preparar todo o
ambiente para rodar com Docker.

## Estado atual

Até agora, deixei a infraestrutura inicial da API pronta:

- API executando com PHP e Apache;
- MySQL isolado na rede do Docker;
- variáveis de ambiente obrigatórias e booleanas validadas;
- conexão PDO centralizada e validada com o MySQL;
- criptografia autenticada implementada e testada com Libsodium;
- hash protegido para consultas de dados criptografados;
- migrations versionadas para usuários, pedidos, refresh tokens e blacklist;
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
- rotas HTTP de login, refresh e logout conectadas;
- autenticação de rotas pelo access token armazenado em cookie;
- consulta da blacklist e do usuário ativo em cada requisição protegida;
- autorização por perfil `ADMIN` e `OPERADOR`;
- rota autenticada para consultar a sessão atual;
- listagem de usuários restrita ao perfil `ADMIN`;
- comando de terminal para criar administradores sem cadastro público;
- healthchecks configurados para a API e o banco;
- rota `GET /health` disponível para verificar a API;
- testes unitários e de integração configurados com PHPUnit.

Ainda vou implementar criação, atualização e exclusão de usuários pela API,
pedidos e frontend.

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
|   |-- public/index.php                  # Ponto de entrada da API
|   |-- routes/api.php                    # Registro das rotas HTTP
|   |-- src/
|   |   |-- Config/
|   |   |   |-- AuthConfig.php             # Configuração segura da autenticação
|   |   |   `-- Environment.php            # Leitura e validação do ambiente
|   |   |-- Console/
|   |   |   `-- CreateAdminCommand.php     # Regra do comando administrativo
|   |   |-- Controller/
|   |   |   |-- AuthenticationController.php
|   |   |   `-- UserController.php
|   |   |-- Database/
|   |   |   |-- ConnectionFactory.php     # Criação da conexão PDO
|   |   |   `-- MigrationRunner.php       # Execução das migrations
|   |   |-- Dto/
|   |   |   |-- AuthenticatedUser.php       # Usuário e token da requisição
|   |   |   |-- AuthenticationResult.php   # Resultado do login e refresh
|   |   |   |-- CreateUserInput.php        # Dados validados do cadastro
|   |   |   |-- IssuedToken.php            # Token emitido e seus metadados
|   |   |   `-- TokenClaims.php            # Claims validadas do token
|   |   |-- Entity/
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
|   |   |   |-- RefreshTokenNotActiveException.php
|   |   |   |-- RefreshTokenReuseException.php
|   |   |   |-- RouteNotFoundException.php
|   |   |   `-- ValidationException.php
|   |   |-- Http/                         # Entrada e saída HTTP da aplicação
|   |   |-- Logging/                      # Configuração do Monolog
|   |   |-- Middleware/                   # Autenticação e autorização
|   |   |-- Repository/
|   |   |   |-- AuthenticationRepository.php
|   |   |   |-- PdoAuthenticationRepository.php
|   |   |   |-- PdoRefreshTokenRepository.php
|   |   |   |-- PdoTokenBlacklistRepository.php
|   |   |   |-- RefreshTokenRepository.php
|   |   |   |-- TokenBlacklistRepository.php
|   |   |   |-- UserRepository.php         # Contrato de persistência
|   |   |   `-- PdoUserRepository.php      # Implementação com PDO
|   |   |-- Security/
|   |   |   |-- CsrfTokenService.php       # Geração e validação de CSRF
|   |   |   |-- DataCipher.php             # Criptografia autenticada
|   |   |   `-- LookupHasher.php            # Hash protegido para consultas
|   |   |-- Routing/                      # Registro e resolução de rotas
|   |   `-- Service/
|   |       |-- AuthenticationService.php   # Regras de autenticação
|   |       |-- CreateUserInputValidator.php
|   |       |-- JwtService.php              # Emissão e validação de JWT
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
|-- frontend/                             # Aplicação React
|-- refatoracao/                          # Refatoração do PHP legado
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
- Docker Compose

Dependências instaladas na API:

- PHPUnit 11.5
- Firebase PHP-JWT 7.1
- Monolog 3.10

Vou criar o frontend com Node.js 22, React 18.3.1, React Router 7, TypeScript,
Vite e Material UI. Essas dependências ainda não fazem parte do ambiente atual.

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

Suba a API e o MySQL:

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
http://localhost:18080
```

É possível alterar a porta por `API_PORT` no `.env`. Mantive o MySQL na porta
`3306` apenas dentro da rede Docker, sem publicá-la na máquina.

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

Teste rápido:

```bash
curl -sS -w '\nHTTP %{http_code}\n' http://localhost:18080/health
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

O smoke test acessa a API pelo Apache, valida as respostas HTTP 200, 404 e 405,
incluindo o cabeçalho `Allow`, e confirma que essas respostas públicas não
criam cookies. Ele também consulta o MySQL com PDO, confirma que não há
migrations pendentes, verifica as tabelas obrigatórias e executa um logout real
em uma transação temporária para validar a revogação e a blacklist. O smoke
também cria usuários temporários para confirmar autenticação obrigatória,
bloqueio de token inválido ou revogado, acesso comum do `OPERADOR`, bloqueio do
`OPERADOR` em rota administrativa e acesso permitido ao `ADMIN`. Esses usuários
e seus tokens são removidos ao final da execução. Para executar PHPUnit e o
smoke test em sequência:

```bash
docker compose exec api composer check
```

Atualmente, a suíte possui testes unitários para variáveis de ambiente,
criptografia autenticada, hashes de consulta, entidades, validação e services
de usuários, JWT, CSRF, login, renovação de tokens, request, response, router,
logout, autenticação do access token, autorização por perfil, tratamento de
erros, comando de criação de administrador, aplicação HTTP, logging e cookies
de autenticação.
Os testes de cookies verificam atributos `HttpOnly`, `Secure`, `SameSite`,
escopo, expiração, remoção e codificação contra injeção. Os testes de integração
validam a conexão PDO, a persistência e a autenticação com um MySQL real,
incluindo registro, rotação, revogação, reutilização e limpeza de refresh
tokens, além de inserção, consulta e limpeza da blacklist.

Resultado atual:

```text
OK (192 tests, 620 assertions)
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
validação CSRF por double-submit. Nas rotas protegidas, o middleware valida o
access token, consulta a blacklist e carrega o usuário atual do banco. Assim,
desativação e mudança de perfil têm efeito sem esperar o JWT expirar. A
autorização administrativa compara o perfil atual com `ADMIN`. Ainda vou
restringir o CORS à origem do frontend.

## Limitações atuais

A base de domínio, persistência e autenticação está em construção e ainda não
representa a aplicação completa. Neste momento:

- estão expostas as rotas técnicas, de autenticação e a listagem administrativa
  de usuários;
- criação, atualização e exclusão de usuários ainda não estão expostas;
- o CORS ainda não está restrito à origem do frontend;
- a regra de pedidos, o frontend React e a refatoração legada ainda serão
  desenvolvidos.

## Próximas etapas

Meus próximos passos são:

- implementar atualização e exclusão lógica de usuários;
- implementar controllers e endpoints de cadastro de usuários;
- implementar criação, listagem, consulta e atualização de pedidos;
- ampliar os testes unitários e criar testes de integração dos endpoints;
- desenvolver o frontend em React com Material UI;
- refatorar o código PHP legado fornecido no teste.
