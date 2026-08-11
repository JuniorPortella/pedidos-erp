# Gerenciamento de Pedidos

Projeto desenvolvido para o teste técnico de Fullstack. A proposta é criar uma
-API de pedidos em PHP, uma interface em React e deixar todo o ambiente pronto
-para rodar com Docker.

## Estado atual

Até agora, deixei a primeira etapa do ambiente pronta:

- API executando com PHP e Apache;
- MySQL isolado na rede do Docker;
- conexão PHP -> MySQL validada com PDO;
- healthchecks configurados para a API e o banco;
- rota `GET /health` disponível para verificar a API.

Ainda vou implementar as regras de negócio, a autenticação e o frontend.

## Estrutura

```text
PedidosFull/
|-- api/
|   |-- docker/apache/       # Configuração do VirtualHost
|   |-- public/index.php     # Ponto de entrada da API
|   |-- .dockerignore
|   `-- Dockerfile
|-- frontend/                # Aplicação React (próxima etapa)
|-- refatoracao/             # Exercício de refatoração do PHP legado
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
- Docker Compose

Dependências instaladas na API:

- PHPUnit 11.5
- Firebase PHP-JWT 7.1
- Monolog 3.10

Vou criar o frontend com Node.js 22, React 18.3.1, React Router 7, TypeScript e
Vite. Essas dependências ainda não fazem parte do ambiente atual.

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

Suba a API e o MySQL:

```bash
docker compose up -d --build --wait --wait-timeout 180
```

Confira os containers:

```bash
docker compose ps
```

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

Teste rapido:

```bash
curl -sS -w '\nHTTP %{http_code}\n' http://localhost:18080/health
```

Resposta esperada:

```text
{"service":"pedidos-api","status":"ok"}
HTTP 200
```

Para encerrar os containers sem apagar os dados do banco:

```bash
docker compose down
```

Para reiniciar o banco do zero, removendo o volume e todos os dados:

```bash
docker compose down -v
```

## Decisoes do projeto

Escolhi desenvolver a API em PHP puro, sem Laravel ou Symfony. Vou separar o
código em controllers, services, repositories e entities, com autoload PSR-4
pelo Composer.

Vou acessar o MySQL com PDO e prepared statements. Para a autenticação, escolhi
armazenar o JWT em cookie `HttpOnly`. Vou definir `Secure`, `SameSite`, CORS e a
proteção contra CSRF junto com a implementação da autenticação.

## Próximas etapas

Meus próximos passos são:

- configurar o projeto Composer e o autoload PSR-4;
- criar o schema e as migrations do banco;
- implementar cadastro, login e autenticação;
- implementar criação, listagem, consulta e atualização de pedidos;
- adicionar validação, logs e tratamento de erros;
- criar testes unitários e de integração;
- desenvolver o frontend em React;
- refatorar o código PHP legado fornecido no teste.
