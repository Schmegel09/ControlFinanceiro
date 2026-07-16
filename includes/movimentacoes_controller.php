<?php

declare(strict_types=1);

if (!isset($pdo)) {
    throw new RuntimeException('O objeto $pdo não está disponível em movimentacoes_controller.php.');
}

require_once __DIR__ . '/transacoes_service.php';

$usuarioId = (int) ($_SESSION['usuario_id'] ?? 0);
garantirEstruturaTransacoes($pdo);

$inicioPadrao = date('Y-m-01');
$fimPadrao = date('Y-m-d');
$inicioInformado = trim(is_string($_GET['inicio'] ?? null) ? $_GET['inicio'] : '');
$fimInformado = trim(is_string($_GET['fim'] ?? null) ? $_GET['fim'] : '');
$tipoInformado = is_string($_GET['tipo'] ?? null) ? $_GET['tipo'] : '';

$filtroDataInicio = $inicioInformado === '' ? $inicioPadrao : $inicioInformado;
$filtroDataFim = $fimInformado === '' ? $fimPadrao : $fimInformado;
$filtroTipo = in_array($tipoInformado, ['', 'receita', 'despesa'], true) ? $tipoInformado : '';
$filtrosValidos = dataTransacaoValida($filtroDataInicio)
    && dataTransacaoValida($filtroDataFim)
    && $filtroDataInicio <= $filtroDataFim;

if (!$filtrosValidos) {
    $filtroDataInicio = $inicioPadrao;
    $filtroDataFim = $fimPadrao;
}

$movimentacoesAction = '/movimentacoes?' . http_build_query([
    'inicio' => $filtroDataInicio,
    'fim' => $filtroDataFim,
    'tipo' => $filtroTipo,
]);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $acao = is_string($_POST['acao'] ?? null) ? $_POST['acao'] : '';

    if (!tokenCsrfTransacoesValido($_POST['csrf_token'] ?? null)) {
        $resultado = ['sucesso' => false, 'mensagem' => 'Sua sessão expirou. Atualize a página e tente novamente.'];
    } elseif ($acao === 'adicionar_transacao') {
        $resultado = criarTransacao($pdo, $usuarioId, $_POST);
    } elseif ($acao === 'editar') {
        $resultado = editarTransacao($pdo, $usuarioId, $_POST);
    } elseif ($acao === 'deletar') {
        $resultado = excluirTransacao($pdo, $usuarioId, $_POST['id'] ?? null);
    } elseif ($acao === 'deletar_todas') {
        $resultado = excluirTodasTransacoes($pdo, $usuarioId);
    } else {
        $resultado = ['sucesso' => false, 'mensagem' => 'Ação de movimentação inválida.'];
    }

    definirFlashTransacoes($resultado);
    header('Location: ' . $movimentacoesAction, true, 303);
    exit;
}

$flash = consumirFlashTransacoes();
$mensagem = $flash['mensagem'];
$tipoMensagem = $flash['tipo'];
$csrfToken = tokenCsrfTransacoes();

if (!$filtrosValidos && $mensagem === '') {
    $mensagem = 'Informe um período válido, com a data inicial anterior ou igual à data final.';
    $tipoMensagem = 'erro';
}

$query = "SELECT
              t.id,
              t.categoria_id,
              t.tipo,
              COALESCE(c.nome, NULLIF(t.categoria, ''), 'Sem categoria') AS categoria,
              COALESCE(c.cor, '#999999') AS cor,
              t.descricao,
              t.valor,
              t.data,
              t.numero_parcela,
              t.total_parcelas
          FROM transacoes t
          LEFT JOIN categorias c
            ON c.id = t.categoria_id AND c.usuario_id = t.usuario_id
          WHERE t.usuario_id = :usuario_id
            AND t.data BETWEEN :data_inicio AND :data_fim";

$params = [
    ':usuario_id' => $usuarioId,
    ':data_inicio' => $filtroDataInicio,
    ':data_fim' => $filtroDataFim,
];

if ($filtroTipo !== '') {
    $query .= ' AND t.tipo = :tipo';
    $params[':tipo'] = $filtroTipo;
}

$query .= ' ORDER BY t.data DESC, t.id DESC';

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$transacoes = $stmt->fetchAll();

$stmtCategorias = $pdo->prepare(
    'SELECT id, nome, tipo, cor
     FROM categorias
     WHERE usuario_id = :usuario_id
     ORDER BY tipo, nome'
);
$stmtCategorias->execute([':usuario_id' => $usuarioId]);
$categorias = $stmtCategorias->fetchAll();

$stmtTotais = $pdo->prepare(
    "SELECT
         SUM(CASE WHEN tipo = 'receita' THEN valor ELSE 0 END) AS total_receitas,
         SUM(CASE WHEN tipo = 'despesa' THEN valor ELSE 0 END) AS total_despesas
     FROM transacoes
     WHERE usuario_id = :usuario_id
       AND data BETWEEN :data_inicio AND :data_fim"
);
$stmtTotais->execute([
    ':usuario_id' => $usuarioId,
    ':data_inicio' => $filtroDataInicio,
    ':data_fim' => $filtroDataFim,
]);
$totais = $stmtTotais->fetch();

$totalReceitas = (float) ($totais['total_receitas'] ?? 0);
$totalDespesas = (float) ($totais['total_despesas'] ?? 0);
$saldo = $totalReceitas - $totalDespesas;

$stmtTotalTransacoesUsuario = $pdo->prepare(
    'SELECT COUNT(*) FROM transacoes WHERE usuario_id = :usuario_id'
);
$stmtTotalTransacoesUsuario->execute([':usuario_id' => $usuarioId]);
$totalTransacoesUsuario = (int) $stmtTotalTransacoesUsuario->fetchColumn();

$jsonFlags = JSON_HEX_TAG
    | JSON_HEX_APOS
    | JSON_HEX_QUOT
    | JSON_HEX_AMP
    | JSON_INVALID_UTF8_SUBSTITUTE;
$categoriasJson = json_encode($categorias, $jsonFlags) ?: '[]';
$transacoesJson = json_encode($transacoes, $jsonFlags) ?: '[]';
