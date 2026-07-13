<?php

declare(strict_types=1);

return [
    'login' => [
        'arquivo' => 'pages/login.php',
        'protegida' => false,
    ],
    'cadastro' => [
        'arquivo' => 'pages/cadastro.php',
        'protegida' => false,
    ],
    'recuperar-senha' => [
        'arquivo' => 'pages/recuperar_senha.php',
        'protegida' => false,
    ],
    'dashboard' => [
        'arquivo' => 'pages/dashboard.php',
        'protegida' => true,
    ],
    'logout' => [
        'arquivo' => 'pages/logout.php',
        'protegida' => true,
    ],
];