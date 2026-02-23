<?php

namespace AnourValar\EloquentNotification\Tests\Adapters\Sms;

use Tests\TestCase;
use AnourValar\EloquentNotification\Adapters\Sms\SmscAdapter;
use AnourValar\EloquentNotification\Tests\AbstractSuite;
use AnourValar\EloquentNotification\Adapters\Sms\SmsInterface;

class SmscAdapterTest extends AbstractSuite
{
    /**
     * @return void
     */
    public function test_sendMessage()
    {
        config(['eloquent_notification.bindings.' . SmsInterface::class => [
            'smsc_api_key' => 'foo',
            'smsc_sender' => 'bar',
        ]]);

        $this->partialMock(\AnourValar\HttpClient\Http::class, function ($mock) {
            $mock
                ->shouldReceive('body')
                ->withArgs(function ($body) {
                    if ($body['apikey'] != 'foo') {
                        return false;
                    }

                    if ($body['phones'] != '79001234567') {
                        return false;
                    }

                    if ($body['mes'] != 'Hello, World!') {
                        return false;
                    }

                    if ($body['sender'] != 'bar') {
                        return false;
                    }

                    if ($body['fmt'] != '3') {
                        return false;
                    }

                    return true;
                })
                ->andReturnSelf()
                ->once();

            $mock
                ->shouldReceive('exec')
                ->with('https://smsc.ru/rest/send/')
                ->once()
                ->andReturn(new \AnourValar\HttpClient\FakeResponse([], ['id' => 123]));
        });

        \App::make(SmscAdapter::class)->sendMessage('79001234567', 'Hello, World!');
    }

    /**
     * @return void
     */
    public function test_sendMessage_error()
    {
        $this->partialMock(\AnourValar\HttpClient\Http::class, function ($mock) {
            $mock
                ->shouldReceive('exec')
                ->once()
                ->andReturn(new \AnourValar\HttpClient\FakeResponse([], ['error' => 'duplicate request']));
        });

        $this->expectException(\AnourValar\EloquentNotification\Exceptions\ExternalException::class);
        \App::make(SmscAdapter::class)->sendMessage('79001234567', 'Hello, World!');
    }

    /**
     * @return void
     */
    public function test_sendMessage_incorrect()
    {
        $this->partialMock(\AnourValar\HttpClient\Http::class, function ($mock) {
            $mock->shouldReceive('exec')->never();
        });

        \App::make(SmscAdapter::class)->sendMessage('74951234567', 'Hello, World!');
    }
}
