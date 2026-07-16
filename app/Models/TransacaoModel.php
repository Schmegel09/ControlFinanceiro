<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/Core/proteger.php';

const CAMPOS_TRANSACAO_LISTAGEM = "
    t.id,
    t.categoria_id,
    t.tipo,
    COALESCE(c.nome, NULLIF(t.categoria, ''), 'Sem categoria') AS categoria,
    COALESCE(c.cor, '#999999') AS cor,
    t.descricao,
    t.valor,
    COALESCE(NULLIF(t.valor_original, 0), pg.valor_total_grupo, t.valor) AS valor_original,
    t.data,
    COALESCE(pg.data_inicio_grupo, t.data) AS data_inicio,
    t.numero_parcela,
    t.total_parcelas,
    t.grupo_parcelamento,
    u.nome AS registrado_por";

const JOINS_TRANSACAO_LISTAGEM = "
    LEFT JOIN categorias c
      ON c.id = t.categoria_id AND c.carteira_id = t.carteira_id
    LEFT JOIN Usuarios u ON u.id = t.usuario_id
    LEFT JOIN (
        SELECT carteira_id, grupo_parcelamento,
               SUM(valor) AS valor_total_grupo,
               MIN(data) AS data_inicio_grupo
        FROM transacoes
        GROUP BY carteira_id, grupo_parcelamento
    ) pg
      ON pg.carteira_id = t.carteira_id
     AND pg.grupo_parcelamento = t.grupo_parcelamento";

function listarTransacoesPeriodo(
    PDO $pdo,
    int $carteiraId,
    string $inicio,
    string $fim,
    string $tipo = ''
): array {
    $sql = 'SELECT ' . CAMPOS_TRANSACAO_LISTAGEM . '
            FROM transacoes t ' . JOINS_TRANSACAO_LISTAGEM . '
            WHERE t.carteira_id = :carteira_id
              AND t.data BETWEEN :inicio AND :fim';
    $parametros = [
        ':carteira_id' => $carteiraId,
        ':inicio' => $inicio,
        ':fim' => $fim,
    ];

    if ($tipo !== '') {
        $sql .= ' AND t.tipo = :tipo';
        $parametros[':tipo'] = $tipo;
    }

    $sql .= ' ORDER BY t.data DESC, t.id DESC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($parametros);

    return $stmt->fetchAll();
}

function listarUltimasTransacoes(
    PDO $pdo,
    int $carteiraId,
    string $inicio,
    string $fim,
    int $limite = 10
): array {
    $limite = max(1, min(100, $limite));
    $sql = 'SELECT ' . CAMPOS_TRANSACAO_LISTAGEM . '
            FROM transacoes t ' . JOINS_TRANSACAO_LISTAGEM . '
            WHERE t.carteira_id = :carteira_id
              AND t.data BETWEEN :inicio AND :fim
            ORDER BY t.data DESC, t.id DESC
            LIMIT ' . $limite;
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':carteira_id' => $carteiraId,
        ':inicio' => $inicio,
        ':fim' => $fim,
    ]);

    return $stmt->fetchAll();
}

/**
 * @return array{receitas: float, despesas: float, saldo: float}
 */
function buscarTotaisTransacoes(PDO $pdo, int $carteiraId, string $inicio, string $fim): array
{
    $stmt = $pdo->prepare(
        "SELECT
             SUM(CASE WHEN tipo = 'receita' THEN valor ELSE 0 END) AS receitas,
             SUM(CASE WHEN tipo = 'despesa' THEN valor ELSE 0 END) AS despesas
         FROM transacoes
         WHERE carteira_id = :carteira_id AND data BETWEEN :inicio AND :fim"
    );
    $stmt->execute([
        ':carteira_id' => $carteiraId,
        ':inicio' => $inicio,
        ':fim' => $fim,
    ]);
    $totais = $stmt->fetch() ?: [];
    $receitas = (float) ($totais['receitas'] ?? 0);
    $despesas = (float) ($totais['despesas'] ?? 0);

    return [
        'receitas' => $receitas,
        'despesas' => $despesas,
        'saldo' => $receitas - $despesas,
    ];
}

function listarCategoriasCarteira(PDO $pdo, int $carteiraId): array
{
    $stmt = $pdo->prepare(
        'SELECT id, nome, tipo, cor
         FROM categorias
         WHERE carteira_id = :carteira_id
         ORDER BY tipo, nome'
    );
    $stmt->execute([':carteira_id' => $carteiraId]);

    return $stmt->fetchAll();
}

function contarTransacoesCarteira(PDO $pdo, int $carteiraId): int
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM transacoes WHERE carteira_id = :carteira_id');
    $stmt->execute([':carteira_id' => $carteiraId]);

    return (int) $stmt->fetchColumn();
}

function buscarTransacaoCarteira(PDO $pdo, int $carteiraId, int $id): array|false
{
    $stmt = $pdo->prepare(
        'SELECT id, tipo, descricao, valor, valor_original, data, categoria_id,
                numero_parcela, total_parcelas, grupo_parcelamento
         FROM transacoes
         WHERE id = :id AND carteira_id = :carteira_id'
    );
    $stmt->execute([':id' => $id, ':carteira_id' => $carteiraId]);

    return $stmt->fetch();
}

/**
 * @return array<int, array{periodo: string, rotulo: string, receitas: float, despesas: float}>
 */
function buscarEvolucaoFinanceiraPeriodo(
    PDO $pdo,
    int $carteiraId,
    string $inicio,
    string $fim
): array {
    $inicioData = new DateTimeImmutable($inicio);
    $fimData = new DateTimeImmutable($fim);
    $totalDias = (int) $inicioData->diff($fimData)->format('%a');

    if ($totalDias <= 45) {
        $formatoSql = '%Y-%m-%d';
        $formatoRotulo = 'd/m';
    } elseif ($totalDias <= 730) {
        $formatoSql = '%Y-%m';
        $formatoRotulo = 'm/Y';
    } else {
        $formatoSql = '%Y';
        $formatoRotulo = 'Y';
    }

    $stmt = $pdo->prepare(
        "SELECT DATE_FORMAT(data, '{$formatoSql}') AS periodo,
                SUM(CASE WHEN tipo = 'receita' THEN valor ELSE 0 END) AS receitas,
                SUM(CASE WHEN tipo = 'despesa' THEN valor ELSE 0 END) AS despesas
         FROM transacoes
         WHERE carteira_id = :carteira_id
           AND data BETWEEN :inicio AND :fim
         GROUP BY DATE_FORMAT(data, '{$formatoSql}')
         ORDER BY periodo"
    );
    $stmt->execute([
        ':carteira_id' => $carteiraId,
        ':inicio' => $inicio,
        ':fim' => $fim,
    ]);

    $resultado = [];
    foreach ($stmt->fetchAll() as $registro) {
        $periodo = (string) $registro['periodo'];
        $dataRotulo = match ($formatoSql) {
            '%Y-%m-%d' => DateTimeImmutable::createFromFormat('!Y-m-d', $periodo),
            '%Y-%m' => DateTimeImmutable::createFromFormat('!Y-m', $periodo),
            default => DateTimeImmutable::createFromFormat('!Y', $periodo),
        };

        $resultado[] = [
            'periodo' => $periodo,
            'rotulo' => $dataRotulo instanceof DateTimeImmutable ? $dataRotulo->format($formatoRotulo) : $periodo,
            'receitas' => (float) $registro['receitas'],
            'despesas' => (float) $registro['despesas'],
        ];
    }

    return $resultado;
}

/**
 * @return array<int, array{categoria: string, cor: string, total: float}>
 */
function buscarDespesasPorCategoria(
    PDO $pdo,
    int $carteiraId,
    string $inicio,
    string $fim
): array {
    $stmt = $pdo->prepare(
        "SELECT COALESCE(c.nome, NULLIF(t.categoria, ''), 'Sem categoria') AS categoria,
                COALESCE(c.cor, '#9aa3b7') AS cor,
                SUM(t.valor) AS total
         FROM transacoes t
         LEFT JOIN categorias c
           ON c.id = t.categoria_id AND c.carteira_id = t.carteira_id
         WHERE t.carteira_id = :carteira_id
           AND t.tipo = 'despesa'
           AND t.data BETWEEN :inicio AND :fim
         GROUP BY categoria, cor
         ORDER BY total DESC"
    );
    $stmt->execute([
        ':carteira_id' => $carteiraId,
        ':inicio' => $inicio,
        ':fim' => $fim,
    ]);

    return array_map(
        static fn (array $registro): array => [
            'categoria' => (string) $registro['categoria'],
            'cor' => preg_match('/^#[0-9a-fA-F]{6}$/', (string) $registro['cor'])
                ? strtolower((string) $registro['cor'])
                : '#9aa3b7',
            'total' => (float) $registro['total'],
        ],
        $stmt->fetchAll()
    );
}
