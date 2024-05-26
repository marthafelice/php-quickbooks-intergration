<?php

return [
    'db' => [
        'driver' => 'mysql',
        'host' => getenv('DB_HOST'),
        'database' => getenv('DB_DATABASE'),
        'username' => getenv('DB_USERNAME'),
        'password' => getenv('DB_PASSWORD'),
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'prefix' => '',
    ],
    'quickbooks' => [
        'redirect_uri' => getenv('QB_REDIRECT_URI'),
        'client_id' => getenv('QB_CLIENT_ID'),
        'client_secret' => getenv('QB_CLIENT_SECRET'),
        'token_url' => 'https://oauth.platform.intuit.com/oauth2/v1/tokens/bearer',  // Adjust if needed
    ],
];
