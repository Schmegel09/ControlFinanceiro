<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/Core/proteger.php';
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar senha</title>
    <link rel="stylesheet" href="/assets/css/base.css">
    <link rel="stylesheet" href="/assets/css/pages/recuperar-senha.css">
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
                <a href="/recuperar-senha?reiniciar=1">Usar outro e-mail</a>
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
