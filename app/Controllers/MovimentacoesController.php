<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/Core/proteger.php';
require_once dirname(__DIR__, 2) . '/config/conexao.php';

if (!isset($pdo)) {
    throw new RuntimeException('O objeto $pdo não está disponível no MovimentacoesController.');
}

require_once dirname(__DIR__) . '/Services/TransacaoService.php';
require_once dirname(__DIR__) . '/Services/ImportacaoCsvService.php';
require_once dirname(__DIR__) . '/Models/TransacaoModel.php';

$usuarioId = (int) ($_SESSION['usuario_id'] ?? 0);
garantirEstruturaTransacoes($pdo);

if (
    ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET'
    && ($_GET['download'] ?? '') === 'modelo_csv'
) {
    $modeloCsv = gerarModeloImportacaoCsv();
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="modelo_movimentacoes.csv"');
    header('Content-Length: ' . strlen($modeloCsv));
    echo $modeloCsv;
    exit;
}

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
    $destinoAposPost = $movimentacoesAction;

    if (!tokenCsrfTransacoesValido($_POST['csrf_token'] ?? null)) {
        $resultado = ['sucesso' => false, 'mensagem' => 'Sua sessão expirou. Atualize a página e tente novamente.'];
    } elseif ($acao === 'adicionar_transacao') {
        $resultado = criarTransacao($pdo, $usuarioId, $carteiraId, $_POST);
    } elseif ($acao === 'importar_csv') {
        $arquivoCsv = is_array($_FILES['arquivo_csv'] ?? null) ? $_FILES['arquivo_csv'] : [];
        $resultado = importarMovimentacoesCsv($pdo, $usuarioId, $carteiraId, $arquivoCsv);

        if (
            $resultado['sucesso']
            && isset($resultado['data_inicio'], $resultado['data_fim'])
        ) {
            $destinoAposPost = '/movimentacoes?' . http_build_query([
                'inicio' => $resultado['data_inicio'],
                'fim' => $resultado['data_fim'],
                'tipo' => '',
            ]);
        }
    } elseif ($acao === 'editar') {
        $resultado = editarTransacao($pdo, $carteiraId, $_POST);
    } elseif ($acao === 'deletar') {
        $resultado = excluirTransacao($pdo, $carteiraId, $_POST['id'] ?? null);
    } elseif ($acao === 'deletar_todas') {
        $resultado = excluirTodasTransacoes($pdo, $carteiraId);
    } else {
        $resultado = ['sucesso' => false, 'mensagem' => 'Ação de movimentação inválida.'];
    }

    definirFlashTransacoes($resultado);
    header('Location: ' . $destinoAposPost, true, 303);
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

$transacoes = listarTransacoesPeriodo(
    $pdo,
    $carteiraId,
    $filtroDataInicio,
    $filtroDataFim,
    $filtroTipo
);
$categorias = listarCategoriasCarteira($pdo, $carteiraId);
$totais = buscarTotaisTransacoes($pdo, $carteiraId, $filtroDataInicio, $filtroDataFim);
$totalReceitas = $totais['receitas'];
$totalDespesas = $totais['despesas'];
$saldo = $totais['saldo'];
$totalTransacoesCarteira = contarTransacoesCarteira($pdo, $carteiraId);

$jsonFlags = JSON_HEX_TAG
    | JSON_HEX_APOS
    | JSON_HEX_QUOT
    | JSON_HEX_AMP
    | JSON_INVALID_UTF8_SUBSTITUTE;
$categoriasJson = json_encode($categorias, $jsonFlags) ?: '[]';
$transacoesJson = json_encode($transacoes, $jsonFlags) ?: '[]';

require dirname(__DIR__) . '/Views/movimentacoes/index.php';
