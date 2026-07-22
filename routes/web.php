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
    'verificar-email' => [
        'controller' => 'app/Controllers/VerificarEmailController.php',
        'protegida' => false,
        'somente_visitante' => true,
    ],
    'dashboard' => [
        'controller' => 'app/Controllers/DashboardController.php',
        'protegida' => true,
        'tela_cliente' => 'dashboard',
    ],
    'relatorios' => [
        'controller' => 'app/Controllers/RelatoriosController.php',
        'protegida' => true,
        'tela_cliente' => 'relatorios',
    ],
    'logout' => [
        'controller' => 'app/Controllers/LogoutController.php',
        'protegida' => true,
    ],
    'categorias' => [
        'controller' => 'app/Controllers/CategoriasController.php',
        'protegida' => true,
        'tela_cliente' => 'categorias',
    ],
    'movimentacoes' => [
        'controller' => 'app/Controllers/MovimentacoesController.php',
        'protegida' => true,
        'tela_cliente' => 'movimentacoes',
    ],
    'carteiras' => [
        'controller' => 'app/Controllers/CarteirasController.php',
        'protegida' => true,
        'tela_cliente' => 'carteiras',
    ],
    'assinatura-bloqueada' => [
        'controller' => 'app/Controllers/AssinaturaBloqueadaController.php',
        'protegida' => true,
    ],
    'admin-clientes' => [
        'controller' => 'app/Controllers/AdminClientesController.php',
        'protegida' => true,
        'somente_superadmin' => true,
    ],
];
