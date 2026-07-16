<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/Core/proteger.php';

function categoriaDuplicada(
    PDO $pdo,
    int $carteiraId,
    string $nome,
    string $tipo,
    int $ignorarId = 0
): bool {
    $sql = 'SELECT id FROM categorias
            WHERE carteira_id = :carteira_id AND nome = :nome AND tipo = :tipo';
    $parametros = [
        ':carteira_id' => $carteiraId,
        ':nome' => $nome,
        ':tipo' => $tipo,
    ];

    if ($ignorarId > 0) {
        $sql .= ' AND id <> :id';
        $parametros[':id'] = $ignorarId;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($parametros);

    return $stmt->fetchColumn() !== false;
}

function inserirCategoria(
    PDO $pdo,
    int $usuarioId,
    int $carteiraId,
    string $nome,
    string $tipo,
    string $cor
): void {
    $stmt = $pdo->prepare(
        'INSERT INTO categorias (usuario_id, carteira_id, nome, tipo, cor)
         VALUES (:usuario_id, :carteira_id, :nome, :tipo, :cor)'
    );
    $stmt->execute([
        ':usuario_id' => $usuarioId,
        ':carteira_id' => $carteiraId,
        ':nome' => $nome,
        ':tipo' => $tipo,
        ':cor' => $cor,
    ]);
}

function buscarCategoriaCarteira(PDO $pdo, int $carteiraId, int $id): array|false
{
    $stmt = $pdo->prepare(
        'SELECT id, nome, tipo, cor
         FROM categorias WHERE id = :id AND carteira_id = :carteira_id'
    );
    $stmt->execute([':id' => $id, ':carteira_id' => $carteiraId]);

    return $stmt->fetch();
}

function contarTransacoesCategoria(PDO $pdo, int $carteiraId, int $id): int
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM transacoes
         WHERE categoria_id = :id AND carteira_id = :carteira_id'
    );
    $stmt->execute([':id' => $id, ':carteira_id' => $carteiraId]);

    return (int) $stmt->fetchColumn();
}

function atualizarCategoria(
    PDO $pdo,
    int $carteiraId,
    int $id,
    string $nome,
    string $tipo,
    string $cor
): void {
    $pdo->beginTransaction();

    try {
        $stmt = $pdo->prepare(
            'UPDATE categorias SET nome = :nome, tipo = :tipo, cor = :cor
             WHERE id = :id AND carteira_id = :carteira_id'
        );
        $stmt->execute([
            ':nome' => $nome,
            ':tipo' => $tipo,
            ':cor' => $cor,
            ':id' => $id,
            ':carteira_id' => $carteiraId,
        ]);

        $stmt = $pdo->prepare(
            'UPDATE transacoes SET categoria = :nome
             WHERE categoria_id = :id AND carteira_id = :carteira_id'
        );
        $stmt->execute([':nome' => $nome, ':id' => $id, ':carteira_id' => $carteiraId]);
        $pdo->commit();
    } catch (Throwable $erro) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $erro;
    }
}

function excluirCategoria(PDO $pdo, int $carteiraId, int $id): bool
{
    $pdo->beginTransaction();

    try {
        $stmt = $pdo->prepare(
            'UPDATE transacoes SET categoria_id = NULL
             WHERE categoria_id = :id AND carteira_id = :carteira_id'
        );
        $stmt->execute([':id' => $id, ':carteira_id' => $carteiraId]);

        $stmt = $pdo->prepare(
            'DELETE FROM categorias WHERE id = :id AND carteira_id = :carteira_id'
        );
        $stmt->execute([':id' => $id, ':carteira_id' => $carteiraId]);
        $excluida = $stmt->rowCount() > 0;

        if (!$excluida) {
            $pdo->rollBack();
            return false;
        }

        $pdo->commit();
        return true;
    } catch (Throwable $erro) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $erro;
    }
}

function listarCategoriasComTotais(PDO $pdo, int $carteiraId): array
{
    $stmt = $pdo->prepare(
        'SELECT c.id, c.nome, c.tipo, c.cor, c.criada_em,
                COUNT(t.id) AS total_movimentacoes
         FROM categorias c
         LEFT JOIN transacoes t
           ON t.categoria_id = c.id AND t.carteira_id = c.carteira_id
         WHERE c.carteira_id = :carteira_id
         GROUP BY c.id, c.nome, c.tipo, c.cor, c.criada_em
         ORDER BY c.tipo DESC, c.nome ASC, c.id ASC'
    );
    $stmt->execute([':carteira_id' => $carteiraId]);

    return $stmt->fetchAll();
}
