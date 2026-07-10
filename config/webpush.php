<?php

return [
    'public_key'  => env('VAPID_PUBLIC_KEY'),
    'private_key' => env('VAPID_PRIVATE_KEY'),
    'subject'     => env('VAPID_SUBJECT', env('APP_URL')),

    // On Windows/XAMPP OpenSSL needs an explicit config file to generate the
    // ephemeral EC key used when encrypting each push payload.
    'openssl_conf' => env('OPENSSL_CONF'),
];
