<?php

declare(strict_types=1);

if (!isset($pdo)) {
    throw new RuntimeException('O objeto $pdo não está disponível em dashboard_controller.php.');
}

require_once __DIR__ . '/transacoes_service.php';

$usuarioId = (int) ($_SESSION['usuario_id'] ?? 0);
garantirEstruturaTransacoes($pdo);

$inicioPadrao = new DateTimeImmutable('-30 days');
$fimPadrao = new DateTimeImmutable('today');
$inicioInformado = trim(is_string($_GET['inicio'] ?? null) ? $_GET['inicio'] : '');
$fimInformado = trim(is_string($_GET['fim'] ?? null) ? $_GET['fim'] : '');

$inicioFiltro = $inicioInformado === '' ? $inicioPadrao->format('Y-m-d') : $inicioInformado;
$fimFiltro = $fimInformado === '' ? $fimPadrao->format('Y-m-d') : $fimInformado;
$periodoValido = dataTransacaoValida($inicioFiltro) && dataTransacaoValida($fimFiltro);
$periodoMensagem = '';

if ($periodoValido && $fimFiltro < $inicioFiltro) {
    $periodoValido = false;
    $periodoMensagem = 'A data de início precisa ser anterior ou igual à data de fim.';
} elseif (!$periodoValido) {
    $periodoMensagem = 'Período inválido. Use datas válidas no formato AAAA-MM-DD.';
}

if (!$periodoValido) {
    $inicioFiltro = $inicioPadrao->format('Y-m-d');
    $fimFiltro = $fimPadrao->format('Y-m-d');
}

$dashboardAction = '/dashboard?' . http_build_query([
    'inicio' => $inicioFiltro,
    'fim' => $fimFiltro,
]);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $acao = is_string($_POST['acao'] ?? null) ? $_POST['acao'] : '';

    if (!tokenCsrfTransacoesValido($_POST['csrf_token'] ?? null)) {
        $resultado = ['sucesso' => false, 'mensagem' => 'Sua sessão expirou. Atualize a página e tente novamente.'];
    } elseif ($acao === 'adicionar_transacao') {
        $resultado = criarTransacao($pdo, $usuarioId, $_POST);
    } elseif ($acao === 'editar') {
        $resultado = editarTransacao($pdo, $usuarioId, $_POST);
    } else {
        $resultado = ['sucesso' => false, 'mensagem' => 'Ação de movimentação inválida.'];
    }

    definirFlashTransacoes($resultado);
    header('Location: ' . $dashboardAction, true, 303);
    exit;
}

$flash = consumirFlashTransacoes();
$mensagem = $flash['mensagem'];
$tipoMensagem = $flash['tipo'];
$csrfToken = tokenCsrfTransacoes();

$whereClausula = 'WHERE t.usuario_id = :usuario_id AND t.data BETWEEN :inicio AND :fim';
$parametros = [
    ':usuario_id' => $usuarioId,
    ':inicio' => $inicioFiltro,
    ':fim' => $fimFiltro,
];

$totais = $pdo->prepare(
    "SELECT
         SUM(CASE WHEN t.tipo = 'receita' THEN t.valor ELSE 0 END) AS total_receitas,
         SUM(CASE WHEN t.tipo = 'despesa' THEN t.valor ELSE 0 END) AS total_despesas
     FROM transacoes t
     " . $whereClausula
);
$totais->execute($parametros);
$totaisDados = $totais->fetch();

$totalReceitas = (float) ($totaisDados['total_receitas'] ?? 0);
$totalDespesas = (float) ($totaisDados['total_despesas'] ?? 0);
$saldo = $totalReceitas - $totalDespesas;

$ultimasTransacoes = $pdo->prepare(
    "SELECT
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
     " . $whereClausula . "
     ORDER BY t.data DESC, t.id DESC
     LIMIT 10"
);
$ultimasTransacoes->execute($parametros);
$transacoes = $ultimasTransacoes->fetchAll();

$stmtCategorias = $pdo->prepare(
    'SELECT id, nome, tipo, cor
     FROM categorias
     WHERE usuario_id = :usuario_id
     ORDER BY tipo, nome'
);
$stmtCategorias->execute([':usuario_id' => $usuarioId]);
$categorias = $stmtCategorias->fetchAll();

$jsonFlags = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP;
$categoriasJson = json_encode($categorias, $jsonFlags) ?: '[]';
$transacoesJson = json_encode($transacoes, $jsonFlags) ?: '[]';
