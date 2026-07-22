<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/Core/proteger.php';

const STATUS_CLIENTE_VALIDOS = ['pendente', 'ativo', 'em_atraso', 'bloqueado', 'cancelado'];
const TELAS_CLIENTE = [
    'dashboard' => 'Dashboard',
    'movimentacoes' => 'Movimentações',
    'categorias' => 'Categorias',
    'relatorios' => 'Relatórios',
    'carteiras' => 'Carteiras',
];

function garantirEstruturaSaas(PDO $pdo): void
{
    static $estruturaVerificada = false;
    if ($estruturaVerificada) {
        return;
    }

    $permissoesJaExistiam = (bool) $pdo->query(
        "SELECT 1 FROM INFORMATION_SCHEMA.TABLES
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'cliente_permissoes'
         LIMIT 1"
    )->fetchColumn();

    $pdo->exec("CREATE TABLE IF NOT EXISTS clientes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nome VARCHAR(120) NOT NULL,
        dominio VARCHAR(190) NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'pendente',
        vencimento DATE NULL,
        dias_tolerancia SMALLINT UNSIGNED NOT NULL DEFAULT 5,
        bloqueado_em DATETIME NULL,
        motivo_bloqueio VARCHAR(255) NULL,
        criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_cliente_dominio (dominio),
        INDEX idx_clientes_status (status),
        INDEX idx_clientes_vencimento (vencimento)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS cliente_permissoes (
        cliente_id INT NOT NULL,
        tela VARCHAR(40) NOT NULL,
        permitido TINYINT(1) NOT NULL DEFAULT 0,
        atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (cliente_id, tela),
        FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE,
        INDEX idx_cliente_permissoes_tela (tela, permitido)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS cliente_auditoria (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        cliente_id INT NOT NULL,
        administrador_email VARCHAR(190) NOT NULL,
        acao VARCHAR(40) NOT NULL,
        status_anterior VARCHAR(20) NULL,
        status_novo VARCHAR(20) NULL,
        motivo VARCHAR(255) NULL,
        criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE,
        INDEX idx_cliente_auditoria_cliente (cliente_id, criado_em)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS email_verificacoes (
        usuario_id INT NOT NULL PRIMARY KEY,
        token_hash CHAR(64) NOT NULL,
        expira_em DATETIME NOT NULL,
        criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (usuario_id) REFERENCES Usuarios(id) ON DELETE CASCADE,
        UNIQUE KEY unique_email_verificacao_token (token_hash),
        INDEX idx_email_verificacoes_expiracao (expira_em)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $possuiEmailVerificado = (bool) $pdo->query(
        "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'Usuarios'
           AND COLUMN_NAME = 'email_verificado_em'
         LIMIT 1"
    )->fetchColumn();

    if (!$possuiEmailVerificado) {
        $pdo->exec('ALTER TABLE Usuarios ADD COLUMN email_verificado_em DATETIME NULL AFTER email');
        $pdo->exec('UPDATE Usuarios SET email_verificado_em = NOW() WHERE email_verificado_em IS NULL');
    }

    $possuiClienteId = (bool) $pdo->query(
        "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'Usuarios'
           AND COLUMN_NAME = 'cliente_id'
         LIMIT 1"
    )->fetchColumn();

    if (!$possuiClienteId) {
        $pdo->exec('ALTER TABLE Usuarios ADD COLUMN cliente_id INT NULL AFTER senha');
    }

    $possuiPapelSistema = (bool) $pdo->query(
        "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'Usuarios'
           AND COLUMN_NAME = 'papel_sistema'
         LIMIT 1"
    )->fetchColumn();

    if (!$possuiPapelSistema) {
        $pdo->exec(
            "ALTER TABLE Usuarios
             ADD COLUMN papel_sistema VARCHAR(20) NOT NULL DEFAULT 'usuario' AFTER cliente_id"
        );
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS superadmin_auditoria (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        administrador_id INT NOT NULL,
        usuario_alvo_id INT NOT NULL,
        papel_anterior VARCHAR(20) NOT NULL,
        papel_novo VARCHAR(20) NOT NULL,
        criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_superadmin_auditoria_admin (administrador_id, criado_em),
        INDEX idx_superadmin_auditoria_alvo (usuario_alvo_id, criado_em)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // O .env funciona como bootstrap e acesso de emergência. Uma conta
    // configurada nele nunca perde a função durante a migração automática.
    if (function_exists('emailsSuperAdminConfigurados')) {
        $stmtPromover = $pdo->prepare(
            "UPDATE Usuarios SET papel_sistema = 'superadmin' WHERE LOWER(email) = :email"
        );
        foreach (emailsSuperAdminConfigurados() as $emailSuperAdmin) {
            $stmtPromover->execute([':email' => $emailSuperAdmin]);
        }
    }

    $possuiIndiceCliente = (bool) $pdo->query(
        "SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'Usuarios'
           AND INDEX_NAME = 'idx_usuarios_cliente'
         LIMIT 1"
    )->fetchColumn();

    if (!$possuiIndiceCliente) {
        $pdo->exec('ALTER TABLE Usuarios ADD INDEX idx_usuarios_cliente (cliente_id)');
    }

    // Usuários anteriores à implantação são mantidos ativos para evitar
    // uma interrupção inesperada. Novos cadastros começam pendentes.
    $usuariosSemCliente = $pdo->query(
        'SELECT id, nome FROM Usuarios WHERE cliente_id IS NULL ORDER BY id'
    )->fetchAll();

    foreach ($usuariosSemCliente as $usuario) {
        vincularClienteAoUsuario(
            $pdo,
            (int) $usuario['id'],
            (string) $usuario['nome'],
            'ativo'
        );
    }

    if (!$permissoesJaExistiam) {
        $stmtPermissaoInicial = $pdo->prepare(
            'INSERT INTO cliente_permissoes (cliente_id, tela, permitido)
             SELECT id, :tela, 1 FROM clientes
             ON DUPLICATE KEY UPDATE permitido = VALUES(permitido)'
        );
        foreach (array_keys(TELAS_CLIENTE) as $tela) {
            $stmtPermissaoInicial->execute([':tela' => $tela]);
        }
    }

    $estruturaVerificada = true;
}

function vincularClienteAoUsuario(PDO $pdo, int $usuarioId, string $nome, string $status = 'pendente'): int
{
    if (!in_array($status, STATUS_CLIENTE_VALIDOS, true)) {
        throw new InvalidArgumentException('Status de cliente inválido.');
    }

    $gerenciarTransacao = !$pdo->inTransaction();
    if ($gerenciarTransacao) {
        $pdo->beginTransaction();
    }

    try {
        $stmt = $pdo->prepare(
            'SELECT cliente_id FROM Usuarios WHERE id = :usuario_id FOR UPDATE'
        );
        $stmt->execute([':usuario_id' => $usuarioId]);
        $clienteExistente = (int) ($stmt->fetchColumn() ?: 0);

        if ($clienteExistente > 0) {
            if ($gerenciarTransacao) {
                $pdo->commit();
            }
            return $clienteExistente;
        }

        $stmt = $pdo->prepare(
            'INSERT INTO clientes (nome, status) VALUES (:nome, :status)'
        );
        $stmt->execute([
            ':nome' => trim($nome) !== '' ? trim($nome) : 'Novo cliente',
            ':status' => $status,
        ]);
        $clienteId = (int) $pdo->lastInsertId();
        inicializarPermissoesCliente($pdo, $clienteId, false);

        $stmt = $pdo->prepare(
            'UPDATE Usuarios SET cliente_id = :cliente_id WHERE id = :usuario_id'
        );
        $stmt->execute([':cliente_id' => $clienteId, ':usuario_id' => $usuarioId]);
        if ($gerenciarTransacao) {
            $pdo->commit();
        }

        return $clienteId;
    } catch (Throwable $erro) {
        if ($gerenciarTransacao && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $erro;
    }
}

/**
 * @return array<string, mixed>|false
 */
function buscarClienteDoUsuario(PDO $pdo, int $usuarioId): array|false
{
    $stmt = $pdo->prepare(
        'SELECT c.id, c.nome, c.dominio, c.status, c.vencimento,
                c.dias_tolerancia, c.bloqueado_em, c.motivo_bloqueio
         FROM Usuarios u
         INNER JOIN clientes c ON c.id = u.cliente_id
         WHERE u.id = :usuario_id
         LIMIT 1'
    );
    $stmt->execute([':usuario_id' => $usuarioId]);

    return $stmt->fetch();
}

/**
 * @return array{permitido: bool, status_efetivo: string, mensagem: string, cliente: array<string, mixed>|null}
 */
function avaliarAcessoCliente(PDO $pdo, int $usuarioId): array
{
    $cliente = buscarClienteDoUsuario($pdo, $usuarioId);
    if (!$cliente) {
        return [
            'permitido' => false,
            'status_efetivo' => 'pendente',
            'mensagem' => 'Sua conta ainda não está vinculada a um cliente ativo.',
            'cliente' => null,
        ];
    }

    $status = (string) $cliente['status'];
    if ($status === 'pendente') {
        return acessoClienteNegado($cliente, 'Seu cadastro aguarda aprovação do administrador.');
    }

    if ($status === 'bloqueado') {
        $motivo = trim((string) ($cliente['motivo_bloqueio'] ?? ''));
        return acessoClienteNegado(
            $cliente,
            $motivo !== '' ? $motivo : 'O acesso deste cliente está temporariamente bloqueado.'
        );
    }

    if ($status === 'cancelado') {
        return acessoClienteNegado($cliente, 'A assinatura deste cliente foi cancelada.');
    }

    $vencimento = is_string($cliente['vencimento']) && $cliente['vencimento'] !== ''
        ? DateTimeImmutable::createFromFormat('!Y-m-d', $cliente['vencimento'])
        : false;

    if ($vencimento instanceof DateTimeImmutable) {
        $hoje = new DateTimeImmutable('today');
        $limite = $vencimento->modify('+' . (int) $cliente['dias_tolerancia'] . ' days');

        if ($hoje > $limite) {
            return acessoClienteNegado(
                $cliente,
                'O período de tolerância da assinatura terminou. Regularize o pagamento para recuperar o acesso.',
                'bloqueado'
            );
        }

        if ($hoje > $vencimento || $status === 'em_atraso') {
            return [
                'permitido' => true,
                'status_efetivo' => 'em_atraso',
                'mensagem' => 'Pagamento pendente. O acesso permanece liberado durante o período de tolerância.',
                'cliente' => $cliente,
            ];
        }
    } elseif ($status === 'em_atraso') {
        return acessoClienteNegado(
            $cliente,
            'A assinatura está em atraso e não possui uma data de tolerância configurada.',
            'bloqueado'
        );
    }

    return [
        'permitido' => true,
        'status_efetivo' => 'ativo',
        'mensagem' => '',
        'cliente' => $cliente,
    ];
}

/**
 * @param array<string, mixed> $cliente
 * @return array{permitido: bool, status_efetivo: string, mensagem: string, cliente: array<string, mixed>}
 */
function acessoClienteNegado(array $cliente, string $mensagem, ?string $status = null): array
{
    return [
        'permitido' => false,
        'status_efetivo' => $status ?? (string) $cliente['status'],
        'mensagem' => $mensagem,
        'cliente' => $cliente,
    ];
}

/**
 * @return array<int, array<string, mixed>>
 */
function listarClientesAdministracao(PDO $pdo): array
{
    $stmt = $pdo->query(
        'SELECT c.id, c.nome, c.dominio, c.status, c.vencimento,
                c.dias_tolerancia, c.bloqueado_em, c.motivo_bloqueio,
                c.criado_em, c.atualizado_em,
                COUNT(u.id) AS total_usuarios,
                SUM(CASE WHEN u.email_verificado_em IS NOT NULL THEN 1 ELSE 0 END) AS emails_verificados,
                GROUP_CONCAT(u.email ORDER BY u.id SEPARATOR ", ") AS emails
         FROM clientes c
         LEFT JOIN Usuarios u ON u.cliente_id = c.id
         GROUP BY c.id, c.nome, c.dominio, c.status, c.vencimento,
                  c.dias_tolerancia, c.bloqueado_em, c.motivo_bloqueio,
                  c.criado_em, c.atualizado_em
         ORDER BY FIELD(c.status, "pendente", "em_atraso", "bloqueado", "ativo", "cancelado"),
                  c.nome, c.id'
    );

    $clientes = $stmt->fetchAll();
    foreach ($clientes as &$cliente) {
        $cliente['permissoes'] = obterPermissoesCliente($pdo, (int) $cliente['id']);
    }
    unset($cliente);

    return $clientes;
}

function inicializarPermissoesCliente(PDO $pdo, int $clienteId, bool $permitido): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO cliente_permissoes (cliente_id, tela, permitido)
         VALUES (:cliente_id, :tela, :permitido)
         ON DUPLICATE KEY UPDATE permitido = permitido'
    );

    foreach (array_keys(TELAS_CLIENTE) as $tela) {
        $stmt->execute([
            ':cliente_id' => $clienteId,
            ':tela' => $tela,
            ':permitido' => $permitido ? 1 : 0,
        ]);
    }
}

/**
 * @return array<string, bool>
 */
function obterPermissoesCliente(PDO $pdo, int $clienteId): array
{
    $permissoes = array_fill_keys(array_keys(TELAS_CLIENTE), false);
    $stmt = $pdo->prepare(
        'SELECT tela, permitido FROM cliente_permissoes WHERE cliente_id = :cliente_id'
    );
    $stmt->execute([':cliente_id' => $clienteId]);

    foreach ($stmt->fetchAll() as $registro) {
        $tela = (string) $registro['tela'];
        if (array_key_exists($tela, $permissoes)) {
            $permissoes[$tela] = (int) $registro['permitido'] === 1;
        }
    }

    return $permissoes;
}

/**
 * @return array<string, bool>
 */
function obterPermissoesUsuario(PDO $pdo, int $usuarioId): array
{
    $stmt = $pdo->prepare('SELECT cliente_id FROM Usuarios WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $usuarioId]);
    $clienteId = (int) ($stmt->fetchColumn() ?: 0);

    return $clienteId > 0
        ? obterPermissoesCliente($pdo, $clienteId)
        : array_fill_keys(array_keys(TELAS_CLIENTE), false);
}

/**
 * @param array<string, bool> $permissoes
 */
function telaClientePermitida(array $permissoes, string $tela): bool
{
    return isset(TELAS_CLIENTE[$tela]) && ($permissoes[$tela] ?? false) === true;
}

/**
 * @param array<string, bool> $permissoes
 */
function urlPrimeiraTelaPermitida(array $permissoes): ?string
{
    foreach (array_keys(TELAS_CLIENTE) as $tela) {
        if (($permissoes[$tela] ?? false) === true) {
            return '/' . $tela;
        }
    }

    return null;
}

/**
 * @param array<int, string> $telasPermitidas
 */
function atualizarPermissoesCliente(
    PDO $pdo,
    int $clienteId,
    array $telasPermitidas,
    string $administradorEmail
): void {
    $telasPermitidas = array_values(array_intersect(array_keys(TELAS_CLIENTE), $telasPermitidas));
    $stmtCliente = $pdo->prepare('SELECT status FROM clientes WHERE id = :id LIMIT 1');
    $stmtCliente->execute([':id' => $clienteId]);
    $statusCliente = $stmtCliente->fetchColumn();
    if ($statusCliente === false) {
        throw new RuntimeException('Cliente não encontrado.');
    }

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(
            'INSERT INTO cliente_permissoes (cliente_id, tela, permitido)
             VALUES (:cliente_id, :tela, :permitido)
             ON DUPLICATE KEY UPDATE permitido = VALUES(permitido)'
        );
        foreach (array_keys(TELAS_CLIENTE) as $tela) {
            $stmt->execute([
                ':cliente_id' => $clienteId,
                ':tela' => $tela,
                ':permitido' => in_array($tela, $telasPermitidas, true) ? 1 : 0,
            ]);
        }

        $rotulos = array_map(
            static fn (string $tela): string => TELAS_CLIENTE[$tela],
            $telasPermitidas
        );
        $stmt = $pdo->prepare(
            'INSERT INTO cliente_auditoria
                (cliente_id, administrador_email, acao, status_anterior, status_novo, motivo)
             VALUES (:cliente_id, :administrador_email, :acao, :status_anterior, :status_novo, :motivo)'
        );
        $stmt->execute([
            ':cliente_id' => $clienteId,
            ':administrador_email' => $administradorEmail,
            ':acao' => 'atualizar_telas',
            ':status_anterior' => (string) $statusCliente,
            ':status_novo' => (string) $statusCliente,
            ':motivo' => $rotulos === [] ? 'Nenhuma tela liberada' : 'Telas: ' . implode(', ', $rotulos),
        ]);
        $pdo->commit();
    } catch (Throwable $erro) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $erro;
    }
}

function usuarioSuperAdminAtual(PDO $pdo, int $usuarioId): bool
{
    $stmt = $pdo->prepare(
        'SELECT email, papel_sistema FROM Usuarios WHERE id = :usuario_id LIMIT 1'
    );
    $stmt->execute([':usuario_id' => $usuarioId]);
    $usuario = $stmt->fetch();

    if (!$usuario) {
        $_SESSION['papel_sistema'] = 'usuario';
        return false;
    }

    $email = strtolower(trim((string) $usuario['email']));
    $protegidoPeloAmbiente = function_exists('emailsSuperAdminConfigurados')
        && in_array($email, emailsSuperAdminConfigurados(), true);
    $superAdmin = (string) $usuario['papel_sistema'] === 'superadmin' || $protegidoPeloAmbiente;

    $_SESSION['usuario_email'] = $email;
    $_SESSION['papel_sistema'] = $superAdmin ? 'superadmin' : 'usuario';

    return $superAdmin;
}

/**
 * @return array<int, array<string, mixed>>
 */
function listarUsuariosAdministracao(PDO $pdo): array
{
    $stmt = $pdo->query(
        'SELECT u.id, u.nome, u.email, u.email_verificado_em, u.papel_sistema,
                c.id AS cliente_id, c.nome AS cliente_nome, c.status AS cliente_status
         FROM Usuarios u
         LEFT JOIN clientes c ON c.id = u.cliente_id
         ORDER BY u.papel_sistema DESC, u.nome, u.id'
    );
    $emailsProtegidos = function_exists('emailsSuperAdminConfigurados')
        ? emailsSuperAdminConfigurados()
        : [];

    return array_map(
        static function (array $usuario) use ($emailsProtegidos): array {
            $usuario['protegido_pelo_ambiente'] = in_array(
                strtolower(trim((string) $usuario['email'])),
                $emailsProtegidos,
                true
            );
            return $usuario;
        },
        $stmt->fetchAll()
    );
}

function alterarPapelSistemaUsuario(
    PDO $pdo,
    int $usuarioAlvoId,
    string $novoPapel,
    int $administradorId
): void {
    if (!in_array($novoPapel, ['usuario', 'superadmin'], true)) {
        throw new InvalidArgumentException('Função de sistema inválida.');
    }

    if ($usuarioAlvoId === $administradorId && $novoPapel !== 'superadmin') {
        throw new RuntimeException('Você não pode remover sua própria função de superadministrador.');
    }

    $pdo->beginTransaction();

    try {
        $stmt = $pdo->prepare(
            'SELECT email, papel_sistema FROM Usuarios WHERE id = :id FOR UPDATE'
        );
        $stmt->execute([':id' => $usuarioAlvoId]);
        $usuario = $stmt->fetch();

        if (!$usuario) {
            throw new RuntimeException('Usuário não encontrado.');
        }

        $email = strtolower(trim((string) $usuario['email']));
        $protegidoPeloAmbiente = function_exists('emailsSuperAdminConfigurados')
            && in_array($email, emailsSuperAdminConfigurados(), true);

        if ($protegidoPeloAmbiente && $novoPapel !== 'superadmin') {
            throw new RuntimeException('Essa conta está protegida pelo SUPERADMIN_EMAILS do ambiente.');
        }

        $papelAnterior = (string) $usuario['papel_sistema'];
        if ($papelAnterior === $novoPapel) {
            $pdo->commit();
            return;
        }

        $stmt = $pdo->prepare(
            'UPDATE Usuarios SET papel_sistema = :papel WHERE id = :id'
        );
        $stmt->execute([':papel' => $novoPapel, ':id' => $usuarioAlvoId]);

        $stmt = $pdo->prepare(
            'INSERT INTO superadmin_auditoria
                (administrador_id, usuario_alvo_id, papel_anterior, papel_novo)
             VALUES (:administrador_id, :usuario_alvo_id, :papel_anterior, :papel_novo)'
        );
        $stmt->execute([
            ':administrador_id' => $administradorId,
            ':usuario_alvo_id' => $usuarioAlvoId,
            ':papel_anterior' => $papelAnterior,
            ':papel_novo' => $novoPapel,
        ]);

        $pdo->commit();
    } catch (Throwable $erro) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $erro;
    }
}

function normalizarDominioCliente(string $dominio): ?string
{
    $dominio = strtolower(trim($dominio));
    $dominio = preg_replace('#^https?://#', '', $dominio) ?? $dominio;
    $dominio = rtrim(explode('/', $dominio, 2)[0], '.');

    if ($dominio === '') {
        return null;
    }

    if (strlen($dominio) > 190 || !filter_var($dominio, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
        throw new InvalidArgumentException('Informe somente um domínio válido, sem caminho.');
    }

    return $dominio;
}

/**
 * @param array{nome: string, dominio: string, status: string, vencimento: string, dias_tolerancia: int, motivo_bloqueio: string} $dados
 */
function atualizarClienteAdministracao(PDO $pdo, int $clienteId, array $dados, string $administradorEmail): void
{
    if (!in_array($dados['status'], STATUS_CLIENTE_VALIDOS, true)) {
        throw new InvalidArgumentException('Status inválido.');
    }

    if ($dados['dias_tolerancia'] < 0 || $dados['dias_tolerancia'] > 90) {
        throw new InvalidArgumentException('A tolerância deve estar entre 0 e 90 dias.');
    }

    $vencimento = trim($dados['vencimento']);
    if ($vencimento !== '') {
        $data = DateTimeImmutable::createFromFormat('!Y-m-d', $vencimento);
        if (!$data || $data->format('Y-m-d') !== $vencimento) {
            throw new InvalidArgumentException('Data de vencimento inválida.');
        }
    }

    $dominio = normalizarDominioCliente($dados['dominio']);
    $nome = trim($dados['nome']);
    if ($nome === '' || strlen($nome) > 120) {
        throw new InvalidArgumentException('O nome do cliente é obrigatório e deve ter até 120 caracteres.');
    }

    $motivo = trim($dados['motivo_bloqueio']);
    if (strlen($motivo) > 255) {
        throw new InvalidArgumentException('O motivo deve ter até 255 caracteres.');
    }

    $pdo->beginTransaction();

    try {
        $stmt = $pdo->prepare('SELECT status, vencimento FROM clientes WHERE id = :id FOR UPDATE');
        $stmt->execute([':id' => $clienteId]);
        $statusAnterior = $stmt->fetchColumn();
        if ($statusAnterior === false) {
            throw new RuntimeException('Cliente não encontrado.');
        }

        $bloqueadoEm = in_array($dados['status'], ['bloqueado', 'cancelado'], true)
            ? date('Y-m-d H:i:s')
            : null;

        $stmt = $pdo->prepare(
            'UPDATE clientes
             SET nome = :nome,
                 dominio = :dominio,
                 status = :status,
                 vencimento = :vencimento,
                 dias_tolerancia = :dias_tolerancia,
                 bloqueado_em = :bloqueado_em,
                 motivo_bloqueio = :motivo
             WHERE id = :id'
        );
        $stmt->execute([
            ':nome' => $nome,
            ':dominio' => $dominio,
            ':status' => $dados['status'],
            ':vencimento' => $vencimento !== '' ? $vencimento : null,
            ':dias_tolerancia' => $dados['dias_tolerancia'],
            ':bloqueado_em' => $bloqueadoEm,
            ':motivo' => $motivo !== '' ? $motivo : null,
            ':id' => $clienteId,
        ]);

        $stmt = $pdo->prepare(
            'INSERT INTO cliente_auditoria
                (cliente_id, administrador_email, acao, status_anterior, status_novo, motivo)
             VALUES (:cliente_id, :administrador_email, :acao, :status_anterior, :status_novo, :motivo)'
        );
        $stmt->execute([
            ':cliente_id' => $clienteId,
            ':administrador_email' => $administradorEmail,
            ':acao' => 'atualizar_acesso',
            ':status_anterior' => (string) $statusAnterior,
            ':status_novo' => $dados['status'],
            ':motivo' => $motivo !== '' ? $motivo : null,
        ]);

        $pdo->commit();
    } catch (Throwable $erro) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $erro;
    }
}

function renovarCliente(PDO $pdo, int $clienteId, int $dias, string $administradorEmail): void
{
    if ($dias < 1 || $dias > 366) {
        throw new InvalidArgumentException('A renovação deve ter entre 1 e 366 dias.');
    }

    $pdo->beginTransaction();

    try {
        $stmt = $pdo->prepare('SELECT status, vencimento FROM clientes WHERE id = :id FOR UPDATE');
        $stmt->execute([':id' => $clienteId]);
        $cliente = $stmt->fetch();
        if (!$cliente) {
            throw new RuntimeException('Cliente não encontrado.');
        }

        $hoje = new DateTimeImmutable('today');
        $vencimentoAtual = is_string($cliente['vencimento'])
            ? DateTimeImmutable::createFromFormat('!Y-m-d', $cliente['vencimento'])
            : false;
        $base = $vencimentoAtual instanceof DateTimeImmutable && $vencimentoAtual > $hoje
            ? $vencimentoAtual
            : $hoje;
        $novoVencimento = $base->modify('+' . $dias . ' days')->format('Y-m-d');

        $stmt = $pdo->prepare(
            "UPDATE clientes
             SET status = 'ativo', vencimento = :vencimento,
                 bloqueado_em = NULL, motivo_bloqueio = NULL
             WHERE id = :id"
        );
        $stmt->execute([':vencimento' => $novoVencimento, ':id' => $clienteId]);

        $stmt = $pdo->prepare(
            'INSERT INTO cliente_auditoria
                (cliente_id, administrador_email, acao, status_anterior, status_novo, motivo)
             VALUES (:cliente_id, :administrador_email, :acao, :status_anterior, :status_novo, :motivo)'
        );
        $stmt->execute([
            ':cliente_id' => $clienteId,
            ':administrador_email' => $administradorEmail,
            ':acao' => 'renovar',
            ':status_anterior' => (string) $cliente['status'],
            ':status_novo' => 'ativo',
            ':motivo' => 'Renovação por ' . $dias . ' dias',
        ]);

        $pdo->commit();
    } catch (Throwable $erro) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $erro;
    }
}

function alterarStatusRapidoCliente(
    PDO $pdo,
    int $clienteId,
    string $novoStatus,
    string $administradorEmail,
    string $motivo = ''
): void {
    if (!in_array($novoStatus, STATUS_CLIENTE_VALIDOS, true)) {
        throw new InvalidArgumentException('Status inválido.');
    }

    $motivo = trim($motivo);

    if (strlen($motivo) > 255) {
        throw new InvalidArgumentException(
            'O motivo deve ter até 255 caracteres.'
        );
    }

    $pdo->beginTransaction();

    try {
        $stmt = $pdo->prepare(
            'SELECT status, vencimento
             FROM clientes
             WHERE id = :id
             FOR UPDATE'
        );

        $stmt->execute([
            ':id' => $clienteId,
        ]);

        $cliente = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$cliente) {
            throw new RuntimeException('Cliente não encontrado.');
        }

        $statusAnterior = (string) $cliente['status'];
        $vencimentoAtual = $cliente['vencimento'] ?? null;

        $bloqueadoEm = in_array(
            $novoStatus,
            ['bloqueado', 'cancelado'],
            true
        )
            ? date('Y-m-d H:i:s')
            : null;

        $motivoPadrao = match ($novoStatus) {
            'bloqueado' =>
                'Acesso bloqueado manualmente pelo administrador.',

            'cancelado' =>
                'Assinatura cancelada pelo administrador.',

            'em_atraso' =>
                'Pagamento marcado como pendente pelo administrador.',

            default => '',
        };

        $motivoFinal = $motivo !== ''
            ? $motivo
            : $motivoPadrao;

        /*
         * Mantém o vencimento atual por padrão.
         * O cálculo é feito no PHP para evitar a comparação
         * de textos com collations diferentes dentro do MySQL.
         */
        $novoVencimento = $vencimentoAtual;

        if ($novoStatus === 'ativo') {
            $hoje = new DateTimeImmutable('today');

            $dataVencimentoAtual = false;

            if (
                is_string($vencimentoAtual)
                && $vencimentoAtual !== ''
            ) {
                $dataVencimentoAtual = DateTimeImmutable::createFromFormat(
                    '!Y-m-d',
                    $vencimentoAtual
                );
            }

            if (
                !$dataVencimentoAtual
                || $dataVencimentoAtual < $hoje
            ) {
                $novoVencimento = $hoje
                    ->modify('+30 days')
                    ->format('Y-m-d');
            }
        } elseif (
            $novoStatus === 'em_atraso'
            && (
                $vencimentoAtual === null
                || $vencimentoAtual === ''
            )
        ) {
            $novoVencimento = date('Y-m-d');
        }

        $stmt = $pdo->prepare(
            'UPDATE clientes
             SET status = :status,
                 vencimento = :vencimento,
                 bloqueado_em = :bloqueado_em,
                 motivo_bloqueio = :motivo
             WHERE id = :id'
        );

        $stmt->execute([
            ':status' => $novoStatus,
            ':vencimento' => $novoVencimento,
            ':bloqueado_em' => $bloqueadoEm,
            ':motivo' => $motivoFinal !== ''
                ? $motivoFinal
                : null,
            ':id' => $clienteId,
        ]);

        $stmt = $pdo->prepare(
            'INSERT INTO cliente_auditoria (
                cliente_id,
                administrador_email,
                acao,
                status_anterior,
                status_novo,
                motivo
             ) VALUES (
                :cliente_id,
                :administrador_email,
                :acao,
                :status_anterior,
                :status_novo,
                :motivo
             )'
        );

        $stmt->execute([
            ':cliente_id' => $clienteId,
            ':administrador_email' => $administradorEmail,
            ':acao' => 'alterar_status_rapido',
            ':status_anterior' => $statusAnterior,
            ':status_novo' => $novoStatus,
            ':motivo' => $motivoFinal !== ''
                ? $motivoFinal
                : null,
        ]);

        $pdo->commit();
    } catch (Throwable $erro) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $erro;
    }
}

function tokenCsrfAdmin(): string
{
    if (!isset($_SESSION['csrf_admin']) || !is_string($_SESSION['csrf_admin'])) {
        $_SESSION['csrf_admin'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_admin'];
}

function tokenCsrfAdminValido(mixed $token): bool
{
    return is_string($token)
        && isset($_SESSION['csrf_admin'])
        && is_string($_SESSION['csrf_admin'])
        && hash_equals($_SESSION['csrf_admin'], $token);
}

function criarVerificacaoEmail(PDO $pdo, int $usuarioId): string
{
    $token = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $token);
    $expiraEm = (new DateTimeImmutable('+24 hours'))->format('Y-m-d H:i:s');

    $stmt = $pdo->prepare(
        'INSERT INTO email_verificacoes (usuario_id, token_hash, expira_em)
         VALUES (:usuario_id, :token_hash, :expira_em)
         ON DUPLICATE KEY UPDATE
             token_hash = VALUES(token_hash),
             expira_em = VALUES(expira_em),
             criado_em = CURRENT_TIMESTAMP'
    );
    $stmt->execute([
        ':usuario_id' => $usuarioId,
        ':token_hash' => $tokenHash,
        ':expira_em' => $expiraEm,
    ]);

    return $token;
}

function podeReenviarVerificacaoEmail(PDO $pdo, int $usuarioId): bool
{
    $stmt = $pdo->prepare(
        'SELECT 1 FROM email_verificacoes
         WHERE usuario_id = :usuario_id
           AND criado_em > DATE_SUB(NOW(), INTERVAL 60 SECOND)
         LIMIT 1'
    );
    $stmt->execute([':usuario_id' => $usuarioId]);

    return $stmt->fetchColumn() === false;
}

function confirmarVerificacaoEmail(PDO $pdo, string $token): bool
{
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
        return false;
    }

    $pdo->beginTransaction();

    try {
        $stmt = $pdo->prepare(
            'SELECT usuario_id FROM email_verificacoes
             WHERE token_hash = :token_hash AND expira_em >= NOW()
             LIMIT 1 FOR UPDATE'
        );
        $stmt->execute([':token_hash' => hash('sha256', $token)]);
        $usuarioId = (int) ($stmt->fetchColumn() ?: 0);

        if ($usuarioId <= 0) {
            $pdo->rollBack();
            return false;
        }

        $stmt = $pdo->prepare(
            'UPDATE Usuarios SET email_verificado_em = NOW() WHERE id = :usuario_id'
        );
        $stmt->execute([':usuario_id' => $usuarioId]);

        $stmt = $pdo->prepare('DELETE FROM email_verificacoes WHERE usuario_id = :usuario_id');
        $stmt->execute([':usuario_id' => $usuarioId]);
        $pdo->commit();

        return true;
    } catch (Throwable $erro) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $erro;
    }
}

function urlBaseAplicacao(): string
{
    $configurada = trim((string) ($_ENV['APP_URL'] ?? getenv('APP_URL') ?: ''));
    if ($configurada !== '' && filter_var($configurada, FILTER_VALIDATE_URL)) {
        return rtrim($configurada, '/');
    }

    $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? 'localhost'));
    if (!preg_match('/^(?:localhost(?::\d{1,5})?|[a-z0-9.-]+(?::\d{1,5})?)$/', $host)) {
        $host = 'localhost';
    }

    return (requisicaoHttps() ? 'https://' : 'http://') . $host;
}
