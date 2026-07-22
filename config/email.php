<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/Core/proteger.php';

// Carrega as variáveis de ambiente (assume que config/conexao.php já chamou o dotenv)
$smtpHost = getenv('SMTP_HOST') ?: ($_ENV['SMTP_HOST'] ?? '');
$smtpPort = getenv('SMTP_PORT') ?: ($_ENV['SMTP_PORT'] ?? '');
$smtpUser = getenv('SMTP_USERNAME') ?: ($_ENV['SMTP_USERNAME'] ?? '');
$smtpPass = getenv('SMTP_PASSWORD') ?: ($_ENV['SMTP_PASSWORD'] ?? '');
$fromEmail = getenv('SMTP_FROM_EMAIL') ?: ($_ENV['SMTP_FROM_EMAIL'] ?? ('noreply@' . ($_SERVER['HTTP_HOST'] ?? 'localhost')));
$fromName = getenv('SMTP_FROM_NAME') ?: ($_ENV['SMTP_FROM_NAME'] ?? 'Controle Financeiro');

// Defina constantes para uso direto em outros arquivos, se preferir
defined('SMTP_HOST') || define('SMTP_HOST', $smtpHost);
defined('SMTP_PORT') || define('SMTP_PORT', $smtpPort);
defined('SMTP_USERNAME') || define('SMTP_USERNAME', $smtpUser);
defined('SMTP_PASSWORD') || define('SMTP_PASSWORD', $smtpPass);
defined('SMTP_FROM_EMAIL') || define('SMTP_FROM_EMAIL', $fromEmail);
defined('SMTP_FROM_NAME') || define('SMTP_FROM_NAME', $fromName);

/**
 * enviarEmail: usa PHPMailer (se instalado) com SMTP, ou faz fallback para mail().
 * Preencha o .env com as chaves descritas abaixo.
 * Variáveis esperadas no .env:
 * SMTP_HOST, SMTP_PORT, SMTP_USERNAME, SMTP_PASSWORD, SMTP_FROM_EMAIL, SMTP_FROM_NAME
 */
function enviarEmail(
    string $para,
    string $assunto,
    string $mensagem,
    bool $html = false
): bool {
    $host = trim((string) SMTP_HOST);
    $port = (int) SMTP_PORT;
    $user = trim((string) SMTP_USERNAME);
    $pass = (string) SMTP_PASSWORD;
    $from = trim((string) SMTP_FROM_EMAIL);
    $name = trim((string) SMTP_FROM_NAME);

    if (!filter_var($para, FILTER_VALIDATE_EMAIL)) {
        error_log('Tentativa de envio para endereço inválido: ' . $para);
        return false;
    }

    if (!filter_var($from, FILTER_VALIDATE_EMAIL)) {
        error_log('SMTP_FROM_EMAIL inválido: ' . $from);
        return false;
    }

    if (class_exists(\PHPMailer\PHPMailer\PHPMailer::class)) {
        try {
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);

            $mail->isSMTP();
            $mail->Host = $host !== '' ? $host : 'localhost';
            $mail->SMTPAuth = $user !== '' && $pass !== '';

            if ($mail->SMTPAuth) {
                $mail->Username = $user;
                $mail->Password = $pass;
            }

            $mail->Port = $port > 0 ? $port : 587;

            if ($mail->Port === 465) {
                $mail->SMTPSecure =
                    \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
            } else {
                $mail->SMTPSecure =
                    \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            }

            /*
             * Preferencialmente mantenha a validação de certificado habilitada.
             * Só use estas opções se o servidor realmente apresentar erro
             * de certificado.
             */
            $mail->SMTPOptions = [
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true,
                ],
            ];

            $mail->SMTPDebug = 0;
            $mail->CharSet = 'UTF-8';
            $mail->Encoding = 'base64';

            $mail->setFrom($from, $name);
            $mail->addAddress($para);

            $mail->Subject = $assunto;

            if ($html) {
                $mail->isHTML(true);
                $mail->Body = $mensagem;
                $mail->AltBody = html_entity_decode(
                    strip_tags(
                        str_replace(
                            ['<br>', '<br/>', '<br />'],
                            "\n",
                            $mensagem
                        )
                    ),
                    ENT_QUOTES,
                    'UTF-8'
                );
            } else {
                $mail->isHTML(false);
                $mail->Body = $mensagem;
                $mail->AltBody = $mensagem;
            }

            return $mail->send();
        } catch (\Throwable $erro) {
            error_log(
                'Erro ao enviar e-mail via PHPMailer para '
                    . $para
                    . ': '
                    . $erro->getMessage()
            );

            return false;
        }
    }

    $headers = 'From: '
        . $name
        . ' <'
        . $from
        . ">\r\n";

    $headers .= 'Reply-To: ' . $from . "\r\n";
    $headers .= "MIME-Version: 1.0\r\n";

    if ($html) {
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    } else {
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    }

    $headers .= "Content-Transfer-Encoding: 8bit\r\n";
    $headers .= 'X-Mailer: PHP/' . phpversion();

    return mail(
        $para,
        $assunto,
        $mensagem,
        $headers
    );
}
