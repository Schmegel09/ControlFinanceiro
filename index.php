<?php

declare(strict_types=1);

session_start();

/*
Legenda de alterações:
- 2026-07-14: Adicionada constante `APP_INIT` para proteger includes.
- Rotas "pretty" ativadas: URLs como `/login` são reescritas para `index.php?page=login` via `.htaccess`.
- Mapa de rotas está em `routes/web.php` — edite esse arquivo para adicionar/alterar páginas.

O que alterar se precisar modificar comportamento:
- Arquivos de rota: `routes/web.php` (mapeamento `rota => arquivo`).
- Reescrita de URL: `.htaccess` (regra RewriteRule);
- Proteção de includes: `includes/proteger.php` (verifica `APP_INIT`).
*/
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
    header('Location: /login');
    exit;
}

require __DIR__ . '/' . $rota['arquivo'];
