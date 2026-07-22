<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/Core/proteger.php';

/** @var string $inicioFiltro */
/** @var string $fimFiltro */
/** @var string $inicioFiltroExibicao */
/** @var string $fimFiltroExibicao */
/** @var bool $periodoValido */
/** @var string $periodoMensagem */
/** @var string $dashboardAction */
/** @var string $mensagem */
/** @var string $tipoMensagem */
/** @var string $csrfToken */
/** @var float $totalReceitas */
/** @var float $totalDespesas */
/** @var float $saldo */
/** @var bool $exibirAutorMovimentacao */
/** @var array<int, array<string, mixed>> $transacoes */
/** @var array<int, array{periodo: string, rotulo: string, receitas: float, despesas: float}> $evolucaoFinanceira */
/** @var array<int, array{categoria: string, cor: string, total: float}> $despesasPorCategoria */
/** @var float|int $totalDespesasGrafico */

if (!isset(
    $inicioFiltro,
    $fimFiltro,
    $inicioFiltroExibicao,
    $fimFiltroExibicao,
    $periodoValido,
    $periodoMensagem,
    $dashboardAction,
    $mensagem,
    $tipoMensagem,
    $csrfToken,
    $totalReceitas,
    $totalDespesas,
    $saldo,
    $exibirAutorMovimentacao,
    $transacoes,
    $evolucaoFinanceira,
    $despesasPorCategoria,
    $totalDespesasGrafico
)) {
    throw new RuntimeException('Os dados da view do Dashboard não foram informados.');
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Controle Financeiro</title>
    <link rel="stylesheet" href="/assets/css/base.css">
    <link rel="stylesheet" href="/assets/css/pages/dashboard.css">
    <link rel="stylesheet" href="/assets/css/components.css">
</head>
<body>
    <main class="container">
        <h1>Bem-vindo, <?= htmlspecialchars($_SESSION['usuario_nome'] ?? 'Usuário', ENT_QUOTES, 'UTF-8') ?></h1>
        <p class="subtitle">Acompanhe seu saldo e seus últimos lançamentos. Novas receitas e despesas são registradas na aba Movimentações.</p>

        <?php if (($avisoAssinatura ?? '') !== ''): ?>
            <div class="msg aviso-assinatura" role="status">
                <?= htmlspecialchars($avisoAssinatura, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <?php require dirname(__DIR__) . '/partials/carteira_switcher.php'; ?>

        <?php if ($mensagem !== ''): ?>
            <div class="msg <?= $tipoMensagem === 'erro' ? 'erro' : 'sucesso' ?>" role="<?= $tipoMensagem === 'erro' ? 'alert' : 'status' ?>" aria-live="<?= $tipoMensagem === 'erro' ? 'assertive' : 'polite' ?>">
                <?= htmlspecialchars($mensagem, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <div class="actions dashboard-toolbar">
            <nav class="dashboard-nav" aria-label="Navegação principal">
                <?php if (telaClientePermitida($permissoesCliente ?? [], 'movimentacoes')): ?>
                    <a href="/movimentacoes" class="button">Movimentações</a>
                <?php endif; ?>
                <?php if (telaClientePermitida($permissoesCliente ?? [], 'categorias')): ?>
                    <a href="/categorias" class="button">Categorias</a>
                <?php endif; ?>
                <?php if (telaClientePermitida($permissoesCliente ?? [], 'relatorios')): ?>
                    <a href="/relatorios" class="button">Relatórios</a>
                <?php endif; ?>
                <?php if (usuarioSuperAdmin()): ?>
                    <a href="/admin-clientes" class="button admin-btn">Administrar clientes</a>
                <?php endif; ?>
                <a href="/logout" class="button logout-btn">Sair</a>
            </nav>
            <form method="get" action="/dashboard" class="period-filter">
                <label>
                    <span>Início</span>
                    <input type="date" name="inicio" value="<?= htmlspecialchars($inicioFiltro, ENT_QUOTES, 'UTF-8') ?>">
                </label>
                <label>
                    <span>Fim</span>
                    <input type="date" name="fim" value="<?= htmlspecialchars($fimFiltro, ENT_QUOTES, 'UTF-8') ?>">
                </label>
                <button type="submit">Filtrar</button>
            </form>
        </div>

        <?php if (!$periodoValido): ?>
            <div class="msg erro" role="alert"><?= htmlspecialchars($periodoMensagem, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <div class="top-row">
            <div class="card">
                <h2>Total de Receitas</h2>
                <strong>R$ <?= number_format($totalReceitas, 2, ',', '.') ?></strong>
                <p class="card-period">Período: <?= htmlspecialchars($inicioFiltroExibicao, ENT_QUOTES, 'UTF-8') ?> até <?= htmlspecialchars($fimFiltroExibicao, ENT_QUOTES, 'UTF-8') ?></p>
            </div>
            <div class="card">
                <h2>Total de Despesas</h2>
                <strong>R$ <?= number_format($totalDespesas, 2, ',', '.') ?></strong>
                <p class="card-period">Período: <?= htmlspecialchars($inicioFiltroExibicao, ENT_QUOTES, 'UTF-8') ?> até <?= htmlspecialchars($fimFiltroExibicao, ENT_QUOTES, 'UTF-8') ?></p>
            </div>
            <div class="card balance">
                <h2>Saldo do Período</h2>
                <strong>R$ <?= number_format($saldo, 2, ',', '.') ?></strong>
                <p class="card-period">Período: <?= htmlspecialchars($inicioFiltroExibicao, ENT_QUOTES, 'UTF-8') ?> até <?= htmlspecialchars($fimFiltroExibicao, ENT_QUOTES, 'UTF-8') ?></p>
            </div>
        </div>

        <section class="charts-grid" aria-label="Gráficos financeiros do período">
            <article class="chart-card">
                <div class="chart-header">
                    <div>
                        <h2>Evolução financeira</h2>
                        <p>Compara receitas e despesas ao longo do período selecionado.</p>
                    </div>
                    <div class="chart-legend" aria-hidden="true">
                        <span><i class="income"></i>Receitas</span>
                        <span><i class="expense"></i>Despesas</span>
                    </div>
                </div>

                <?php if ($evolucaoFinanceira === []): ?>
                    <div class="chart-empty">Registre movimentações neste período para visualizar a evolução.</div>
                <?php else: ?>
                    <div class="line-chart-wrap">
                        <canvas
                            id="financial-evolution-chart"
                            role="img"
                            aria-label="Gráfico de evolução de receitas e despesas"
                        ></canvas>
                    </div>
                <?php endif; ?>
            </article>

            <article class="chart-card">
                <div class="chart-header">
                    <div>
                        <h2>Despesas por categoria</h2>
                        <p>Mostra onde está concentrada a maior parte dos gastos.</p>
                    </div>
                </div>

                <?php if ($despesasPorCategoria === [] || $totalDespesasGrafico <= 0): ?>
                    <div class="chart-empty">Ainda não existem despesas neste período.</div>
                <?php else: ?>
                    <div class="category-chart-layout">
                        <div class="donut-wrap">
                            <canvas
                                id="expense-category-chart"
                                role="img"
                                aria-label="Gráfico de distribuição das despesas por categoria"
                            ></canvas>
                            <div class="donut-center" aria-hidden="true">
                                Total
                                <strong>R$ <?= number_format($totalDespesasGrafico, 2, ',', '.') ?></strong>
                            </div>
                        </div>
                        <ul class="category-chart-legend" aria-label="Valores por categoria">
                            <?php foreach ($despesasPorCategoria as $categoriaGrafico): ?>
                                <li>
                                    <i class="category-legend-dot" style="--category-color: <?= htmlspecialchars($categoriaGrafico['cor'], ENT_QUOTES, 'UTF-8') ?>"></i>
                                    <span>
                                        <?= htmlspecialchars($categoriaGrafico['categoria'], ENT_QUOTES, 'UTF-8') ?>
                                        <strong>R$ <?= number_format((float) $categoriaGrafico['total'], 2, ',', '.') ?></strong>
                                    </span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
            </article>
        </section>

        <div class="grid">
            <div class="table-card" role="region" aria-labelledby="ultimos-lancamentos-titulo" tabindex="0">
                <h2 id="ultimos-lancamentos-titulo">Últimos lançamentos</h2>
                <?php if (count($transacoes) === 0): ?>
                    <p>Nenhuma transação registrada ainda. Acesse Movimentações para fazer seu primeiro lançamento.</p>
                    <?php if (telaClientePermitida($permissoesCliente ?? [], 'movimentacoes')): ?>
                        <a href="/movimentacoes" class="button empty-action">Registrar movimentação</a>
                    <?php endif; ?>
                <?php else: ?>
                    <table class="transactions-table <?= $exibirAutorMovimentacao ? 'with-author' : '' ?>" role="table">
                        <caption class="sr-only">Dez lançamentos mais recentes no período selecionado</caption>
                        <thead role="rowgroup">
                            <tr role="row">
                                <th scope="col" role="columnheader">Tipo</th>
                                <th scope="col" role="columnheader">Categoria</th>
                                <th scope="col" role="columnheader">Descrição</th>
                                <th scope="col" role="columnheader">Valor</th>
                                <th scope="col" role="columnheader">Parcela</th>
                                <?php if ($exibirAutorMovimentacao): ?>
                                    <th scope="col" role="columnheader">Registrado por</th>
                                <?php endif; ?>
                                <th scope="col" role="columnheader">Data</th>
                                <th scope="col" role="columnheader">Ações</th>
                            </tr>
                        </thead>
                        <tbody role="rowgroup">
                            <?php foreach ($transacoes as $transacao): ?>
                                <?php
                                $confirmacaoExclusao = (int) $transacao['total_parcelas'] > 1
                                    ? sprintf(
                                        'Excluir somente a parcela %d de %d? As demais parcelas permanecerão cadastradas.',
                                        (int) $transacao['numero_parcela'],
                                        (int) $transacao['total_parcelas']
                                    )
                                    : 'Deseja realmente excluir esta movimentação?';
                                ?>
                                <tr role="row">
                                    <td data-label="Tipo" role="cell"><span class="tag <?= htmlspecialchars($transacao['tipo'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(ucfirst($transacao['tipo']), ENT_QUOTES, 'UTF-8') ?></span></td>
                                    <td data-label="Categoria" role="cell">
                                        <?php if ($transacao['categoria']): ?>
                                            <span class="tx-category">
                                                <span class="color-dot" style="--category-color: <?= htmlspecialchars($transacao['cor'], ENT_QUOTES, 'UTF-8') ?>"></span>
                                                <span><?= htmlspecialchars($transacao['categoria'], ENT_QUOTES, 'UTF-8') ?></span>
                                            </span>
                                        <?php else: ?>
                                            <span class="tx-category tx-category-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="Descrição" role="cell"><?= htmlspecialchars($transacao['descricao'] ?: '-', ENT_QUOTES, 'UTF-8') ?></td>
                                    <td data-label="Valor" role="cell">
                                        <span class="tx-value <?= htmlspecialchars($transacao['tipo'], ENT_QUOTES, 'UTF-8') ?>">
                                            <?= $transacao['tipo'] === 'receita' ? '+' : '-' ?> R$ <?= number_format((float)$transacao['valor'], 2, ',', '.') ?>
                                        </span>
                                    </td>
                                    <td data-label="Parcela" role="cell">
                                        <?php if ($transacao['total_parcelas'] > 1): ?>
                                            <span class="installment-text"><?= (int)$transacao['numero_parcela'] ?>/<?= (int)$transacao['total_parcelas'] ?></span>
                                        <?php else: ?>
                                            <span class="installment-text">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <?php if ($exibirAutorMovimentacao): ?>
                                        <td data-label="Registrado por" role="cell"><?= htmlspecialchars($transacao['registrado_por'] ?: '-', ENT_QUOTES, 'UTF-8') ?></td>
                                    <?php endif; ?>
                                    <td data-label="Data" role="cell"><?= date('d/m/Y', strtotime($transacao['data'])) ?></td>
                                    <td data-label="Ações" role="cell">
                                        <div class="tx-actions">
                                            <button
                                                type="button"
                                                class="tx-edit-btn"
                                                aria-label="Editar movimentação de <?= date('d/m/Y', strtotime($transacao['data'])) ?>: <?= htmlspecialchars($transacao['descricao'] ?: $transacao['categoria'], ENT_QUOTES, 'UTF-8') ?>"
                                                onclick="abrirEdicao(<?= (int) $transacao['id'] ?>, this)"
                                            >Editar</button>
                                            <form method="post" action="<?= htmlspecialchars($dashboardAction, ENT_QUOTES, 'UTF-8') ?>" class="tx-delete-form" onsubmit="return confirm('<?= htmlspecialchars($confirmacaoExclusao, ENT_QUOTES, 'UTF-8') ?>')">
                                                <input type="hidden" name="acao" value="deletar">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                                <input type="hidden" name="id" value="<?= (int) $transacao['id'] ?>">
                                                <button
                                                    type="submit"
                                                    class="tx-delete-btn"
                                                    aria-label="Excluir movimentação de <?= date('d/m/Y', strtotime($transacao['data'])) ?>: <?= htmlspecialchars($transacao['descricao'] ?: $transacao['categoria'], ENT_QUOTES, 'UTF-8') ?>"
                                                >Excluir</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <div class="info-card info-card-spaced">
            <h2>Informações rápidas</h2>
            <p>Use este painel para acompanhar suas receitas, despesas e saldo. Para fazer um novo lançamento, acesse <strong>Movimentações</strong>.</p>
            <p>Acesse <strong>Categorias</strong> para criar e gerenciar suas categorias personalizadas.</p>
        </div>
    </main>

    <?php
    $edicaoAction = $dashboardAction;
    require dirname(__DIR__) . '/partials/transacao_edit_modal.php';
    require dirname(__DIR__) . '/partials/dashboard_charts_script.php';
    ?>

</body>
</html>
