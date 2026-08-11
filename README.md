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
- healthchecks configurados para a API e o banco;
- rota `GET /health` disponível para verificar a API;
- testes unitários e de integração configurados com PHPUnit.

Ainda vou implementar atualização e exclusão de usuários, endpoints de
autenticação, logs, pedidos e frontend.

## Estrutura

```text
PedidosFull/
|-- api/
|   |-- bin/migrate.php                   # Comando de migrations
|   |-- database/migrations/              # Alterações versionadas do banco
|   |-- docker/apache/                    # VirtualHost do Apache
|   |-- public/index.php                  # Ponto de entrada da API
|   |-- src/
|   |   |-- Config/
|   |   |   |-- AuthConfig.php             # Configuração segura da autenticação
|   |   |   `-- Environment.php            # Leitura e validação do ambiente
|   |   |-- Database/
|   |   |   |-- ConnectionFactory.php     # Criação da conexão PDO
|   |   |   `-- MigrationRunner.php       # Execução das migrations
|   |   |-- Dto/
|   |   |   |-- CreateUserInput.php        # Dados validados do cadastro
|   |   |   |-- IssuedToken.php            # Token emitido e seus metadados
|   |   |   `-- TokenClaims.php            # Claims validadas do token
|   |   |-- Entity/
|   |   |   |-- TokenType.php              # Tipos access e refresh
|   |   |   |-- User.php                   # Entidade de usuário
|   |   |   `-- UserProfile.php            # Perfis de acesso
|   |   |-- Exception/
|   |   |   |-- InvalidTokenException.php
|   |   |   `-- ValidationException.php
|   |   |-- Repository/
|   |   |   |-- AuthenticationRepository.php
|   |   |   |-- PdoAuthenticationRepository.php
|   |   |   |-- UserRepository.php         # Contrato de persistência
|   |   |   `-- PdoUserRepository.php      # Implementação com PDO
|   |   |-- Security/
|   |   |   |-- DataCipher.php             # Criptografia autenticada
|   |   |   `-- LookupHasher.php            # Hash protegido para consultas
|   |   `-- Service/
|   |       |-- CreateUserInputValidator.php
|   |       |-- JwtService.php              # Emissão e validação de JWT
|   |       `-- UserService.php             # Regras de usuários
|   |-- tests/
|   |   |-- Unit/                         # Testes isolados
|   |   `-- Integration/                  # Testes com serviços reais
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

Atualmente, a suíte possui testes unitários para variáveis de ambiente,
criptografia autenticada, hashes de consulta, entidades, validação e services
de usuários e JWT. Os testes de integração validam a conexão PDO, a persistência
e a autenticação com um MySQL real.

Resultado atual:

```text
OK (65 tests, 164 assertions)
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
token já estão implementadas. Vou persistir e rotacionar os refresh tokens,
adicionar blacklist no logout e aplicar `SameSite` e CORS restrito à origem do
frontend nos endpoints HTTP. O schema necessário para acompanhar a rotação,
detectar reutilização e registrar tokens revogados já está preparado no MySQL.

## Próximas etapas

Meus próximos passos são:

- implementar atualização e exclusão lógica de usuários;
- implementar repositories e regras de rotação e blacklist dos tokens JWT;
- implementar cadastro, login e autorização por perfil;
- implementar criação, listagem, consulta e atualização de pedidos;
- adicionar validação, logs e tratamento de erros;
- ampliar os testes unitários e criar testes de integração dos endpoints;
- desenvolver o frontend em React com Material UI;
- refatorar o código PHP legado fornecido no teste.
