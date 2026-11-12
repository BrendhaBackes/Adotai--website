<?php
declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';

$lista = [];
$erro = '';
try {
    $lista = db()->query(
        "SELECT id_veterinario, nome, cnpj, crmv, especialidade, email, telefone, canal_contato, cidade, uf,
                endereco, descricao, servicos, planos, valor_consulta, foto_url
         FROM veterinario
         WHERE status = 'Ativo'
         ORDER BY nome ASC"
    )->fetchAll();
} catch (Throwable $e) {
    $erro = mensagemErro($e);
}

cabecalho('Veterinários parceiros');
?>
<section class="vet-directory-intro">
    <div>
        <span class="eyebrow">CUIDADO COM QUEM ENTENDE</span>
        <h2>Uma rede para acompanhar a saúde do seu companheiro</h2>
        <p>Conheça profissionais veterinários parceiros, solicite um horário e converse diretamente sobre as necessidades do seu animal.</p>
        <a class="btn btn-primary" href="#profissionais">Encontrar um profissional</a>
    </div>
    <div class="vet-directory-highlight">
        <span aria-hidden="true">✚</span>
        <strong><?= number_format(count($lista), 0, ',', '.') ?></strong>
        <span>profissionais ativos no momento</span>
    </div>
</section>

<section class="vet-services">
    <div class="home-section-heading"><div><span class="eyebrow">APOIO PARA CADA FASE</span><h2>Como os profissionais podem ajudar</h2></div></div>
    <div class="vet-services-grid">
        <article><span aria-hidden="true">♡</span><h3>Consultas de rotina</h3><p>Converse sobre avaliação clínica, desenvolvimento e bem-estar do seu pet.</p></article>
        <article><span aria-hidden="true">✦</span><h3>Prevenção</h3><p>Peça orientação profissional sobre vacinas, cuidados preventivos e hábitos saudáveis.</p></article>
        <article><span aria-hidden="true">⌂</span><h3>Acompanhamento</h3><p>Organize retornos e mantenha o histórico de cuidados junto à clínica.</p></article>
    </div>
</section>

<section id="profissionais" class="vet-directory">
    <div class="home-section-heading">
        <div><span class="eyebrow">PROFISSIONAIS PARCEIROS</span><h2>Encontre seu veterinário</h2></div>
        <?php if ($lista): ?><span class="plans-count"><?= count($lista) ?> profissional(is) disponível(is)</span><?php endif; ?>
    </div>
    <?php if ($erro): ?>
        <div class="database-message error"><?= h($erro) ?></div>
    <?php elseif (!$lista): ?>
        <div class="empty-state">
            <h2>Nenhum profissional ativo no momento</h2>
            <p>Novos parceiros aparecerão aqui assim que forem aprovados pela equipe.</p>
            <a class="btn btn-primary" href="cadastro_veterinario.php">Quero participar como veterinário</a>
        </div>
    <?php else: ?>
        <div class="vet-directory-grid">
            <?php foreach ($lista as $veterinario): ?>
                <?php preg_match('/^./u', (string)$veterinario['nome'], $iniciais); ?>
                <article class="vet-profile-card">
                    <div class="vet-profile-heading">
                        <?php if (perfilFotoSrc($veterinario['foto_url'] ?? '')): ?>
                            <img class="vet-avatar" style="object-fit:cover" src="<?= h(perfilFotoSrc($veterinario['foto_url'])) ?>" alt="Foto de <?= h($veterinario['nome']) ?>">
                        <?php else: ?>
                            <span class="vet-avatar" aria-hidden="true"><?= h($iniciais[0] ?? '?') ?></span>
                        <?php endif; ?>
                        <div><span class="vet-active-label"><i></i> Parceiro ativo</span><h3><?= h($veterinario['nome']) ?></h3></div>
                    </div>
                    <div class="vet-profile-details">
                        <?php if (!empty($veterinario['especialidade'])): ?><p><span>Área de atuação</span><strong><?= h($veterinario['especialidade']) ?></strong></p><?php endif; ?>
                        <?php if (!empty($veterinario['crmv'])): ?><p><span>Registro profissional</span><strong><?= h($veterinario['crmv']) ?></strong></p><?php endif; ?>
                        <?php if (!empty($veterinario['cidade']) || !empty($veterinario['uf'])): ?><p><span>Localização</span><strong><?= h($veterinario['cidade'] ?? '') ?><?= !empty($veterinario['uf']) ? ' - ' . h($veterinario['uf']) : '' ?></strong></p><?php endif; ?>
                        <?php if (!empty($veterinario['endereco'])): ?><p><span>Endereço</span><strong><?= h($veterinario['endereco']) ?></strong></p><?php endif; ?>
                        <?php if (!empty($veterinario['cnpj'])): ?><p><span>Clínica / CNPJ</span><strong><?= h($veterinario['cnpj']) ?></strong></p><?php endif; ?>
                    </div>
                    <?php if (!empty($veterinario['descricao'])): ?><p><?= nl2br(h($veterinario['descricao'])) ?></p><?php endif; ?>
                    <?php if (!empty($veterinario['servicos'])): ?><div class="vet-profile-details"><p><span>Serviços</span><strong><?= nl2br(h($veterinario['servicos'])) ?></strong></p></div><?php endif; ?>
                    <?php if (!empty($veterinario['planos'])): ?><div class="vet-profile-details"><p><span>Planos e valores informados</span><strong><?= nl2br(h($veterinario['planos'])) ?></strong></p></div><?php endif; ?>
                    <div class="vet-profile-actions">
                            <?php
                            $retornoAgendamento = 'agendar_consulta.php?id_veterinario=' . (int)$veterinario['id_veterinario'];
                            $agendamentoUrl = usuarioId() > 0
                                ? $retornoAgendamento
                                : 'login.php?retorno=' . urlencode($retornoAgendamento);
                            ?>
                            <a class="btn btn-primary" href="<?= h($agendamentoUrl) ?>">Solicitar consulta</a>
                        <?php if (($veterinario['canal_contato'] ?? 'whatsapp') === 'whatsapp' && !empty($veterinario['telefone'])): ?>
                            <a class="vet-contact-link" target="_blank" rel="noopener noreferrer" href="<?= h(linkWhatsApp($veterinario['telefone'])) ?>">Conversar pelo WhatsApp</a>
                        <?php elseif (!empty($veterinario['email'])): ?>
                            <a class="vet-contact-link" href="mailto:<?= h($veterinario['email']) ?>">Enviar e-mail ao profissional</a>
                        <?php elseif (!empty($veterinario['telefone'])): ?>
                            <a class="vet-contact-link" href="tel:<?= h(preg_replace('/\D+/', '', $veterinario['telefone']) ?? '') ?>">Ligar para <?= h($veterinario['nome']) ?></a>
                        <?php endif; ?>
                    </div>
                    <p class="vet-price-note"><?php if ($veterinario['valor_consulta'] !== null): ?>Consulta informada: R$ <?= number_format((float)$veterinario['valor_consulta'], 2, ',', '.') ?>. <?php endif; ?>Confirme disponibilidade, valores e condições diretamente com o profissional.</p>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<section class="vet-disclaimer">
    <strong>Antes de agendar</strong>
    <p>O pedido de consulta é uma solicitação de horário, não uma confirmação automática. A clínica confirma disponibilidade, valores e instruções diretamente com você.</p>
    <a class="text-link" href="planos.php#planos-veterinarios">Conhecer planos e cuidados veterinários →</a>
</section>

<div class="vet-top-action"><span>É veterinário(a) e quer fazer parte da rede?</span><a class="btn btn-outline" href="cadastro_veterinario.php">Criar conta profissional</a></div>
<?php rodape(); ?>
