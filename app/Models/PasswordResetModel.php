<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/Core/proteger.php';

function garantirEstruturaRecuperacaoSenha(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS password_resets (
        id INT AUTO_INCREMENT PRIMARY KEY,
        usuario_id INT NOT NULL,
        codigo VARCHAR(6) NOT NULL,
        expires_at DATETIME NOT NULL,
        tentativas INT DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX (codigo),
        FOREIGN KEY (usuario_id) REFERENCES Usuarios(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function substituirCodigoRecuperacao(
    PDO $pdo,
    int $usuarioId,
    string $codigo,
    string $expiraEm
): void {
    $pdo->beginTransaction();

    try {
        excluirCodigosRecuperacao($pdo, $usuarioId);
        $stmt = $pdo->prepare(
            'INSERT INTO password_resets (usuario_id, codigo, expires_at)
             VALUES (:usuario_id, :codigo, :expires_at)'
        );
        $stmt->execute([
            ':usuario_id' => $usuarioId,
            ':codigo' => $codigo,
            ':expires_at' => $expiraEm,
        ]);
        $pdo->commit();
    } catch (Throwable $erro) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $erro;
    }
}

function codigoRecuperacaoValido(PDO $pdo, int $usuarioId, string $codigo): bool
{
    $stmt = $pdo->prepare(
        'SELECT 1 FROM password_resets
         WHERE usuario_id = :usuario_id
           AND codigo = :codigo
           AND expires_at >= NOW()
         LIMIT 1'
    );
    $stmt->execute([':usuario_id' => $usuarioId, ':codigo' => $codigo]);

    return $stmt->fetchColumn() !== false;
}

function excluirCodigosRecuperacao(PDO $pdo, int $usuarioId): void
{
    $stmt = $pdo->prepare('DELETE FROM password_resets WHERE usuario_id = :usuario_id');
    $stmt->execute([':usuario_id' => $usuarioId]);
}
