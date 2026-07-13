<?php

declare(strict_types=1);

session_start();

if (isset($_SESSION['usuario_id'])) {
    header('Location: index.php?page=dashboard');
    exit;
}

require_once dirname(__DIR__) . '/config/conexao.php';
require_once dirname(__DIR__) . '/config/email.php';

// Criar tabela apenas se não existir
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

$mensagem = '';
$tipo_mensagem = '';
$etapa = 1;
$email_session = $_SESSION['email_recuperacao'] ?? '';
$usuario_id_session = $_SESSION['usuario_id_recuperacao'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? 'enviar_codigo';

    if ($acao === 'enviar_codigo') {
        // Etapa 1: Enviar código para o email
        $email = trim($_POST['email'] ?? '');

        if ($email === '') {
            $mensagem = 'Informe o e-mail cadastrado.';
            $tipo_mensagem = 'erro';
            $etapa = 1;
        } else {
            $consulta = $pdo->prepare('SELECT id FROM Usuarios WHERE email = :email LIMIT 1');
            $consulta->execute([':email' => $email]);
            $usuario = $consulta->fetch();

            if ($usuario) {
                $codigo = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                $expiresAt = (new DateTimeImmutable('+15 minutes'))->format('Y-m-d H:i:s');

                $pdo->prepare('DELETE FROM password_resets WHERE usuario_id = :usuario_id')
                    ->execute([':usuario_id' => $usuario['id']]);

                $pdo->prepare(
                    'INSERT INTO password_resets (usuario_id, codigo, expires_at)
                     VALUES (:usuario_id, :codigo, :expires_at)'
                )->execute([
                    ':usuario_id' => $usuario['id'],
                    ':codigo' => $codigo,
                    ':expires_at' => $expiresAt,
                ]);

                $assunto = 'Código de recuperação de senha';
                $corpo = "Olá,\n\nSeu codigo de recuperacao de senha é:\n\n{$codigo}\n\nEste código expira em 15 minutos.\n\nSe você não solicitou a alteração, ignore esta mensagem.";

                // Gravamos o corpo do email em arquivo temporário para depuração
                @file_put_contents('/tmp/last_recovery_email.txt', date('Y-m-d H:i:s') . " - Para: {$email} - Codigo: {$codigo}\n" . $corpo . "\n----\n", FILE_APPEND);

                if (enviarEmail($email, $assunto, $corpo)) {
                    $_SESSION['email_recuperacao'] = $email;
                    $_SESSION['usuario_id_recuperacao'] = $usuario['id'];
                    $mensagem = 'Código enviado! Insira o código abaixo.';
                    $tipo_mensagem = 'sucesso';
                    $etapa = 2;
                    $email_session = $email;
                    $usuario_id_session = $usuario['id'];
                } else {
                    $mensagem = 'Erro ao enviar email. Verifique configuração SMTP.';
                    $tipo_mensagem = 'erro';
                    $etapa = 1;
                }
            } else {
                $mensagem = 'Se o e-mail existir em nosso sistema, você receberá um código.';
                $tipo_mensagem = 'info';
                $etapa = 1;
            }
        }
    } elseif ($acao === 'validar_codigo') {
        // Etapa 2: Validar código
        $codigo = trim($_POST['codigo'] ?? '');
        // Normalizar: remover quaisquer caracteres não numéricos (espaços, quebras de linha, etc.)
        $codigo = preg_replace('/\\D/', '', $codigo);
        $usuario_id_recuperacao = $_SESSION['usuario_id_recuperacao'] ?? null;

        if (!$usuario_id_recuperacao) {
            $mensagem = 'Sessão expirada. Comece novamente.';
            $tipo_mensagem = 'erro';
            $etapa = 1;
        } elseif ($codigo === '') {
            $mensagem = 'Informe o código recebido.';
            $tipo_mensagem = 'erro';
            $etapa = 2;
            $email_session = $_SESSION['email_recuperacao'] ?? '';
            $usuario_id_session = $usuario_id_recuperacao;
        } elseif (strlen($codigo) !== 6) {
            $mensagem = 'Código inválido. Use os 6 dígitos recebidos por e-mail.';
            $tipo_mensagem = 'erro';
            $etapa = 2;
            $email_session = $_SESSION['email_recuperacao'] ?? '';
            $usuario_id_session = $usuario_id_recuperacao;
        } else {
            $consulta = $pdo->prepare(
                'SELECT usuario_id FROM password_resets
                 WHERE usuario_id = :usuario_id
                   AND codigo = :codigo
                   AND expires_at >= NOW()
                 LIMIT 1'
            );
            $consulta->execute([
                ':usuario_id' => $usuario_id_recuperacao,
                ':codigo' => $codigo,
            ]);

            $reset = $consulta->fetch();

            if ($reset) {
                $mensagem = 'Código validado! Agora defina sua nova senha.';
                $tipo_mensagem = 'sucesso';
                $etapa = 3;
                $email_session = $_SESSION['email_recuperacao'] ?? '';
                $usuario_id_session = $usuario_id_recuperacao;
            } else {
                $mensagem = 'Código inválido ou expirado.';
                $tipo_mensagem = 'erro';
                $etapa = 2;
                $email_session = $_SESSION['email_recuperacao'] ?? '';
                $usuario_id_session = $usuario_id_recuperacao;
            }
        }
    } elseif ($acao === 'alterar_senha') {
        // Etapa 3: Alterar senha
        $senha = $_POST['senha'] ?? '';
        $senha2 = $_POST['senha2'] ?? '';
        $usuario_id_recuperacao = $_SESSION['usuario_id_recuperacao'] ?? null;

        if (!$usuario_id_recuperacao) {
            $mensagem = 'Sessão expirada. Comece novamente.';
            $tipo_mensagem = 'erro';
            $etapa = 1;
        } elseif ($senha === '' || $senha2 === '') {
            $mensagem = 'Preencha todos os campos.';
            $tipo_mensagem = 'erro';
            $etapa = 3;
            $email_session = $_SESSION['email_recuperacao'] ?? '';
            $usuario_id_session = $usuario_id_recuperacao;
        } elseif ($senha !== $senha2) {
            $mensagem = 'As senhas não coincidem.';
            $tipo_mensagem = 'erro';
            $etapa = 3;
            $email_session = $_SESSION['email_recuperacao'] ?? '';
            $usuario_id_session = $usuario_id_recuperacao;
        } elseif (strlen($senha) < 6) {
            $mensagem = 'A senha precisa ter pelo menos 6 caracteres.';
            $tipo_mensagem = 'erro';
            $etapa = 3;
            $email_session = $_SESSION['email_recuperacao'] ?? '';
            $usuario_id_session = $usuario_id_recuperacao;
        } else {
            $pdo->prepare('UPDATE Usuarios SET senha = :senha WHERE id = :id')
                ->execute([
                    ':senha' => password_hash($senha, PASSWORD_DEFAULT),
                    ':id' => $usuario_id_recuperacao,
                ]);

            $pdo->prepare('DELETE FROM password_resets WHERE usuario_id = :usuario_id')
                ->execute([':usuario_id' => $usuario_id_recuperacao]);

            unset($_SESSION['email_recuperacao']);
            unset($_SESSION['usuario_id_recuperacao']);

            header('Location: index.php?page=login&reset=sucesso');
            exit;
        }
    }
}

// Se temos email na sessão, mostrar etapa 2 ou 3
if ($email_session && !isset($_POST['acao'])) {
    $etapa = (isset($_SESSION['usuario_id_recuperacao'])) ? 2 : 1;
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar senha</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; max-width: 400px; margin: 0 auto; }
        .container { border: 1px solid #ddd; padding: 20px; border-radius: 5px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input { width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 3px; box-sizing: border-box; }
        button { background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 3px; cursor: pointer; width: 100%; }
        button:hover { background: #0056b3; }
        .msg { padding: 10px; margin-bottom: 15px; border-radius: 3px; }
        .erro { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .sucesso { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .info { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
        a { color: #007bff; text-decoration: none; }
        a:hover { text-decoration: underline; }
        .progress { background: #e9ecef; padding: 10px; border-radius: 3px; margin-bottom: 15px; font-size: 12px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Recuperar senha</h1>

        <div class="progress">
            Etapa <?= htmlspecialchars((string)$etapa) ?> de 3
        </div>

        <?php if ($mensagem !== ''): ?>
            <div class="msg <?= htmlspecialchars($tipo_mensagem) ?>">
                <?= htmlspecialchars($mensagem, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <?php if ($etapa === 1): ?>
            <!-- ETAPA 1: Email -->
            <form method="post" action="index.php?page=recuperar-senha">
                <input type="hidden" name="acao" value="enviar_codigo">
                <div class="form-group">
                    <label for="email">E-mail cadastrado</label>
                    <input type="email" id="email" name="email" required autofocus>
                </div>
                <button type="submit">Enviar código</button>
            </form>

        <?php elseif ($etapa === 2 && $usuario_id_session): ?>
            <!-- ETAPA 2: Validar Código -->
            <form method="post" action="index.php?page=recuperar-senha">
                <input type="hidden" name="acao" value="validar_codigo">
                <div class="form-group">
                    <label for="codigo">Código de 6 dígitos</label>
                    <input type="text" id="codigo" name="codigo" placeholder="000000" maxlength="6" pattern="[0-9]{6}" required autofocus>
                    <small>Verifique seu e-mail: <?= htmlspecialchars($email_session, ENT_QUOTES, 'UTF-8') ?></small>
                </div>
                <button type="submit">Validar código</button>
            </form>
            <p>
                <a href="index.php?page=recuperar-senha">Usar outro e-mail</a>
            </p>

        <?php elseif ($etapa === 3 && $usuario_id_session): ?>
            <!-- ETAPA 3: Nova Senha -->
            <form method="post" action="index.php?page=recuperar-senha">
                <input type="hidden" name="acao" value="alterar_senha">
                <div class="form-group">
                    <label for="senha">Nova senha</label>
                    <input type="password" id="senha" name="senha" minlength="6" required autofocus>
                </div>
                <div class="form-group">
                    <label for="senha2">Confirme a nova senha</label>
                    <input type="password" id="senha2" name="senha2" minlength="6" required>
                </div>
                <button type="submit">Alterar senha</button>
            </form>

        <?php endif; ?>

        <p>
            <a href="index.php?page=login">Voltar ao login</a>
        </p>
    </div>
</body>
</html>
