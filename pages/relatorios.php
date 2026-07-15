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
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; padding: 20px; }
        .container { max-width: 1100px; margin: 0 auto; background: white; padding: 32px; border-radius: 18px; box-shadow: 0 18px 50px rgba(0,0,0,0.15); }
        h1 { color: #333; margin-bottom: 8px; font-size: 34px; }
        .subtitle { color: #777; margin-bottom: 24px; font-size: 16px; }
        .top-row { display: flex; justify-content: space-between; flex-wrap: wrap; gap: 16px; margin-bottom: 28px; }
        .actions { display: flex; gap: 12px; flex-wrap: wrap; }
        a.button { padding: 12px 22px; background: #667eea; color: white; text-decoration: none; border-radius: 10px; font-weight: 600; transition: transform 0.2s, box-shadow 0.2s; }
        a.button:hover { transform: translateY(-2px); box-shadow: 0 10px 30px rgba(102, 126, 234, 0.25); }
        .filter-card, .chart-card, .table-card { background: #f7f8ff; border-radius: 18px; padding: 24px; }
        .filter-card h3, .chart-card h3, .table-card h3 { margin-bottom: 18px; color: #333; }
        .form { display: flex; gap: 14px; flex-wrap: wrap; align-items: flex-end; }
        .form label { font-size: 14px; color: #555; }
        .form input { padding: 12px 14px; border-radius: 10px; border: 1px solid #d7dbf0; font-size: 15px; }
        button { background: #667eea; color: white; border: none; padding: 12px 18px; border-radius: 12px; font-size: 15px; font-weight: 700; cursor: pointer; transition: transform 0.2s; }
        button:hover { transform: translateY(-2px); }
        .chart { margin-top: 20px; }
        .bar-row { display: grid; grid-template-columns: 1fr auto; gap: 16px; align-items: center; margin-bottom: 14px; }
        .bar-label { color: #444; font-size: 14px; }
        .bar-track { background: #e9ecf7; border-radius: 999px; position: relative; height: 16px; overflow: hidden; }
        .bar-fill { height: 100%; border-radius: 999px; }
        .bar-value { min-width: 60px; text-align: right; color: #333; font-size: 13px; }
        .flag { display: inline-block; padding: 4px 10px; border-radius: 999px; font-size: 12px; color: white; }
        .receita { background: #209f71; }
        .despesa { background: #ef5f5f; }
        .table-card table { width: 100%; border-collapse: collapse; }
        .table-card th, .table-card td { text-align: left; padding: 14px 12px; border-bottom: 1px solid #e8ebf8; }
        .table-card th { color: #666; font-size: 14px; text-transform: uppercase; letter-spacing: .02em; }
        .table-card td { color: #333; font-size: 15px; }
        .msg { margin-top: 14px; padding: 16px 18px; border-radius: 14px; font-size: 15px; background: #ffe8e8; color: #922d2d; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Relatórios</h1>
        <p class="subtitle">Filtre por período para ver o desempenho das categorias e um gráfico simples.</p>

        <div class="top-row">
            <div class="actions">
                <a href="/dashboard" class="button">Voltar ao Dashboard</a>
                <a href="/logout" class="button logout-btn">Sair</a>
            </div>
        </div>

        <?php if (!empty($erroPeriodo)): ?>
            <div class="msg"><?= htmlspecialchars($erroPeriodo, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <div class="filter-card">
            <h3>Filtrar por período</h3>
            <form class="form" method="get" action="/relatorios">
                <label>
                    Início<br>
                    <input type="date" name="inicio" value="<?= htmlspecialchars($inicioFiltro, ENT_QUOTES, 'UTF-8') ?>">
                </label>
                <label>
                    Fim<br>
                    <input type="date" name="fim" value="<?= htmlspecialchars($fimFiltro, ENT_QUOTES, 'UTF-8') ?>">
                </label>
                <button type="submit">Aplicar filtro</button>
            </form>
        </div>

        <div class="chart-card" style="margin-top: 24px;">
            <h3>Gráfico por categoria</h3>
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
            <h3>Resumo por categoria</h3>
            <table>
                <thead>
                    <tr>
                        <th>Categoria</th>
                        <th>Receita</th>
                        <th>Despesa</th>
                        <th>Saldo</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($categoriasOrdenadas) === 0): ?>
                        <tr>
                            <td colspan="4" style="padding: 18px; color: #666;">Sem dados no período selecionado.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($categoriasOrdenadas as $categoria => $dados): ?>
                            <tr>
                                <td><?= htmlspecialchars($categoria, ENT_QUOTES, 'UTF-8') ?></td>
                                <td>R$ <?= number_format($dados['receita'], 2, ',', '.') ?></td>
                                <td>R$ <?= number_format($dados['despesa'], 2, ',', '.') ?></td>
                                <td>R$ <?= number_format($dados['receita'] - $dados['despesa'], 2, ',', '.') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
