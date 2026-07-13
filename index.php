<?php

declare(strict_types=1);

session_start();

$rotas = require __DIR__ . '/routes/web.php';

// Pega a página da query string ou da URL
$pagina = $_GET['page'] ?? null;

if ($pagina === null) {
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $path = trim($path, '/');
    $pagina = basename($path, '.php') ?: 'login';
}

$pagina = basename($pagina, '.php');

if (!isset($rotas[$pagina])) {
    http_response_code(404);
    require __DIR__ . '/pages/404.php';
    exit;
}

$rota = $rotas[$pagina];

if (
    $rota['protegida'] === true
    && !isset($_SESSION['usuario_id'])
) {
    header('Location: index.php?page=login');
    exit;
}

require __DIR__ . '/' . $rota['arquivo'];
