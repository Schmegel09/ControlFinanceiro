<?php

declare(strict_types=1);

/*
Legenda de alterações em `includes/proteger.php`:
- Arquivo criado para evitar que arquivos da pasta `pages/` sejam acessados diretamente.
- Verifica se a aplicação foi inicializada (`APP_INIT` definida no front controller/dispatcher).

Como ajustar:
- Se quiser alterar a resposta em caso de acesso direto, edite o `http_response_code` ou a mensagem de `exit()`.
*/

// Protege arquivos que não devem ser acessados diretamente via URL.
if (!defined('APP_INIT')) {
	http_response_code(403);
	exit('Acesso direto não permitido.');
}
