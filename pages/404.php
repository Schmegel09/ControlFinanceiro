<?php

declare(strict_types=1);

/*
Legenda (404.php):
- Página de erro exibida quando a rota requisitada não existe no `routes/web.php`.
- Para personalizar, edite este arquivo e o layout HTML.
*/

?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 - Página não encontrada</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { min-height: 100vh; min-height: 100dvh; display: flex; align-items: center; justify-content: center; padding: clamp(12px, 4vw, 20px); background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        main { width: min(100%, 560px); padding: clamp(28px, 7vw, 48px); background: white; border-radius: 18px; box-shadow: 0 18px 50px rgba(0,0,0,.18); text-align: center; }
        h1 { margin-bottom: 14px; color: #333; font-size: clamp(26px, 8vw, 38px); line-height: 1.2; }
        p { margin-top: 12px; color: #666; font-size: 16px; line-height: 1.6; }
        a.button { display: inline-flex; align-items: center; justify-content: center; min-height: 44px; margin-top: 12px; padding: 12px 22px; border-radius: 10px; background: #4f5fc9; color: white; font-weight: 700; text-decoration: none; }
        a.button:hover { background: #3f4fae; }
    </style>
    <?php require dirname(__DIR__) . '/includes/responsive_styles.php'; ?>
</head>
<body>
    <main>
        <h1>404 - Página não encontrada</h1>
        <p>A página que você está procurando não existe.</p>
        <p><a href="/login" class="button">Voltar para o início</a></p>
    </main>
</body>
</html>
