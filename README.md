# API para cadastro de usuários Comum/Lojista e realização de transferências.

Este projeto foi desenvolvido como parte de um desafio técnico, com o objetivo de implementar uma API RESTful para
transferência de valores entre usuários comuns e lojistas.

A aplicação valida regras de negócio como saldo disponível, tipo de usuário e autorização externa antes de concluir
uma transação. Após a transferência, uma notificação é enviada de forma assíncrona.

---

### Tecnologias que foram utilizadas:
- PHP 8.2  
- Laravel  
- Docker 
- Docker Compose
- PostgreSQL 

---

### Tecnologias necessárias para rodar:
- Make  
- Docker
- Docker Compose

---

### Configuração do ambiente:

Executar o comando abaixo para iniciar o serviço:
```bash
make start
```

---

### Comandos disponíveis:
```bash
# Iniciar os containers
make start

# Executar os testes
make test

# Parar os containers
make stop

# Visualizar os logs
make logs
```

---