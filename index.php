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

    require_once __DIR__ . '/config/conexao.php';
    require_once __DIR__ . '/app/Services/SaasService.php';

    garantirEstruturaSaas($pdo);
    $superAdmin = usuarioSuperAdminAtual($pdo, (int) $_SESSION['usuario_id']);

    if (($rota['somente_superadmin'] ?? false) === true && !$superAdmin) {
        http_response_code(403);
        require __DIR__ . '/app/Views/errors/403.php';
        exit;
    }

    $acessoCliente = $superAdmin
        ? [
            'permitido' => true,
            'status_efetivo' => 'ativo',
            'mensagem' => '',
            'cliente' => buscarClienteDoUsuario($pdo, (int) $_SESSION['usuario_id']) ?: null,
        ]
        : avaliarAcessoCliente($pdo, (int) $_SESSION['usuario_id']);

    $rotasPermitidasSemAssinatura = ['assinatura-bloqueada', 'logout'];

    if (!$acessoCliente['permitido'] && !in_array($pagina, $rotasPermitidasSemAssinatura, true)) {
        header('Location: /assinatura-bloqueada', true, 302);
        exit;
    }

    if ($acessoCliente['permitido'] && $pagina === 'assinatura-bloqueada') {
        header('Location: /dashboard', true, 302);
        exit;
    }

    $permissoesCliente = $superAdmin
        ? array_fill_keys(array_keys(TELAS_CLIENTE), true)
        : obterPermissoesUsuario($pdo, (int) $_SESSION['usuario_id']);
    $telaDaRota = is_string($rota['tela_cliente'] ?? null) ? $rota['tela_cliente'] : null;

    if (
        !$superAdmin
        && $telaDaRota !== null
        && !telaClientePermitida($permissoesCliente, $telaDaRota)
    ) {
        http_response_code(403);
        require __DIR__ . '/app/Views/errors/permissao.php';
        exit;
    }

    if (!in_array($pagina, ['logout', 'assinatura-bloqueada', 'admin-clientes'], true)) {
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
        $avisoAssinatura = $acessoCliente['status_efetivo'] === 'em_atraso'
            ? $acessoCliente['mensagem']
            : '';
    }
}

if (($rota['somente_visitante'] ?? false) === true) {
    redirecionarSeAutenticado();
}

require __DIR__ . '/' . $rota['controller'];
