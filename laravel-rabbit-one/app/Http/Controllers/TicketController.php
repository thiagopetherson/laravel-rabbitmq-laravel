<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

// Rabbit
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Connection\AMQPStreamConnection;

// Models
use App\Models\Ticket;

class TicketController extends Controller
{   

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {       
        try {
           
            // Conectar ao RabbitMQ
            $connection = new AMQPStreamConnection('rabbitmq', 5672, 'guest', 'guest');
            $channel = $connection->channel();
            
            // Configurações da exchange e filas
            $exchange = 'product_events';
            $queue = 'product_create_queue';
            $responseQueue = 'product_create_response_queue';

            // Declarar exchange e filas
            $channel->exchange_declare($exchange, 'direct', false, true, false);
            $channel->queue_declare($queue, false, true, false, false);
            $channel->queue_bind($queue, $exchange, $queue);
            $channel->queue_declare($responseQueue, false, true, false, false);

            // Criar a mensagem a ser enviada
            $data = json_encode(['name' => $request->name, 'price' => $request->price]);
            $correlationId = uniqid();
            $message = new AMQPMessage($data, ['correlation_id' => $correlationId, 'reply_to' => $responseQueue]);

            Log::info('Publicando mensagem', [
                'exchange' => $exchange,
                'routing_key' => $queue,
                'message' => $data
            ]);

            // Publicar a mensagem
            $channel->basic_publish($message, $exchange, $queue);

            // Variável para armazenar o ticket criado
            $ticket = null;

            // Definir callback para processar a resposta
            $callback = function ($msg) use (&$ticket, $correlationId) {
                Log::info('Callback chamado com a mensagem', ['message' => $msg->body]);

                if ($msg->get('correlation_id') == $correlationId) {
                    $response = json_decode($msg->body, true);

                    if (isset($response['name']) && isset($response['id'])) {
                        // Salvar a resposta na tabela tickets
                        $ticket = new Ticket();
                        $ticket->product_name = $response['name'];
                        $ticket->product_id = $response['id'];
                        $ticket->save();
                        $ticket->refresh();

                        Log::info('Resposta recebida e ticket salvo: ', $response);
                    } else {
                        Log::warning('Resposta recebida não contém os dados esperados', ['response' => $response]);
                    }
                } else {
                    Log::warning('Correlation ID não corresponde', ['expected' => $correlationId, 'received' => $msg->get('correlation_id')]);
                }
            };           

            // Consumir a resposta da fila responseQueue
            $channel->basic_consume($responseQueue, '', false, true, false, false, $callback);

            while (!$ticket) {
                Log::info('Aguardando resposta...');
                $channel->wait(null, false, 60); // Timeout de 60 segundos para evitar loop infinito
            }

            // Fechar o canal e a conexão
            $channel->close();
            $connection->close();

            // Retornar os dados do ticket na resposta
            if ($ticket)
                return response()->json(['ticket_id' => $ticket->id, 'product_name' => $ticket->product_name, 'product_id' => $ticket->product_id], 200);
            
            return response()->json(['error' => 'Failed to create ticket'], 500);       
        } catch (\Exception $e) {
            Log::error('Controller error: ' . $e->getMessage());
            return response()->json(['error' => 'Internal Server Error'], 500);
        } 
    }    
}
