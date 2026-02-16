<?php

namespace AnourValar\EloquentNotification\Tests\Adapters\Push;

use Tests\TestCase;
use AnourValar\EloquentNotification\Adapters\Push\PushInterface;
use AnourValar\EloquentNotification\Adapters\Push\FCMAdapter;
use AnourValar\EloquentNotification\Tests\AbstractSuite;
use AnourValar\EloquentNotification\Exceptions\Error;

class FCMAdapterTest extends AbstractSuite
{
    /**
     * Init
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        config(['eloquent_notification.bindings.' . PushInterface::class => [
            'fcm_service_account' => [
                'project_id' => '456',
                'private_key' => $this->testKey(),
                'client_email' => 'k1@example.org',
            ],
        ]]);
    }

    /**
     * @return void
     */
    public function test_sendMessage_success_1()
    {
        \Cache::memo()->put(implode(' / ', [FCMAdapter::class .'::' . 'obtainAccessToken', 'k1@example.org', '456']), '789', 100);

        $this->partialMock(\AnourValar\HttpClient\Http::class, function ($mock) {
            $mock
                ->shouldReceive('body')
                ->withArgs(function ($body) {
                    if ($body['message']['token'] != '123') {
                        return false;
                    }

                    if ($body['message']['notification'] != ['title' => 'foo', 'body' => 'bar']) {
                        return false;
                    }

                    if ($body['message']['data'] !== ['hello' => 'world']) {
                        return false;
                    }

                    return true;
                })
                ->andReturnSelf()
                ->once();

            $mock
                ->shouldReceive('authToken')
                ->with('789')
                ->once()
                ->andReturnSelf();

            $mock
                ->shouldReceive('exec')
                ->with('https://fcm.googleapis.com/v1/projects/456/messages:send')
                ->once()
                ->andReturn(new \AnourValar\HttpClient\FakeResponse([], ['name' => 'projects/456/messages/100500']));
        });

        \App::make(FCMAdapter::class)->sendMessage('123', 'foo', 'bar', ['hello' => 'world']);
    }

    /**
     * @return void
     */
    public function test_sendMessage_success_2()
    {
        $this->partialMock(\AnourValar\HttpClient\Http::class, function ($mock) {
            $mock
                ->shouldReceive('body')
                ->withArgs(function ($body) {
                    unset($body['assertion']);
                    return $body == [
                        'type' => 'service_account',
                        'client_email' => 'k1@example.org',
                        'private_key' => $this->testKey(),
                        'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                        'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                    ];
                })
                ->andReturnSelf()
                ->once();

            $mock
                ->shouldReceive('exec')
                ->with('https://oauth2.googleapis.com/token')
                ->once()
                ->andReturn(new \AnourValar\HttpClient\FakeResponse([], ['access_token' => '789']));


            $mock
                ->shouldReceive('body')
                ->withArgs(function ($body) {
                    if ($body['message']['token'] != '123') {
                        return false;
                    }

                    if ($body['message']['notification'] != ['title' => 'foo', 'body' => 'bar']) {
                        return false;
                    }

                    if ($body['message']['data'] !== null) {
                        return false;
                    }

                    return true;
                })
                ->andReturnSelf()
                ->once();

            $mock
                ->shouldReceive('authToken')
                ->with('789')
                ->once()
                ->andReturnSelf();

            $mock
                ->shouldReceive('exec')
                ->with('https://fcm.googleapis.com/v1/projects/456/messages:send')
                ->once()
                ->andReturn(new \AnourValar\HttpClient\FakeResponse([], ['name' => 'projects/456/messages/100500']));
        });

        \App::make(FCMAdapter::class)->sendMessage('123', 'foo', 'bar');
    }

    /**
     * @return void
     */
    public function test_sendMessage_failure_1()
    {
        \Cache::memo()->put(implode(' / ', [FCMAdapter::class .'::' . 'obtainAccessToken', 'k1@example.org', '456']), '789', 100);

        $this->partialMock(\AnourValar\HttpClient\Http::class, function ($mock) {
            $mock
                ->shouldReceive('body')
                ->withArgs(function ($body) {
                    if ($body['message']['token'] != '123') {
                        return false;
                    }

                    if ($body['message']['notification'] != ['title' => 'foo', 'body' => 'bar']) {
                        return false;
                    }

                    if ($body['message']['data'] !== ['hello' => 'world']) {
                        return false;
                    }

                    return true;
                })
                ->andReturnSelf()
                ->once();

            $mock
                ->shouldReceive('authToken')
                ->with('789')
                ->once()
                ->andReturnSelf();

            $mock
                ->shouldReceive('exec')
                ->with('https://fcm.googleapis.com/v1/projects/456/messages:send')
                ->once()
                ->andReturn(new \AnourValar\HttpClient\FakeResponse([], ['code' => '401'], 401));
        });

        $this->expectException(\AnourValar\EloquentNotification\Exceptions\ExternalException::class);
        \App::make(FCMAdapter::class)->sendMessage('123', 'foo', 'bar', ['hello' => 'world']);
    }

    /**
     * @return void
     */
    public function test_sendMessage_failure_2()
    {
        $this->partialMock(\AnourValar\HttpClient\Http::class, function ($mock) {
            $mock
                ->shouldReceive('body')
                ->withArgs(function ($body) {
                    unset($body['assertion']);
                    return $body == [
                        'type' => 'service_account',
                        'client_email' => 'k1@example.org',
                        'private_key' => $this->testKey(),
                        'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                        'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                    ];
                })
                ->andReturnSelf()
                ->once();

            $mock
                ->shouldReceive('exec')
                ->with('https://oauth2.googleapis.com/token')
                ->once()
                ->andReturn(new \AnourValar\HttpClient\FakeResponse([], ['code' => '401'], 401));
        });

        $this->expectException(\AnourValar\EloquentNotification\Exceptions\ExternalException::class);
        \App::make(FCMAdapter::class)->sendMessage('123', 'foo', 'bar');
    }

    /**
     * @return string
     */
    private function testKey(): string
    {
        static $key;
        if (! $key) {
            $res = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
            openssl_pkey_export($res, $key);
        }

        return $key;
    }
}
