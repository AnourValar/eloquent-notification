<?php

namespace AnourValar\EloquentNotification\Tests\Adapters\Telegram;

use Tests\TestCase;
use AnourValar\EloquentNotification\Adapters\Telegram\TelegramAdapter;
use AnourValar\EloquentNotification\Tests\AbstractSuite;
use AnourValar\EloquentNotification\Exceptions\Error;

class TelegramAdapterTest extends AbstractSuite
{
    /**
     * @return void
     */
    public function test_sendMessage_error_1()
    {
        $this->partialMock(\AnourValar\HttpClient\Http::class, function ($mock) {
            $mock
                ->shouldReceive('exec')
                ->once()
                ->andReturn(new \AnourValar\HttpClient\FakeResponse([], ['ok' => false]));
        });

        $this->expectException(\AnourValar\EloquentNotification\Exceptions\ExternalException::class);
        try {
            \App::make(TelegramAdapter::class)->sendMessage('test_user', 'Hello, World!');
        } catch (\AnourValar\EloquentNotification\Exceptions\ExternalException $e) {
            $this->assertSame(Error::ETC, $e->error);
            throw $e;
        }
    }

    /**
     * @return void
     */
    public function test_sendMessage_error_2()
    {
        $this->partialMock(\AnourValar\HttpClient\Http::class, function ($mock) {
            $mock
                ->shouldReceive('exec')
                ->once()
                ->andReturn(new \AnourValar\HttpClient\FakeResponse([], ['ok' => false, 'description' => 'Forbidden: bot was blocked by the user']));
        });

        $this->expectException(\AnourValar\EloquentNotification\Exceptions\ExternalException::class);
        try {
            \App::make(TelegramAdapter::class)->sendMessage('test_user', 'Hello, World!');
        } catch (\AnourValar\EloquentNotification\Exceptions\ExternalException $e) {
            $this->assertSame(Error::USER_BLOCK, $e->error);
            throw $e;
        }
    }

    /**
     * @return void
     */
    public function test_sendMessage_working_hours()
    {
        $this->partialMock(\AnourValar\HttpClient\Http::class, function ($mock) {
            $mock
                ->shouldReceive('body')
                ->withArgs(function ($body) {
                    if ($body['chat_id'] != 'test_user') {
                        return false;
                    }

                    if ($body['text'] != 'Hello, World!') {
                        return false;
                    }

                    if ($body['disable_notification'] != false) {
                        return false;
                    }

                    return true;
                })
                ->andReturnSelf()
                ->once();

            $mock
                ->shouldReceive('exec')
                ->once()
                ->andReturn(new \AnourValar\HttpClient\FakeResponse([], ['ok' => true]));
        });

        \App::make(TelegramAdapter::class)->sendMessage('test_user', 'Hello, World!');
    }

    /**
     * @return void
     */
    public function test_fetchChatId()
    {
        \Date::setTestNow('2025-05-11 14:00:00');

        $this->partialMock(\AnourValar\HttpClient\Http::class, function ($mock) {
            $response = [
                'ok' => true,
                'result' => [
                    [
                        'update_id' => 1,
                        'message' => [
                            'chat' => [
                                'id' => '456',
                                'username' => 'foo',
                            ],
                            'date' => \Date::parse('2025-04-25 14:00:00')->timestamp,
                        ],
                    ],
                    [
                        'update_id' => 2,
                        'message' => [
                            'chat' => [
                                'id' => '456',
                                'username' => 'foo',
                            ],
                            'date' => \Date::parse('2025-04-27 13:00:00')->timestamp,
                        ],
                    ],
                    [
                        'update_id' => 4,
                        'message' => [
                            'chat' => [
                                'id' => '123',
                                'username' => 'TeSt_user',
                            ],
                            'date' => \Date::parse('2025-04-27 14:00:00')->timestamp,
                        ],
                    ],
                    [
                        'update_id' => 5,
                        'message' => [
                            'chat' => [
                                'id' => '123',
                                'username' => 'TeSt_user',
                            ],
                            'date' => \Date::parse('2025-05-11 14:00:00')->timestamp,
                        ],
                    ],
                    [
                        'update_id' => 6,
                        'edited_message' => [
                            'chat' => [
                                'id' => '123',
                                'username' => 'TeSt_user',
                            ],
                            'date' => \Date::parse('2025-05-11 14:00:00')->timestamp,
                        ],
                    ],
                ],
            ];
            $mock
                ->shouldReceive('exec')
                ->once()
                ->andReturn(new \AnourValar\HttpClient\FakeResponse([], $response));

            $response = [
                'ok' => true,
                'result' => [
                    [
                        'update_id' => 4,
                        'message' => [
                            'chat' => [
                                'id' => '123',
                                'username' => 'TeSt_user',
                            ],
                            'date' => \Date::parse('2025-04-27 14:00:00')->timestamp,
                        ],
                    ],
                    [
                        'update_id' => 5,
                        'message' => [
                            'chat' => [
                                'id' => '123',
                                'username' => 'TeSt_user',
                            ],
                            'date' => \Date::parse('2025-05-11 14:00:00')->timestamp,
                        ],
                    ],
                ],
            ];
            $mock
                ->shouldReceive('exec')
                ->twice()
                ->andReturn(new \AnourValar\HttpClient\FakeResponse([], $response));

            $mock
                ->shouldReceive('body')
                ->once()
                ->withArgs(function ($body) {
                    return $body['offset'] == '3';
                })
                ->andReturnSelf()
                ->once();
        });

        $this->assertEquals('123', \App::make(TelegramAdapter::class)->fetchChatId('test_user'));
        $this->assertEquals('123', \App::make(TelegramAdapter::class)->fetchChatId('test_user'));

        $this->assertNull(\App::make(TelegramAdapter::class)->fetchChatId('bar'));
    }

    /**
     * @return void
     */
    public function test_fetchChatId_empty()
    {
        $this->partialMock(\AnourValar\HttpClient\Http::class, function ($mock) {
            $response = [
                'ok' => true,
                'result' => [],
            ];
            $mock
                ->shouldReceive('exec')
                ->once()
                ->andReturn(new \AnourValar\HttpClient\FakeResponse([], $response));
        });

        $this->assertNull(\App::make(TelegramAdapter::class)->fetchChatId('test_user'));
    }
}
