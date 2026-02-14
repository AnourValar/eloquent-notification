<?php

namespace AnourValar\EloquentNotification\Adapters\Telegram;

class TelegramAdapter implements TelegramInterface
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
                $http->timeouts(3000, 10000)->extendInfo();
            });

        $this->config = config('eloquent_notification.bindings.' . TelegramInterface::class);
    }

    /**
     * {@inheritDoc}
     * @see \AnourValar\EloquentNotification\Adapters\Telegram\MockAdapter::fromConfig()
     */
    public function fromConfig(array $config): self
    {
        $object = clone $this;
        $object->config = $config;

        return $object;
    }

    /**
     * {@inheritDoc}
     * @see \AnourValar\EloquentNotification\Adapters\Telegram\TelegramInterface::sendMessage()
     */
    public function sendMessage(string $chatId, string $message): void
    {
        if (strpos($chatId, '@') === 0) {
            return; // it s a mock
        }

        // Send a request
        $response = $this
            ->http
            ->asJsonClient()
            ->method('POST')
            ->body([
                'chat_id' => $chatId,
                'text' => $message,
                'parse_mode' => 'HTML',
                'disable_notification' => false, // @TODO?
            ])
            ->exec('https://api.telegram.org/bot' . $this->config['token'] . '/sendMessage');


        // Check a response
        if (! $response->success('ok')) {
            $error = \AnourValar\EloquentNotification\Exceptions\Error::ETC;
            if (isset($response->json()['description']) && $response->json()['description'] == 'Forbidden: bot was blocked by the user') {
                $error = \AnourValar\EloquentNotification\Exceptions\Error::USER_BLOCK;
            }

            throw new \AnourValar\EloquentNotification\Exceptions\ExternalException('telegram.send_message', $response->dump(), $error);
        }
    }

    /**
     * {@inheritDoc}
     * @see \AnourValar\EloquentNotification\Adapters\Telegram\TelegramInterface::fetchChatId()
     */
    public function fetchChatId(string $username): ?string
    {
        $username = mb_strtolower($username);

        $cacheKey = implode(' / ', [__METHOD__, $username]);
        if ($chatId = \Cache::driver('array')->get($cacheKey)) {
            return $chatId;
        }

        // Send a request
        $response = $this->http->asJsonClient()->exec('https://api.telegram.org/bot' . $this->config['token'] . '/getUpdates');


        // Check a response
        if (! $response->success('ok')) {
            throw new \AnourValar\EloquentNotification\Exceptions\ExternalException('telegram.fetch_chat', $response->dump());
        }


        // Handle
        $time = now()->addDays(-14)->timestamp;
        $chatId = null;
        $updateId = null;

        foreach ($response['result'] as $item) {
            if (! isset($item['message'])) {
                continue;
            }

            if (mb_strtolower($item['message']['chat']['username'] ?? '') == $username) {
                $chatId = $item['message']['chat']['id'];
            }

            if ($item['message']['date'] < $time) {
                $updateId = $item['update_id'];
            }
        }


        // Fixation (clean up)
        if ($updateId) {
            $response = $this
                ->http
                ->asJsonClient()
                ->method('POST')
                ->body(['offset' => ($updateId + 1)])
                ->exec('https://api.telegram.org/bot' . $this->config['token'] . '/getUpdates');

            if (! $response->success('ok')) {
                throw new \AnourValar\EloquentNotification\Exceptions\ExternalException('telegram.fetch_chat.offset', $response->dump());
            }
        }


        // Result
        if (is_null($chatId)) {
            return null;
        }

        \Cache::driver('array')->forever($cacheKey, $chatId);
        return $chatId;
    }
}
