<?php

declare(strict_types=1);

require_once __DIR__ . '/proteger.php';

const NOME_COOKIE_SESSAO = 'controle_financeiro_session';
const INTERVALO_RENOVACAO_SESSAO = 900;

function requisicaoHttps(): bool
{
    return isset($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off';
}

function iniciarSessaoSegura(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');

    session_name(NOME_COOKIE_SESSAO);
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => requisicaoHttps(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}

function usuarioAutenticado(): bool
{
    $usuarioId = $_SESSION['usuario_id'] ?? null;

    return (is_int($usuarioId) && $usuarioId > 0)
        || (is_string($usuarioId) && ctype_digit($usuarioId) && (int) $usuarioId > 0);
}

function renovarSessaoSeNecessario(): void
{
    if (!usuarioAutenticado()) {
        return;
    }

    $agora = time();
    $ultimaRenovacao = (int) ($_SESSION['sessao_renovada_em'] ?? 0);

    if ($ultimaRenovacao === 0 || ($agora - $ultimaRenovacao) >= INTERVALO_RENOVACAO_SESSAO) {
        session_regenerate_id(true);
        $_SESSION['sessao_renovada_em'] = $agora;
    }

    $_SESSION['ultima_atividade'] = $agora;
}

function autenticarUsuario(int $usuarioId, string $usuarioNome): void
{
    session_regenerate_id(true);

    $_SESSION['usuario_id'] = $usuarioId;
    $_SESSION['usuario_nome'] = $usuarioNome;
    $_SESSION['autenticado_em'] = time();
    $_SESSION['sessao_renovada_em'] = time();
    $_SESSION['ultima_atividade'] = time();
}

function guardarDestinoAposLogin(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
        return;
    }

    $uri = is_string($_SERVER['REQUEST_URI'] ?? null) ? $_SERVER['REQUEST_URI'] : '';
    $caminhoExtraido = parse_url($uri, PHP_URL_PATH);
    $caminho = is_string($caminhoExtraido) ? $caminhoExtraido : '';
    $destinosIgnorados = ['/login', '/logout', '/cadastro', '/recuperar-senha'];

    if (
        $uri !== ''
        && strlen($uri) <= 2048
        && str_starts_with($uri, '/')
        && !str_starts_with($uri, '//')
        && !in_array($caminho, $destinosIgnorados, true)
    ) {
        $_SESSION['destino_apos_login'] = $uri;
    }
}

function obterDestinoAposLogin(): string
{
    $destino = $_SESSION['destino_apos_login'] ?? null;
    unset($_SESSION['destino_apos_login']);

    if (is_string($destino) && str_starts_with($destino, '/') && !str_starts_with($destino, '//')) {
        return $destino;
    }

    return '/dashboard';
}

function exigirAutenticacao(): void
{
    if (usuarioAutenticado()) {
        renovarSessaoSeNecessario();
        return;
    }

    unset(
        $_SESSION['usuario_id'],
        $_SESSION['usuario_nome'],
        $_SESSION['autenticado_em'],
        $_SESSION['sessao_renovada_em'],
        $_SESSION['ultima_atividade']
    );
    guardarDestinoAposLogin();

    header('Location: /login', true, 302);
    exit;
}

function redirecionarSeAutenticado(): void
{
    if (!usuarioAutenticado()) {
        return;
    }

    renovarSessaoSeNecessario();
    header('Location: /dashboard', true, 302);
    exit;
}

function encerrarSessao(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $parametros = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 42000,
            'path' => $parametros['path'],
            'domain' => $parametros['domain'],
            'secure' => $parametros['secure'],
            'httponly' => $parametros['httponly'],
            'samesite' => $parametros['samesite'] ?? 'Lax',
        ]);
    }

    session_destroy();
}

function enviarCabecalhosSeguranca(bool $conteudoPrivado = false): void
{
    if (headers_sent()) {
        return;
    }

    header_remove('X-Powered-By');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');

    if (requisicaoHttps()) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }

    if ($conteudoPrivado) {
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
    }
}
