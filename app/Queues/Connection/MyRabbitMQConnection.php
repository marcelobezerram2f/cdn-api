<?php


namespace App\Queues\Connection;

use PhpAmqpLib\Connection\AMQPStreamConnection;

class MyRabbitMQConnection extends AMQPStreamConnection
{
    public function __construct()
    {
        try {
            parent::__construct(
                trim(env('RABBITMQ_HOST')),
                trim(env('RABBITMQ_PORT')),
                trim(env('RABBITMQ_USER')),
                trim(env('RABBITMQ_PASSWORD')),
                trim(env('RABBITMQ_VHOST')),

            );
        } catch (\Exception $e) {
            parent::__construct(
                trim(env('RABBITMQ2_HOST')),
                trim(env('RABBITMQ_PORT')),
                trim(env('RABBITMQ_USER')),
                trim(env('RABBITMQ_PASSWORD')),
                trim(env('RABBITMQ_VHOST')),
            );
        }
    }
}
