<?php

namespace AnourValar\EloquentNotification\Adapters\Sms;

class MtsAdapter implements SmsInterface
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

        // Send a request
        $response = $this
            ->http
            ->authToken($this->config['mts_token'])
            ->method('POST')
            ->body(['number' => $this->config['mts_sender'], 'destination' => $phone, 'text' => $message])
            ->exec('https://api.exolve.ru/messaging/v1/SendSMS');

        // Check a response
        if (! $response->success('message_id')) {
            if (stripos($response['error']['message'] ?? '', 'Number does not exist')) {
                return;
            }

            throw new \AnourValar\EloquentNotification\Exceptions\ExternalException('mts.send_message', $response->dump());
        }
    }
}
