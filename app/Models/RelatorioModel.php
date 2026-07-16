<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/Core/proteger.php';

/**
 * @return array<string, array{receita: float, despesa: float}>
 */
function buscarResumoCategoriasPeriodo(
    PDO $pdo,
    int $carteiraId,
    string $inicio,
    string $fim
): array {
    $stmt = $pdo->prepare(
        'SELECT categoria, tipo, SUM(valor) AS total
         FROM transacoes
         WHERE carteira_id = :carteira_id
           AND data BETWEEN :inicio AND :fim
         GROUP BY categoria, tipo
         ORDER BY total DESC'
    );
    $stmt->execute([
        ':carteira_id' => $carteiraId,
        ':inicio' => $inicio,
        ':fim' => $fim,
    ]);

    $categorias = [];
    while ($registro = $stmt->fetch()) {
        $categoria = (string) $registro['categoria'];
        if (!isset($categorias[$categoria])) {
            $categorias[$categoria] = ['receita' => 0.0, 'despesa' => 0.0];
        }
        $categorias[$categoria][$registro['tipo']] = (float) $registro['total'];
    }

    return $categorias;
}
