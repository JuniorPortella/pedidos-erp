# Refatoracao do codigo legado

Esta pasta entrega separadamente a Parte 2 do teste tecnico. O codigo original
misturava conexao, SQL, regra de negocio e resposta HTTP em um unico arquivo.
Tambem utilizava credenciais fixas e concatenava a entrada do usuario no SQL.

## Problemas corrigidos

- credenciais removidas do codigo e recebidas por variaveis de ambiente;
- conexao PDO centralizada com excecoes e prepared statements nativos;
- entrada do cliente enviada separadamente do comando SQL;
- validacao movida para o service;
- persistencia isolada por um contrato de repository;
- camada HTTP limitada a interpretar entrada e montar a resposta;
- erros internos registrados, mas ocultados da resposta HTTP;
- logs JSON de sucesso e erro enviados para `stderr`;
- testes unitarios independentes de um banco real.

## Estrutura

```text
refatoracao/
|-- database/schema.sql
|-- public/index.php
|-- src/
|   |-- Database/ConnectionFactory.php
|   |-- Entity/Order.php
|   |-- Exception/
|   |-- Http/
|   |-- Logging/
|   |-- Repository/
|   `-- Service/CreateOrderService.php
|-- tests/
|   |-- Support/
|   `-- Unit/
|-- composer.json
|-- phpunit.xml
`-- README.md
```

## Fluxo

```text
public/index.php
    -> CreateOrderHandler
    -> CreateOrderService
    -> OrderRepository
    -> PdoOrderRepository
    -> PDO/MySQL
```

O SQL utiliza um placeholder:

```sql
INSERT INTO pedidos (cliente_nome)
VALUES (:cliente_nome)
```

O valor de `cliente_nome` e enviado somente em `PDOStatement::execute()`. Assim,
caracteres recebidos do usuario nunca se tornam parte do comando SQL.

## Testes

Instale as dependencias usando a imagem da API:

```bash
docker compose run --rm --no-deps \
    --user "$(id -u):$(id -g)" \
    -e COMPOSER_HOME=/tmp/composer \
    -v "$PWD/refatoracao:/var/www/html" \
    api \
    composer install --no-interaction --prefer-dist
```

Execute a suite separada:

```bash
docker compose run --rm --no-deps \
    -v "$PWD/refatoracao:/var/www/html" \
    api \
    composer test
```

O teste do repository usa mocks de `PDO` e `PDOStatement`. Ele confirma que ate
uma entrada com comandos SQL permanece apenas no array de parametros enviado ao
prepared statement.

## Execucao manual

O exemplo utiliza intencionalmente um banco separado da API principal, pois
reproduz o modelo minimo do codigo legado. Crie a tabela de
`database/schema.sql` nesse banco e configure:

```dotenv
REFACTOR_DB_HOST=mysql
REFACTOR_DB_PORT=3306
REFACTOR_DB_DATABASE=refatoracao
REFACTOR_DB_USERNAME=pedidos
REFACTOR_DB_PASSWORD=TROCAR
```

O ponto de entrada espera `POST` com formulário tradicional:

```text
cliente_nome=Cliente Teste
```
