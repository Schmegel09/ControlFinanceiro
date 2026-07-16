<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/Core/proteger.php';

function garantirEstruturaCarteiras(PDO $pdo): void
{
    static $estruturaVerificada = false;
    if ($estruturaVerificada) {
        return;
    }

    // O índice único é a última etapa da migração. Quando ele existe,
    // evitamos repetir CREATE/ALTER e diversas consultas ao INFORMATION_SCHEMA
    // em toda abertura de página protegida.
    $stmtEstrutura = $pdo->query(
        "SELECT 1
         FROM INFORMATION_SCHEMA.STATISTICS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'categorias'
           AND INDEX_NAME = 'unique_categoria_carteira'
         LIMIT 1"
    );
    if ($stmtEstrutura && $stmtEstrutura->fetchColumn() !== false) {
        $estruturaVerificada = true;
        return;
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS carteiras (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nome VARCHAR(100) NOT NULL,
        tipo ENUM('pessoal','casal') NOT NULL DEFAULT 'pessoal',
        criada_por INT NOT NULL,
        criada_em DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (criada_por) REFERENCES Usuarios(id) ON DELETE CASCADE,
        INDEX idx_carteiras_criador (criada_por)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS carteira_membros (
        carteira_id INT NOT NULL,
        usuario_id INT NOT NULL,
        papel ENUM('administrador','editor') NOT NULL DEFAULT 'editor',
        entrou_em DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (carteira_id, usuario_id),
        FOREIGN KEY (carteira_id) REFERENCES carteiras(id) ON DELETE CASCADE,
        FOREIGN KEY (usuario_id) REFERENCES Usuarios(id) ON DELETE CASCADE,
        INDEX idx_carteira_membros_usuario (usuario_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS carteira_presencas (
        carteira_id INT NOT NULL,
        usuario_id INT NOT NULL,
        visto_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (carteira_id, usuario_id),
        FOREIGN KEY (carteira_id) REFERENCES carteiras(id) ON DELETE CASCADE,
        FOREIGN KEY (usuario_id) REFERENCES Usuarios(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    foreach (['categorias', 'transacoes'] as $tabela) {
        $possuiColuna = (bool) $pdo->query(
            "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = '{$tabela}'
               AND COLUMN_NAME = 'carteira_id'
             LIMIT 1"
        )->fetchColumn();

        if (!$possuiColuna) {
            $pdo->exec("ALTER TABLE {$tabela} ADD COLUMN carteira_id INT NULL AFTER usuario_id");
        }

        $possuiIndice = (bool) $pdo->query(
            "SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = '{$tabela}'
               AND COLUMN_NAME = 'carteira_id'
               AND SEQ_IN_INDEX = 1
             LIMIT 1"
        )->fetchColumn();

        if (!$possuiIndice) {
            $pdo->exec("ALTER TABLE {$tabela} ADD INDEX idx_{$tabela}_carteira (carteira_id)");
        }
    }

    $indiceCategoriaAntigo = (bool) $pdo->query(
        "SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'categorias'
           AND INDEX_NAME = 'unique_categoria'
         LIMIT 1"
    )->fetchColumn();

    if ($indiceCategoriaAntigo) {
        // O MariaDB pode reutilizar o índice único antigo para sustentar a
        // FK de usuario_id. Criamos um índice próprio antes de removê-lo.
        $indiceCategoriaUsuario = (bool) $pdo->query(
            "SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'categorias'
               AND COLUMN_NAME = 'usuario_id'
               AND SEQ_IN_INDEX = 1
               AND INDEX_NAME <> 'unique_categoria'
             LIMIT 1"
        )->fetchColumn();

        if (!$indiceCategoriaUsuario) {
            $pdo->exec('ALTER TABLE categorias ADD INDEX idx_categorias_usuario (usuario_id)');
        }

        $pdo->exec('ALTER TABLE categorias DROP INDEX unique_categoria');
    }

    $indiceCategoriaCarteira = (bool) $pdo->query(
        "SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'categorias'
           AND INDEX_NAME = 'unique_categoria_carteira'
         LIMIT 1"
    )->fetchColumn();

    if (!$indiceCategoriaCarteira) {
        $pdo->exec(
            'ALTER TABLE categorias
             ADD UNIQUE INDEX unique_categoria_carteira (carteira_id, nome, tipo)'
        );
    }

    $estruturaVerificada = true;
}

function garantirCarteiraPessoal(PDO $pdo, int $usuarioId): int
{
    $stmt = $pdo->prepare(
        "SELECT c.id
         FROM carteiras c
         INNER JOIN carteira_membros cm ON cm.carteira_id = c.id
         WHERE cm.usuario_id = :usuario_id AND c.tipo = 'pessoal'
         ORDER BY c.id
         LIMIT 1"
    );
    $stmt->execute([':usuario_id' => $usuarioId]);
    $carteiraId = (int) ($stmt->fetchColumn() ?: 0);

    if ($carteiraId <= 0) {
        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare(
                "INSERT INTO carteiras (nome, tipo, criada_por)
                 VALUES ('Minha carteira', 'pessoal', :usuario_id)"
            );
            $stmt->execute([':usuario_id' => $usuarioId]);
            $carteiraId = (int) $pdo->lastInsertId();

            $stmt = $pdo->prepare(
                "INSERT INTO carteira_membros (carteira_id, usuario_id, papel)
                 VALUES (:carteira_id, :usuario_id, 'administrador')"
            );
            $stmt->execute([':carteira_id' => $carteiraId, ':usuario_id' => $usuarioId]);
            $pdo->commit();
        } catch (Throwable $erro) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $erro;
        }
    }

    $stmt = $pdo->prepare(
        'UPDATE transacoes SET carteira_id = :carteira_id
         WHERE usuario_id = :usuario_id AND carteira_id IS NULL'
    );
    $stmt->execute([':carteira_id' => $carteiraId, ':usuario_id' => $usuarioId]);

    $stmt = $pdo->prepare(
        'UPDATE categorias SET carteira_id = :carteira_id
         WHERE usuario_id = :usuario_id AND carteira_id IS NULL'
    );
    $stmt->execute([':carteira_id' => $carteiraId, ':usuario_id' => $usuarioId]);

    return $carteiraId;
}

function listarCarteirasUsuario(PDO $pdo, int $usuarioId): array
{
    $stmt = $pdo->prepare(
        'SELECT c.id, c.nome, c.tipo, cm.papel
         FROM carteiras c
         INNER JOIN carteira_membros cm ON cm.carteira_id = c.id
         WHERE cm.usuario_id = :usuario_id
         ORDER BY c.tipo, c.nome, c.id'
    );
    $stmt->execute([':usuario_id' => $usuarioId]);

    return $stmt->fetchAll();
}

function buscarCarteiraDoUsuario(PDO $pdo, int $usuarioId, int $carteiraId): array|false
{
    $stmt = $pdo->prepare(
        'SELECT c.id, c.nome, c.tipo, c.criada_por, cm.papel
         FROM carteiras c
         INNER JOIN carteira_membros cm ON cm.carteira_id = c.id
         WHERE c.id = :carteira_id AND cm.usuario_id = :usuario_id
         LIMIT 1'
    );
    $stmt->execute([':carteira_id' => $carteiraId, ':usuario_id' => $usuarioId]);

    return $stmt->fetch();
}

function registrarPresencaCarteira(PDO $pdo, int $usuarioId, int $carteiraId): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO carteira_presencas (carteira_id, usuario_id, visto_em)
         VALUES (:carteira_id, :usuario_id, NOW())
         ON DUPLICATE KEY UPDATE visto_em = NOW()'
    );
    $stmt->execute([':carteira_id' => $carteiraId, ':usuario_id' => $usuarioId]);
}

function listarMembrosCarteira(PDO $pdo, int $carteiraId): array
{
    $stmt = $pdo->prepare(
        "SELECT u.id, u.nome, u.email, cm.papel,
                cp.visto_em,
                CASE WHEN cp.visto_em >= DATE_SUB(NOW(), INTERVAL 2 MINUTE) THEN 1 ELSE 0 END AS online
         FROM carteira_membros cm
         INNER JOIN Usuarios u ON u.id = cm.usuario_id
         LEFT JOIN carteira_presencas cp
           ON cp.carteira_id = cm.carteira_id AND cp.usuario_id = cm.usuario_id
         WHERE cm.carteira_id = :carteira_id
         ORDER BY cm.papel, u.nome"
    );
    $stmt->execute([':carteira_id' => $carteiraId]);

    return $stmt->fetchAll();
}

/**
 * @return array{carteira: array, carteiras: array, membros: array}
 */
function prepararContextoCarteira(PDO $pdo, int $usuarioId): array
{
    garantirEstruturaCarteiras($pdo);
    $pessoalId = garantirCarteiraPessoal($pdo, $usuarioId);
    $carteiras = listarCarteirasUsuario($pdo, $usuarioId);
    $selecionada = (int) ($_SESSION['carteira_id'] ?? $pessoalId);
    $carteira = buscarCarteiraDoUsuario($pdo, $usuarioId, $selecionada);

    if (!$carteira) {
        $selecionada = $pessoalId;
        $carteira = buscarCarteiraDoUsuario($pdo, $usuarioId, $selecionada);
    }

    if (!$carteira) {
        throw new RuntimeException('Não foi possível preparar a carteira do usuário.');
    }

    $_SESSION['carteira_id'] = (int) $carteira['id'];
    registrarPresencaCarteira($pdo, $usuarioId, (int) $carteira['id']);

    return [
        'carteira' => $carteira,
        'carteiras' => $carteiras,
        'membros' => listarMembrosCarteira($pdo, (int) $carteira['id']),
    ];
}

function tokenCsrfCarteiras(): string
{
    if (!isset($_SESSION['csrf_carteiras']) || !is_string($_SESSION['csrf_carteiras'])) {
        $_SESSION['csrf_carteiras'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_carteiras'];
}

function tokenCsrfCarteirasValido(mixed $token): bool
{
    return is_string($token)
        && isset($_SESSION['csrf_carteiras'])
        && is_string($_SESSION['csrf_carteiras'])
        && hash_equals($_SESSION['csrf_carteiras'], $token);
}

/**
 * @return array{sucesso: bool, mensagem: string, carteira_id?: int}
 */
function criarCarteiraCasal(PDO $pdo, int $usuarioId, string $nome): array
{
    $nome = trim($nome);
    if ($nome === '') {
        $nome = 'Carteira do casal';
    }

    if ((function_exists('mb_strlen') ? mb_strlen($nome, 'UTF-8') : strlen($nome)) > 100) {
        return ['sucesso' => false, 'mensagem' => 'O nome da carteira deve ter no máximo 100 caracteres.'];
    }

    $stmt = $pdo->prepare(
        "SELECT c.id FROM carteiras c
         INNER JOIN carteira_membros cm ON cm.carteira_id = c.id
         WHERE cm.usuario_id = :usuario_id AND c.tipo = 'casal'
         LIMIT 1"
    );
    $stmt->execute([':usuario_id' => $usuarioId]);
    if ($stmt->fetchColumn() !== false) {
        return ['sucesso' => false, 'mensagem' => 'Você já participa de uma carteira do casal.'];
    }

    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare(
            "INSERT INTO carteiras (nome, tipo, criada_por)
             VALUES (:nome, 'casal', :usuario_id)"
        );
        $stmt->execute([':nome' => $nome, ':usuario_id' => $usuarioId]);
        $carteiraId = (int) $pdo->lastInsertId();

        $stmt = $pdo->prepare(
            "INSERT INTO carteira_membros (carteira_id, usuario_id, papel)
             VALUES (:carteira_id, :usuario_id, 'administrador')"
        );
        $stmt->execute([':carteira_id' => $carteiraId, ':usuario_id' => $usuarioId]);
        $pdo->commit();
    } catch (Throwable $erro) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log($erro->getMessage());
        return ['sucesso' => false, 'mensagem' => 'Não foi possível criar a carteira do casal.'];
    }

    return ['sucesso' => true, 'mensagem' => 'Carteira do casal criada com sucesso.', 'carteira_id' => $carteiraId];
}

/**
 * @return array{sucesso: bool, mensagem: string}
 */
function adicionarParceiroCarteira(
    PDO $pdo,
    int $usuarioId,
    int $carteiraId,
    string $email
): array {
    $carteira = buscarCarteiraDoUsuario($pdo, $usuarioId, $carteiraId);
    if (!$carteira || $carteira['tipo'] !== 'casal' || $carteira['papel'] !== 'administrador') {
        return ['sucesso' => false, 'mensagem' => 'Você não pode adicionar pessoas nesta carteira.'];
    }

    $email = trim($email);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['sucesso' => false, 'mensagem' => 'Informe um e-mail válido.'];
    }

    $stmt = $pdo->prepare('SELECT id, nome FROM Usuarios WHERE email = :email LIMIT 1');
    $stmt->execute([':email' => $email]);
    $parceiro = $stmt->fetch();

    if (!$parceiro) {
        return ['sucesso' => false, 'mensagem' => 'A outra pessoa precisa criar uma conta antes de ser adicionada.'];
    }

    if ((int) $parceiro['id'] === $usuarioId) {
        return ['sucesso' => false, 'mensagem' => 'Informe o e-mail da outra pessoa.'];
    }

    $stmt = $pdo->prepare(
        "SELECT c.id FROM carteiras c
         INNER JOIN carteira_membros cm ON cm.carteira_id = c.id
         WHERE cm.usuario_id = :usuario_id AND c.tipo = 'casal'
         LIMIT 1"
    );
    $stmt->execute([':usuario_id' => (int) $parceiro['id']]);
    if ($stmt->fetchColumn() !== false) {
        return ['sucesso' => false, 'mensagem' => 'Essa pessoa já participa de outra carteira do casal.'];
    }

    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM carteira_membros WHERE carteira_id = :carteira_id'
    );
    $stmt->execute([':carteira_id' => $carteiraId]);
    if ((int) $stmt->fetchColumn() >= 2) {
        return ['sucesso' => false, 'mensagem' => 'A carteira do casal já possui dois integrantes.'];
    }

    try {
        $stmt = $pdo->prepare(
            "INSERT INTO carteira_membros (carteira_id, usuario_id, papel)
             VALUES (:carteira_id, :usuario_id, 'editor')"
        );
        $stmt->execute([':carteira_id' => $carteiraId, ':usuario_id' => (int) $parceiro['id']]);
    } catch (Throwable $erro) {
        return ['sucesso' => false, 'mensagem' => 'Essa pessoa já participa da carteira do casal.'];
    }

    return ['sucesso' => true, 'mensagem' => $parceiro['nome'] . ' foi adicionado(a) à carteira do casal.'];
}
