<?php

return [
    'paths' => ['oauth/token', 'oauth/jwks'],
    'allowed_methods' => ['GET', 'POST'],
    'allowed_origins' => ['*'],
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['Accept', 'Content-Type', 'Origin'],
    'exposed_headers' => [],
    'max_age' => 600,
    'supports_credentials' => false,
];
