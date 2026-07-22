<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/Core/proteger.php';
require_once dirname(__DIR__) . '/Services/SaasService.php';

/**
 * @param array{tipo: string, mensagem: string} $resultado
 */
function definirFlashAdmin(array $resultado): void
{
    $_SESSION['flash_admin_clientes'] = [
        'mensagem' => $resultado['mensagem'],
        'tipo' => $resultado['tipo'],
    ];
}

/**
 * @return array{mensagem: string, tipo: string}
 */
function consumirFlashAdmin(): array
{
    $flash = $_SESSION['flash_admin_clientes'] ?? null;
    unset($_SESSION['flash_admin_clientes']);

    if (!is_array($flash) || !is_string($flash['mensagem'] ?? null)) {
        return ['mensagem' => '', 'tipo' => 'sucesso'];
    }

    return ['mensagem' => $flash['mensagem'], 'tipo' => $flash['tipo']];
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $resultado = ['tipo' => 'erro', 'mensagem' => 'Não foi possível atualizar o cliente.'];

    try {
        if (!tokenCsrfAdminValido($_POST['csrf_token'] ?? null)) {
            throw new RuntimeException('Sessão expirada. Atualize a página e tente novamente.');
        }

        $acao = is_string($_POST['acao'] ?? null) ? $_POST['acao'] : 'salvar';
        $emailAdmin = (string) ($_SESSION['usuario_email'] ?? 'superadmin');
        $administradorId = (int) ($_SESSION['usuario_id'] ?? 0);

        if ($acao === 'alterar_papel') {
            $usuarioAlvoId = filter_var($_POST['usuario_id'] ?? null, FILTER_VALIDATE_INT);
            $novoPapel = is_string($_POST['papel_sistema'] ?? null)
                ? $_POST['papel_sistema']
                : '';

            if (!$usuarioAlvoId || $usuarioAlvoId <= 0) {
                throw new InvalidArgumentException('Usuário inválido.');
            }

            alterarPapelSistemaUsuario(
                $pdo,
                (int) $usuarioAlvoId,
                $novoPapel,
                $administradorId
            );
            $resultado = [
                'tipo' => 'sucesso',
                'mensagem' => $novoPapel === 'superadmin'
                    ? 'Usuário promovido a superadministrador.'
                    : 'Função de superadministrador removida.',
            ];
        } elseif ($acao === 'renovar') {
            $clienteId = filter_var($_POST['cliente_id'] ?? null, FILTER_VALIDATE_INT);
            if (!$clienteId || $clienteId <= 0) {
                throw new InvalidArgumentException('Cliente inválido para renovação.');
            } else {
                $dias = filter_var($_POST['dias_renovacao'] ?? 30, FILTER_VALIDATE_INT);
                renovarCliente($pdo, (int) $clienteId, $dias ?: 30, $emailAdmin);
                $resultado = ['tipo' => 'sucesso', 'mensagem' => 'Assinatura renovada e acesso liberado.'];
            }
        } elseif ($acao === 'alterar_status_rapido') {
            $clienteId = filter_var($_POST['cliente_id'] ?? null, FILTER_VALIDATE_INT);
            if (!$clienteId || $clienteId <= 0) {
                throw new InvalidArgumentException('Cliente inválido para alterar status.');
            } else {
                $novoStatus = is_string($_POST['novo_status'] ?? null)
                    ? $_POST['novo_status']
                    : '';
                $motivoRapido = is_string($_POST['motivo_rapido'] ?? null)
                    ? $_POST['motivo_rapido']
                    : '';
                alterarStatusRapidoCliente(
                    $pdo,
                    (int) $clienteId,
                    $novoStatus,
                    $emailAdmin,
                    $motivoRapido
                );
                $mensagensStatus = [
                    'ativo' => 'Acesso do cliente liberado.',
                    'em_atraso' => 'Cliente marcado como em atraso.',
                    'bloqueado' => 'Acesso do cliente bloqueado.',
                    'cancelado' => 'Assinatura do cliente cancelada.',
                    'pendente' => 'Cliente retornado para aprovação pendente.',
                ];
                $resultado = [
                    'tipo' => 'sucesso',
                    'mensagem' => $mensagensStatus[$novoStatus] ?? 'Status do cliente atualizado.',
                ];
            }
        } elseif ($acao === 'salvar') {
            $clienteId = filter_var($_POST['cliente_id'] ?? null, FILTER_VALIDATE_INT);
            if (!$clienteId || $clienteId <= 0) {
                throw new InvalidArgumentException('Cliente inválido para salvar.');
            } else {
                atualizarClienteAdministracao(
                    $pdo,
                    (int) $clienteId,
                    [
                        'nome' => is_string($_POST['nome'] ?? null) ? $_POST['nome'] : '',
                        'dominio' => is_string($_POST['dominio'] ?? null) ? $_POST['dominio'] : '',
                        'status' => is_string($_POST['status'] ?? null) ? $_POST['status'] : '',
                        'vencimento' => is_string($_POST['vencimento'] ?? null) ? $_POST['vencimento'] : '',
                        'dias_tolerancia' => (int) ($_POST['dias_tolerancia'] ?? 0),
                        'motivo_bloqueio' => is_string($_POST['motivo_bloqueio'] ?? null)
                            ? $_POST['motivo_bloqueio']
                            : '',
                    ],
                    $emailAdmin
                );
                $telasRecebidas = is_array($_POST['telas'] ?? null) ? $_POST['telas'] : [];
                $telasPermitidas = array_values(array_filter(
                    $telasRecebidas,
                    static fn(mixed $tela): bool => is_string($tela)
                ));
                atualizarPermissoesCliente(
                    $pdo,
                    (int) $clienteId,
                    $telasPermitidas,
                    $emailAdmin
                );
                $resultado = ['tipo' => 'sucesso', 'mensagem' => 'Dados e acesso do cliente atualizados.'];
            }
        } else {
            $resultado = ['tipo' => 'erro', 'mensagem' => 'Ação desconhecida ou inválida.'];
        }
    } catch (PDOException $erro) {
        error_log(sprintf(
            '[ADMIN CLIENTES][PDO] SQLSTATE: %s | Mensagem: %s | Arquivo: %s | Linha: %d',
            $erro->getCode(),
            $erro->getMessage(),
            $erro->getFile(),
            $erro->getLine()
        ));

        $resultado = [
            'tipo' => 'erro',
            'mensagem' => sprintf(
                'Erro no banco [%s]: %s',
                $erro->getCode(),
                $erro->getMessage()
            ),
        ];
    } catch (Throwable $erro) {
        error_log(sprintf(
            '[ADMIN CLIENTES][GERAL] %s | Arquivo: %s | Linha: %d',
            $erro->getMessage(),
            $erro->getFile(),
            $erro->getLine()
        ));

        $resultado = [
            'tipo' => 'erro',
            'mensagem' => $erro->getMessage(),
        ];
    }

    definirFlashAdmin($resultado);
    header('Location: /admin-clientes', true, 303);
    exit;
}

$flash = consumirFlashAdmin();
$mensagem = $flash['mensagem'];
$tipoMensagem = $flash['tipo'];
$clientes = listarClientesAdministracao($pdo);
$usuariosSistema = listarUsuariosAdministracao($pdo);
$csrfTokenAdmin = tokenCsrfAdmin();

$resumoClientes = array_fill_keys(STATUS_CLIENTE_VALIDOS, 0);
foreach ($clientes as $cliente) {
    $status = (string) $cliente['status'];
    if (array_key_exists($status, $resumoClientes)) {
        $resumoClientes[$status]++;
    }
}

require dirname(__DIR__) . '/Views/admin/clientes.php';
