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
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; padding: 20px; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 32px; border-radius: 18px; box-shadow: 0 18px 50px rgba(0,0,0,0.15); }
        h1 { color: #333; margin-bottom: 8px; font-size: 34px; }
        .subtitle { color: #777; margin-bottom: 24px; font-size: 16px; }
        .top-row { display: flex; justify-content: space-between; flex-wrap: wrap; gap: 16px; margin-bottom: 28px; }
        .actions { display: flex; gap: 12px; flex-wrap: wrap; }
        a.button, button.button { padding: 12px 22px; background: #667eea; color: white; text-decoration: none; border: none; border-radius: 10px; font-weight: 600; transition: transform 0.2s, box-shadow 0.2s; cursor: pointer; }
        a.button:hover, button.button:hover { transform: translateY(-2px); box-shadow: 0 10px 30px rgba(102, 126, 234, 0.25); }
        .stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-bottom: 24px; }
        .stat-card { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 12px; text-align: center; }
        .stat-card.receita { background: linear-gradient(135deg, #209f71 0%, #0f7d47 100%); }
        .stat-card.despesa { background: linear-gradient(135deg, #ef5f5f 0%, #dd3333 100%); }
        .stat-card h4 { font-size: 13px; opacity: 0.9; margin-bottom: 6px; }
        .stat-value { font-size: 28px; font-weight: 700; }
        .filters { background: #f7f8ff; border-radius: 18px; padding: 20px; margin-bottom: 24px; }
        .filters h3 { margin-bottom: 16px; color: #333; }
        .filter-row { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; }
        .new-transaction-card { background: #f7f8ff; border-radius: 18px; padding: 24px; margin-bottom: 24px; }
        .new-transaction-card h2 { color: #333; font-size: 22px; margin-bottom: 6px; }
        .form-help { color: #777; font-size: 14px; line-height: 1.5; margin-bottom: 18px; }
        .transaction-form-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; }
        .transaction-form-grid .description-field { grid-column: span 3; }
        .transaction-form-actions { display: flex; align-items: flex-end; }
        .form-group { display: flex; flex-direction: column; }
        label { margin-bottom: 8px; color: #555; font-size: 14px; }
        input, select, textarea { padding: 12px 14px; border-radius: 10px; border: 1px solid #d7dbf0; font-size: 15px; font-family: inherit; background: white; }
        textarea { resize: vertical; min-height: 46px; }
        .filters button[type="submit"], .new-transaction-card button[type="submit"] { background: #667eea; color: white; padding: 12px 24px; border: none; border-radius: 12px; font-size: 15px; font-weight: 700; cursor: pointer; }
        .filters button[type="submit"]:hover, .new-transaction-card button[type="submit"]:hover { background: #5669d5; }
        .transactions-container { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #667eea; color: white; padding: 14px; text-align: left; font-weight: 600; }
        td { padding: 14px; border-bottom: 1px solid #e5e9f0; }
        tr:hover { background: #f7f8ff; }
        .type-badge { display: inline-block; padding: 4px 10px; border-radius: 8px; font-size: 12px; font-weight: 600; }
        .type-badge.receita { background: #e8f7ef; color: #209f71; }
        .type-badge.despesa { background: #ffe8e8; color: #ef5f5f; }
        .msg { margin-bottom: 20px; padding: 16px 18px; border-radius: 14px; font-size: 15px; }
        .msg.sucesso { background: #e8f7ef; color: #1f7a47; }
        .msg.erro { background: #ffe8e8; color: #922d2d; }
        .actions-cell { display: flex; gap: 8px; }
        .delete-btn { padding: 8px 12px; border-radius: 8px; border: none; font-size: 13px; cursor: pointer; font-weight: 600; }
        .delete-btn { background: #ff6b6b; color: white; }
        .delete-btn:hover { background: #ff5252; }
        .color-dot { width: 12px; height: 12px; border-radius: 50%; display: inline-block; margin-right: 6px; }
        .no-data { text-align: center; padding: 40px; color: #999; }
        @media (max-width: 768px) {
            .stats { grid-template-columns: 1fr; }
            .filter-row { grid-template-columns: 1fr; }
            .transaction-form-grid { grid-template-columns: 1fr; }
            .transaction-form-grid .description-field { grid-column: auto; }
            table { font-size: 14px; }
            th, td { padding: 10px; }
        }
    </style>
    <?php require dirname(__DIR__) . '/includes/transacao_edit_styles.php'; ?>
</head>
<body>
    <div class="container">
        <h1>Movimentações</h1>
        <p class="subtitle">Visualize e gerencie todas as suas transações.</p>

        <div class="top-row">
            <div class="actions">
                <a href="/dashboard" class="button">Dashboard</a>
                <a href="/categorias" class="button">Categorias</a>
                <a href="/logout" class="button">Sair</a>
            </div>
        </div>

        <?php if ($mensagem !== ''): ?>
            <div class="msg <?= $tipoMensagem === 'erro' ? 'erro' : 'sucesso' ?>">
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
                        <select id="nova-categoria" name="categoria_id">
                            <option value="">Sem categoria</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="novo-valor">Valor total (R$)</label>
                        <input type="text" id="novo-valor" name="valor" inputmode="decimal" placeholder="1500,00" required>
                    </div>

                    <div class="form-group">
                        <label for="novas-parcelas">Parcelas</label>
                        <select id="novas-parcelas" name="parcelas">
                            <option value="1">À vista (1x)</option>
                            <?php for ($parcela = 2; $parcela <= 12; $parcela++): ?>
                                <option value="<?= $parcela ?>"><?= $parcela ?>x</option>
                            <?php endfor; ?>
                        </select>
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

                <button type="submit" class="button" style="margin-top: 18px;">Salvar movimentação</button>
            </form>
        </section>

        <div class="stats">
            <div class="stat-card receita">
                <h4>Total de Receitas</h4>
                <div class="stat-value">R$ <?= number_format($totalReceitas, 2, ',', '.') ?></div>
            </div>
            <div class="stat-card despesa">
                <h4>Total de Despesas</h4>
                <div class="stat-value">R$ <?= number_format($totalDespesas, 2, ',', '.') ?></div>
            </div>
            <div class="stat-card">
                <h4>Saldo do Período</h4>
                <div class="stat-value">R$ <?= number_format($saldo, 2, ',', '.') ?></div>
            </div>
        </div>

        <div class="filters">
            <h3>Filtros</h3>
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

        <?php if (count($transacoes) === 0): ?>
            <div class="no-data">
                <p>Nenhuma transação encontrada para este período.</p>
            </div>
        <?php else: ?>
            <div class="transactions-container">
                <table>
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th>Tipo</th>
                            <th>Categoria</th>
                            <th>Descrição</th>
                            <th>Valor</th>
                            <th>Parcela</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transacoes as $t): ?>
                            <tr>
                                <td><?= date('d/m/Y', strtotime($t['data'])) ?></td>
                                <td>
                                    <span class="type-badge <?= $t['tipo'] ?>">
                                        <?= $t['tipo'] === 'receita' ? 'Receita' : 'Despesa' ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($t['categoria']): ?>
                                        <span class="color-dot" style="background-color: <?= htmlspecialchars($t['cor'], ENT_QUOTES, 'UTF-8') ?>"></span>
                                        <?= htmlspecialchars($t['categoria'], ENT_QUOTES, 'UTF-8') ?>
                                    <?php else: ?>
                                        <span style="color: #999;">Sem categoria</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($t['descricao'] ?: '-', ENT_QUOTES, 'UTF-8') ?></td>
                                <td style="font-weight: 600; color: <?= $t['tipo'] === 'receita' ? '#209f71' : '#ef5f5f' ?>">
                                    <?= $t['tipo'] === 'receita' ? '+' : '-' ?> R$ <?= number_format($t['valor'], 2, ',', '.') ?>
                                </td>
                                <td>
                                    <?php if ($t['total_parcelas'] > 1): ?>
                                        <span style="font-size: 13px; color: #666;"><?= (int)$t['numero_parcela'] ?>/<?= (int)$t['total_parcelas'] ?></span>
                                    <?php else: ?>
                                        <span style="font-size: 13px; color: #999;">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="actions-cell">
                                        <button
                                            type="button"
                                            class="tx-edit-btn"
                                            onclick="abrirEdicao(<?= (int) $t['id'] ?>, this)"
                                        >Editar</button>
                                        <form method="post" action="<?= htmlspecialchars($movimentacoesAction, ENT_QUOTES, 'UTF-8') ?>" style="display: inline;">
                                            <input type="hidden" name="acao" value="deletar">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                            <input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
                                            <button type="submit" class="delete-btn" onclick="return confirm('Deseja realmente excluir esta movimentação?')">Excluir</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <?php
    $edicaoAction = $movimentacoesAction;
    require dirname(__DIR__) . '/includes/transacao_edit_modal.php';
    ?>

    <script>
        const novaMovimentacaoTipo = document.getElementById('nova-tipo');
        const novaMovimentacaoCategoria = document.getElementById('nova-categoria');

        function atualizarCategoriasNovaMovimentacao() {
            novaMovimentacaoCategoria.innerHTML = '<option value="">Sem categoria</option>';

            categoriasEdicaoData
                .filter(categoria => categoria.tipo === novaMovimentacaoTipo.value)
                .forEach(categoria => {
                    const opcao = document.createElement('option');
                    opcao.value = categoria.id;
                    opcao.textContent = categoria.nome;
                    novaMovimentacaoCategoria.appendChild(opcao);
                });
        }

        novaMovimentacaoTipo.addEventListener('change', atualizarCategoriasNovaMovimentacao);
        atualizarCategoriasNovaMovimentacao();
    </script>
</body>
</html>
