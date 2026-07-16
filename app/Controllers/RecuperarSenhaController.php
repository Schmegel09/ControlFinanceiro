<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/Core/proteger.php';
require_once dirname(__DIR__, 2) . '/config/conexao.php';
require_once dirname(__DIR__, 2) . '/config/email.php';
require_once dirname(__DIR__) . '/Models/UsuarioModel.php';
require_once dirname(__DIR__) . '/Models/PasswordResetModel.php';

garantirEstruturaRecuperacaoSenha($pdo);

if (($_GET['reiniciar'] ?? '') === '1') {
    unset(
        $_SESSION['email_recuperacao'],
        $_SESSION['usuario_id_recuperacao'],
        $_SESSION['codigo_recuperacao_validado']
    );
}

$mensagem = '';
$tipo_mensagem = '';
$etapa = 1;
$email_session = is_string($_SESSION['email_recuperacao'] ?? null)
    ? $_SESSION['email_recuperacao']
    : '';
$usuario_id_session = (int) ($_SESSION['usuario_id_recuperacao'] ?? 0);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $acao = is_string($_POST['acao'] ?? null) ? $_POST['acao'] : 'enviar_codigo';

    if ($acao === 'enviar_codigo') {
        $email = trim(is_string($_POST['email'] ?? null) ? $_POST['email'] : '');

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $mensagem = 'Informe um e-mail válido.';
            $tipo_mensagem = 'erro';
        } else {
            $usuario = buscarUsuarioPorEmail($pdo, $email);

            if ($usuario) {
                $codigo = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                $expiraEm = (new DateTimeImmutable('+15 minutes'))->format('Y-m-d H:i:s');
                substituirCodigoRecuperacao($pdo, (int) $usuario['id'], $codigo, $expiraEm);

                $assunto = 'Código de recuperação de senha';
                $corpo = "Olá,\n\nSeu código de recuperação é: {$codigo}\n\n"
                    . "Este código expira em 15 minutos.\n\n"
                    . "Se você não solicitou a alteração, ignore esta mensagem.";

                if (enviarEmail($email, $assunto, $corpo)) {
                    $_SESSION['email_recuperacao'] = $email;
                    $_SESSION['usuario_id_recuperacao'] = (int) $usuario['id'];
                    unset($_SESSION['codigo_recuperacao_validado']);
                    $mensagem = 'Código enviado! Insira o código abaixo.';
                    $tipo_mensagem = 'sucesso';
                    $etapa = 2;
                    $email_session = $email;
                    $usuario_id_session = (int) $usuario['id'];
                } else {
                    $mensagem = 'Erro ao enviar e-mail. Verifique a configuração SMTP.';
                    $tipo_mensagem = 'erro';
                }
            } else {
                $mensagem = 'Se o e-mail existir em nosso sistema, você receberá um código.';
                $tipo_mensagem = 'info';
            }
        }
    } elseif ($acao === 'validar_codigo') {
        $codigoInformado = is_string($_POST['codigo'] ?? null) ? $_POST['codigo'] : '';
        $codigo = preg_replace('/\D/', '', $codigoInformado) ?? '';
        $usuarioIdRecuperacao = (int) ($_SESSION['usuario_id_recuperacao'] ?? 0);

        if ($usuarioIdRecuperacao <= 0) {
            $mensagem = 'Sessão expirada. Comece novamente.';
            $tipo_mensagem = 'erro';
        } elseif (strlen($codigo) !== 6) {
            $mensagem = 'Código inválido. Use os 6 dígitos recebidos por e-mail.';
            $tipo_mensagem = 'erro';
            $etapa = 2;
        } elseif (codigoRecuperacaoValido($pdo, $usuarioIdRecuperacao, $codigo)) {
            $_SESSION['codigo_recuperacao_validado'] = true;
            $mensagem = 'Código validado! Agora defina sua nova senha.';
            $tipo_mensagem = 'sucesso';
            $etapa = 3;
        } else {
            $mensagem = 'Código inválido ou expirado.';
            $tipo_mensagem = 'erro';
            $etapa = 2;
        }
    } elseif ($acao === 'alterar_senha') {
        $senha = is_string($_POST['senha'] ?? null) ? $_POST['senha'] : '';
        $senhaConfirmacao = is_string($_POST['senha2'] ?? null) ? $_POST['senha2'] : '';
        $usuarioIdRecuperacao = (int) ($_SESSION['usuario_id_recuperacao'] ?? 0);
        $codigoValidado = ($_SESSION['codigo_recuperacao_validado'] ?? false) === true;

        if ($usuarioIdRecuperacao <= 0 || !$codigoValidado) {
            $mensagem = 'Valide novamente o código de recuperação.';
            $tipo_mensagem = 'erro';
            $etapa = $usuarioIdRecuperacao > 0 ? 2 : 1;
        } elseif ($senha === '' || $senhaConfirmacao === '') {
            $mensagem = 'Preencha todos os campos.';
            $tipo_mensagem = 'erro';
            $etapa = 3;
        } elseif ($senha !== $senhaConfirmacao) {
            $mensagem = 'As senhas não coincidem.';
            $tipo_mensagem = 'erro';
            $etapa = 3;
        } elseif (strlen($senha) < 6) {
            $mensagem = 'A senha precisa ter pelo menos 6 caracteres.';
            $tipo_mensagem = 'erro';
            $etapa = 3;
        } else {
            atualizarSenhaUsuario($pdo, $usuarioIdRecuperacao, password_hash($senha, PASSWORD_DEFAULT));
            excluirCodigosRecuperacao($pdo, $usuarioIdRecuperacao);
            unset(
                $_SESSION['email_recuperacao'],
                $_SESSION['usuario_id_recuperacao'],
                $_SESSION['codigo_recuperacao_validado']
            );
            header('Location: /login?reset=sucesso', true, 303);
            exit;
        }
    }
}

$email_session = is_string($_SESSION['email_recuperacao'] ?? null)
    ? $_SESSION['email_recuperacao']
    : $email_session;
$usuario_id_session = (int) ($_SESSION['usuario_id_recuperacao'] ?? $usuario_id_session);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' && $usuario_id_session > 0) {
    $etapa = ($_SESSION['codigo_recuperacao_validado'] ?? false) === true ? 3 : 2;
}

require dirname(__DIR__) . '/Views/auth/recuperar_senha.php';
