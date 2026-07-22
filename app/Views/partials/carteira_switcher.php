<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/Core/proteger.php';

/** @var array{id: int, nome: string, tipo: string, papel?: string} $carteiraAtual */
/** @var array<int, array{id: int, nome: string, tipo: string, papel?: string}> $carteirasDisponiveis */
/** @var array<int, array{id: int, nome: string, online: int|string}> $membrosCarteiraAtual */
/** @var string $csrfTokenCarteiras */
/** @var string $urlRetornoCarteira */

if (!isset($carteiraAtual, $carteirasDisponiveis, $membrosCarteiraAtual, $csrfTokenCarteiras)) {
    throw new RuntimeException('Contexto da carteira não informado para o seletor.');
}

$outrosOnline = array_values(array_filter(
    $membrosCarteiraAtual,
    static fn (array $membro): bool => (int) $membro['id'] !== (int) ($_SESSION['usuario_id'] ?? 0)
        && (int) $membro['online'] === 1
));
?>


<section class="wallet-bar" aria-label="Carteira em uso">
    <?php if (telaClientePermitida($permissoesCliente ?? [], 'carteiras')): ?>
    <form method="post" action="/carteiras" class="wallet-switch-form">
        <input type="hidden" name="acao" value="selecionar">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfTokenCarteiras, ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="voltar" value="<?= htmlspecialchars($urlRetornoCarteira ?? '/dashboard', ENT_QUOTES, 'UTF-8') ?>">

        <div class="wallet-switch-field">
            <label for="wallet-switch-select">Carteira em uso</label>
            <select id="wallet-switch-select" name="carteira_id">
                <?php foreach ($carteirasDisponiveis as $carteira): ?>
                    <option value="<?= (int) $carteira['id'] ?>" <?= (int) $carteira['id'] === (int) $carteiraAtual['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($carteira['nome'], ENT_QUOTES, 'UTF-8') ?> (<?= $carteira['tipo'] === 'pessoal' ? 'privada' : 'casal' ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <button type="submit" class="wallet-switch-submit">Trocar</button>
        <a href="/carteiras" class="wallet-manage-link">Gerenciar</a>
    </form>
    <?php else: ?>
        <div class="wallet-switch-field">
            <label>Carteira em uso</label>
            <strong><?= htmlspecialchars((string) $carteiraAtual['nome'], ENT_QUOTES, 'UTF-8') ?></strong>
        </div>
    <?php endif; ?>

    <div class="wallet-meta" id="wallet-presence" data-current-user="<?= (int) ($_SESSION['usuario_id'] ?? 0) ?>">
        <?php if ($outrosOnline !== []): ?>
            <span class="wallet-online"><?= htmlspecialchars($outrosOnline[0]['nome'], ENT_QUOTES, 'UTF-8') ?> está online</span>
        <?php elseif ($carteiraAtual['tipo'] === 'casal'): ?>
            <span>Carteira compartilhada · ninguém mais online agora</span>
        <?php else: ?>
            <span>Somente você tem acesso</span>
        <?php endif; ?>
    </div>
</section>

<script>
    (() => {
        const presence = document.getElementById('wallet-presence');
        if (!presence || typeof window.fetch !== 'function') return;

        const currentUser = Number(presence.dataset.currentUser || 0);
        const refreshPresence = async () => {
            try {
                const response = await fetch('/api/presenca/status', {
                    headers: { 'Accept': 'application/json' },
                    credentials: 'same-origin',
                    cache: 'no-store'
                });
                if (!response.ok) return;

                const data = await response.json();
                const otherOnline = Array.isArray(data.membros)
                    ? data.membros.find((member) => Number(member.id) !== currentUser && member.online === true)
                    : null;

                presence.replaceChildren();
                const text = document.createElement('span');
                if (otherOnline) {
                    text.className = 'wallet-online';
                    text.textContent = `${otherOnline.nome} está online`;
                } else if (data.tipo_carteira === 'casal') {
                    text.textContent = 'Carteira compartilhada · ninguém mais online agora';
                } else {
                    text.textContent = 'Somente você tem acesso';
                }
                presence.appendChild(text);
            } catch (error) {
                // Mantém o último estado exibido se a atualização falhar.
            }
        };

        window.setInterval(refreshPresence, 30000);
    })();
</script>
