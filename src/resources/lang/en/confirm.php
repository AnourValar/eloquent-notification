<?php

return [
    'incorrect' => 'Request error.',
    'miscount' => 'Confirmation of factors required: :qty',
    'expired' => 'The request has expired. Please try again from the beginning.',
    'incorrect_code' => 'Incorrect code.',
    'email_is_empty' => 'E-mail must be filled in.',
    'phone_is_empty' => 'Phone must be filled in.',

    'email_not_exists' => 'The specified email is not registered.',
    'email_already_exists' => 'The specified email is already registered.',
    'too_many' => 'Too many requests. Wait for :seconds s.',
    'phone_not_exists' => 'The specified phone is not registered.',
    'phone_already_exists' => 'The specified phone is already registered.',

    'notification' => [
        'mail' => [
            'subject' => 'Email verification',
            'body_line1' => 'Hello!',
            'body_line2' => 'You are receiving this email because we received a request to verify your email address.',
            'body_code' => 'Code',
            'body_line3' => 'If you did not make this request, simply ignore this email.',
        ],
        'sms' => 'Phone verification code: :code',
    ],
];
