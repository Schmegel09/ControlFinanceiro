<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/Core/proteger.php';

function buscarUsuarioPorEmail(PDO $pdo, string $email): array|false
{
    $stmt = $pdo->prepare(
        'SELECT id, nome, email, senha, email_verificado_em, papel_sistema
         FROM Usuarios WHERE email = :email LIMIT 1'
    );
    $stmt->execute([':email' => $email]);

    return $stmt->fetch();
}

function criarUsuario(PDO $pdo, string $nome, string $email, string $senhaHash): int
{
    $stmt = $pdo->prepare(
        'INSERT INTO Usuarios (nome, email, senha) VALUES (:nome, :email, :senha)'
    );
    $stmt->execute([
        ':nome' => $nome,
        ':email' => $email,
        ':senha' => $senhaHash,
    ]);

    return (int) $pdo->lastInsertId();
}

function atualizarSenhaUsuario(PDO $pdo, int $usuarioId, string $senhaHash): void
{
    $stmt = $pdo->prepare('UPDATE Usuarios SET senha = :senha WHERE id = :id');
    $stmt->execute([':senha' => $senhaHash, ':id' => $usuarioId]);
}
