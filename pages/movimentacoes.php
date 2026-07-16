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

require_once dirname(__DIR__) . '/includes/movimentacoes_controller.php';
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Movimentações - Controle Financeiro</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; min-height: 100dvh; padding: clamp(10px, 2vw, 20px); }
        .container { width: 100%; max-width: 1200px; margin: 0 auto; background: white; padding: clamp(20px, 3vw, 32px); border-radius: 18px; box-shadow: 0 18px 50px rgba(0,0,0,0.15); }
        h1 { color: #333; margin-bottom: 8px; font-size: clamp(26px, 4vw, 34px); line-height: 1.2; overflow-wrap: anywhere; }
        .subtitle { color: #666; margin-bottom: 24px; font-size: 16px; }
        .top-row { display: flex; justify-content: space-between; flex-wrap: wrap; gap: 16px; margin-bottom: 28px; }
        .actions { display: flex; gap: 12px; flex-wrap: wrap; }
        a.button, button.button { display: inline-flex; align-items: center; justify-content: center; min-height: 44px; padding: 12px 22px; background: #4f5fc9; color: white; text-align: center; text-decoration: none; border: none; border-radius: 10px; font-weight: 600; transition: transform 0.2s, box-shadow 0.2s; cursor: pointer; }
        a.button:hover, button.button:hover { transform: translateY(-2px); box-shadow: 0 10px 30px rgba(102, 126, 234, 0.25); }
        .stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-bottom: 24px; }
        .stat-card { background: linear-gradient(135deg, #4f5fc9 0%, #67398c 100%); color: white; padding: 20px; border-radius: 12px; text-align: center; }
        .stat-card.receita { background: linear-gradient(135deg, #167552 0%, #0f673d 100%); }
        .stat-card.despesa { background: linear-gradient(135deg, #b42f3c 0%, #8f2630 100%); }
        .stat-card h2 { font-size: 13px; margin-bottom: 6px; }
        .stat-value { font-size: clamp(22px, 3vw, 28px); font-weight: 700; overflow-wrap: anywhere; }
        .filters { background: #f7f8ff; border-radius: 18px; padding: 20px; margin-bottom: 24px; }
        .filters h2 { margin-bottom: 16px; color: #333; font-size: 20px; }
        .filter-row { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; }
        .new-transaction-card { background: #f7f8ff; border-radius: 18px; padding: 24px; margin-bottom: 24px; }
        .new-transaction-card h2 { color: #333; font-size: 22px; margin-bottom: 6px; }
        .form-help { color: #666; font-size: 14px; line-height: 1.5; margin-bottom: 18px; }
        .transaction-form-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 16px; }
        .transaction-form-grid .description-field { grid-column: span 3; }
        .transaction-form-actions { display: flex; align-items: flex-end; }
        .form-group { display: flex; min-width: 0; flex-direction: column; }
        label { margin-bottom: 8px; color: #555; font-size: 14px; }
        input, select, textarea { padding: 12px 14px; border-radius: 10px; border: 1px solid #d7dbf0; font-size: 15px; font-family: inherit; background: white; }
        textarea { resize: vertical; min-height: 46px; }
        .field-help { margin-top: 6px; color: #666; font-size: 12px; line-height: 1.4; }
        .field-help a { color: #3f4fae; font-weight: 700; }
        .installment-preview { margin-top: 16px; padding: 13px 15px; border: 1px solid #d8def3; border-radius: 12px; background: white; color: #3d4568; font-size: 14px; line-height: 1.5; }
        .installment-preview strong { color: #293b91; }
        .installment-preview.error { border-color: #efc5ca; background: #fff5f6; color: #922d2d; }
        .form-group[hidden], .installment-preview[hidden] { display: none; }
        .filters button[type="submit"], .new-transaction-card button[type="submit"] { min-height: 44px; background: #4f5fc9; color: white; padding: 12px 24px; border: none; border-radius: 12px; font-size: 15px; font-weight: 700; cursor: pointer; }
        .filters button[type="submit"]:hover, .new-transaction-card button[type="submit"]:hover { background: #3f4fae; }
        .filters button[type="submit"] { margin-top: 16px; }
        .bulk-actions { display: flex; align-items: center; justify-content: space-between; gap: 20px; margin-bottom: 24px; padding: 20px; border: 1px solid #efc5ca; border-radius: 16px; background: #fff5f6; }
        .bulk-actions h2 { margin-bottom: 6px; color: #8f2630; font-size: 18px; }
        .bulk-actions p { color: #6c3b40; font-size: 14px; line-height: 1.5; }
        .delete-all-form { flex: 0 0 auto; margin: 0; }
        .delete-all-button { min-height: 44px; padding: 12px 18px; border: none; border-radius: 10px; background: #c93643; color: white; font-size: 14px; font-weight: 700; cursor: pointer; }
        .delete-all-button:hover { background: #a92733; }
        .delete-all-button:focus-visible { outline: 3px solid #8f2630; outline-offset: 3px; }
        .transactions-container { overflow-x: auto; border-radius: 14px; }
        table { width: 100%; min-width: 820px; border-collapse: collapse; }
        th { background: #4f5fc9; color: white; padding: 14px; text-align: left; font-weight: 600; }
        td { padding: 14px; border-bottom: 1px solid #e5e9f0; }
        tr:hover { background: #f7f8ff; }
        .type-badge { display: inline-block; padding: 4px 10px; border-radius: 8px; font-size: 12px; font-weight: 600; }
        .type-badge.receita { background: #e8f7ef; color: #167552; }
        .type-badge.despesa { background: #ffe8e8; color: #b42f3c; }
        .installment-cell { color: #555; font-size: 13px; font-weight: 600; white-space: nowrap; }
        .msg { margin-bottom: 20px; padding: 16px 18px; border-radius: 14px; font-size: 15px; }
        .msg.sucesso { background: #e8f7ef; color: #1f7a47; }
        .msg.erro { background: #ffe8e8; color: #922d2d; }
        .color-dot { width: 12px; height: 12px; border-radius: 50%; display: inline-block; margin-right: 6px; }
        .no-data { text-align: center; padding: 40px; color: #666; }
        @media (max-width: 1024px) {
            .transaction-form-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .transaction-form-grid .description-field { grid-column: span 2; }
        }
        @media (max-width: 960px) {
            .transactions-container { overflow: visible; border-radius: 0; }
            .transactions-table { display: block; min-width: 0; font-size: 14px; }
            .transactions-table thead { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0, 0, 0, 0); white-space: nowrap; border: 0; }
            .transactions-table tbody { display: grid; gap: 14px; }
            .transactions-table tr { display: block; overflow: hidden; background: white; border: 1px solid #e1e5f4; border-radius: 14px; box-shadow: 0 6px 18px rgba(49, 55, 92, 0.08); }
            .transactions-table tr:hover { background: white; }
            .transactions-table td { display: grid; grid-template-columns: minmax(90px, 0.8fr) minmax(0, 1.2fr); gap: 12px; align-items: center; width: 100%; padding: 11px 14px; text-align: right; overflow-wrap: anywhere; }
            .transactions-table td::before { content: attr(data-label); color: #666; font-size: 12px; font-weight: 700; letter-spacing: .02em; text-align: left; text-transform: uppercase; }
            .transactions-table td:last-child { border-bottom: 0; }
            .transactions-table .tx-actions { justify-content: flex-end; }
        }
        @media (max-width: 768px) {
            .stats { grid-template-columns: 1fr; }
            .filter-row { grid-template-columns: 1fr; }
            .transaction-form-grid { grid-template-columns: minmax(0, 1fr); }
            .transaction-form-grid .description-field { grid-column: auto; }
            .actions { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); width: 100%; }
            .actions a:last-child { grid-column: 1 / -1; }
            .bulk-actions { align-items: stretch; flex-direction: column; }
            .delete-all-form, .delete-all-button { width: 100%; }
        }
        @media (max-width: 480px) {
            body { padding: 0; }
            .container { min-height: 100vh; min-height: 100dvh; border-radius: 0; box-shadow: none; }
            .actions { gap: 8px; }
            .actions a.button { padding-inline: 10px; }
            .new-transaction-card, .filters { padding: 18px; }
            .stat-card { padding: 18px 14px; }
            .transactions-table td { grid-template-columns: 82px minmax(0, 1fr); padding-inline: 12px; }
        }
    </style>
    <?php require dirname(__DIR__) . '/includes/responsive_styles.php'; ?>
    <?php require dirname(__DIR__) . '/includes/transacao_edit_styles.php'; ?>
</head>
<body>
    <main class="container">
        <h1>Movimentações</h1>
        <p class="subtitle">Visualize e gerencie todas as suas transações.</p>

        <div class="top-row">
            <nav class="actions" aria-label="Navegação principal">
                <a href="/dashboard" class="button">Dashboard</a>
                <a href="/categorias" class="button">Categorias</a>
                <a href="/logout" class="button">Sair</a>
            </nav>
        </div>

        <?php if ($mensagem !== ''): ?>
            <div class="msg <?= $tipoMensagem === 'erro' ? 'erro' : 'sucesso' ?>" role="<?= $tipoMensagem === 'erro' ? 'alert' : 'status' ?>" aria-live="<?= $tipoMensagem === 'erro' ? 'assertive' : 'polite' ?>">
                <?= htmlspecialchars($mensagem, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <section class="new-transaction-card" aria-labelledby="nova-movimentacao-titulo">
            <h2 id="nova-movimentacao-titulo">Registrar nova movimentação</h2>
            <p class="form-help">Cadastre receitas e despesas também por esta aba. Em caso de parcelamento, uma movimentação será criada para cada mês.</p>

            <form method="post" action="<?= htmlspecialchars($movimentacoesAction, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="acao" value="adicionar_transacao">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

                <div class="transaction-form-grid">
                    <div class="form-group">
                        <label for="nova-tipo">Tipo</label>
                        <select id="nova-tipo" name="tipo" required>
                            <option value="receita">Receita</option>
                            <option value="despesa">Despesa</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="nova-categoria">Categoria</label>
                        <select id="nova-categoria" name="categoria_id" aria-describedby="nova-categoria-ajuda">
                            <option value="">Sem categoria</option>
                            <?php foreach ($categorias as $categoria): ?>
                                <option
                                    value="<?= (int) $categoria['id'] ?>"
                                    data-tipo="<?= htmlspecialchars($categoria['tipo'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
                                    data-nome="<?= htmlspecialchars($categoria['nome'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
                                ><?= htmlspecialchars($categoria['nome'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small id="nova-categoria-ajuda" class="field-help">As categorias são filtradas conforme o tipo selecionado. <a href="/categorias">Gerenciar categorias</a></small>
                    </div>

                    <div class="form-group">
                        <label for="novo-valor">Valor total (R$)</label>
                        <input type="text" id="novo-valor" name="valor" inputmode="decimal" placeholder="1500,00" required>
                    </div>

                    <div class="form-group" id="parcelas-group" hidden>
                        <label for="novas-parcelas">Quantidade de parcelas</label>
                        <input
                            type="number"
                            id="novas-parcelas"
                            name="parcelas"
                            min="1"
                            max="<?= MAX_PARCELAS_TRANSACAO ?>"
                            step="1"
                            value="1"
                            inputmode="numeric"
                            aria-describedby="parcelas-ajuda parcelas-preview"
                            disabled
                            required
                        >
                        <small id="parcelas-ajuda" class="field-help">Digite de 1 a <?= MAX_PARCELAS_TRANSACAO ?> parcelas.</small>
                    </div>

                    <div class="form-group">
                        <label for="nova-data">Data</label>
                        <input type="date" id="nova-data" name="data" value="<?= date('Y-m-d') ?>" required>
                    </div>

                    <div class="form-group description-field">
                        <label for="nova-descricao">Descrição (opcional)</label>
                        <textarea id="nova-descricao" name="descricao" maxlength="255" placeholder="Observações sobre esta movimentação"></textarea>
                    </div>
                </div>

                <p id="parcelas-preview" class="installment-preview" aria-live="polite" hidden>
                    Informe o valor total para visualizar a divisão das parcelas.
                </p>

                <button type="submit" class="button" style="margin-top: 18px;">Salvar movimentação</button>
            </form>
        </section>

        <?php require dirname(__DIR__) . '/includes/movimentacao_form_script.php'; ?>

        <div class="stats">
            <div class="stat-card receita">
                <h2>Total de Receitas</h2>
                <div class="stat-value">R$ <?= number_format($totalReceitas, 2, ',', '.') ?></div>
            </div>
            <div class="stat-card despesa">
                <h2>Total de Despesas</h2>
                <div class="stat-value">R$ <?= number_format($totalDespesas, 2, ',', '.') ?></div>
            </div>
            <div class="stat-card">
                <h2>Saldo do Período</h2>
                <div class="stat-value">R$ <?= number_format($saldo, 2, ',', '.') ?></div>
            </div>
        </div>

        <div class="filters">
            <h2>Filtros</h2>
            <form method="get" action="/movimentacoes">
                <div class="filter-row">
                    <div class="form-group">
                        <label for="inicio">Data Inicial</label>
                        <input type="date" id="inicio" name="inicio" value="<?= htmlspecialchars($filtroDataInicio, ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="form-group">
                        <label for="fim">Data Final</label>
                        <input type="date" id="fim" name="fim" value="<?= htmlspecialchars($filtroDataFim, ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="form-group">
                        <label for="filtro-tipo">Tipo</label>
                        <select id="filtro-tipo" name="tipo">
                            <option value="">Todos</option>
                            <option value="receita" <?= $filtroTipo === 'receita' ? 'selected' : '' ?>>Receitas</option>
                            <option value="despesa" <?= $filtroTipo === 'despesa' ? 'selected' : '' ?>>Despesas</option>
                        </select>
                    </div>
                </div>
                <button type="submit" class="button">Filtrar</button>
            </form>
        </div>

        <?php if ($totalTransacoesUsuario > 0): ?>
            <section class="bulk-actions" aria-labelledby="excluir-todas-titulo">
                <div>
                    <h2 id="excluir-todas-titulo">Excluir todos os lançamentos</h2>
                    <p>Esta opção apagará permanentemente <?= $totalTransacoesUsuario ?> <?= $totalTransacoesUsuario === 1 ? 'lançamento' : 'lançamentos' ?>, inclusive os que não aparecem no filtro atual. Suas categorias serão mantidas.</p>
                </div>
                <form
                    method="post"
                    action="<?= htmlspecialchars($movimentacoesAction, ENT_QUOTES, 'UTF-8') ?>"
                    class="delete-all-form"
                    onsubmit="return confirm('Esta ação não pode ser desfeita. Deseja excluir permanentemente todos os seus <?= $totalTransacoesUsuario ?> lançamentos?')"
                >
                    <input type="hidden" name="acao" value="deletar_todas">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                    <button type="submit" class="delete-all-button">Excluir tudo</button>
                </form>
            </section>
        <?php endif; ?>

        <h2 id="lista-movimentacoes-titulo" class="sr-only">Lista de movimentações</h2>
        <?php if (count($transacoes) === 0): ?>
            <div class="no-data">
                <p>Nenhuma transação encontrada para este período.</p>
            </div>
        <?php else: ?>
            <div class="transactions-container" role="region" aria-labelledby="lista-movimentacoes-titulo" tabindex="0">
                <table class="transactions-table" role="table">
                    <caption class="sr-only">Movimentações encontradas no período selecionado</caption>
                    <thead role="rowgroup">
                        <tr role="row">
                            <th scope="col" role="columnheader">Data</th>
                            <th scope="col" role="columnheader">Tipo</th>
                            <th scope="col" role="columnheader">Categoria</th>
                            <th scope="col" role="columnheader">Descrição</th>
                            <th scope="col" role="columnheader">Valor</th>
                            <th scope="col" role="columnheader">Parcela</th>
                            <th scope="col" role="columnheader">Ações</th>
                        </tr>
                    </thead>
                    <tbody role="rowgroup">
                        <?php foreach ($transacoes as $t): ?>
                            <?php
                            $totalParcelas = max(1, (int) ($t['total_parcelas'] ?? 1));
                            $numeroParcela = max(1, (int) ($t['numero_parcela'] ?? 1));
                            $parcelaExibida = $totalParcelas > 1
                                ? "{$numeroParcela}/{$totalParcelas}"
                                : '-';
                            $confirmacaoExclusao = $totalParcelas > 1
                                ? sprintf(
                                    'Excluir somente a parcela %d de %d? As demais parcelas permanecerão cadastradas.',
                                    $numeroParcela,
                                    $totalParcelas
                                )
                                : 'Deseja realmente excluir esta movimentação?';
                            ?>
                            <tr role="row">
                                <td data-label="Data" role="cell"><?= date('d/m/Y', strtotime($t['data'])) ?></td>
                                <td data-label="Tipo" role="cell">
                                    <span class="type-badge <?= $t['tipo'] ?>">
                                        <?= $t['tipo'] === 'receita' ? 'Receita' : 'Despesa' ?>
                                    </span>
                                </td>
                                <td data-label="Categoria" role="cell">
                                    <?php if ($t['categoria']): ?>
                                        <span class="tx-category">
                                            <span class="color-dot" style="background-color: <?= htmlspecialchars($t['cor'], ENT_QUOTES, 'UTF-8') ?>"></span>
                                            <span><?= htmlspecialchars($t['categoria'], ENT_QUOTES, 'UTF-8') ?></span>
                                        </span>
                                    <?php else: ?>
                                        <span class="tx-category" style="color: #666;">Sem categoria</span>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Descrição" role="cell"><?= htmlspecialchars($t['descricao'] ?: '-', ENT_QUOTES, 'UTF-8') ?></td>
                                <td data-label="Valor" role="cell" style="font-weight: 600; color: <?= $t['tipo'] === 'receita' ? '#167552' : '#b42f3c' ?>">
                                    <?= $t['tipo'] === 'receita' ? '+' : '-' ?> R$ <?= number_format((float) $t['valor'], 2, ',', '.') ?>
                                </td>
                                <td data-label="Parcela" role="cell"><span class="installment-cell"><?= htmlspecialchars($parcelaExibida, ENT_QUOTES, 'UTF-8') ?></span></td>
                                <td data-label="Ações" role="cell">
                                    <div class="tx-actions">
                                        <button
                                            type="button"
                                            class="tx-edit-btn"
                                            aria-label="Editar movimentação de <?= date('d/m/Y', strtotime($t['data'])) ?>: <?= htmlspecialchars($t['descricao'] ?: $t['categoria'], ENT_QUOTES, 'UTF-8') ?>"
                                            onclick="abrirEdicao(<?= (int) $t['id'] ?>, this)"
                                        >Editar</button>
                                        <form method="post" action="<?= htmlspecialchars($movimentacoesAction, ENT_QUOTES, 'UTF-8') ?>" class="tx-delete-form" onsubmit="return confirm('<?= htmlspecialchars($confirmacaoExclusao, ENT_QUOTES, 'UTF-8') ?>')">
                                            <input type="hidden" name="acao" value="deletar">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                            <input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
                                            <button
                                                type="submit"
                                                class="tx-delete-btn"
                                                aria-label="Excluir movimentação de <?= date('d/m/Y', strtotime($t['data'])) ?>: <?= htmlspecialchars($t['descricao'] ?: $t['categoria'], ENT_QUOTES, 'UTF-8') ?>"
                                            >Excluir</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </main>

    <?php
    $edicaoAction = $movimentacoesAction;
    require dirname(__DIR__) . '/includes/transacao_edit_modal.php';
    ?>

</body>
</html>
