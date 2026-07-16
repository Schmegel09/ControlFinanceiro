<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/Core/proteger.php';

const MAX_PARCELAS_TRANSACAO = 120;

/**
 * Mantém a estrutura usada pelo dashboard e pela página de movimentações alinhada.
 */
function garantirEstruturaTransacoes(PDO $pdo): void
{
    static $estruturaVerificada = false;
    if ($estruturaVerificada) {
        return;
    }

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
        grupo_parcelamento VARCHAR(64) DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (usuario_id) REFERENCES Usuarios(id) ON DELETE CASCADE,
        FOREIGN KEY (categoria_id) REFERENCES categorias(id) ON DELETE SET NULL,
        INDEX (data),
        INDEX (usuario_id),
        INDEX idx_transacoes_grupo (grupo_parcelamento)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $colunasObrigatorias = [
        'valor_original' => 'ALTER TABLE transacoes ADD COLUMN valor_original DECIMAL(10,2) NOT NULL DEFAULT 0',
        'numero_parcela' => 'ALTER TABLE transacoes ADD COLUMN numero_parcela INT DEFAULT 1',
        'total_parcelas' => 'ALTER TABLE transacoes ADD COLUMN total_parcelas INT DEFAULT 1',
        'grupo_parcelamento' => 'ALTER TABLE transacoes ADD COLUMN grupo_parcelamento VARCHAR(64) DEFAULT NULL AFTER total_parcelas',
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

    $indiceGrupoExiste = (bool) $pdo->query(
        "SELECT 1
         FROM INFORMATION_SCHEMA.STATISTICS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'transacoes'
           AND COLUMN_NAME = 'grupo_parcelamento'
           AND SEQ_IN_INDEX = 1
         LIMIT 1"
    )->fetchColumn();

    if (!$indiceGrupoExiste) {
        $pdo->exec('ALTER TABLE transacoes ADD INDEX idx_transacoes_grupo (grupo_parcelamento)');
    }

    vincularParcelasLegadas($pdo);
    $estruturaVerificada = true;
}

/**
 * Vincula registros criados antes da existência do identificador de grupo.
 * As parcelas sempre foram inseridas em sequência. A numeração crescente
 * permite recuperar o grupo mesmo que alguma parcela tenha sido excluída.
 */
function vincularParcelasLegadas(PDO $pdo): void
{
    $possuiRegistrosSemGrupo = (bool) $pdo->query(
        "SELECT 1
         FROM transacoes
         WHERE grupo_parcelamento IS NULL OR grupo_parcelamento = ''
         LIMIT 1"
    )->fetchColumn();

    if (!$possuiRegistrosSemGrupo) {
        return;
    }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->query(
            "SELECT id, usuario_id, valor_original, numero_parcela, total_parcelas
             FROM transacoes
             WHERE grupo_parcelamento IS NULL OR grupo_parcelamento = ''
             ORDER BY usuario_id, id
             FOR UPDATE"
        );
        $registros = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $atualizar = $pdo->prepare(
            'UPDATE transacoes
             SET grupo_parcelamento = :grupo
             WHERE id = :id AND usuario_id = :usuario_id'
        );
        $chaveAnterior = null;
        $parcelaAnterior = 0;
        $grupoAtual = '';

        foreach ($registros as $registro) {
            $totalParcelas = max(1, (int) ($registro['total_parcelas'] ?? 1));
            $numeroParcela = max(1, (int) ($registro['numero_parcela'] ?? 1));
            // Tipo, categoria e descrição não participam da chave porque uma
            // versão anterior permitia alterá-los em somente uma das parcelas.
            $chave = serialize([
                (string) $registro['usuario_id'],
                (string) $registro['valor_original'],
                (string) $totalParcelas,
            ]);
            $continuaGrupo = $totalParcelas > 1
                && $chave === $chaveAnterior
                && $numeroParcela > $parcelaAnterior;

            if (!$continuaGrupo) {
                $grupoAtual = gerarGrupoParcelamento();
            }

            $atualizar->execute([
                ':grupo' => $grupoAtual,
                ':id' => (int) $registro['id'],
                ':usuario_id' => (int) $registro['usuario_id'],
            ]);

            $chaveAnterior = $chave;
            $parcelaAnterior = $numeroParcela;
        }

        $pdo->commit();
    } catch (Throwable $erro) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $erro;
    }
}

function gerarGrupoParcelamento(): string
{
    return bin2hex(random_bytes(16));
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
function obterDadosCategoriaTransacao(PDO $pdo, int $carteiraId, ?int $categoriaId, string $tipo): array
{
    if ($categoriaId === null) {
        return ['valida' => true, 'nome' => 'Sem categoria'];
    }

    $stmt = $pdo->prepare(
        'SELECT nome
         FROM categorias
         WHERE id = :id AND carteira_id = :carteira_id AND tipo = :tipo'
    );
    $stmt->execute([
        ':id' => $categoriaId,
        ':carteira_id' => $carteiraId,
        ':tipo' => $tipo,
    ]);

    $nome = $stmt->fetchColumn();

    if (!is_string($nome) || $nome === '') {
        return ['valida' => false, 'nome' => ''];
    }

    return ['valida' => true, 'nome' => $nome];
}

/**
 * Insere todas as parcelas de uma movimentação, distribuindo também
 * eventuais centavos restantes entre as primeiras parcelas.
 */
function inserirParcelasTransacao(
    PDO $pdo,
    int $usuarioId,
    int $carteiraId,
    ?int $categoriaId,
    string $categoriaNome,
    string $tipo,
    string $descricao,
    string $valorTotal,
    string $data,
    int $parcelas,
    string $grupoParcelamento
): void {
    $valorTotalCentavos = (int) round(((float) $valorTotal) * 100);
    $valorBaseCentavos = intdiv($valorTotalCentavos, $parcelas);
    $centavosRestantes = $valorTotalCentavos % $parcelas;
    $dataBase = DateTimeImmutable::createFromFormat('!Y-m-d', $data);

    if (!$dataBase instanceof DateTimeImmutable) {
        throw new InvalidArgumentException('Data base inválida para o parcelamento.');
    }

    $stmt = $pdo->prepare(
        'INSERT INTO transacoes
            (usuario_id, carteira_id, categoria_id, tipo, categoria, descricao, valor, valor_original,
             data, numero_parcela, total_parcelas, grupo_parcelamento)
         VALUES
            (:usuario_id, :carteira_id, :categoria_id, :tipo, :categoria, :descricao, :valor, :valor_original,
             :data, :numero_parcela, :total_parcelas, :grupo_parcelamento)'
    );

    for ($i = 1; $i <= $parcelas; $i++) {
        $valorParcelaCentavos = $valorBaseCentavos + ($i <= $centavosRestantes ? 1 : 0);
        $dataParcela = calcularDataParcela($dataBase, $i - 1);

        $stmt->execute([
            ':usuario_id' => $usuarioId,
            ':carteira_id' => $carteiraId,
            ':categoria_id' => $categoriaId,
            ':tipo' => $tipo,
            ':categoria' => $categoriaNome,
            ':descricao' => $descricao,
            ':valor' => number_format($valorParcelaCentavos / 100, 2, '.', ''),
            ':valor_original' => $valorTotal,
            ':data' => $dataParcela->format('Y-m-d'),
            ':numero_parcela' => $i,
            ':total_parcelas' => $parcelas,
            ':grupo_parcelamento' => $grupoParcelamento,
        ]);
    }
}

/**
 * @return array{sucesso: bool, mensagem: string}
 */
function criarTransacao(PDO $pdo, int $usuarioId, int $carteiraId, array $dados): array
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

    // Receitas são sempre registradas em um único lançamento.
    if ($tipo === 'receita') {
        $parcelas = 1;
    }

    if ($valor === null) {
        return ['sucesso' => false, 'mensagem' => 'Informe um valor válido maior que zero, com no máximo duas casas decimais.'];
    }

    if (!dataTransacaoValida($data)) {
        return ['sucesso' => false, 'mensagem' => 'Informe uma data válida.'];
    }

    if ($parcelas < 1 || $parcelas > MAX_PARCELAS_TRANSACAO) {
        return [
            'sucesso' => false,
            'mensagem' => 'O número de parcelas deve estar entre 1 e ' . MAX_PARCELAS_TRANSACAO . '.',
        ];
    }

    if (tamanhoTextoTransacao($descricao) > 255) {
        return ['sucesso' => false, 'mensagem' => 'A descrição deve ter no máximo 255 caracteres.'];
    }

    if (!$categoriaResultado['valida']) {
        return ['sucesso' => false, 'mensagem' => 'Selecione uma categoria válida.'];
    }

    $categoriaId = $categoriaResultado['id'];
    $dadosCategoria = obterDadosCategoriaTransacao($pdo, $carteiraId, $categoriaId, $tipo);
    if (!$dadosCategoria['valida']) {
        return ['sucesso' => false, 'mensagem' => 'A categoria selecionada não pertence a este tipo de movimentação.'];
    }

    $valorTotalCentavos = (int) round(((float) $valor) * 100);
    if ($valorTotalCentavos < $parcelas) {
        return ['sucesso' => false, 'mensagem' => 'O valor total é muito baixo para a quantidade de parcelas selecionada.'];
    }

    try {
        $pdo->beginTransaction();
        inserirParcelasTransacao(
            $pdo,
            $usuarioId,
            $carteiraId,
            $categoriaId,
            $dadosCategoria['nome'],
            $tipo,
            $descricao,
            $valor,
            $data,
            $parcelas,
            gerarGrupoParcelamento()
        );

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
function editarTransacao(PDO $pdo, int $carteiraId, array $dados): array
{
    $idRaw = is_scalar($dados['id'] ?? null) ? (string) $dados['id'] : '';
    $id = ctype_digit($idRaw) ? (int) $idRaw : 0;
    $tipo = is_string($dados['tipo'] ?? null) ? $dados['tipo'] : '';
    $descricao = trim(is_string($dados['descricao'] ?? null) ? $dados['descricao'] : '');
    $valor = normalizarValorTransacao(is_scalar($dados['valor'] ?? null) ? (string) $dados['valor'] : '');
    $data = trim(is_string($dados['data'] ?? null) ? $dados['data'] : '');
    $parcelasForamInformadas = array_key_exists('parcelas', $dados);
    $parcelasRaw = is_scalar($dados['parcelas'] ?? null) ? (string) $dados['parcelas'] : '';
    $parcelas = ctype_digit($parcelasRaw) ? (int) $parcelasRaw : 0;
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
        'SELECT id, usuario_id, categoria_id, categoria, total_parcelas, grupo_parcelamento
         FROM transacoes
         WHERE id = :id AND carteira_id = :carteira_id'
    );
    $stmtExistente->execute([':id' => $id, ':carteira_id' => $carteiraId]);
    $transacaoExistente = $stmtExistente->fetch();

    if (!$transacaoExistente) {
        return ['sucesso' => false, 'mensagem' => 'Movimentação não encontrada.'];
    }

    if ($tipo === 'receita') {
        $parcelas = 1;
    } elseif (!$parcelasForamInformadas) {
        $parcelas = max(1, (int) $transacaoExistente['total_parcelas']);
    }

    if ($parcelas < 1 || $parcelas > MAX_PARCELAS_TRANSACAO) {
        return [
            'sucesso' => false,
            'mensagem' => 'O número de parcelas deve estar entre 1 e ' . MAX_PARCELAS_TRANSACAO . '.',
        ];
    }

    $valorTotalCentavos = (int) round(((float) $valor) * 100);
    if ($valorTotalCentavos < $parcelas) {
        return ['sucesso' => false, 'mensagem' => 'O valor total é muito baixo para a quantidade de parcelas selecionada.'];
    }

    $categoriaId = $categoriaResultado['id'];
    $dadosCategoria = obterDadosCategoriaTransacao($pdo, $carteiraId, $categoriaId, $tipo);
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

    $grupoParcelamento = is_string($transacaoExistente['grupo_parcelamento'])
        && $transacaoExistente['grupo_parcelamento'] !== ''
        ? $transacaoExistente['grupo_parcelamento']
        : gerarGrupoParcelamento();

    try {
        $pdo->beginTransaction();

        $stmtExcluir = $pdo->prepare(
            'DELETE FROM transacoes
             WHERE carteira_id = :carteira_id AND grupo_parcelamento = :grupo_parcelamento'
        );
        $stmtExcluir->execute([
            ':carteira_id' => $carteiraId,
            ':grupo_parcelamento' => $grupoParcelamento,
        ]);

        // Compatibilidade defensiva com uma linha antiga ainda sem grupo.
        if ($stmtExcluir->rowCount() === 0) {
            $stmtExcluirLegada = $pdo->prepare(
                'DELETE FROM transacoes WHERE id = :id AND carteira_id = :carteira_id'
            );
            $stmtExcluirLegada->execute([':id' => $id, ':carteira_id' => $carteiraId]);
        }

        inserirParcelasTransacao(
            $pdo,
            (int) $transacaoExistente['usuario_id'],
            $carteiraId,
            $categoriaId,
            $dadosCategoria['nome'],
            $tipo,
            $descricao,
            $valor,
            $data,
            $parcelas,
            $grupoParcelamento
        );

        $pdo->commit();
    } catch (Throwable $erro) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log($erro->getMessage());

        return ['sucesso' => false, 'mensagem' => 'Não foi possível atualizar a movimentação. Tente novamente.'];
    }

    $mensagem = $parcelas > 1
        ? "Movimentação e suas {$parcelas} parcelas foram atualizadas com sucesso."
        : 'Movimentação atualizada com sucesso.';

    return ['sucesso' => true, 'mensagem' => $mensagem];
}

/**
 * @return array{sucesso: bool, mensagem: string}
 */
function excluirTransacao(PDO $pdo, int $carteiraId, mixed $idInformado): array
{
    $idRaw = is_scalar($idInformado) ? (string) $idInformado : '';
    $id = ctype_digit($idRaw) ? (int) $idRaw : 0;

    if ($id <= 0) {
        return ['sucesso' => false, 'mensagem' => 'Movimentação não encontrada.'];
    }

    try {
        $stmt = $pdo->prepare('DELETE FROM transacoes WHERE id = :id AND carteira_id = :carteira_id');
        $stmt->execute([':id' => $id, ':carteira_id' => $carteiraId]);
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
 * Exclui todas as movimentações da carteira em uso, sem afetar outras carteiras ou categorias.
 *
 * @return array{sucesso: bool, mensagem: string}
 */
function excluirTodasTransacoes(PDO $pdo, int $carteiraId): array
{
    if ($carteiraId <= 0) {
        return ['sucesso' => false, 'mensagem' => 'Carteira inválida.'];
    }

    try {
        $stmt = $pdo->prepare('DELETE FROM transacoes WHERE carteira_id = :carteira_id');
        $stmt->execute([':carteira_id' => $carteiraId]);
        $totalExcluido = $stmt->rowCount();
    } catch (Throwable $erro) {
        error_log($erro->getMessage());

        return ['sucesso' => false, 'mensagem' => 'Não foi possível excluir todos os lançamentos. Tente novamente.'];
    }

    if ($totalExcluido === 0) {
        return ['sucesso' => false, 'mensagem' => 'Nenhum lançamento foi encontrado para exclusão.'];
    }

    $mensagem = $totalExcluido === 1
        ? '1 lançamento foi excluído com sucesso.'
        : "{$totalExcluido} lançamentos foram excluídos com sucesso.";

    return ['sucesso' => true, 'mensagem' => $mensagem];
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
