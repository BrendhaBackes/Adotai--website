<?php
declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';

exigirTipo(['veterinario', 'veterinario_pendente']);

$perfil = [];
$planos = 0;
$erro = '';
try {
    $db = db();
    $stmt = $db->prepare(
        'SELECT id_veterinario, nome, status
         FROM veterinario WHERE id_usuario = :usuario LIMIT 1'
    );
    $stmt->execute(['usuario' => usuarioId()]);
    $perfil = $stmt->fetch() ?: [];
    if (!$perfil) {
        http_response_code(404);
        exit('Perfil veterinário não encontrado.');
    }

    $stmt = $db->prepare('SELECT COUNT(*) FROM plano_veterinario WHERE id_veterinario = :id');
    $stmt->execute(['id' => $perfil['id_veterinario']]);
    $planos = (int)$stmt->fetchColumn();
} catch (Throwable $e) {
    $erro = mensagemErro($e);
}

cabecalho('Painel veterinário');
?>
<?php if ($erro): ?><div class="notice error"><?= h($erro) ?></div><?php endif; ?>
<section class="intro-box">
    <h2>Olá, <?= h($perfil['nome'] ?? 'profissional') ?>!</h2>
    <p>Gerencie seu perfil público, escolha como os tutores podem falar com você e cadastre os planos oferecidos pela sua clínica.</p>
    <p><strong>Status do perfil:</strong> <?= h($perfil['status'] ?? 'Pendente') ?>.</p>
</section>
<div class="stats-grid">
    <article class="stat-card"><span>Planos cadastrados</span><strong><?= $planos ?></strong></article>
    <article class="stat-card"><span>Visibilidade do perfil</span><strong><?= ($perfil['status'] ?? '') === 'Ativo' ? 'Ativo' : 'Aguardando' ?></strong></article>
</div>
<div class="filter-actions">
    <a class="btn btn-primary" href="perfil_veterinario.php">Editar perfil e contato</a>
    <?php if (($perfil['status'] ?? '') === 'Ativo'): ?>
        <a class="btn btn-outline" href="gerenciar_planos_veterinario.php">Gerenciar meus planos</a>
    <?php else: ?>
        <span class="form-help">O cadastro de planos fica disponível após a aprovação do perfil.</span>
    <?php endif; ?>
    <a class="btn btn-outline" href="veterinarios.php">Ver diretório público</a>
</div>
<?php rodape(); ?>
