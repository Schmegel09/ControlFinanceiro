<?php

declare(strict_types=1);

/*
Proteção compartilhada pelos arquivos internos da aplicação:
- Impede execução isolada de controllers, models, services e views.
- Verifica se a aplicação foi inicializada (`APP_INIT` definida no front controller/dispatcher).

Como ajustar:
- Se quiser alterar a resposta em caso de acesso direto, edite o `http_response_code` ou a mensagem de `exit()`.
*/

// Protege arquivos que não devem ser acessados diretamente via URL.
if (!defined('APP_INIT')) {
	http_response_code(403);
	exit('Acesso direto não permitido.');
}
