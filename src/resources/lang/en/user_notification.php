<?php

return [
    'attributes' => [
        'id' => 'ID',
        'user_id' => 'User',
        'trigger' => 'Trigger',
        'channels' => 'Channels',
            'channels.*' => 'Channel',
        'created_at' => 'Created At',
        'updated_at' => 'Updated At',
    ],

    'user_id_not_exists' => 'Non-existent user.',
    'channels_not_exists' => 'Non-existent channels.',

    'trigger' => [
        'logged_in' => 'Logged in',
    ],

    'channels' => [
        'database' => 'Cabinet',
        'mail' => 'E-mail',
        'sms' => 'SMS',
        'telegram' => 'Telegram',
    ],
];
