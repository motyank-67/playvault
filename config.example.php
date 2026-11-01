<?php
return [
    'host' => '127.0.0.1',
    'port' => 5433,
    'database' => 'playvault',
    'user' => getenv('USER') ?: 'postgres',
    'password' => '',
];
