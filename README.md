# Gerenciamento de Pedidos

Projeto desenvolvido para o teste técnico de Fullstack. A proposta é criar uma
API de pedidos em PHP, uma interface em React e deixar todo o ambiente pronto
para rodar com Docker.

O projeto ainda está no começo. Este README será atualizado junto com a
implementação, principalmente com os comandos de execução, exemplos da API e
decisões que surgirem durante o desenvolvimento.

## Estrutura

```text
PedidosFull/
├── api/          # API, autenticação, logs e testes
├── frontend/     # Aplicação React
├── refatoracao/  # Exercício de refatoração do PHP legado
└── README.md
```

## Stack escolhida

### API

- PHP 8.2
- Apache (`php:8.2-apache-bookworm`)
- Composer 2.10
- MySQL 8.4 LTS
- PDO MySQL
- PHPUnit 11.5
- Firebase PHP-JWT 6.11
- PHP dotenv 5.6
- Monolog 3.10

A API será feita em PHP puro, sem framework. O código será dividido entre
controllers, services, repositories e entities, com autoload PSR-4 pelo
Composer.

### Frontend

- Node.js 22
- React 18.3.1
- React Router 7.18.2
- TypeScript 5.9.3
- Vite 7.3.6
- ESLint 9
- Prettier 3

O React Router ficará na versão 7 porque ela funciona com React 18. A versão 8
já exige React 19. O Node também será executado pelo Docker, para não interferir
nos outros projetos da máquina.

## O que será implementado

- Cadastro e login com JWT
- Criação, listagem, consulta e atualização de pedidos
- Validação dos dados recebidos pela API
- Logs de requisições e erros
- Testes unitários e de integração
- Refatoração do código PHP legado
- Login, listagem e cadastro de pedidos no frontend

## Ambiente

O ambiente terá containers separados para a API, o frontend e o MySQL. As
versões instaladas ficarão registradas em `composer.lock` e
`package-lock.json`.

