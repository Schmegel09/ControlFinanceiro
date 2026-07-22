<?php

declare(strict_types=1);

define('APP_INIT', true);

require_once __DIR__ . '/app/Core/auth.php';

iniciarSessaoSegura();
enviarCabecalhosSeguranca(true);

if (!usuarioAutenticado()) {
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['erro' => 'Não autenticado']);
    exit;
}

renovarSessaoSeNecessario();

require_once __DIR__ . '/config/conexao.php';
require_once __DIR__ . '/app/Services/SaasService.php';
require_once __DIR__ . '/app/Services/TransacaoService.php';
require_once __DIR__ . '/app/Services/CarteiraService.php';

garantirEstruturaSaas($pdo);
$superAdmin = usuarioSuperAdminAtual($pdo, (int) $_SESSION['usuario_id']);
$acessoCliente = avaliarAcessoCliente($pdo, (int) $_SESSION['usuario_id']);

if (!$superAdmin && !$acessoCliente['permitido']) {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'erro' => 'Acesso bloqueado',
        'status' => $acessoCliente['status_efetivo'],
        'mensagem' => $acessoCliente['mensagem'],
    ]);
    exit;
}

$permissoesCliente = $superAdmin
    ? array_fill_keys(array_keys(TELAS_CLIENTE), true)
    : obterPermissoesUsuario($pdo, (int) $_SESSION['usuario_id']);
$pathApi = is_string($_GET['path'] ?? null) ? trim($_GET['path'], '/') : '';
$apiPermitida = $superAdmin;

if (!$apiPermitida && str_starts_with($pathApi, 'transacao/')) {
    $apiPermitida = telaClientePermitida($permissoesCliente, 'dashboard')
        || telaClientePermitida($permissoesCliente, 'movimentacoes');
} elseif (!$apiPermitida && $pathApi === 'presenca/status') {
    $apiPermitida = in_array(true, $permissoesCliente, true);
}

if (!$apiPermitida) {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'erro' => 'Tela não liberada',
        'mensagem' => 'Este cliente não possui permissão para acessar esse recurso.',
    ]);
    exit;
}

garantirEstruturaTransacoes($pdo);
$contextoCarteira = prepararContextoCarteira($pdo, (int) $_SESSION['usuario_id']);
$carteiraAtual = $contextoCarteira['carteira'];
$carteiraId = (int) $carteiraAtual['id'];

require __DIR__ . '/app/Controllers/ApiController.php';
