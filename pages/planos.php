<?php
require_once __DIR__ . '/_bootstrap.php';
$planos = [];
$planosProfissionais = [];
$veterinariosAtivos = 0;
$erro = '';
try {
    $db = db();
    $sql = "SELECT id_plano, nome, descricao, publico_alvo, beneficios, valor, periodicidade, destaque
            FROM plano
            WHERE ativo = 1
            ORDER BY destaque DESC, ordem_exibicao ASC, nome ASC";
    $planos = $db->query($sql)->fetchAll(MYSQLI_ASSOC);
    $veterinariosAtivos = (int)$db->query("SELECT COUNT(*) FROM veterinario WHERE status = 'Ativo'")->fetchColumn();
    $planosProfissionais = $db->query(
        "SELECT p.id_plano, p.nome AS nome_plano, p.descricao, p.beneficios, p.valor, p.periodicidade,
                v.nome AS nome_veterinario, v.especialidade, v.email, v.telefone, v.canal_contato, v.cidade, v.uf
         FROM plano_veterinario p
         INNER JOIN veterinario v ON v.id_veterinario = p.id_veterinario
         WHERE p.ativo = 1 AND v.status = 'Ativo'
         ORDER BY p.nome, v.nome"
    )->fetchAll(MYSQLI_ASSOC);
} catch (Throwable $e) {
    $erro = mensagemErro($e);
}
cabecalho('Planos e cuidados veterinários');
?>
<section class="intro-box">
    <span class="card-icon" aria-hidden="true">✦</span>
    <h2>Adoção responsável e cuidado veterinário, lado a lado</h2>
    <p>Conheça as formas gratuitas de participar da rede Adotaí e encontre apoio veterinário para acompanhar a saúde do seu companheiro.</p>
    <div class="plans-quick-links">
        <a href="#planos-comunidade">Planos da comunidade</a>
        <a href="#planos-veterinarios">Planos veterinários</a>
        <a href="veterinarios.php">Profissionais parceiros</a>
    </div>
</section>
<?php if ($erro): ?>
    <div id="planos-comunidade" class="notice error"><strong>Não foi possível carregar os planos.</strong><br><?= h($erro) ?> Verifique se executou a migração do banco descrita no README.</div>
<?php elseif (!$planos): ?>
    <div id="planos-comunidade" class="empty-state">
        <h2>Nenhum plano disponível no momento</h2>
        <p>Assim que a equipe cadastrar e ativar os planos no painel administrativo, eles aparecerão automaticamente nesta página.</p>
    </div>
<?php else: ?>
    <section id="planos-comunidade" class="plans-section">
        <div class="home-section-heading">
            <div><span class="eyebrow">PARTICIPAR É GRATUITO</span><h2>Planos para cada jornada</h2></div>
            <span class="plans-count"><?= count($planos) ?> opções ativas</span>
        </div>
        <div class="cards-grid planos-grid">
        <?php foreach ($planos as $plano): ?>
            <article class="info-card plano-card <?= !empty($plano['destaque']) ? 'plano-destaque' : '' ?>">
                <?php if (!empty($plano['destaque'])): ?><span class="plano-selo">Em destaque</span><?php endif; ?>
                <span class="card-icon" aria-hidden="true"><?= stripos((string)$plano['nome'], 'protetor') !== false ? '⌂' : (stripos((string)$plano['nome'], 'ong') !== false ? '✿' : '♡') ?></span>
                <h2><?= h($plano['nome']) ?></h2>
                <?php if (!empty($plano['publico_alvo'])): ?><p class="plano-publico"><?= h($plano['publico_alvo']) ?></p><?php endif; ?>
                <p><?= nl2br(h($plano['descricao'])) ?></p>
                <?php if (trim((string)$plano['beneficios']) !== ''): ?>
                    <div class="plano-beneficios">
                        <strong>O que você pode fazer</strong>
                        <ul>
                            <?php foreach (preg_split('/[\r\n;]+/', (string)$plano['beneficios'], -1, PREG_SPLIT_NO_EMPTY) as $beneficio): ?>
                                <li><span aria-hidden="true">✓</span><?= h(trim($beneficio)) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                <div class="plano-preco">
                    <?php if ((float)$plano['valor'] <= 0): ?>
                        <strong>Gratuito</strong>
                    <?php else: ?>
                        <strong>R$ <?= number_format((float)$plano['valor'], 2, ',', '.') ?></strong>
                        <?php if (!empty($plano['periodicidade'])): ?><span>/ <?= h($plano['periodicidade']) ?></span><?php endif; ?>
                    <?php endif; ?>
                </div>
                <a class="btn <?= !empty($plano['destaque']) ? 'btn-primary' : 'btn-outline' ?>" href="<?= stripos((string)$plano['nome'], 'protetor') !== false ? 'cadastro_protetor.php' : 'cadastro.php' ?>">
                    <?= stripos((string)$plano['nome'], 'protetor') !== false ? 'Quero divulgar animais' : 'Criar minha conta' ?>
                </a>
            </article>
        <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>
<section id="planos-veterinarios" class="vet-plans-section">
    <div class="vet-plans-intro">
        <span class="eyebrow">SAÚDE PARA TODAS AS FASES</span>
        <h2>Planos e cuidados veterinários</h2>
        <p>O melhor cuidado depende da idade, do histórico e das necessidades de cada animal. Converse com um profissional para montar uma rotina adequada — sem pacotes iguais para todos.</p>
        <div class="vet-availability"><span aria-hidden="true">✚</span><strong><?= number_format($veterinariosAtivos, 0, ',', '.') ?></strong> profissionais parceiros ativos</div>
    </div>
    <section class="plans-section">
        <div class="home-section-heading"><div><span class="eyebrow">CADASTRADOS PELOS PROFISSIONAIS</span><h2>Planos veterinários disponíveis</h2></div></div>
        <?php if (!$planosProfissionais): ?>
            <div class="empty-state"><p>Os planos cadastrados pelos veterinários aparecerão aqui após a aprovação do profissional.</p></div>
        <?php else: ?>
            <div class="cards-grid planos-grid">
                <?php foreach ($planosProfissionais as $plano): ?>
                    <article class="info-card plano-card">
                        <span class="card-icon" aria-hidden="true">✚</span>
                        <h3><?= h($plano['nome_plano']) ?></h3>
                        <p class="plano-publico"><?= h($plano['nome_veterinario']) ?><?= !empty($plano['especialidade']) ? ' · ' . h($plano['especialidade']) : '' ?></p>
                        <?php if (!empty($plano['cidade']) || !empty($plano['uf'])): ?><p><?= h($plano['cidade'] ?? '') ?><?= !empty($plano['uf']) ? ' - ' . h($plano['uf']) : '' ?></p><?php endif; ?>
                        <p><?= nl2br(h($plano['descricao'])) ?></p>
                        <?php if (trim((string)$plano['beneficios']) !== ''): ?>
                            <div class="plano-beneficios"><strong>Inclui</strong><ul>
                                <?php foreach (preg_split('/[\r\n;]+/', (string)$plano['beneficios'], -1, PREG_SPLIT_NO_EMPTY) as $beneficio): ?>
                                    <li><span aria-hidden="true">✓</span><?= h(trim($beneficio)) ?></li>
                                <?php endforeach; ?>
                            </ul></div>
                        <?php endif; ?>
                        <div class="plano-preco">
                            <?php if ((float)$plano['valor'] <= 0): ?><strong>Gratuito</strong>
                            <?php else: ?><strong>R$ <?= number_format((float)$plano['valor'], 2, ',', '.') ?></strong><?php if (!empty($plano['periodicidade'])): ?><span>/ <?= h($plano['periodicidade']) ?></span><?php endif; ?><?php endif; ?>
                        </div>
                        <?php if (($plano['canal_contato'] ?? 'whatsapp') === 'whatsapp' && !empty($plano['telefone'])): ?>
                            <a class="btn btn-primary" target="_blank" rel="noopener noreferrer" href="<?= h(linkWhatsApp($plano['telefone'])) ?>">Consultar pelo WhatsApp</a>
                        <?php elseif (!empty($plano['email'])): ?>
                            <a class="btn btn-primary" href="mailto:<?= h($plano['email']) ?>">Consultar por e-mail</a>
                        <?php endif; ?>
                        <p class="form-help">Confirme disponibilidade, valores e condições diretamente com o profissional.</p>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
    <div class="vet-plan-grid">
        <article class="vet-plan-card">
            <span class="vet-plan-icon">♡</span>
            <span class="eyebrow">ACOMPANHAMENTO</span>
            <h3>Rotina preventiva</h3>
            <p>Organize consultas de rotina e converse sobre prevenção, alimentação e bem-estar.</p>
            <ul><li>Avaliação clínica individual</li><li>Orientação conforme a fase da vida</li><li>Planejamento de retornos com o profissional</li></ul>
        </article>
        <article class="vet-plan-card vet-plan-card-featured">
            <span class="vet-plan-icon">✚</span>
            <span class="eyebrow">CUIDADO ESSENCIAL</span>
            <h3>Vacinas e prevenção</h3>
            <p>Peça uma avaliação para entender quais cuidados preventivos são indicados para o seu pet.</p>
            <ul><li>Histórico de vacinação avaliado</li><li>Orientação profissional personalizada</li><li>Cuidados preventivos recomendados</li></ul>
        </article>
        <article class="vet-plan-card">
            <span class="vet-plan-icon">⌂</span>
            <span class="eyebrow">APOIO CONTÍNUO</span>
            <h3>Retornos e acompanhamento</h3>
            <p>Mantenha o acompanhamento quando o veterinário indicar reavaliações ou continuidade do cuidado.</p>
            <ul><li>Retornos combinados com a clínica</li><li>Acompanhamento do plano de cuidado</li><li>Histórico organizado com o profissional</li></ul>
        </article>
    </div>
    <div class="vet-plans-note">
        <span aria-hidden="true">ℹ</span>
        <p><strong>Sobre valores e cobertura:</strong> preços, disponibilidade, vacinas, exames, retornos e condições de cada plano são definidos pelo profissional ou clínica e confirmados antes do atendimento. O Adotaí não realiza cobrança nem substitui uma avaliação veterinária.</p>
    </div>
    <div class="vet-top-action">
        <a class="btn btn-primary" href="veterinarios.php">Ver veterinários parceiros</a>
        <?php if (usuarioId() > 0): ?>
            <a class="btn btn-outline" href="agendar_consulta.php">Solicitar uma consulta</a>
        <?php else: ?>
            <a class="btn btn-outline" href="login.php?retorno=<?= urlencode('agendar_consulta.php') ?>">Entre para solicitar uma consulta</a>
        <?php endif; ?>
        <a class="text-link" href="cadastro_veterinario.php">Sou veterinário e quero participar →</a>
    </div>
</section>
<section class="plans-help">
    <h2>Não sabe por onde começar?</h2>
    <p>Conheça um animal, converse com o responsável pela adoção e consulte um profissional parceiro para planejar os primeiros cuidados.</p>
    <a class="btn btn-primary" href="catalogo.php">Encontrar um animal</a>
</section>
<?php rodape(); ?>
