<?php

namespace App\Http\Controllers;

// use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Connection\AMQPStreamConnection;

class RabbitMQController extends Controller
{
    public function send()
    {
        // Enviando uma mensagem para as filas "pdf_create" e "pdf_log"

        $connection = new AMQPStreamConnection('rabbitmq', 5672, 'guest', 'guest');

        $message = new AMQPMessage('Hello World Queue - Sending');

        $channel = $connection->channel();
        $channel->basic_publish($message, 'pdf_events', 'pdf_create');
        $channel->basic_publish($message, 'pdf_events', 'pdf_log');

        $channel->close();
        $connection->close();

        echo "Message published to RabbitMQ \n";
    }

    public function consumer()
    {
        // Esse código abaixo consome mensagens apenas da fila "pdf_log_queue".

        $connection = new AMQPStreamConnection('rabbitmq', 5672, 'guest', 'guest');

        $channel = $connection->channel();

        $callback = function ($msg) {
            Log::info('[x] Recebido: ', [$msg->getBody()]);
        };

        $channel->basic_consume('pdf_log_queue', 'pdf_log_queue', false, true, false, false, $callback);

        $channel->close();
        $connection->close();
    }
}
