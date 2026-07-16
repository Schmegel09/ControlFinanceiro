<?php

declare(strict_types=1);

define('APP_INIT', true);

require_once __DIR__ . '/app/Core/auth.php';

iniciarSessaoSegura();
enviarCabecalhosSeguranca();

/*
Legenda de alterações:
- A constante `APP_INIT` protege os arquivos internos de execução direta.
- Rotas "pretty" ativadas: URLs como `/login` são reescritas para `index.php?page=login` via `.htaccess`.
- Mapa de rotas está em `routes/web.php` e aponta para controladores em `app/Controllers`.

O que alterar se precisar modificar comportamento:
- Arquivos de rota: `routes/web.php` (mapeamento `rota => controller`).
- Reescrita de URL: `.htaccess` (regra RewriteRule);
- Proteção do código interno: `app/Core/proteger.php` (verifica `APP_INIT`).
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
    require __DIR__ . '/app/Views/errors/404.php';
    exit;
}

$rota = $rotas[$pagina];

if (
    $rota['protegida'] === true
    && !usuarioAutenticado()
) {
    exigirAutenticacao();
}

if ($rota['protegida'] === true) {
    renovarSessaoSeNecessario();
    enviarCabecalhosSeguranca(true);

    if ($pagina !== 'logout') {
        require_once __DIR__ . '/config/conexao.php';
        require_once __DIR__ . '/app/Services/TransacaoService.php';
        require_once __DIR__ . '/app/Services/CarteiraService.php';

        garantirEstruturaTransacoes($pdo);
        $contextoCarteira = prepararContextoCarteira($pdo, (int) $_SESSION['usuario_id']);
        $carteiraAtual = $contextoCarteira['carteira'];
        $carteiraId = (int) $carteiraAtual['id'];
        $carteirasDisponiveis = $contextoCarteira['carteiras'];
        $membrosCarteiraAtual = $contextoCarteira['membros'];
        $csrfTokenCarteiras = tokenCsrfCarteiras();
        $uriAtual = is_string($_SERVER['REQUEST_URI'] ?? null) ? $_SERVER['REQUEST_URI'] : '/dashboard';
        $urlRetornoCarteira = str_starts_with($uriAtual, '/') && !str_starts_with($uriAtual, '//')
            ? $uriAtual
            : '/dashboard';
    }
}

if (($rota['somente_visitante'] ?? false) === true) {
    redirecionarSeAutenticado();
}

require __DIR__ . '/' . $rota['controller'];
