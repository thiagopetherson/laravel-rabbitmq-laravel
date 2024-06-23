<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

// Rabbit
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

// Models
use App\Models\Product;

class SaveProducts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:save-products';

    // O Comando pra executar esse command é o 'php artisan app:save-products'

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Consume messages from RabbitMQ, save to products table and return response';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        try {
            // Conectar ao RabbitMQ
            $connection = new AMQPStreamConnection('rabbitmq', 5672, 'guest', 'guest');
            $channel = $connection->channel();

            $exchange = 'product_events';
            $queue = 'product_create_queue';
            $responseQueue = 'product_create_response_queue';

            // Declarar exchange e filas
            $channel->exchange_declare($exchange, 'direct', false, true, false);
            $channel->queue_declare($queue, false, true, false, false);
            $channel->queue_bind($queue, $exchange, $queue);
            $channel->queue_declare($responseQueue, false, true, false, false);
          
            // Callback para processar mensagens da fila
            $callback = function (AMQPMessage $msg) use ($channel) {
                try {
                    $data = json_decode($msg->getBody(), true);

                    Log::info('Mensagem recebida', $data);
                    
                    if (isset($data['name']) && isset($data['price'])) {
                        // Cria um novo produto e salva no banco de dados
                        $product = new Product();
                        $product->name = $data['name'];
                        $product->price = $data['price'];
                        $product->save();

                        Log::info('Produto salvo: ', $data);

                        // Envia a resposta de volta para a fila especificada no 'reply_to'
                        $response = new AMQPMessage(json_encode(['id' => $product->id, 'name' => $product->name]), ['correlation_id' => $msg->get('correlation_id')]);
                        
                        $channel->basic_publish($response, '', $msg->get('reply_to'));

                        Log::info('Resposta enviada: ', ['id' => $product->id, 'name' => $product->name]);
                    } else {
                        Log::warning('Dados da mensagem inválidos: ', $data);
                    }
                } catch (\Exception $e) {
                    Log::error('Erro ao processar a mensagem: ' . $e->getMessage());
                }
            };

            // Consumir mensagens da fila
            $channel->basic_consume($queue, '', false, true, false, false, $callback);

            // Mantém o consumidor ativo
            while ($channel->is_consuming()) {
                $channel->wait();
            }

            // Fechar o canal e a conexão
            $channel->close();
            $connection->close();
        } catch(\Exception $e) {
            Log::error('Command error: ' . $e->getMessage());
        }
    }
}
