<?php

declare(strict_types=1);

if (!isset($pdo)) {
    throw new RuntimeException('O objeto $pdo não está disponível em categorias_controller.php.');
}

$usuarioId = (int) ($_SESSION['usuario_id'] ?? 0);

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
