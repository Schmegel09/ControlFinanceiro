<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/Core/proteger.php';
require_once dirname(__DIR__, 2) . '/config/conexao.php';

/** @var PDO $pdo Conexão preparada pelo front controller. */
/** @var int $carteiraId Carteira validada para o usuário autenticado. */
/** @var array{id: int, nome: string, tipo: string, papel?: string} $carteiraAtual */

if (!isset($pdo, $carteiraId, $carteiraAtual)) {
    throw new RuntimeException('O contexto do Dashboard não foi preparado corretamente.');
}

require_once dirname(__DIR__) . '/Services/TransacaoService.php';
require_once dirname(__DIR__) . '/Models/TransacaoModel.php';

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
    } elseif ($acao === 'editar') {
        $resultado = editarTransacao($pdo, $carteiraId, $_POST);
    } elseif ($acao === 'deletar') {
        $resultado = excluirTransacao($pdo, $carteiraId, $_POST['id'] ?? null);
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

$totais = buscarTotaisTransacoes($pdo, $carteiraId, $inicioFiltro, $fimFiltro);
$totalReceitas = $totais['receitas'];
$totalDespesas = $totais['despesas'];
$saldo = $totais['saldo'];
$transacoes = listarUltimasTransacoes($pdo, $carteiraId, $inicioFiltro, $fimFiltro);
$categorias = listarCategoriasCarteira($pdo, $carteiraId);
$exibirAutorMovimentacao = ($carteiraAtual['tipo'] ?? 'pessoal') === 'casal';
$inicioFiltroExibicao = (new DateTimeImmutable($inicioFiltro))->format('d/m/Y');
$fimFiltroExibicao = (new DateTimeImmutable($fimFiltro))->format('d/m/Y');
$evolucaoFinanceira = buscarEvolucaoFinanceiraPeriodo($pdo, $carteiraId, $inicioFiltro, $fimFiltro);
$despesasPorCategoriaCompletas = buscarDespesasPorCategoria($pdo, $carteiraId, $inicioFiltro, $fimFiltro);
$despesasPorCategoria = array_slice($despesasPorCategoriaCompletas, 0, 5);

if (count($despesasPorCategoriaCompletas) > 5) {
    $totalOutras = array_sum(array_column(array_slice($despesasPorCategoriaCompletas, 5), 'total'));
    $despesasPorCategoria[] = [
        'categoria' => 'Outras',
        'cor' => '#9aa3b7',
        'total' => $totalOutras,
    ];
}
$totalDespesasGrafico = array_sum(array_column($despesasPorCategoria, 'total'));

$jsonFlags = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP;
$categoriasJson = json_encode($categorias, $jsonFlags) ?: '[]';
$transacoesJson = json_encode($transacoes, $jsonFlags) ?: '[]';
$evolucaoFinanceiraJson = json_encode($evolucaoFinanceira, $jsonFlags) ?: '[]';
$despesasPorCategoriaJson = json_encode($despesasPorCategoria, $jsonFlags) ?: '[]';

require dirname(__DIR__) . '/Views/dashboard/index.php';
