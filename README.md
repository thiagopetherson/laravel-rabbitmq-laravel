# Laravel + RabbitMQ + Laravel

Testando a comunicação através de mensageria entre duas aplicações (servços) Laravel e um serviço do RabbitMQ.

![image](https://github.com/thiagopetherson/laravel-rabbitmq-laravel/assets/44420212/2d495e72-4ec1-4740-ac47-0d638802ae95)

## Seguir os seguinte passos

- Clonar o projeto e rodar os respectivos comandos de instalação dos pacotes
- Criar a network "laravel-rabbitmq-laravel-network"
- Na aplicação do RabbitMQ, rodar o comando "docker compose up -d" para subir seu container
- Na aplicação do Laravel 1, rodar o comando "./vendor/bin/sail up -d" para subir seu container
- Na aplicação do Laravel 2, rodar o comando "./vendor/bin/sail up -d" para subir seu container
- Ir na interface do RabbitMQ e criar a fila "product_create_queue" e a fila "product_create_response_queue"
- Ir na interface do RabbitMQ e criar a exchange "product_events"
- Ir na interface do RabbitMQ e fazer o bind entre a exchange "product_events" e as filas "product_create_queue" e "product_create_response_queue".
- Entrar no container do container da aplicação Laravel 1 e rodar o comando "php artisan app:save-products" para deixar o command executando.

- OBS: Teremos dois containers de MySQL rodando. Então atentar-se nessas tabelas na hora de acessar o endpoint. 
- OBS: O endpoint foi criado no arquivo de rotas de api.

- Pronto, só testar o endpoint !!!

## Tecnologias utilizadas

- PHP
- Laravel
- RabbitMQ
