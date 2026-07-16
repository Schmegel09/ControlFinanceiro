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
function enviarEmail(string $para, string $assunto, string $mensagem): bool
{
    // usa as constantes definidas acima
    $host = SMTP_HOST;
    $port = SMTP_PORT;
    $user = SMTP_USERNAME;
    $pass = SMTP_PASSWORD;
    $from = SMTP_FROM_EMAIL;
    $name = SMTP_FROM_NAME;

    // Se PHPMailer estiver instalado, usar SMTP autenticado
    if (class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
        try {
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = $host ?: 'localhost';
            $mail->SMTPAuth = ($user !== '' && $pass !== '');
            if ($mail->SMTPAuth) {
                $mail->Username = $user;
                $mail->Password = $pass;
            }
            $mail->Port = $port ?: 587;
            $mail->SMTPSecure = ($mail->Port == 465) ? 'ssl' : 'tls';
            // Desabilita verificação rigorosa de certificado (Hostinger pode ter certificados auto-assinados)
            $mail->SMTPOptions = array(
                'ssl' => array(
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                )
            );
            // Depuração desativada em produção (alterar para 2 se necessário diagnosticar)
            $mail->SMTPDebug = 0;
            $mail->Debugoutput = 'echo';

            $mail->CharSet = 'UTF-8';
            $mail->Encoding = 'base64';
            $mail->setFrom($from, $name);
            $mail->addAddress($para);
            $mail->Subject = $assunto;
            $mail->Body = $mensagem;
            $mail->AltBody = strip_tags($mensagem);

            $sent = $mail->send();
            if (!$sent) {
                error_log('PHPMailer send returned false for recipient: ' . $para);
            }
            return $sent;
        } catch (\Exception $e) {
            error_log('PHPMailer exception (' . $host . ':' . $port . '): ' . $e->getMessage());
            return false;
        }
    }

    // Fallback nativo
    $headers = 'From: ' . $name . ' <' . $from . "\r\n";
    $headers .= 'Reply-To: ' . $from . "\r\n";
    $headers .= 'MIME-Version: 1.0' . "\r\n";
    $headers .= 'Content-Type: text/plain; charset=UTF-8' . "\r\n";
    $headers .= 'Content-Transfer-Encoding: 8bit' . "\r\n";
    $headers .= 'X-Mailer: PHP/' . phpversion();
    return mail($para, $assunto, $mensagem, $headers);
}
