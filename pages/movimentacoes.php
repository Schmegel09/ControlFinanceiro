<?php

declare(strict_types=1);

session_start();

require_once dirname(__DIR__) . '/config/conexao.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: /login');
    exit;
}

$usuarioId = (int) $_SESSION['usuario_id'];

$pdo->exec("CREATE TABLE IF NOT EXISTS categorias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    nome VARCHAR(100) NOT NULL,
    tipo ENUM('receita','despesa') NOT NULL,
    cor VARCHAR(7) DEFAULT '#667eea',
    criada_em DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_categoria (usuario_id, nome, tipo),
    FOREIGN KEY (usuario_id) REFERENCES Usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$pdo->exec("CREATE TABLE IF NOT EXISTS transacoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    categoria_id INT NULL,
    tipo ENUM('receita','despesa') NOT NULL,
    descricao VARCHAR(255) NOT NULL,
    valor DECIMAL(10, 2) UNSIGNED NOT NULL,
    valor_original DECIMAL(10,2) NOT NULL,
    data DATE NOT NULL,
    numero_parcela INT DEFAULT 1,
    total_parcelas INT DEFAULT 1,
    criada_em DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES Usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (categoria_id) REFERENCES categorias(id) ON DELETE SET NULL,
    INDEX (data),
    INDEX (usuario_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$mensagem = '';
$tipoMensagem = 'sucesso';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'editar') {
        $id = (int) ($_POST['id'] ?? 0);
        $descricao = trim($_POST['descricao'] ?? '');
        $valor = floatval($_POST['valor'] ?? 0);
        $data = trim($_POST['data'] ?? '');
        $categoriaId = !empty($_POST['categoria_id']) ? (int) $_POST['categoria_id'] : null;

        if ($id > 0 && $descricao !== '' && $valor > 0 && $data !== '') {
            $stmt = $pdo->prepare(
                'UPDATE transacoes
                 SET descricao = :descricao, valor = :valor, data = :data, categoria_id = :categoria_id
                 WHERE id = :id AND usuario_id = :usuario_id'
            );

            try {
                $stmt->execute([
                    ':descricao' => $descricao,
                    ':valor' => $valor,
                    ':data' => $data,
                    ':categoria_id' => $categoriaId,
                    ':id' => $id,
                    ':usuario_id' => $usuarioId,
                ]);
                $mensagem = 'Transação atualizada com sucesso.';
                $tipoMensagem = 'sucesso';
            } catch (PDOException $e) {
                $mensagem = 'Erro ao atualizar transação.';
                $tipoMensagem = 'erro';
            }
        } else {
            $mensagem = 'Preencha todos os campos corretamente.';
            $tipoMensagem = 'erro';
        }
    } elseif ($acao === 'deletar') {
        $id = (int) ($_POST['id'] ?? 0);

        if ($id > 0) {
            $stmt = $pdo->prepare('DELETE FROM transacoes WHERE id = :id AND usuario_id = :usuario_id');
            $stmt->execute([':id' => $id, ':usuario_id' => $usuarioId]);

            if ($stmt->rowCount() > 0) {
                $mensagem = 'Transação deletada com sucesso.';
                $tipoMensagem = 'sucesso';
            } else {
                $mensagem = 'Transação não encontrada.';
                $tipoMensagem = 'erro';
            }
        }
    }
}

$filtroTipo = $_GET['tipo'] ?? '';
$filtroDataInicio = $_GET['inicio'] ?? date('Y-m-01');
$filtroDataFim = $_GET['fim'] ?? date('Y-m-d');

$query = 'SELECT t.id, t.tipo, t.descricao, t.valor, t.data, t.numero_parcela, t.total_parcelas, c.nome AS categoria, c.cor
          FROM transacoes t
          LEFT JOIN categorias c ON t.categoria_id = c.id
          WHERE t.usuario_id = :usuario_id AND t.data BETWEEN :data_inicio AND :data_fim';

$params = [
    ':usuario_id' => $usuarioId,
    ':data_inicio' => $filtroDataInicio,
    ':data_fim' => $filtroDataFim,
];

if ($filtroTipo !== '') {
    $query .= ' AND t.tipo = :tipo';
    $params[':tipo'] = $filtroTipo;
}

$query .= ' ORDER BY t.data DESC, t.id DESC';

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$transacoes = $stmt->fetchAll();

$stmtCategorias = $pdo->prepare('SELECT id, nome, tipo FROM categorias WHERE usuario_id = :usuario_id ORDER BY tipo, nome');
$stmtCategorias->execute([':usuario_id' => $usuarioId]);
$categorias = $stmtCategorias->fetchAll();

$categoriasReceita = array_filter($categorias, function ($c) {
    return $c['tipo'] === 'receita';
});
$categoriasDespesa = array_filter($categorias, function ($c) {
    return $c['tipo'] === 'despesa';
});

$stmtTotais = $pdo->prepare(
    'SELECT SUM(CASE WHEN tipo = "receita" THEN valor ELSE 0 END) AS total_receitas,
            SUM(CASE WHEN tipo = "despesa" THEN valor ELSE 0 END) AS total_despesas
     FROM transacoes
     WHERE usuario_id = :usuario_id AND data BETWEEN :data_inicio AND :data_fim'
);
$stmtTotais->execute([
    ':usuario_id' => $usuarioId,
    ':data_inicio' => $filtroDataInicio,
    ':data_fim' => $filtroDataFim,
]);
$totais = $stmtTotais->fetch();

$totalReceitas = (float) ($totais['total_receitas'] ?? 0);
$totalDespesas = (float) ($totais['total_despesas'] ?? 0);
$saldo = $totalReceitas - $totalDespesas;

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
        .form-group { display: flex; flex-direction: column; }
        label { margin-bottom: 8px; color: #555; font-size: 14px; }
        input, select { padding: 12px 14px; border-radius: 10px; border: 1px solid #d7dbf0; font-size: 15px; }
        button[type="submit"] { background: #667eea; color: white; padding: 12px 24px; border: none; border-radius: 12px; font-size: 15px; font-weight: 700; cursor: pointer; }
        button[type="submit"]:hover { background: #5669d5; }
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
        .edit-btn, .delete-btn { padding: 8px 12px; border-radius: 8px; border: none; font-size: 13px; cursor: pointer; font-weight: 600; }
        .edit-btn { background: #667eea; color: white; }
        .edit-btn:hover { background: #5669d5; }
        .delete-btn { background: #ff6b6b; color: white; }
        .delete-btn:hover { background: #ff5252; }
        .modal { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center; }
        .modal.active { display: flex; animation: fadeIn 0.2s; }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        .modal-content { background: white; padding: 32px; border-radius: 18px; width: 90%; max-width: 500px; max-height: 90vh; overflow-y: auto; animation: slideUp 0.3s; }
        @keyframes slideUp { from { transform: translateY(50px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
        .modal-header { margin-bottom: 20px; }
        .modal-header h2 { color: #333; }
        .close-btn { float: right; font-size: 28px; font-weight: bold; color: #aaa; cursor: pointer; border: none; background: none; }
        .close-btn:hover { color: #000; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px; }
        .form-row.full { grid-template-columns: 1fr; }
        textarea { font-family: inherit; resize: vertical; }
        .color-dot { width: 12px; height: 12px; border-radius: 50%; display: inline-block; margin-right: 6px; }
        .no-data { text-align: center; padding: 40px; color: #999; }
        @media (max-width: 768px) {
            .stats { grid-template-columns: 1fr; }
            .filter-row { grid-template-columns: 1fr; }
            table { font-size: 14px; }
            th, td { padding: 10px; }
        }
    </style>
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
                        <label for="tipo">Tipo</label>
                        <select id="tipo" name="tipo">
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
                                <td><?= htmlspecialchars($t['descricao'], ENT_QUOTES, 'UTF-8') ?></td>
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
                                        <button class="edit-btn" onclick="abrirEdicao(<?= (int) $t['id'] ?>, '<?= htmlspecialchars($t['tipo'], ENT_QUOTES, 'UTF-8') ?>', '<?= (int) ($t['id'] ?? 0) ?>')">Editar</button>
                                        <form method="post" action="/movimentacoes" style="display: inline;">
                                            <input type="hidden" name="acao" value="deletar">
                                            <input type="hidden" name="id" value="<?= htmlspecialchars($t['id'], ENT_QUOTES, 'UTF-8') ?>">
                                            <button type="submit" class="delete-btn" onclick="return confirm('Tem certeza?')">Deletar</button>
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

    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <button class="close-btn" onclick="fecharEdicao()">&times;</button>
                <h2>Editar Transação</h2>
            </div>
            <form method="post" action="/movimentacoes" id="editForm">
                <input type="hidden" name="acao" value="editar">
                <input type="hidden" name="id" id="edit-id">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="edit-data">Data</label>
                        <input type="date" id="edit-data" name="data" required>
                    </div>
                    <div class="form-group">
                        <label for="edit-tipo">Tipo</label>
                        <input type="text" id="edit-tipo" name="tipo-display" disabled>
                    </div>
                </div>

                <div class="form-row full">
                    <div class="form-group">
                        <label for="edit-parcela-info">Parcelamento</label>
                        <input type="text" id="edit-parcela-info" disabled style="background: #f0f0f0;">
                    </div>
                </div>

                <div class="form-row full">
                    <div class="form-group">
                        <label for="edit-categoria">Categoria</label>
                        <select id="edit-categoria" name="categoria_id">
                            <option value="">Sem categoria</option>
                        </select>
                    </div>
                </div>

                <div class="form-row full">
                    <div class="form-group">
                        <label for="edit-descricao">Descrição</label>
                        <textarea id="edit-descricao" name="descricao" rows="4" required></textarea>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="edit-valor">Valor (R$)</label>
                        <input type="number" id="edit-valor" name="valor" step="0.01" min="0.01" required>
                    </div>
                </div>

                <button type="submit" class="button" style="width: 100%;">Salvar alterações</button>
            </form>
        </div>
    </div>

    <script>
        function abrirEdicao(id, tipo, transacaoId) {
            fetch(`/api/transacao/${id}`, { method: 'GET' })
                .then(r => r.json())
                .then(data => {
                    document.getElementById('edit-id').value = data.id;
                    document.getElementById('edit-data').value = data.data;
                    document.getElementById('edit-tipo').value = tipo === 'receita' ? 'Receita' : 'Despesa';
                    document.getElementById('edit-descricao').value = data.descricao;
                    document.getElementById('edit-valor').value = data.valor;
                    
                    // Preencher informação de parcela
                    if (data.total_parcelas > 1) {
                        document.getElementById('edit-parcela-info').value = `Parcela ${data.numero_parcela} de ${data.total_parcelas}`;
                    } else {
                        document.getElementById('edit-parcela-info').value = 'À vista';
                    }
                    
                    const categoriaSelect = document.getElementById('edit-categoria');
                    categoriaSelect.innerHTML = '<option value="">Sem categoria</option>';
                    
                    const categorias = <?= json_encode($categorias) ?>;
                    const filtered = categorias.filter(c => c.tipo === tipo);
                    filtered.forEach(c => {
                        const opt = document.createElement('option');
                        opt.value = c.id;
                        opt.textContent = c.nome;
                        categoriaSelect.appendChild(opt);
                    });
                    
                    if (data.categoria_id) {
                        categoriaSelect.value = data.categoria_id;
                    }
                    
                    document.getElementById('editModal').classList.add('active');
                });
        }

        function fecharEdicao() {
            document.getElementById('editModal').classList.remove('active');
        }

        document.getElementById('editModal').addEventListener('click', function(e) {
            if (e.target === this) fecharEdicao();
        });
    </script>
</body>
</html>
