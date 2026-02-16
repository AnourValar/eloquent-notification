<?php

namespace AnourValar\EloquentNotification\Adapters\Push;

class FCMAdapter implements PushInterface
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

        $this->config = config('eloquent_notification.bindings.' . PushInterface::class);
    }

    /**
     * {@inheritDoc}
     * @see \AnourValar\EloquentNotification\Adapters\Push\PushInterface::fromConfig()
     */
    public function fromConfig(array $config): self
    {
        $object = clone $this;
        $object->config = $config;

        return $object;
    }

    /**
     * {@inheritDoc}
     * @see \AnourValar\EloquentNotification\Adapters\Push\PushInterface::sendMessage()
     */
    public function sendMessage(string $receiver, string $title, string $body, array $data = []): void
    {
        if (strpos($receiver, '@') === 0) {
            return; // it s a mock
        }

        $response = $this
            ->http
            ->method('POST')
            ->authToken($this->obtainAccessToken())
            ->body([
                'message' => [
                    'token' => $receiver,
                    'notification' => ['title' => $title, 'body' => $body],
                    'data' => $data ? $data : null,
                ],
            ])
            ->exec(sprintf('https://fcm.googleapis.com/v1/projects/%s/messages:send', $this->config['fcm_service_account']['project_id']));

        if (! $response->success('name')) {
            $error = \AnourValar\EloquentNotification\Exceptions\Error::ETC;
            //$error = \AnourValar\EloquentNotification\Exceptions\Error::USER_BLOCK; // @TODO ?

            throw new \AnourValar\EloquentNotification\Exceptions\ExternalException('fcm.send_message', $response->dump(), $error);
        }
    }

    /**
     * @return string
     */
    protected function obtainAccessToken(): string
    {
        $expireIn = 3600;

        return \Cache::memo()->remember(
            implode(' / ', [__METHOD__, $this->config['fcm_service_account']['client_email'], $this->config['fcm_service_account']['project_id']]),
            ($expireIn - 60),
            function () use ($expireIn) {
                $now = time();

                $header = $this->base64UrlEncode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
                $payload = $this->base64UrlEncode(json_encode([
                    'iss' => $this->config['fcm_service_account']['client_email'],
                    'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                    'aud' => 'https://oauth2.googleapis.com/token',
                    'iat' => $now,
                    'exp' => $now + $expireIn,
                ]));

                $data = $header . '.' . $payload;
                openssl_sign($data, $signature, $this->config['fcm_service_account']['private_key'], 'sha256');
                $jwt = $data . '.' . $this->base64UrlEncode($signature);

                $response = $this
                    ->http
                    ->method('POST')
                    ->body([
                        'type' => 'service_account',
                        'client_email' => $this->config['fcm_service_account']['client_email'],
                        'private_key' => $this->config['fcm_service_account']['private_key'],
                        'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                        'assertion' => $jwt,
                        'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                    ])
                    ->exec('https://oauth2.googleapis.com/token');

                if (! $response->success('access_token')) {
                    throw new \AnourValar\EloquentNotification\Exceptions\ExternalException('fcm.send_message.access_token', $response->dump());
                }

                return $response['access_token'];
            }
        );
    }

    /**
     * @param string $data
     * @return string
     */
    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
