<?php
// Emails are sent through Brevo (brevo.com). Render's free plan blocks SMTP, so we use
// Brevo's web API instead. If BREVO_API_KEY is empty (e.g. on XAMPP), emails are saved
// as HTML files in storage/mail so you can still open and check them.
return [
    'api_key'    => env('BREVO_API_KEY', ''),
    'from_email' => env('MAIL_FROM_EMAIL', 'no-reply@trueballot.test'),
    'from_name'  => env('MAIL_FROM_NAME', 'TrueBallot'),
];
