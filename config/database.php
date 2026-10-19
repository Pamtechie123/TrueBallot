<?php
// Local (XAMPP): the defaults below are used.
// Live (Render + Aiven): set these as environment variables on Render.
return [
    'host'     => env('DB_HOST', '127.0.0.1'),
    'port'     => (int) env('DB_PORT', '3306'),
    'database' => env('DB_NAME', 'trueballot'),
    'username' => env('DB_USER', 'root'),
    'password' => env('DB_PASS', ''),
    'charset'  => 'utf8mb4',

    // Aiven only accepts secure connections. Put the CA certificate from the Aiven console
    // in config/ca.pem (or set DB_SSL_CA to its path). Leave it out locally.
    'ssl_ca'   => env('DB_SSL_CA', BASE_PATH . '/config/ca.pem'),
];
