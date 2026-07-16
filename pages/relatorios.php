<?php

declare(strict_types=1);

session_start();

require_once dirname(__DIR__) . '/config/conexao.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: /login');
    exit;
}

$usuarioId = (int) $_SESSION['usuario_id'];

$inicio = trim($_GET['inicio'] ?? '');
$fim = trim($_GET['fim'] ?? '');

try {
    if ($inicio === '') {
        $inicioData = new DateTimeImmutable('-30 days');
    } else {
        $inicioData = new DateTimeImmutable($inicio);
    }

    if ($fim === '') {
        $fimData = new DateTimeImmutable('now');
    } else {
        $fimData = new DateTimeImmutable($fim);
    }

    if ($fimData < $inicioData) {
        throw new RuntimeException('A data de início deve ser anterior ou igual à data de fim.');
    }
} catch (Throwable $e) {
    $inicioData = new DateTimeImmutable('-30 days');
    $fimData = new DateTimeImmutable('now');
    $erroPeriodo = $e->getMessage();
}

$inicioFiltro = $inicioData->format('Y-m-d');
$fimFiltro = $fimData->format('Y-m-d');
$movimentacoesPeriodoUrl = '/movimentacoes?' . http_build_query([
    'inicio' => $inicioFiltro,
    'fim' => $fimFiltro,
]);

$transacoesPorCategoria = $pdo->prepare(
    'SELECT categoria, tipo, SUM(valor) AS total
     FROM transacoes
     WHERE usuario_id = :usuario_id
       AND data BETWEEN :inicio AND :fim
     GROUP BY categoria, tipo
     ORDER BY total DESC'
);
$transacoesPorCategoria->execute([
    ':usuario_id' => $usuarioId,
    ':inicio' => $inicioFiltro,
    ':fim' => $fimFiltro,
]);

$categorias = [];
while ($row = $transacoesPorCategoria->fetch()) {
    $categoria = $row['categoria'];
    if (!isset($categorias[$categoria])) {
        $categorias[$categoria] = ['receita' => 0, 'despesa' => 0];
    }
    $categorias[$categoria][$row['tipo']] = (float) $row['total'];
}

$categoriasOrdenadas = $categorias;
arsort($categoriasOrdenadas);
$categoriasOrdenadas = array_slice($categoriasOrdenadas, 0, 8, true);

$totalCategorias = array_map(function (array $val) {
    return $val['receita'] - $val['despesa'];
}, $categoriasOrdenadas);

?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatórios - Controle Financeiro</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; min-height: 100dvh; padding: clamp(10px, 2vw, 20px); }
        .container { width: 100%; max-width: 1100px; margin: 0 auto; background: white; padding: clamp(20px, 3vw, 32px); border-radius: 18px; box-shadow: 0 18px 50px rgba(0,0,0,0.15); }
        h1 { color: #333; margin-bottom: 8px; font-size: clamp(26px, 4vw, 34px); line-height: 1.2; overflow-wrap: anywhere; }
        .subtitle { color: #666; margin-bottom: 24px; font-size: 16px; }
        .top-row { display: flex; justify-content: space-between; flex-wrap: wrap; gap: 16px; margin-bottom: 28px; }
        .actions { display: flex; gap: 12px; flex-wrap: wrap; }
        a.button { display: inline-flex; align-items: center; justify-content: center; min-height: 44px; padding: 12px 22px; background: #4f5fc9; color: white; text-align: center; text-decoration: none; border-radius: 10px; font-weight: 600; transition: transform 0.2s, box-shadow 0.2s; }
        a.button:hover { transform: translateY(-2px); box-shadow: 0 10px 30px rgba(102, 126, 234, 0.25); }
        .filter-card, .chart-card, .table-card { background: #f7f8ff; border-radius: 18px; padding: 24px; }
        .filter-card h2, .chart-card h2, .table-card h2 { margin-bottom: 18px; color: #333; font-size: 20px; }
        .form { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 14px; align-items: end; }
        .form label { display: flex; min-width: 0; flex-direction: column; gap: 8px; font-size: 14px; color: #555; }
        .form input { padding: 12px 14px; border-radius: 10px; border: 1px solid #d7dbf0; font-size: 15px; }
        button { min-height: 44px; background: #4f5fc9; color: white; border: none; padding: 12px 18px; border-radius: 12px; font-size: 15px; font-weight: 700; cursor: pointer; transition: transform 0.2s; }
        button:hover { transform: translateY(-2px); }
        .chart { margin-top: 20px; }
        .bar-row { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 16px; align-items: center; margin-bottom: 14px; }
        .bar-label { color: #444; font-size: 14px; overflow-wrap: anywhere; }
        .bar-track { background: #e9ecf7; border-radius: 999px; position: relative; height: 16px; overflow: hidden; }
        .bar-fill { height: 100%; border-radius: 999px; }
        .bar-value { min-width: 60px; max-width: 180px; text-align: right; color: #333; font-size: 13px; overflow-wrap: anywhere; }
        .flag { display: inline-block; padding: 4px 10px; border-radius: 999px; font-size: 12px; color: white; }
        .receita { background: #209f71; }
        .despesa { background: #ef5f5f; }
        .table-responsive { overflow-x: auto; }
        .table-card table { width: 100%; min-width: 620px; border-collapse: collapse; }
        .table-card th, .table-card td { text-align: left; padding: 14px 12px; border-bottom: 1px solid #e8ebf8; }
        .table-card th { color: #666; font-size: 14px; text-transform: uppercase; letter-spacing: .02em; }
        .table-card td { color: #333; font-size: 15px; }
        .msg { margin: 0 0 20px; padding: 16px 18px; border-radius: 14px; font-size: 15px; background: #ffe8e8; color: #922d2d; }
        @media (max-width: 768px) {
            .actions { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); width: 100%; }
            .actions a:last-child { grid-column: 1 / -1; }
            .bar-row { grid-template-columns: minmax(0, 1fr); gap: 6px; }
            .bar-value { max-width: none; text-align: left; }
            .table-responsive { overflow: visible; }
            .table-card .summary-table { display: block; min-width: 0; }
            .summary-table thead { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0, 0, 0, 0); white-space: nowrap; border: 0; }
            .summary-table tbody { display: grid; gap: 14px; }
            .summary-table tr { display: block; overflow: hidden; background: white; border: 1px solid #e1e5f4; border-radius: 14px; }
            .summary-table td { display: grid; grid-template-columns: minmax(92px, .8fr) minmax(0, 1.2fr); gap: 12px; width: 100%; padding: 11px 14px; text-align: right; overflow-wrap: anywhere; }
            .summary-table td::before { content: attr(data-label); color: #666; font-size: 12px; font-weight: 700; letter-spacing: .02em; text-align: left; text-transform: uppercase; }
            .summary-table td:last-child { border-bottom: 0; }
            .summary-table .empty-row { border: 0; }
            .summary-table .empty-row td { display: block; text-align: left; }
            .summary-table .empty-row td::before { content: none; }
        }
        @media (max-width: 480px) {
            body { padding: 0; }
            .container { min-height: 100vh; min-height: 100dvh; border-radius: 0; box-shadow: none; }
            .actions { gap: 8px; }
            .actions a.button { padding-inline: 10px; }
            .filter-card, .chart-card, .table-card { padding: 18px; }
            .form { grid-template-columns: 1fr; }
            .form button { width: 100%; }
            .summary-table td { grid-template-columns: 86px minmax(0, 1fr); padding-inline: 12px; }
        }
    </style>
    <?php require dirname(__DIR__) . '/includes/responsive_styles.php'; ?>
</head>
<body>
    <main class="container">
        <h1>Relatórios</h1>
        <p class="subtitle">Filtre por período para ver o desempenho das categorias e um gráfico simples.</p>

        <div class="top-row">
            <nav class="actions" aria-label="Navegação principal">
                <a href="/dashboard" class="button">Voltar ao Dashboard</a>
                <a href="<?= htmlspecialchars($movimentacoesPeriodoUrl, ENT_QUOTES, 'UTF-8') ?>" class="button">Gerenciar lançamentos</a>
                <a href="/logout" class="button logout-btn">Sair</a>
            </nav>
        </div>

        <?php if (!empty($erroPeriodo)): ?>
            <div class="msg" role="alert"><?= htmlspecialchars($erroPeriodo, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <div class="filter-card">
            <h2>Filtrar por período</h2>
            <form class="form" method="get" action="/relatorios">
                <label>
                    <span>Início</span>
                    <input type="date" name="inicio" value="<?= htmlspecialchars($inicioFiltro, ENT_QUOTES, 'UTF-8') ?>">
                </label>
                <label>
                    <span>Fim</span>
                    <input type="date" name="fim" value="<?= htmlspecialchars($fimFiltro, ENT_QUOTES, 'UTF-8') ?>">
                </label>
                <button type="submit">Aplicar filtro</button>
            </form>
        </div>

        <div class="chart-card" style="margin-top: 24px;">
            <h2>Gráfico por categoria</h2>
            <div class="chart">
                <?php if (count($categoriasOrdenadas) === 0): ?>
                    <p>Nenhuma transação encontrada no período selecionado.</p>
                <?php else: ?>
                    <?php
                    $maxValor = max(array_map(function ($val) {
                        return abs($val['receita']) + abs($val['despesa']);
                    }, $categoriasOrdenadas));
                    $maxValor = max($maxValor, 1);
                    ?>
                    <?php foreach ($categoriasOrdenadas as $categoria => $dados): ?>
                        <div class="bar-row">
                            <div>
                                <div class="bar-label"><?= htmlspecialchars($categoria, ENT_QUOTES, 'UTF-8') ?></div>
                                <div class="bar-track">
                                    <?php if ($dados['receita'] > 0): ?>
                                        <div class="bar-fill receita" style="width: <?= round(($dados['receita'] / $maxValor) * 100, 2) ?>%"></div>
                                    <?php endif; ?>
                                    <?php if ($dados['despesa'] > 0): ?>
                                        <div class="bar-fill despesa" style="width: <?= round(($dados['despesa'] / $maxValor) * 100, 2) ?>%; position: absolute; left: 0;"></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="bar-value">R$ <?= number_format($dados['receita'] - $dados['despesa'], 2, ',', '.') ?></div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="table-card" style="margin-top: 24px;">
            <h2>Resumo por categoria</h2>
            <div class="table-responsive" role="region" aria-label="Resumo financeiro por categoria" tabindex="0">
                <table class="summary-table" role="table">
                    <caption class="sr-only">Resumo financeiro por categoria no período selecionado</caption>
                    <thead role="rowgroup">
                        <tr role="row">
                            <th scope="col" role="columnheader">Categoria</th>
                            <th scope="col" role="columnheader">Receita</th>
                            <th scope="col" role="columnheader">Despesa</th>
                            <th scope="col" role="columnheader">Saldo</th>
                        </tr>
                    </thead>
                    <tbody role="rowgroup">
                        <?php if (count($categoriasOrdenadas) === 0): ?>
                            <tr class="empty-row" role="row">
                                <td colspan="4" role="cell" style="padding: 18px; color: #666;">Sem dados no período selecionado.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($categoriasOrdenadas as $categoria => $dados): ?>
                                <tr role="row">
                                    <td data-label="Categoria" role="cell"><?= htmlspecialchars($categoria, ENT_QUOTES, 'UTF-8') ?></td>
                                    <td data-label="Receita" role="cell">R$ <?= number_format($dados['receita'], 2, ',', '.') ?></td>
                                    <td data-label="Despesa" role="cell">R$ <?= number_format($dados['despesa'], 2, ',', '.') ?></td>
                                    <td data-label="Saldo" role="cell">R$ <?= number_format($dados['receita'] - $dados['despesa'], 2, ',', '.') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</body>
</html>
