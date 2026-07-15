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
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; padding: 20px; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 32px; border-radius: 18px; box-shadow: 0 18px 50px rgba(0,0,0,0.15); }
        h1 { color: #333; margin-bottom: 8px; font-size: 34px; }
        .subtitle { color: #777; margin-bottom: 28px; font-size: 16px; }
        .top-row { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 30px; }
        .card { background: #f7f8ff; border-radius: 16px; padding: 24px; box-shadow: inset 0 0 0 1px rgba(102, 126, 234, 0.08); }
        .card h2 { margin-bottom: 12px; color: #555; font-size: 16px; }
        .card strong { font-size: 28px; color: #222; display: block; }
        .card.balance { background: linear-gradient(135deg, #667eea, #764ba2); color: white; }
        .card.balance strong { color: #fff; }
        .actions { display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 30px; }
        a.button { padding: 12px 22px; background: #667eea; color: white; text-decoration: none; border-radius: 10px; font-weight: 600; transition: transform 0.2s, box-shadow 0.2s; }
        a.button:hover { transform: translateY(-2px); box-shadow: 0 10px 30px rgba(102, 126, 234, 0.25); }
        .grid { display: grid; grid-template-columns: minmax(280px, 0.8fr) minmax(0, 1.4fr); gap: 24px; }
        .form-card, .table-card, .info-card { background: #f7f8ff; border-radius: 18px; padding: 24px; }
        .table-card { overflow-x: auto; }
        .form-card h3, .table-card h3, .info-card h3 { margin-bottom: 18px; color: #333; }
        .form-group { margin-bottom: 16px; }
        label { display: block; margin-bottom: 8px; color: #555; font-size: 14px; }
        input, select, textarea { width: 100%; padding: 14px 16px; border-radius: 12px; border: 1px solid #d7dbf0; font-size: 15px; background: white; }
        textarea { min-height: 100px; resize: vertical; }
        button { background: #667eea; color: white; border: none; padding: 14px 20px; border-radius: 12px; font-size: 15px; font-weight: 700; cursor: pointer; transition: transform 0.2s; }
        button:hover { transform: translateY(-2px); }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: 14px 12px; border-bottom: 1px solid #e8ebf8; }
        th { color: #666; font-size: 14px; text-transform: uppercase; letter-spacing: .02em; }
        td { color: #333; font-size: 15px; }
        .tag { display: inline-flex; align-items: center; gap: 8px; padding: 8px 12px; border-radius: 999px; font-size: 13px; font-weight: 700; }
        .tag.receita { background: rgba(32, 159, 113, 0.12); color: #209f71; }
        .tag.despesa { background: rgba(239, 95, 95, 0.12); color: #ef5f5f; }
        .msg { margin-bottom: 20px; padding: 16px 18px; border-radius: 14px; font-size: 15px; }
        .msg.sucesso { background: #e8f7ef; color: #1f7a47; }
        .msg.erro { background: #ffe8e8; color: #922d2d; }
        .info-card p { line-height: 1.8; color: #555; }
        .color-dot { width: 12px; height: 12px; border-radius: 50%; display: inline-block; margin-right: 6px; }
        @media (max-width: 768px) {
            .top-row { grid-template-columns: 1fr; }
            .grid { grid-template-columns: 1fr; }
        }
    </style>
    <?php require dirname(__DIR__) . '/includes/transacao_edit_styles.php'; ?>
</head>
<body>
    <div class="container">
        <h1>Bem-vindo ao seu Controle Financeiro, <?= htmlspecialchars($_SESSION['usuario_nome'] ?? 'Usuário', ENT_QUOTES, 'UTF-8') ?></h1>
        <p class="subtitle">Aqui você pode registrar suas receitas e despesas, ver o saldo e acompanhar os últimos lançamentos.</p>

        <?php if ($mensagem !== ''): ?>
            <div class="msg <?= $tipoMensagem === 'erro' ? 'erro' : 'sucesso' ?>">
                <?= htmlspecialchars($mensagem, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <div class="actions" style="justify-content: space-between; align-items: center; flex-wrap: wrap;">
            <div style="display: flex; gap: 12px; flex-wrap: wrap;">
                <a href="/logout" class="button logout-btn">Sair</a>
                <a href="/relatorios" class="button">Relatórios</a>
                <a href="/categorias" class="button">Categorias</a>
                <a href="/movimentacoes" class="button">Movimentações</a>
            </div>
            <form method="get" action="/dashboard" style="display: flex; gap: 12px; flex-wrap: wrap; align-items: center;">
                <label style="font-size: 14px; color: #444; display: flex; align-items: center; gap: 6px;">
                    Início
                    <input type="date" name="inicio" value="<?= htmlspecialchars($inicioFiltro, ENT_QUOTES, 'UTF-8') ?>" style="width: auto;">
                </label>
                <label style="font-size: 14px; color: #444; display: flex; align-items: center; gap: 6px;">
                    Fim
                    <input type="date" name="fim" value="<?= htmlspecialchars($fimFiltro, ENT_QUOTES, 'UTF-8') ?>" style="width: auto;">
                </label>
                <button type="submit" style="padding: 12px 18px;">Filtrar</button>
            </form>
        </div>

        <?php if (!$periodoValido): ?>
            <div class="msg erro"><?= htmlspecialchars($periodoMensagem, ENT_QUOTES, 'UTF-8') ?></div>
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
                <p style="margin-top: 8px; color: rgba(255,255,255,0.85); font-size: 13px;">Período: <?= htmlspecialchars($inicioFiltro, ENT_QUOTES, 'UTF-8') ?> até <?= htmlspecialchars($fimFiltro, ENT_QUOTES, 'UTF-8') ?></p>
            </div>
        </div>

        <div class="grid">
            <div class="form-card">
                <h3>Registrar nova transação</h3>
                <form method="post" action="<?= htmlspecialchars($dashboardAction, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="acao" value="adicionar_transacao">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

                    <div class="form-group">
                        <label for="tipo">Tipo</label>
                        <select id="tipo" name="tipo" required onchange="atualizarCategorias()">
                            <option value="receita">Receita</option>
                            <option value="despesa">Despesa</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="categoria_id">Categoria</label>
                        <select id="categoria_id" name="categoria_id">
                            <option value="">Sem categoria</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="valor">Valor</label>
                        <input type="text" id="valor" name="valor" placeholder="R$ 1500,00" required>
                    </div>

                    <div class="form-group">
                        <label for="parcelas">Parcelas</label>
                        <select id="parcelas" name="parcelas">
                            <option value="1">À vista (1x)</option>
                            <option value="2">2x</option>
                            <option value="3">3x</option>
                            <option value="4">4x</option>
                            <option value="5">5x</option>
                            <option value="6">6x</option>
                            <option value="7">7x</option>
                            <option value="8">8x</option>
                            <option value="9">9x</option>
                            <option value="10">10x</option>
                            <option value="11">11x</option>
                            <option value="12">12x</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="data">Data</label>
                        <input type="date" id="data" name="data" required value="<?= date('Y-m-d') ?>">
                    </div>

                    <div class="form-group">
                        <label for="descricao">Descrição</label>
                        <textarea id="descricao" name="descricao" placeholder="Opcional: Observações sobre essa transação"></textarea>
                    </div>

                    <button type="submit">Salvar transação</button>
                </form>
            </div>

            <div class="table-card">
                <h3>Últimos lançamentos</h3>
                <?php if (count($transacoes) === 0): ?>
                    <p>Nenhuma transação registrada ainda. Use o formulário ao lado para começar.</p>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Tipo</th>
                                <th>Categoria</th>
                                <th>Descrição</th>
                                <th>Valor</th>
                                <th>Parcela</th>
                                <th>Data</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($transacoes as $transacao): ?>
                                <tr>
                                    <td><span class="tag <?= htmlspecialchars($transacao['tipo'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(ucfirst($transacao['tipo']), ENT_QUOTES, 'UTF-8') ?></span></td>
                                    <td>
                                        <?php if ($transacao['categoria']): ?>
                                            <span class="color-dot" style="background-color: <?= htmlspecialchars($transacao['cor'], ENT_QUOTES, 'UTF-8') ?>"></span>
                                            <?= htmlspecialchars($transacao['categoria'], ENT_QUOTES, 'UTF-8') ?>
                                        <?php else: ?>
                                            <span style="color: #999;">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($transacao['descricao'] ?: '-', ENT_QUOTES, 'UTF-8') ?></td>
                                    <td>R$ <?= number_format((float)$transacao['valor'], 2, ',', '.') ?></td>
                                    <td>
                                        <?php if ($transacao['total_parcelas'] > 1): ?>
                                            <span style="font-size: 13px; color: #666;"><?= (int)$transacao['numero_parcela'] ?>/<?= (int)$transacao['total_parcelas'] ?></span>
                                        <?php else: ?>
                                            <span style="font-size: 13px; color: #999;">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= date('d/m/Y', strtotime($transacao['data'])) ?></td>
                                    <td>
                                        <button
                                            type="button"
                                            class="tx-edit-btn"
                                            onclick="abrirEdicao(<?= (int) $transacao['id'] ?>, this)"
                                        >Editar</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <div class="info-card" style="margin-top: 24px;">
            <h3>Informações rápidas</h3>
            <p>Use este painel para registrar receitas e despesas diariamente. Os valores são somados automaticamente e o saldo mostra a diferença entre receitas e despesas.</p>
            <p>Acesse <strong>Categorias</strong> para criar e gerenciar suas categorias personalizadas.</p>
        </div>
    </div>

    <?php
    $edicaoAction = $dashboardAction;
    require dirname(__DIR__) . '/includes/transacao_edit_modal.php';
    ?>

    <script>
        const categoriasData = <?= $categoriasJson ?>;

        function atualizarCategorias() {
            const tipo = document.getElementById('tipo').value;
            const select = document.getElementById('categoria_id');

            select.innerHTML = '<option value="">Sem categoria</option>';

            categoriasData
                .filter(c => c.tipo === tipo)
                .forEach(c => {
                    const opt = document.createElement('option');
                    opt.value = c.id;
                    opt.textContent = c.nome;
                    select.appendChild(opt);
                });
        }

        atualizarCategorias();
    </script>
</body>
</html>
