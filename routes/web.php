<?php

declare(strict_types=1);
/*
Legenda de `routes/web.php`:
- Aqui você define as rotas amigáveis e o arquivo correspondente.
- Formato: 'rota' => ['arquivo' => 'pages/exemplo.php', 'protegida' => true|false]
- Para adicionar uma nova página `/minha-rota`, crie `pages/minha_rota.php` e adicione a chave 'minha-rota' mapeando para o arquivo.

Exemplo:
    'relatorios' => [ 'arquivo' => 'pages/relatorios.php', 'protegida' => true ],

*/

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
    'relatorios' => [
        'arquivo' => 'pages/relatorios.php',
        'protegida' => true,
    ],
    'logout' => [
        'arquivo' => 'pages/logout.php',
        'protegida' => true,
    ],
    'categorias' => [
        'arquivo' => 'pages/categorias.php',
        'protegida' => true,
    ],
    'movimentacoes' => [
        'arquivo' => 'pages/movimentacoes.php',
        'protegida' => true,
    ],
];