<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/Core/proteger.php';
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatórios - Controle Financeiro</title>
    <link rel="stylesheet" href="/assets/css/base.css">
    <link rel="stylesheet" href="/assets/css/pages/relatorios.css">
    <link rel="stylesheet" href="/assets/css/components.css">
</head>
<body>
    <main class="container">
        <h1>Relatórios</h1>
        <p class="subtitle">Filtre por período para ver o desempenho das categorias e um gráfico simples.</p>

        <?php require dirname(__DIR__) . '/partials/carteira_switcher.php'; ?>

        <div class="top-row">
            <nav class="actions" aria-label="Navegação principal">
                <?php if (telaClientePermitida($permissoesCliente ?? [], 'dashboard')): ?>
                    <a href="/dashboard" class="button">Voltar ao Dashboard</a>
                <?php endif; ?>
                <?php if (telaClientePermitida($permissoesCliente ?? [], 'movimentacoes')): ?>
                    <a href="<?= htmlspecialchars($movimentacoesPeriodoUrl, ENT_QUOTES, 'UTF-8') ?>" class="button">Gerenciar lançamentos</a>
                <?php endif; ?>
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

        <div class="chart-card section-spaced">
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
                                        <div class="bar-fill receita" style="--bar-width: <?= round(($dados['receita'] / $maxValor) * 100, 2) ?>%"></div>
                                    <?php endif; ?>
                                    <?php if ($dados['despesa'] > 0): ?>
                                        <div class="bar-fill despesa" style="--bar-width: <?= round(($dados['despesa'] / $maxValor) * 100, 2) ?>%"></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="bar-value">R$ <?= number_format($dados['receita'] - $dados['despesa'], 2, ',', '.') ?></div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="table-card section-spaced">
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
                                <td colspan="4" role="cell" class="empty-summary-cell">Sem dados no período selecionado.</td>
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
