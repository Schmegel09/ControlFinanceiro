<?php

declare(strict_types=1);

/*
Legenda (recuperar_senha.php):
- Implementa fluxo de recuperação em 3 etapas (enviar código, validar, alterar senha).
- Ação dos formulários aponta para `/recuperar-senha` e usa `acao` hidden para diferenciar etapas.
- Mantém prepared statements para consultas; evite concatenar valores em SQL.
*/

session_start();

if (isset($_SESSION['usuario_id'])) {
    header('Location: /dashboard');
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

                // E-mail será enviado via enviarEmail()

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

            header('Location: /login?reset=sucesso');
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
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; min-height: 100dvh; display: flex; justify-content: center; align-items: center; padding: clamp(12px, 4vw, 20px); }
        .container { background: white; padding: clamp(26px, 7vw, 40px); border-radius: 12px; box-shadow: 0 10px 40px rgba(0,0,0,0.2); max-width: 420px; width: 100%; }
        h1 { color: #333; margin-bottom: 10px; font-size: clamp(24px, 7vw, 28px); line-height: 1.2; overflow-wrap: anywhere; }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 8px; font-weight: 600; color: #555; font-size: 14px; }
        input { width: 100%; min-height: 44px; padding: 12px; border: 2px solid #e0e0e0; border-radius: 6px; font-size: 16px; transition: border-color 0.3s; }
        input:focus { outline: none; border-color: #667eea; }
        button { min-height: 44px; background: linear-gradient(135deg, #4f5fc9 0%, #6d3f91 100%); color: white; padding: 12px 20px; border: none; border-radius: 6px; cursor: pointer; width: 100%; font-weight: 600; font-size: 16px; transition: transform 0.2s, box-shadow 0.2s; }
        button:hover { transform: translateY(-2px); box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4); }
        button:active { transform: translateY(0); }
        .msg { padding: 14px; margin-bottom: 20px; border-radius: 6px; font-size: 14px; border-left: 4px solid; }
        .erro { background: #fff5f5; color: #721c24; border-left-color: #f5c6cb; }
        .sucesso { background: #f0f9f7; color: #155724; border-left-color: #c3e6cb; }
        .info { background: #f0f7ff; color: #0c5460; border-left-color: #bee5eb; }
        a { color: #4656bd; text-decoration: none; font-weight: 500; transition: color 0.2s; }
        a:hover { color: #764ba2; text-decoration: underline; }
        .progress { background: #f0f0f0; padding: 12px; border-radius: 6px; margin-bottom: 20px; font-size: 13px; color: #666; font-weight: 600; text-align: center; }
        p { margin-top: 15px; text-align: center; font-size: 14px; color: #666; }
        small { display: block; margin-top: 6px; color: #666; font-size: 12px; overflow-wrap: anywhere; }
        @media (max-height: 700px) { body { align-items: flex-start; } }
        @media (max-width: 480px) { .container { padding: 28px 20px; } }
    </style>
    <?php require dirname(__DIR__) . '/includes/responsive_styles.php'; ?>
</head>
<body>
    <main class="container">
        <h1>Recuperar senha</h1>

        <div class="progress">
            Etapa <?= htmlspecialchars((string)$etapa) ?> de 3
        </div>

        <?php if ($mensagem !== ''): ?>
            <div class="msg <?= htmlspecialchars($tipo_mensagem) ?>" role="<?= $tipo_mensagem === 'erro' ? 'alert' : 'status' ?>">
                <?= htmlspecialchars($mensagem, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <?php if ($etapa === 1): ?>
            <!-- ETAPA 1: Email -->
            <form method="post" action="/recuperar-senha">
                <input type="hidden" name="acao" value="enviar_codigo">
                <div class="form-group">
                    <label for="email">E-mail cadastrado</label>
                    <input type="email" id="email" name="email" required autofocus>
                </div>
                <button type="submit">Enviar código</button>
            </form>

        <?php elseif ($etapa === 2 && $usuario_id_session): ?>
            <!-- ETAPA 2: Validar Código -->
            <form method="post" action="/recuperar-senha">
                <input type="hidden" name="acao" value="validar_codigo">
                <div class="form-group">
                    <label for="codigo">Código de 6 dígitos</label>
                    <input type="text" id="codigo" name="codigo" placeholder="000000" maxlength="6" pattern="[0-9]{6}" required autofocus>
                    <small>Verifique seu e-mail: <?= htmlspecialchars($email_session, ENT_QUOTES, 'UTF-8') ?></small>
                </div>
                <button type="submit">Validar código</button>
            </form>
            <p>
                <a href="/recuperar-senha">Usar outro e-mail</a>
            </p>

        <?php elseif ($etapa === 3 && $usuario_id_session): ?>
            <!-- ETAPA 3: Nova Senha -->
            <form method="post" action="/recuperar-senha">
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
            <a href="/login">Voltar ao login</a>
        </p>
    </main>
</body>
</html>
