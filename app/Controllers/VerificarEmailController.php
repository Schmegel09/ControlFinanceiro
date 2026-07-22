<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/Core/proteger.php';
require_once dirname(__DIR__, 2) . '/config/conexao.php';
require_once dirname(__DIR__, 2) . '/config/email.php';
require_once dirname(__DIR__) . '/Models/UsuarioModel.php';
require_once dirname(__DIR__) . '/Services/SaasService.php';

garantirEstruturaSaas($pdo);

$mensagem = '';
$tipoMensagem = 'info';
$exibirFormularioReenvio = true;

$token = is_string($_GET['token'] ?? null) ? strtolower(trim($_GET['token'])) : '';
if ($token !== '') {
    if (confirmarVerificacaoEmail($pdo, $token)) {
        $mensagem = 'E-mail confirmado com sucesso. Agora aguarde a aprovação do administrador.';
        $tipoMensagem = 'sucesso';
        $exibirFormularioReenvio = false;
    } else {
        $mensagem = 'O link é inválido ou expirou. Solicite um novo link.';
        $tipoMensagem = 'erro';
    }
} elseif (($_GET['enviado'] ?? '') === '1') {
    $mensagem = 'Conta criada! Enviamos um link de confirmação para o seu e-mail. Verifique também a caixa de spam.';
    $tipoMensagem = 'sucesso';
    $exibirFormularioReenvio = false;
} elseif (($_GET['enviado'] ?? '') === 'erro') {
    $mensagem = 'O cadastro foi criado, mas o e-mail não pôde ser enviado. Tente reenviar abaixo.';
    $tipoMensagem = 'erro';
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $email = trim(is_string($_POST['email'] ?? null) ? $_POST['email'] : '');

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $mensagem = 'Informe um e-mail válido.';
        $tipoMensagem = 'erro';
    } else {
        $usuario = buscarUsuarioPorEmail($pdo, $email);

        if (
            $usuario
            && empty($usuario['email_verificado_em'])
            && podeReenviarVerificacaoEmail($pdo, (int) $usuario['id'])
        ) {
            $novoToken = criarVerificacaoEmail($pdo, (int) $usuario['id']);
            $link = urlBaseAplicacao() . '/verificar-email?token=' . urlencode($novoToken);
            $corpo = "Olá!\n\nConfirme seu e-mail acessando o link abaixo:\n{$link}\n\nO link expira em 24 horas.";
            enviarEmail((string) $usuario['email'], 'Novo link de confirmação', $corpo);
        }

        // Resposta neutra para não revelar quais e-mails estão cadastrados.
        $mensagem = 'Se existir um cadastro pendente para esse e-mail, um novo link será enviado.';
        $tipoMensagem = 'sucesso';
    }
}

require dirname(__DIR__) . '/Views/auth/verificar_email.php';
