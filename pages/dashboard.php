<?php

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once dirname(__DIR__) . '/config/conexao.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: /login');
    exit;
}

require_once dirname(__DIR__) . '/includes/dashboard_controller.php';
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Controle Financeiro</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; min-height: 100dvh; padding: clamp(10px, 2vw, 20px); }
        .container { width: 100%; max-width: 1200px; margin: 0 auto; background: white; padding: clamp(20px, 3vw, 32px); border-radius: 18px; box-shadow: 0 18px 50px rgba(0,0,0,0.15); }
        h1 { color: #333; margin-bottom: 8px; font-size: clamp(26px, 4vw, 34px); line-height: 1.2; overflow-wrap: anywhere; }
        .subtitle { color: #666; margin-bottom: 28px; font-size: 16px; }
        .top-row { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 30px; }
        .card { background: #f7f8ff; border-radius: 16px; padding: 24px; box-shadow: inset 0 0 0 1px rgba(102, 126, 234, 0.08); }
        .card h2 { margin-bottom: 12px; color: #555; font-size: 16px; }
        .card strong { font-size: 28px; color: #222; display: block; }
        .card.balance { background: linear-gradient(135deg, #4f5fc9, #67398c); color: white; }
        .card.balance h2, .card.balance strong { color: #fff; }
        .actions { display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 30px; }
        .dashboard-toolbar { justify-content: space-between; align-items: center; }
        .dashboard-nav, .period-filter { display: flex; gap: 12px; flex-wrap: wrap; align-items: center; }
        .period-filter label { display: flex; align-items: center; gap: 6px; margin: 0; color: #444; }
        .period-filter input { width: auto; }
        a.button { display: inline-flex; align-items: center; justify-content: center; min-height: 44px; padding: 12px 22px; background: #4f5fc9; color: white; text-align: center; text-decoration: none; border-radius: 10px; font-weight: 600; transition: transform 0.2s, box-shadow 0.2s; }
        a.button:hover { transform: translateY(-2px); box-shadow: 0 10px 30px rgba(102, 126, 234, 0.25); }
        .grid { display: grid; grid-template-columns: 1fr; gap: 24px; }
        .table-card, .info-card { background: #f7f8ff; border-radius: 18px; padding: 24px; }
        .table-card { min-width: 0; overflow-x: auto; }
        .table-card h2, .info-card h2 { margin-bottom: 18px; color: #333; font-size: 20px; }
        label { display: block; margin-bottom: 8px; color: #555; font-size: 14px; }
        input, select, textarea { width: 100%; padding: 14px 16px; border-radius: 12px; border: 1px solid #d7dbf0; font-size: 15px; background: white; }
        textarea { min-height: 100px; resize: vertical; }
        button { background: #4f5fc9; color: white; border: none; padding: 14px 20px; border-radius: 12px; font-size: 15px; font-weight: 700; cursor: pointer; transition: transform 0.2s; }
        button:hover { transform: translateY(-2px); }
        table { width: 100%; min-width: 760px; border-collapse: collapse; }
        th, td { text-align: left; padding: 14px 12px; border-bottom: 1px solid #e8ebf8; }
        th { color: #666; font-size: 14px; text-transform: uppercase; letter-spacing: .02em; }
        td { color: #333; font-size: 15px; }
        .tag { display: inline-flex; align-items: center; gap: 8px; padding: 8px 12px; border-radius: 999px; font-size: 13px; font-weight: 700; }
        .tag.receita { background: rgba(32, 159, 113, 0.12); color: #167552; }
        .tag.despesa { background: rgba(239, 95, 95, 0.12); color: #b42f3c; }
        .msg { margin-bottom: 20px; padding: 16px 18px; border-radius: 14px; font-size: 15px; }
        .msg.sucesso { background: #e8f7ef; color: #1f7a47; }
        .msg.erro { background: #ffe8e8; color: #922d2d; }
        .info-card p { line-height: 1.8; color: #555; }
        .color-dot { width: 12px; height: 12px; border-radius: 50%; display: inline-block; margin-right: 6px; }
        @media (max-width: 768px) {
            .top-row { grid-template-columns: 1fr; }
            .dashboard-toolbar { align-items: stretch; }
            .dashboard-nav, .period-filter { width: 100%; }
            .dashboard-nav { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .period-filter { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); padding: 16px; background: #f7f8ff; border-radius: 14px; }
            .period-filter label { align-items: stretch; flex-direction: column; }
            .period-filter input { width: 100%; }
            .period-filter button { grid-column: 1 / -1; min-height: 44px; }
            .table-card { padding: 18px; overflow: visible; }
            .transactions-table { display: block; min-width: 0; }
            .transactions-table thead { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0, 0, 0, 0); white-space: nowrap; border: 0; }
            .transactions-table tbody { display: grid; gap: 14px; }
            .transactions-table tr { display: block; overflow: hidden; background: white; border: 1px solid #e1e5f4; border-radius: 14px; box-shadow: 0 6px 18px rgba(49, 55, 92, 0.06); }
            .transactions-table td { display: grid; grid-template-columns: minmax(90px, 0.8fr) minmax(0, 1.2fr); gap: 12px; align-items: center; width: 100%; padding: 11px 14px; text-align: right; overflow-wrap: anywhere; }
            .transactions-table td::before { content: attr(data-label); color: #666; font-size: 12px; font-weight: 700; letter-spacing: .02em; text-align: left; text-transform: uppercase; }
            .transactions-table td:last-child { border-bottom: 0; }
            .transactions-table .tx-actions { justify-content: flex-end; }
        }
        @media (max-width: 480px) {
            body { padding: 0; }
            .container { min-height: 100vh; min-height: 100dvh; border-radius: 0; box-shadow: none; }
            .subtitle { margin-bottom: 20px; }
            .dashboard-nav { gap: 8px; }
            .dashboard-nav a.button { padding-inline: 10px; }
            .period-filter { grid-template-columns: 1fr; }
            .period-filter button { grid-column: auto; width: 100%; }
            .card, .table-card, .info-card { padding: 18px; }
            .card strong { font-size: 24px; overflow-wrap: anywhere; }
            .transactions-table td { grid-template-columns: 82px minmax(0, 1fr); padding-inline: 12px; }
        }
    </style>
    <?php require dirname(__DIR__) . '/includes/responsive_styles.php'; ?>
    <?php require dirname(__DIR__) . '/includes/transacao_edit_styles.php'; ?>
</head>
<body>
    <main class="container">
        <h1>Bem-vindo ao seu Controle Financeiro, <?= htmlspecialchars($_SESSION['usuario_nome'] ?? 'Usuário', ENT_QUOTES, 'UTF-8') ?></h1>
        <p class="subtitle">Acompanhe seu saldo e seus últimos lançamentos. Novas receitas e despesas são registradas na aba Movimentações.</p>

        <?php if ($mensagem !== ''): ?>
            <div class="msg <?= $tipoMensagem === 'erro' ? 'erro' : 'sucesso' ?>" role="<?= $tipoMensagem === 'erro' ? 'alert' : 'status' ?>" aria-live="<?= $tipoMensagem === 'erro' ? 'assertive' : 'polite' ?>">
                <?= htmlspecialchars($mensagem, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <div class="actions dashboard-toolbar">
            <nav class="dashboard-nav" aria-label="Navegação principal">
                <a href="/logout" class="button logout-btn">Sair</a>
                <a href="/relatorios" class="button">Relatórios</a>
                <a href="/categorias" class="button">Categorias</a>
                <a href="/movimentacoes" class="button">Movimentações</a>
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
                <p style="margin-top: 8px; color: #666; font-size: 13px;">Período: <?= htmlspecialchars($inicioFiltro, ENT_QUOTES, 'UTF-8') ?> até <?= htmlspecialchars($fimFiltro, ENT_QUOTES, 'UTF-8') ?></p>
            </div>
            <div class="card">
                <h2>Total de Despesas</h2>
                <strong>R$ <?= number_format($totalDespesas, 2, ',', '.') ?></strong>
                <p style="margin-top: 8px; color: #666; font-size: 13px;">Período: <?= htmlspecialchars($inicioFiltro, ENT_QUOTES, 'UTF-8') ?> até <?= htmlspecialchars($fimFiltro, ENT_QUOTES, 'UTF-8') ?></p>
            </div>
            <div class="card balance">
                <h2>Saldo Atual</h2>
                <strong>R$ <?= number_format($saldo, 2, ',', '.') ?></strong>
                <p style="margin-top: 8px; color: #fff; font-size: 13px;">Período: <?= htmlspecialchars($inicioFiltro, ENT_QUOTES, 'UTF-8') ?> até <?= htmlspecialchars($fimFiltro, ENT_QUOTES, 'UTF-8') ?></p>
            </div>
        </div>

        <div class="grid">
            <div class="table-card" role="region" aria-labelledby="ultimos-lancamentos-titulo" tabindex="0">
                <h2 id="ultimos-lancamentos-titulo">Últimos lançamentos</h2>
                <?php if (count($transacoes) === 0): ?>
                    <p>Nenhuma transação registrada ainda. Acesse Movimentações para fazer seu primeiro lançamento.</p>
                    <a href="/movimentacoes" class="button" style="margin-top: 16px;">Registrar movimentação</a>
                <?php else: ?>
                    <table class="transactions-table" role="table">
                        <caption class="sr-only">Dez lançamentos mais recentes no período selecionado</caption>
                        <thead role="rowgroup">
                            <tr role="row">
                                <th scope="col" role="columnheader">Tipo</th>
                                <th scope="col" role="columnheader">Categoria</th>
                                <th scope="col" role="columnheader">Descrição</th>
                                <th scope="col" role="columnheader">Valor</th>
                                <th scope="col" role="columnheader">Parcela</th>
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
                                                <span class="color-dot" style="background-color: <?= htmlspecialchars($transacao['cor'], ENT_QUOTES, 'UTF-8') ?>"></span>
                                                <span><?= htmlspecialchars($transacao['categoria'], ENT_QUOTES, 'UTF-8') ?></span>
                                            </span>
                                        <?php else: ?>
                                            <span class="tx-category" style="color: #666;">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="Descrição" role="cell"><?= htmlspecialchars($transacao['descricao'] ?: '-', ENT_QUOTES, 'UTF-8') ?></td>
                                    <td data-label="Valor" role="cell">R$ <?= number_format((float)$transacao['valor'], 2, ',', '.') ?></td>
                                    <td data-label="Parcela" role="cell">
                                        <?php if ($transacao['total_parcelas'] > 1): ?>
                                            <span style="font-size: 13px; color: #666;"><?= (int)$transacao['numero_parcela'] ?>/<?= (int)$transacao['total_parcelas'] ?></span>
                                        <?php else: ?>
                                            <span style="font-size: 13px; color: #666;">-</span>
                                        <?php endif; ?>
                                    </td>
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

        <div class="info-card" style="margin-top: 24px;">
            <h2>Informações rápidas</h2>
            <p>Use este painel para acompanhar suas receitas, despesas e saldo. Para fazer um novo lançamento, acesse <strong>Movimentações</strong>.</p>
            <p>Acesse <strong>Categorias</strong> para criar e gerenciar suas categorias personalizadas.</p>
        </div>
    </main>

    <?php
    $edicaoAction = $dashboardAction;
    require dirname(__DIR__) . '/includes/transacao_edit_modal.php';
    ?>

</body>
</html>
