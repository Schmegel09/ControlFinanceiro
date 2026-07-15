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

$mensagem = '';
$tipoMensagem = 'sucesso';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'adicionar') {
        $nome = trim($_POST['nome'] ?? '');
        $tipo = $_POST['tipo'] ?? '';
        $cor = trim($_POST['cor'] ?? '#667eea');

        if ($nome === '' || !in_array($tipo, ['receita', 'despesa'], true)) {
            $mensagem = 'Preencha todos os campos corretamente.';
            $tipoMensagem = 'erro';
        } else {
            try {
                $stmt = $pdo->prepare(
                    'INSERT INTO categorias (usuario_id, nome, tipo, cor)
                     VALUES (:usuario_id, :nome, :tipo, :cor)'
                );

                $stmt->execute([
                    ':usuario_id' => $usuarioId,
                    ':nome' => $nome,
                    ':tipo' => $tipo,
                    ':cor' => $cor,
                ]);

                $mensagem = 'Categoria criada com sucesso.';
                $tipoMensagem = 'sucesso';
            } catch (PDOException $e) {
                if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                    $mensagem = 'Esta categoria já existe.';
                } else {
                    $mensagem = 'Erro ao criar categoria.';
                }
                $tipoMensagem = 'erro';
            }
        }
    } elseif ($acao === 'deletar') {
        $id = (int) ($_POST['id'] ?? 0);

        if ($id > 0) {
            $stmt = $pdo->prepare('DELETE FROM categorias WHERE id = :id AND usuario_id = :usuario_id');
            $stmt->execute([':id' => $id, ':usuario_id' => $usuarioId]);

            if ($stmt->rowCount() > 0) {
                $mensagem = 'Categoria deletada com sucesso.';
                $tipoMensagem = 'sucesso';
            } else {
                $mensagem = 'Categoria não encontrada.';
                $tipoMensagem = 'erro';
            }
        }
    }
}

$categorias = $pdo->prepare(
    'SELECT id, nome, tipo, cor, criada_em FROM categorias WHERE usuario_id = :usuario_id ORDER BY tipo, nome'
);
$categorias->execute([':usuario_id' => $usuarioId]);
$categoriasList = $categorias->fetchAll();

$categoriasReceita = array_filter($categoriasList, function ($c) {
    return $c['tipo'] === 'receita';
});
$categoriasDespesa = array_filter($categoriasList, function ($c) {
    return $c['tipo'] === 'despesa';
});

?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Categorias - Controle Financeiro</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; padding: 20px; }
        .container { max-width: 1000px; margin: 0 auto; background: white; padding: 32px; border-radius: 18px; box-shadow: 0 18px 50px rgba(0,0,0,0.15); }
        h1 { color: #333; margin-bottom: 8px; font-size: 34px; }
        .subtitle { color: #777; margin-bottom: 24px; font-size: 16px; }
        .top-row { display: flex; justify-content: space-between; flex-wrap: wrap; gap: 16px; margin-bottom: 28px; }
        .actions { display: flex; gap: 12px; flex-wrap: wrap; }
        a.button, button.button { padding: 12px 22px; background: #667eea; color: white; text-decoration: none; border: none; border-radius: 10px; font-weight: 600; transition: transform 0.2s, box-shadow 0.2s; cursor: pointer; }
        a.button:hover, button.button:hover { transform: translateY(-2px); box-shadow: 0 10px 30px rgba(102, 126, 234, 0.25); }
        .form-card { background: #f7f8ff; border-radius: 18px; padding: 24px; margin-bottom: 24px; }
        .form-card h3 { margin-bottom: 18px; color: #333; }
        .form-row { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-bottom: 16px; }
        .form-group { display: flex; flex-direction: column; }
        label { margin-bottom: 8px; color: #555; font-size: 14px; }
        input, select { padding: 12px 14px; border-radius: 10px; border: 1px solid #d7dbf0; font-size: 15px; }
        button[type="submit"] { background: #667eea; color: white; padding: 12px 24px; border: none; border-radius: 12px; font-size: 15px; font-weight: 700; cursor: pointer; }
        button[type="submit"]:hover { background: #5669d5; }
        .list-card { background: #f7f8ff; border-radius: 18px; padding: 24px; }
        .list-card h3 { margin-bottom: 18px; color: #333; }
        .category-item { display: grid; grid-template-columns: auto 1fr auto auto; gap: 16px; align-items: center; padding: 16px; background: white; border-radius: 12px; margin-bottom: 12px; border-left: 4px solid; }
        .category-item.receita { border-left-color: #209f71; }
        .category-item.despesa { border-left-color: #ef5f5f; }
        .color-box { width: 24px; height: 24px; border-radius: 6px; }
        .category-info h4 { margin-bottom: 4px; color: #333; }
        .category-info small { color: #666; }
        .msg { margin-bottom: 20px; padding: 16px 18px; border-radius: 14px; font-size: 15px; }
        .msg.sucesso { background: #e8f7ef; color: #1f7a47; }
        .msg.erro { background: #ffe8e8; color: #922d2d; }
        .delete-btn { padding: 8px 12px; background: #ff6b6b; color: white; text-decoration: none; border: none; border-radius: 8px; font-size: 13px; cursor: pointer; }
        .delete-btn:hover { background: #ff5252; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Categorias</h1>
        <p class="subtitle">Crie e gerencie as categorias para suas receitas e despesas.</p>

        <div class="top-row">
            <div class="actions">
                <a href="/dashboard" class="button">Dashboard</a>
                <a href="/movimentacoes" class="button">Movimentações</a>
                <a href="/logout" class="button logout-btn">Sair</a>
            </div>
        </div>

        <?php if ($mensagem !== ''): ?>
            <div class="msg <?= $tipoMensagem === 'erro' ? 'erro' : 'sucesso' ?>">
                <?= htmlspecialchars($mensagem, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <div class="form-card">
            <h3>Nova categoria</h3>
            <form method="post" action="/categorias">
                <input type="hidden" name="acao" value="adicionar">
                <div class="form-row">
                    <div class="form-group">
                        <label for="nome">Nome</label>
                        <input type="text" id="nome" name="nome" placeholder="Ex: Salário, Alimentação" required>
                    </div>
                    <div class="form-group">
                        <label for="tipo">Tipo</label>
                        <select id="tipo" name="tipo" required>
                            <option value="receita">Receita</option>
                            <option value="despesa">Despesa</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="cor">Cor</label>
                        <input type="color" id="cor" name="cor" value="#667eea">
                    </div>
                </div>
                <button type="submit" class="button">Criar categoria</button>
            </form>
        </div>

        <div class="list-card">
            <h3>Receitas (<?= count($categoriasReceita) ?>)</h3>
            <?php if (count($categoriasReceita) === 0): ?>
                <p>Nenhuma categoria de receita criada.</p>
            <?php else: ?>
                <?php foreach ($categoriasReceita as $cat): ?>
                    <div class="category-item receita">
                        <div class="color-box" style="background-color: <?= htmlspecialchars($cat['cor'], ENT_QUOTES, 'UTF-8') ?>"></div>
                        <div class="category-info">
                            <h4><?= htmlspecialchars($cat['nome'], ENT_QUOTES, 'UTF-8') ?></h4>
                            <small>Criada em <?= date('d/m/Y', strtotime($cat['criada_em'])) ?></small>
                        </div>
                        <form method="post" action="/categorias" style="display: inline;">
                            <input type="hidden" name="acao" value="deletar">
                            <input type="hidden" name="id" value="<?= htmlspecialchars($cat['id'], ENT_QUOTES, 'UTF-8') ?>">
                            <button type="submit" class="delete-btn" onclick="return confirm('Tem certeza?')">Deletar</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="list-card" style="margin-top: 24px;">
            <h3>Despesas (<?= count($categoriasDespesa) ?>)</h3>
            <?php if (count($categoriasDespesa) === 0): ?>
                <p>Nenhuma categoria de despesa criada.</p>
            <?php else: ?>
                <?php foreach ($categoriasDespesa as $cat): ?>
                    <div class="category-item despesa">
                        <div class="color-box" style="background-color: <?= htmlspecialchars($cat['cor'], ENT_QUOTES, 'UTF-8') ?>"></div>
                        <div class="category-info">
                            <h4><?= htmlspecialchars($cat['nome'], ENT_QUOTES, 'UTF-8') ?></h4>
                            <small>Criada em <?= date('d/m/Y', strtotime($cat['criada_em'])) ?></small>
                        </div>
                        <form method="post" action="/categorias" style="display: inline;">
                            <input type="hidden" name="acao" value="deletar">
                            <input type="hidden" name="id" value="<?= htmlspecialchars($cat['id'], ENT_QUOTES, 'UTF-8') ?>">
                            <button type="submit" class="delete-btn" onclick="return confirm('Tem certeza?')">Deletar</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
