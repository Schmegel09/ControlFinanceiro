<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/Core/proteger.php';
require_once dirname(__DIR__, 2) . '/config/conexao.php';
require_once dirname(__DIR__) . '/Models/RelatorioModel.php';

$inicio = trim(is_string($_GET['inicio'] ?? null) ? $_GET['inicio'] : '');
$fim = trim(is_string($_GET['fim'] ?? null) ? $_GET['fim'] : '');
$erroPeriodo = '';

try {
    $inicioData = $inicio === ''
        ? new DateTimeImmutable('-30 days')
        : new DateTimeImmutable($inicio);
    $fimData = $fim === ''
        ? new DateTimeImmutable('now')
        : new DateTimeImmutable($fim);

    if ($fimData < $inicioData) {
        throw new RuntimeException('A data de início deve ser anterior ou igual à data de fim.');
    }
} catch (Throwable $erro) {
    $inicioData = new DateTimeImmutable('-30 days');
    $fimData = new DateTimeImmutable('now');
    $erroPeriodo = $erro->getMessage();
}

$inicioFiltro = $inicioData->format('Y-m-d');
$fimFiltro = $fimData->format('Y-m-d');
$movimentacoesPeriodoUrl = '/movimentacoes?' . http_build_query([
    'inicio' => $inicioFiltro,
    'fim' => $fimFiltro,
]);

$categoriasOrdenadas = buscarResumoCategoriasPeriodo(
    $pdo,
    $carteiraId,
    $inicioFiltro,
    $fimFiltro
);
uasort(
    $categoriasOrdenadas,
    static fn (array $a, array $b): int => ($b['receita'] + $b['despesa']) <=> ($a['receita'] + $a['despesa'])
);
$categoriasOrdenadas = array_slice($categoriasOrdenadas, 0, 8, true);

require dirname(__DIR__) . '/Views/relatorios/index.php';
