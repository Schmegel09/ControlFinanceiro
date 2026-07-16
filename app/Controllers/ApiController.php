<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/Core/proteger.php';
require_once dirname(__DIR__, 2) . '/config/conexao.php';
require_once dirname(__DIR__) . '/Models/TransacaoModel.php';
require_once dirname(__DIR__) . '/Services/CarteiraService.php';

header('Content-Type: application/json; charset=utf-8');

$path = is_string($_GET['path'] ?? null) ? $_GET['path'] : '';
$metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$partes = explode('/', trim($path, '/'));

if (count($partes) < 2) {
    http_response_code(400);
    echo json_encode(['erro' => 'Requisição inválida']);
    return;
}

[$recurso, $acao] = $partes;

if ($recurso === 'presenca' && $acao === 'status' && $metodo === 'GET') {
    registrarPresencaCarteira($pdo, (int) $_SESSION['usuario_id'], $carteiraId);
    $membros = listarMembrosCarteira($pdo, $carteiraId);

    echo json_encode([
        'tipo_carteira' => $carteiraAtual['tipo'],
        'membros' => array_map(
            static fn (array $membro): array => [
                'id' => (int) $membro['id'],
                'nome' => $membro['nome'],
                'online' => (int) $membro['online'] === 1,
            ],
            $membros
        ),
    ], JSON_INVALID_UTF8_SUBSTITUTE);
    return;
}

if ($recurso === 'transacao' && ctype_digit($acao) && $metodo === 'GET') {
    $transacao = buscarTransacaoCarteira($pdo, $carteiraId, (int) $acao);

    if (!$transacao) {
        http_response_code(404);
        echo json_encode(['erro' => 'Transação não encontrada']);
        return;
    }

    echo json_encode($transacao, JSON_INVALID_UTF8_SUBSTITUTE);
    return;
}

http_response_code(404);
echo json_encode(['erro' => 'Recurso não encontrado']);
