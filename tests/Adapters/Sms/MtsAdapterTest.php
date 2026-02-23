<?php

namespace AnourValar\EloquentNotification\Tests\Adapters\Sms;

use Tests\TestCase;
use AnourValar\EloquentNotification\Adapters\Sms\MtsAdapter;
use AnourValar\EloquentNotification\Tests\AbstractSuite;
use AnourValar\EloquentNotification\Adapters\Sms\SmsInterface;

class MtsAdapterTest extends AbstractSuite
{
    /**
     * @return void
     */
    public function test_sendMessage_error()
    {
        $this->partialMock(\AnourValar\HttpClient\Http::class, function ($mock) {
            $mock
                ->shouldReceive('exec')
                ->once()
                ->andReturn(new \AnourValar\HttpClient\FakeResponse([], ['ok' => false]));
        });

        $this->expectException(\AnourValar\EloquentNotification\Exceptions\ExternalException::class);
        \App::make(MtsAdapter::class)->sendMessage('79001234567', 'Hello, World!');
    }

    /**
     * @return void
     */
    public function test_sendMessage()
    {
        config(['eloquent_notification.bindings.' . SmsInterface::class => [
            'mts_token' => 'foo',
            'mts_sender' => 'bar',
        ]]);

        $this->partialMock(\AnourValar\HttpClient\Http::class, function ($mock) {
            $mock
                ->shouldReceive('body')
                ->withArgs(function ($body) {
                    if ($body['number'] != 'bar') {
                        return false;
                    }

                    if ($body['destination'] != '79001234567') {
                        return false;
                    }

                    if ($body['text'] != 'Hello, World!') {
                        return false;
                    }

                    return true;
                })
                ->andReturnSelf()
                ->once();

            $mock
                ->shouldReceive('exec')
                ->with('https://api.exolve.ru/messaging/v1/SendSMS')
                ->once()
                ->andReturn(new \AnourValar\HttpClient\FakeResponse([], ['message_id' => 575976245313935471]));
        });

        \App::make(MtsAdapter::class)->sendMessage('79001234567', 'Hello, World!');
    }
}
