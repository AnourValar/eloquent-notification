<?php

namespace AnourValar\EloquentNotification\Adapters\Sms;

class SmscAdapter implements SmsInterface
{
    /**
     * @var \AnourValar\HttpClient\Http
     */
    protected $http;

    /**
     * @var array
     */
    protected $config;

    /**
     * DI
     */
    public function __construct()
    {
        $this->http = \App::make(\AnourValar\HttpClient\Http::class)
            ->remember(function (\AnourValar\HttpClient\Http $http) {
                $http->asJsonClient()->timeouts(3000, 10000)->extendInfo();
            });

        $this->config = config('eloquent_notification.bindings.' . SmsInterface::class);
    }

    /**
     * {@inheritDoc}
     * @see \AnourValar\EloquentNotification\Adapters\Sms\SmsInterface::sendMessage()
     */
    public function sendMessage(string $phone, string $message): void
    {
        if (! preg_match('#^[7][9][0-9]+$#', $phone)) {
            return;
        }

        // Prepare the request
        $data = [
            'apikey' => $this->config['smsc_api_key'],
            'phones' => $phone,
            'mes' => $message,
            'sender' => $this->config['smsc_sender'],
            'fmt' => 3, // json response
        ];

        // Sent the request
        $response = $this
            ->http
            ->method('POST')
            ->body($data)
            ->exec('https://smsc.ru/rest/send/');

        // Handle the response
        if (! $response->success('id')) {
            throw new \AnourValar\EloquentNotification\Exceptions\ExternalException('smsc.send_message', $response->dump());
        }
    }
}
