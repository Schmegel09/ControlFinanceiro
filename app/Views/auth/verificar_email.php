<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/Core/proteger.php';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirmar e-mail - Controle Financeiro</title>
    <link rel="stylesheet" href="/assets/css/base.css">
    <link rel="stylesheet" href="/assets/css/pages/login.css">
</head>
<body>
    <main class="container">
        <h1>Confirmar e-mail</h1>

        <?php if ($mensagem !== ''): ?>
            <div class="msg <?= $tipoMensagem === 'sucesso' ? 'sucesso' : '' ?>" role="<?= $tipoMensagem === 'erro' ? 'alert' : 'status' ?>">
                <?= htmlspecialchars($mensagem, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <?php if ($exibirFormularioReenvio): ?>
            <form method="post" action="/verificar-email">
                <div class="form-group">
                    <label for="email">E-mail usado no cadastro</label>
                    <input type="email" id="email" name="email" required autocomplete="email">
                </div>
                <button type="submit">Reenviar confirmação</button>
            </form>
        <?php endif; ?>

        <p><a href="/login">Voltar ao login</a></p>
    </main>
</body>
</html>
