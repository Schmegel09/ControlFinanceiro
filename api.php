<?php

declare(strict_types=1);

session_start();

require_once __DIR__ . '/config/conexao.php';

if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['erro' => 'Não autenticado']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

$path = $_GET['path'] ?? '';
$metodo = $_SERVER['REQUEST_METHOD'];
$usuarioId = (int) $_SESSION['usuario_id'];

$partes = explode('/', trim($path, '/'));

if (count($partes) < 2) {
    http_response_code(400);
    echo json_encode(['erro' => 'Requisição inválida']);
    exit;
}

$recurso = $partes[0];
$acao = $partes[1];

if ($recurso === 'transacao' && is_numeric($acao) && $metodo === 'GET') {
    $id = (int) $acao;

    $stmt = $pdo->prepare(
        'SELECT id, tipo, descricao, valor, data, categoria_id, numero_parcela, total_parcelas
         FROM transacoes
         WHERE id = :id AND usuario_id = :usuario_id'
    );
    $stmt->execute([':id' => $id, ':usuario_id' => $usuarioId]);
    $transacao = $stmt->fetch();

    if (!$transacao) {
        http_response_code(404);
        echo json_encode(['erro' => 'Transação não encontrada']);
        exit;
    }

    echo json_encode($transacao);
    exit;
}

http_response_code(404);
echo json_encode(['erro' => 'Recurso não encontrado']);
