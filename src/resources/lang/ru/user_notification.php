<?php

return [
    'attributes' => [
        'id' => 'ID',
        'user_id' => 'Пользователь',
        'trigger' => 'Триггер',
        'channels' => 'Каналы',
            'channels.*' => 'Канал',
        'created_at' => 'Дата создания',
        'updated_at' => 'Дата изменения',
    ],

    'user_id_not_exists' => 'Несуществующий пользователь.',
    'channels_not_exists' => 'Несуществующие каналы.',

    'trigger' => [
        'logged_in' => 'Вход в систему',
    ],

    'channels' => [
        'database' => 'ЛК',
        'mail' => 'E-mail',
        'sms' => 'SMS',
        'telegram' => 'Telegram',
    ],
];
