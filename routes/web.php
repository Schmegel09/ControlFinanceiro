<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/Core/proteger.php';
/*
Legenda de `routes/web.php`:
- Aqui você define as rotas amigáveis e o controlador correspondente.
- Formato: 'rota' => ['controller' => 'app/Controllers/ExemploController.php', ...]
- O controlador prepara os dados e carrega uma view de `app/Views`.

Exemplo:
    'relatorios' => [ 'controller' => 'app/Controllers/RelatoriosController.php', 'protegida' => true ],

*/

return [
    'login' => [
        'controller' => 'app/Controllers/LoginController.php',
        'protegida' => false,
        'somente_visitante' => true,
    ],
    'cadastro' => [
        'controller' => 'app/Controllers/CadastroController.php',
        'protegida' => false,
        'somente_visitante' => true,
    ],
    'recuperar-senha' => [
        'controller' => 'app/Controllers/RecuperarSenhaController.php',
        'protegida' => false,
        'somente_visitante' => true,
    ],
    'dashboard' => [
        'controller' => 'app/Controllers/DashboardController.php',
        'protegida' => true,
    ],
    'relatorios' => [
        'controller' => 'app/Controllers/RelatoriosController.php',
        'protegida' => true,
    ],
    'logout' => [
        'controller' => 'app/Controllers/LogoutController.php',
        'protegida' => true,
    ],
    'categorias' => [
        'controller' => 'app/Controllers/CategoriasController.php',
        'protegida' => true,
    ],
    'movimentacoes' => [
        'controller' => 'app/Controllers/MovimentacoesController.php',
        'protegida' => true,
    ],
    'carteiras' => [
        'controller' => 'app/Controllers/CarteirasController.php',
        'protegida' => true,
    ],
];
