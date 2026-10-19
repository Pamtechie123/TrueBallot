<?php
return [
    'name'     => 'TrueBallot',

    // Full address of the site, used for links inside emails.
    // Local: http://localhost/TrueBallot   Live: https://your-app.onrender.com
    'url'      => rtrim(env('APP_URL', 'http://localhost/TrueBallot'), '/'),

    // Show technical details on error pages. Keep this false on the live site.
    'debug'    => env('APP_DEBUG', 'false') === 'true',

    // Election start and end times are read and shown in this time zone (Nigeria).
    'timezone' => 'Africa/Lagos',
];
