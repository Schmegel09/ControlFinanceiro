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
require_once __DIR__ . '/app/Services/TransacaoService.php';
require_once __DIR__ . '/app/Services/CarteiraService.php';

garantirEstruturaTransacoes($pdo);
$contextoCarteira = prepararContextoCarteira($pdo, (int) $_SESSION['usuario_id']);
$carteiraAtual = $contextoCarteira['carteira'];
$carteiraId = (int) $carteiraAtual['id'];

require __DIR__ . '/app/Controllers/ApiController.php';
