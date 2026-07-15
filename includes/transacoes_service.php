<?php

declare(strict_types=1);

/**
 * Mantém a estrutura usada pelo dashboard e pela página de movimentações alinhada.
 */
function garantirEstruturaTransacoes(PDO $pdo): void
{
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
        categoria VARCHAR(100) NOT NULL DEFAULT 'Sem categoria',
        descricao VARCHAR(255) DEFAULT NULL,
        valor DECIMAL(10,2) NOT NULL,
        valor_original DECIMAL(10,2) NOT NULL,
        data DATE NOT NULL,
        numero_parcela INT DEFAULT 1,
        total_parcelas INT DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (usuario_id) REFERENCES Usuarios(id) ON DELETE CASCADE,
        FOREIGN KEY (categoria_id) REFERENCES categorias(id) ON DELETE SET NULL,
        INDEX (data),
        INDEX (usuario_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $colunasObrigatorias = [
        'valor_original' => 'ALTER TABLE transacoes ADD COLUMN valor_original DECIMAL(10,2) NOT NULL DEFAULT 0',
        'numero_parcela' => 'ALTER TABLE transacoes ADD COLUMN numero_parcela INT DEFAULT 1',
        'total_parcelas' => 'ALTER TABLE transacoes ADD COLUMN total_parcelas INT DEFAULT 1',
        'categoria_id' => 'ALTER TABLE transacoes ADD COLUMN categoria_id INT NULL AFTER usuario_id',
        'categoria' => "ALTER TABLE transacoes ADD COLUMN categoria VARCHAR(100) NOT NULL DEFAULT 'Sem categoria' AFTER tipo",
    ];

    $colunasExistentes = $pdo->query(
        "SELECT COLUMN_NAME
         FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'transacoes'"
    )->fetchAll(PDO::FETCH_COLUMN);
    $mapaColunas = array_fill_keys($colunasExistentes, true);

    foreach ($colunasObrigatorias as $coluna => $sql) {
        if (!isset($mapaColunas[$coluna])) {
            $pdo->exec($sql);
        }
    }
}

function tokenCsrfTransacoes(): string
{
    if (!isset($_SESSION['csrf_transacoes']) || !is_string($_SESSION['csrf_transacoes'])) {
        $_SESSION['csrf_transacoes'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_transacoes'];
}

function tokenCsrfTransacoesValido(mixed $token): bool
{
    return is_string($token)
        && isset($_SESSION['csrf_transacoes'])
        && is_string($_SESSION['csrf_transacoes'])
        && hash_equals($_SESSION['csrf_transacoes'], $token);
}

function dataTransacaoValida(string $data): bool
{
    $dataNormalizada = DateTimeImmutable::createFromFormat('!Y-m-d', $data);
    $erros = DateTimeImmutable::getLastErrors();

    return $dataNormalizada !== false
        && ($erros === false || ($erros['warning_count'] === 0 && $erros['error_count'] === 0))
        && $dataNormalizada->format('Y-m-d') === $data;
}

/**
 * Aceita valores como 1500,00, 1500.00 e R$ 1.500,00.
 */
function normalizarValorTransacao(string $valor): ?string
{
    $valor = trim(str_replace(["\u{00A0}", 'R$', ' '], '', $valor));

    if ($valor === '') {
        return null;
    }

    if (preg_match('/^\d{1,3}(?:\.\d{3})+,\d{1,2}$/', $valor)) {
        $valor = str_replace('.', '', $valor);
        $valor = str_replace(',', '.', $valor);
    } elseif (preg_match('/^\d{1,3}(?:,\d{3})+\.\d{1,2}$/', $valor)) {
        $valor = str_replace(',', '', $valor);
    } elseif (preg_match('/^\d{1,3}(?:[.,]\d{3})+$/', $valor)) {
        $valor = str_replace([',', '.'], '', $valor);
    } elseif (preg_match('/^\d+(?:[.,]\d{1,2})?$/', $valor)) {
        $valor = str_replace(',', '.', $valor);
    } else {
        return null;
    }

    $numero = (float) $valor;
    if ($numero <= 0 || $numero > 99999999.99) {
        return null;
    }

    return number_format($numero, 2, '.', '');
}

function tamanhoTextoTransacao(string $texto): int
{
    return function_exists('mb_strlen') ? mb_strlen($texto, 'UTF-8') : strlen($texto);
}

/**
 * @return array{valida: bool, nome: string}
 */
function obterDadosCategoriaTransacao(PDO $pdo, int $usuarioId, ?int $categoriaId, string $tipo): array
{
    if ($categoriaId === null) {
        return ['valida' => true, 'nome' => 'Sem categoria'];
    }

    $stmt = $pdo->prepare(
        'SELECT nome
         FROM categorias
         WHERE id = :id AND usuario_id = :usuario_id AND tipo = :tipo'
    );
    $stmt->execute([
        ':id' => $categoriaId,
        ':usuario_id' => $usuarioId,
        ':tipo' => $tipo,
    ]);

    $nome = $stmt->fetchColumn();

    if (!is_string($nome) || $nome === '') {
        return ['valida' => false, 'nome' => ''];
    }

    return ['valida' => true, 'nome' => $nome];
}

/**
 * @return array{sucesso: bool, mensagem: string}
 */
function criarTransacao(PDO $pdo, int $usuarioId, array $dados): array
{
    $tipo = is_string($dados['tipo'] ?? null) ? $dados['tipo'] : '';
    $descricao = trim(is_string($dados['descricao'] ?? null) ? $dados['descricao'] : '');
    $valor = normalizarValorTransacao(is_scalar($dados['valor'] ?? null) ? (string) $dados['valor'] : '');
    $data = trim(is_string($dados['data'] ?? null) ? $dados['data'] : '');
    $parcelasRaw = is_scalar($dados['parcelas'] ?? null) ? (string) $dados['parcelas'] : '1';
    $parcelas = ctype_digit($parcelasRaw) ? (int) $parcelasRaw : 0;
    $categoriaResultado = obterCategoriaIdTransacao($dados['categoria_id'] ?? null);

    if (!in_array($tipo, ['receita', 'despesa'], true)) {
        return ['sucesso' => false, 'mensagem' => 'Selecione se a movimentação é uma receita ou despesa.'];
    }

    if ($valor === null) {
        return ['sucesso' => false, 'mensagem' => 'Informe um valor válido maior que zero, com no máximo duas casas decimais.'];
    }

    if (!dataTransacaoValida($data)) {
        return ['sucesso' => false, 'mensagem' => 'Informe uma data válida.'];
    }

    if ($parcelas < 1 || $parcelas > 12) {
        return ['sucesso' => false, 'mensagem' => 'O número de parcelas deve estar entre 1 e 12.'];
    }

    if (tamanhoTextoTransacao($descricao) > 255) {
        return ['sucesso' => false, 'mensagem' => 'A descrição deve ter no máximo 255 caracteres.'];
    }

    if (!$categoriaResultado['valida']) {
        return ['sucesso' => false, 'mensagem' => 'Selecione uma categoria válida.'];
    }

    $categoriaId = $categoriaResultado['id'];
    $dadosCategoria = obterDadosCategoriaTransacao($pdo, $usuarioId, $categoriaId, $tipo);
    if (!$dadosCategoria['valida']) {
        return ['sucesso' => false, 'mensagem' => 'A categoria selecionada não pertence a este tipo de movimentação.'];
    }

    $valorTotalCentavos = (int) round(((float) $valor) * 100);
    if ($valorTotalCentavos < $parcelas) {
        return ['sucesso' => false, 'mensagem' => 'O valor total é muito baixo para a quantidade de parcelas selecionada.'];
    }

    $valorBaseCentavos = intdiv($valorTotalCentavos, $parcelas);
    $centavosRestantes = $valorTotalCentavos % $parcelas;
    $dataBase = DateTimeImmutable::createFromFormat('!Y-m-d', $data);

    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare(
            'INSERT INTO transacoes
                (usuario_id, categoria_id, tipo, categoria, descricao, valor, valor_original, data, numero_parcela, total_parcelas)
             VALUES
                (:usuario_id, :categoria_id, :tipo, :categoria, :descricao, :valor, :valor_original, :data, :numero_parcela, :total_parcelas)'
        );

        for ($i = 1; $i <= $parcelas; $i++) {
            $valorParcelaCentavos = $valorBaseCentavos + ($i <= $centavosRestantes ? 1 : 0);
            $dataParcela = calcularDataParcela($dataBase, $i - 1);

            $stmt->execute([
                ':usuario_id' => $usuarioId,
                ':categoria_id' => $categoriaId,
                ':tipo' => $tipo,
                ':categoria' => $dadosCategoria['nome'],
                ':descricao' => $descricao,
                ':valor' => number_format($valorParcelaCentavos / 100, 2, '.', ''),
                ':valor_original' => $valor,
                ':data' => $dataParcela->format('Y-m-d'),
                ':numero_parcela' => $i,
                ':total_parcelas' => $parcelas,
            ]);
        }

        $pdo->commit();
    } catch (Throwable $erro) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log($erro->getMessage());

        return ['sucesso' => false, 'mensagem' => 'Não foi possível registrar a movimentação. Tente novamente.'];
    }

    $mensagem = $parcelas > 1
        ? "Movimentação registrada com sucesso em {$parcelas} parcelas."
        : 'Movimentação registrada com sucesso.';

    return ['sucesso' => true, 'mensagem' => $mensagem];
}

/**
 * @return array{sucesso: bool, mensagem: string}
 */
function editarTransacao(PDO $pdo, int $usuarioId, array $dados): array
{
    $idRaw = is_scalar($dados['id'] ?? null) ? (string) $dados['id'] : '';
    $id = ctype_digit($idRaw) ? (int) $idRaw : 0;
    $tipo = is_string($dados['tipo'] ?? null) ? $dados['tipo'] : '';
    $descricao = trim(is_string($dados['descricao'] ?? null) ? $dados['descricao'] : '');
    $valor = normalizarValorTransacao(is_scalar($dados['valor'] ?? null) ? (string) $dados['valor'] : '');
    $data = trim(is_string($dados['data'] ?? null) ? $dados['data'] : '');
    $preservarCategoriaLegada = ($dados['categoria_id'] ?? null) === '__legacy__';
    $categoriaResultado = $preservarCategoriaLegada
        ? ['valida' => true, 'id' => null]
        : obterCategoriaIdTransacao($dados['categoria_id'] ?? null);

    if ($id <= 0) {
        return ['sucesso' => false, 'mensagem' => 'Movimentação não encontrada.'];
    }

    if (!in_array($tipo, ['receita', 'despesa'], true)) {
        return ['sucesso' => false, 'mensagem' => 'Selecione um tipo de movimentação válido.'];
    }

    if ($valor === null) {
        return ['sucesso' => false, 'mensagem' => 'Informe um valor válido maior que zero, com no máximo duas casas decimais.'];
    }

    if (!dataTransacaoValida($data)) {
        return ['sucesso' => false, 'mensagem' => 'Informe uma data válida.'];
    }

    if (tamanhoTextoTransacao($descricao) > 255) {
        return ['sucesso' => false, 'mensagem' => 'A descrição deve ter no máximo 255 caracteres.'];
    }

    if (!$categoriaResultado['valida']) {
        return ['sucesso' => false, 'mensagem' => 'Selecione uma categoria válida.'];
    }

    $stmtExistente = $pdo->prepare(
        'SELECT id, categoria_id, categoria, total_parcelas
         FROM transacoes
         WHERE id = :id AND usuario_id = :usuario_id'
    );
    $stmtExistente->execute([':id' => $id, ':usuario_id' => $usuarioId]);
    $transacaoExistente = $stmtExistente->fetch();

    if (!$transacaoExistente) {
        return ['sucesso' => false, 'mensagem' => 'Movimentação não encontrada.'];
    }

    $categoriaId = $categoriaResultado['id'];
    $dadosCategoria = obterDadosCategoriaTransacao($pdo, $usuarioId, $categoriaId, $tipo);
    if (!$dadosCategoria['valida']) {
        return ['sucesso' => false, 'mensagem' => 'A categoria selecionada não pertence a este tipo de movimentação.'];
    }

    // Registros antigos podem ter somente a categoria textual. Mantemos esse valor
    // ao editar outros campos para não apagar informação histórica.
    if (
        $preservarCategoriaLegada
        && $categoriaId === null
        && is_string($transacaoExistente['categoria'])
        && trim($transacaoExistente['categoria']) !== ''
        && $transacaoExistente['categoria'] !== 'Sem categoria'
    ) {
        $dadosCategoria['nome'] = $transacaoExistente['categoria'];
    }

    try {
        $stmt = $pdo->prepare(
            'UPDATE transacoes
             SET tipo = :tipo,
                 categoria = :categoria,
                 descricao = :descricao,
                 valor = :valor,
                 valor_original = CASE WHEN total_parcelas = 1 THEN :valor_original ELSE valor_original END,
                 data = :data,
                 categoria_id = :categoria_id
             WHERE id = :id AND usuario_id = :usuario_id'
        );
        $stmt->execute([
            ':tipo' => $tipo,
            ':categoria' => $dadosCategoria['nome'],
            ':descricao' => $descricao,
            ':valor' => $valor,
            ':valor_original' => $valor,
            ':data' => $data,
            ':categoria_id' => $categoriaId,
            ':id' => $id,
            ':usuario_id' => $usuarioId,
        ]);
    } catch (Throwable $erro) {
        error_log($erro->getMessage());

        return ['sucesso' => false, 'mensagem' => 'Não foi possível atualizar a movimentação. Tente novamente.'];
    }

    $mensagem = (int) $transacaoExistente['total_parcelas'] > 1
        ? 'Parcela atualizada com sucesso. As demais parcelas não foram alteradas.'
        : 'Movimentação atualizada com sucesso.';

    return ['sucesso' => true, 'mensagem' => $mensagem];
}

/**
 * @return array{sucesso: bool, mensagem: string}
 */
function excluirTransacao(PDO $pdo, int $usuarioId, mixed $idInformado): array
{
    $idRaw = is_scalar($idInformado) ? (string) $idInformado : '';
    $id = ctype_digit($idRaw) ? (int) $idRaw : 0;

    if ($id <= 0) {
        return ['sucesso' => false, 'mensagem' => 'Movimentação não encontrada.'];
    }

    try {
        $stmt = $pdo->prepare('DELETE FROM transacoes WHERE id = :id AND usuario_id = :usuario_id');
        $stmt->execute([':id' => $id, ':usuario_id' => $usuarioId]);
    } catch (Throwable $erro) {
        error_log($erro->getMessage());

        return ['sucesso' => false, 'mensagem' => 'Não foi possível excluir a movimentação. Tente novamente.'];
    }

    if ($stmt->rowCount() === 0) {
        return ['sucesso' => false, 'mensagem' => 'Movimentação não encontrada.'];
    }

    return ['sucesso' => true, 'mensagem' => 'Movimentação excluída com sucesso.'];
}

/**
 * @return array{valida: bool, id: int|null}
 */
function obterCategoriaIdTransacao(mixed $categoriaInformada): array
{
    if ($categoriaInformada === null || $categoriaInformada === '') {
        return ['valida' => true, 'id' => null];
    }

    if (!is_scalar($categoriaInformada)) {
        return ['valida' => false, 'id' => null];
    }

    $categoriaRaw = (string) $categoriaInformada;
    if (!ctype_digit($categoriaRaw) || (int) $categoriaRaw <= 0) {
        return ['valida' => false, 'id' => null];
    }

    return ['valida' => true, 'id' => (int) $categoriaRaw];
}

function calcularDataParcela(DateTimeImmutable $dataBase, int $mesesDepois): DateTimeImmutable
{
    if ($mesesDepois === 0) {
        return $dataBase;
    }

    $primeiroDiaMes = $dataBase->modify("first day of +{$mesesDepois} month");
    $dia = min((int) $dataBase->format('d'), (int) $primeiroDiaMes->format('t'));

    return $primeiroDiaMes->setDate(
        (int) $primeiroDiaMes->format('Y'),
        (int) $primeiroDiaMes->format('m'),
        $dia
    );
}

/**
 * @param array{sucesso: bool, mensagem: string} $resultado
 */
function definirFlashTransacoes(array $resultado): void
{
    $_SESSION['flash_transacoes'] = [
        'mensagem' => $resultado['mensagem'],
        'tipo' => $resultado['sucesso'] ? 'sucesso' : 'erro',
    ];
}

/**
 * @return array{mensagem: string, tipo: string}
 */
function consumirFlashTransacoes(): array
{
    $flash = $_SESSION['flash_transacoes'] ?? null;
    unset($_SESSION['flash_transacoes']);

    if (!is_array($flash) || !is_string($flash['mensagem'] ?? null)) {
        return ['mensagem' => '', 'tipo' => 'sucesso'];
    }

    return [
        'mensagem' => $flash['mensagem'],
        'tipo' => ($flash['tipo'] ?? '') === 'erro' ? 'erro' : 'sucesso',
    ];
}
