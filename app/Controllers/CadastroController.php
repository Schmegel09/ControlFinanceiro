<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/Core/proteger.php';
require_once dirname(__DIR__, 2) . '/config/conexao.php';
require_once dirname(__DIR__) . '/Models/UsuarioModel.php';
require_once dirname(__DIR__) . '/Services/SaasService.php';
require_once dirname(__DIR__, 2) . '/config/email.php';

garantirEstruturaSaas($pdo);

$mensagem = '';
$tipoMensagem = 'erro';
$csrfToken = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $tokenRecebido = is_string($_POST['csrf_token'] ?? null)
        ? $_POST['csrf_token']
        : '';

    $tokenSessao = is_string($_SESSION['csrf_cadastro'] ?? null)
        ? $_SESSION['csrf_cadastro']
        : '';

    if (
        $tokenRecebido === ''
        || $tokenSessao === ''
        || !hash_equals($tokenSessao, $tokenRecebido)
    ) {
        $mensagem = 'Sessão expirada. Atualize a página e tente novamente.';
    } else {
        $nome = trim(
            is_string($_POST['nome'] ?? null)
                ? $_POST['nome']
                : ''
        );

        $email = strtolower(trim(
            is_string($_POST['email'] ?? null)
                ? $_POST['email']
                : ''
        ));

        $senha = is_string($_POST['senha'] ?? null)
            ? $_POST['senha']
            : '';

        if ($nome === '' || $email === '' || $senha === '') {
            $mensagem = 'Preencha todos os campos.';
        } elseif (mb_strlen($nome) > 120) {
            $mensagem = 'O nome deve ter no máximo 120 caracteres.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $mensagem = 'Informe um e-mail válido.';
        } elseif (strlen($email) > 190) {
            $mensagem = 'O e-mail informado é muito longo.';
        } elseif (strlen($senha) < 6) {
            $mensagem = 'A senha precisa ter pelo menos 6 caracteres.';
        } elseif (strlen($senha) > 255) {
            $mensagem = 'A senha informada é muito longa.';
        } elseif (buscarUsuarioPorEmail($pdo, $email)) {
            $mensagem = 'Este e-mail já está cadastrado.';
        } else {
            $usuarioId = 0;

            try {
                $senhaHash = password_hash(
                    $senha,
                    PASSWORD_DEFAULT
                );

                if (!is_string($senhaHash) || $senhaHash === '') {
                    throw new RuntimeException(
                        'Não foi possível proteger a senha.'
                    );
                }

                $pdo->beginTransaction();

                $usuarioId = criarUsuario(
                    $pdo,
                    $nome,
                    $email,
                    $senhaHash
                );

                if ($usuarioId <= 0) {
                    throw new RuntimeException(
                        'Não foi possível identificar o usuário criado.'
                    );
                }

                vincularClienteAoUsuario(
                    $pdo,
                    $usuarioId,
                    $nome,
                    'pendente'
                );

                $pdo->commit();
            } catch (Throwable $erro) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                error_log(sprintf(
                    '[CADASTRO] %s em %s:%d',
                    $erro->getMessage(),
                    $erro->getFile(),
                    $erro->getLine()
                ));

                $usuarioId = 0;
                $mensagem = 'Não foi possível concluir o cadastro. Tente novamente.';
            }

            if ($usuarioId > 0 && $mensagem === '') {
                $emailEnviado = false;

                try {
                    $tokenVerificacao = criarVerificacaoEmail(
                        $pdo,
                        $usuarioId
                    );

                    $linkVerificacao = urlBaseAplicacao()
                        . '/verificar-email?token='
                        . rawurlencode($tokenVerificacao);

                    /*
                         * Evita quebra indevida dos cabeçalhos ou do conteúdo
                         * do e-mail caso o nome tenha caracteres de nova linha.
                         */
                    $nomeEmail = str_replace(
                        ["\r", "\n"],
                        '',
                        $nome
                    );

                    $corpo = "Olá, {$nomeEmail}!\n\n"
                        . "Confirme seu e-mail acessando o link abaixo:\n"
                        . "{$linkVerificacao}\n\n"
                        . "O link expira em 24 horas.\n"
                        . "Depois da confirmação, seu cadastro ainda precisará "
                        . "ser aprovado pelo administrador.";

                    $emailEnviado = enviarEmail(
                        $email,
                        'Confirme seu cadastro',
                        $corpo
                    );
                } catch (Throwable $erro) {
                    error_log(sprintf(
                        '[CADASTRO][EMAIL] Usuário %d: %s em %s:%d',
                        $usuarioId,
                        $erro->getMessage(),
                        $erro->getFile(),
                        $erro->getLine()
                    ));
                }

                if (!$emailEnviado) {
                    error_log(
                        'Não foi possível enviar a confirmação de e-mail '
                            . 'para o usuário ' . $usuarioId
                    );
                }

                /*
                     * O parâmetro enviado só mostra uma mensagem visual.
                     * Ele não confirma o e-mail e não libera o usuário.
                     */
                header(
                    'Location: /verificar-email?enviado='
                        . ($emailEnviado ? '1' : 'erro'),
                    true,
                    303
                );

                exit;
            }
        }
    }
}


/*
 * Disponibiliza o token para a view do formulário.
 */
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (
    !isset($_SESSION['csrf_cadastro'])
    || !is_string($_SESSION['csrf_cadastro'])
) {
    $_SESSION['csrf_cadastro'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['csrf_cadastro'];

require dirname(__DIR__) . '/Views/auth/cadastro.php';
