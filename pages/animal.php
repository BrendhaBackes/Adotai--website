<?php
declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';

$animal = null;
$semelhantes = [];
$erro = '';
$idInformado = (string)($_GET['id'] ?? '');
$idValidado = filter_var($idInformado, FILTER_VALIDATE_INT);
$id = $idValidado !== false && $idValidado >= 0 ? $idValidado : -1;

try {
    if ($id >= 0) {
        $db = db();
        $stmt = $db->prepare(
            'SELECT a.*, c.nome_categoria,
                    COALESCE(NULLIF(p.nome_organizacao, \'\'), u.nome) AS responsavel_nome,
                    p.email_contato, p.telefone_contato, p.tipo_perfil, p.canal_contato
             FROM animal a
             LEFT JOIN categoria c ON c.id_categoria = a.id_categoria
             LEFT JOIN protetor p ON p.id_protetor = a.id_protetor
             LEFT JOIN usuario u ON u.id = p.id_usuario
             WHERE a.id_animal = :id
               AND (a.status_aprovacao = \'Aprovado\' OR :admin = 1
                    OR EXISTS (SELECT 1 FROM protetor dono WHERE dono.id_protetor = a.id_protetor AND dono.id_usuario = :usuario))
             LIMIT 1'
        );
        $stmt->execute([
            'id' => $id,
            'admin' => in_array(tipoUsuario(), ['admin', 'administrador'], true) ? 1 : 0,
            'usuario' => usuarioId(),
        ]);
        $animal = $stmt->fetch() ?: null;
        if ($animal) {
            $stmt = $db->prepare(
                "SELECT id_animal, nome_animal, especie, idade_meses, porte, foto_url, sexo
                 FROM animal
                 WHERE status_aprovacao = 'Aprovado' AND status_adocao = 'Disponível' AND especie = :especie AND id_animal <> :id
                 ORDER BY data_cadastro DESC, nome_animal
                 LIMIT 3"
            );
            $stmt->execute(['especie' => $animal['especie'], 'id' => $id]);
            $semelhantes = $stmt->fetchAll();
        }
    }
} catch (Throwable $e) {
    $erro = mensagemErro($e);
}

$idadeTexto = 'Não informada';
if (isset($animal['idade_meses'])) {
    $meses = (int)$animal['idade_meses'];
    if ($meses < 12) {
        $idadeTexto = $meses . ($meses === 1 ? ' mês' : ' meses');
    } else {
        $anos = intdiv($meses, 12);
        $restante = $meses % 12;
        $idadeTexto = $anos . ($anos === 1 ? ' ano' : ' anos');
        if ($restante > 0) {
            $idadeTexto .= ' e ' . $restante . ($restante === 1 ? ' mês' : ' meses');
        }
    }
}

$contatoHref = '';
$contatoTexto = '';
if (!empty($animal['telefone_contato'])) {
    if (($animal['canal_contato'] ?? 'whatsapp') === 'whatsapp') {
        $contatoHref = linkWhatsApp($animal['telefone_contato']);
        $contatoTexto = 'Falar pelo WhatsApp';
    } else {
        $contatoHref = 'tel:' . (preg_replace('/\D+/', '', (string)$animal['telefone_contato']) ?? '');
        $contatoTexto = 'Ligar para o responsável';
    }
}
if (($animal['canal_contato'] ?? 'whatsapp') === 'email' && !empty($animal['email_contato'])) {
    $contatoHref = 'mailto:' . $animal['email_contato'];
    $contatoTexto = 'Enviar e-mail ao responsável';
} elseif ($contatoHref === '' && !empty($animal['email_contato'])) {
    $contatoHref = 'mailto:' . $animal['email_contato'];
    $contatoTexto = 'Enviar e-mail ao responsável';
}

cabecalho($animal ? 'Conheça ' . (string)$animal['nome_animal'] : 'Conheça o animal');
?>
<?php if ($erro): ?>
    <div class="notice error"><?= h($erro) ?></div>
<?php elseif (!$animal): ?>
    <div class="empty-state">
        <h2>Animal não encontrado</h2>
        <p>Esse cadastro pode ter sido removido ou o link está incorreto.</p>
        <a class="btn btn-primary" href="catalogo.php">Voltar ao catálogo</a>
    </div>
<?php else: ?>
    <nav class="detail-breadcrumb" aria-label="Navegação estrutural">
        <a href="index.php">Início</a><span aria-hidden="true">/</span><a href="catalogo.php">Animais</a><span aria-hidden="true">/</span><span><?= h($animal['nome_animal']) ?></span>
    </nav>
    <?php if (($animal['status_aprovacao'] ?? 'Aprovado') !== 'Aprovado'): ?><div class="notice">Visualização privada: este animal aguarda aprovação e ainda não está publicado.</div><?php endif; ?>
    <section class="animal-detail-hero">
        <div class="animal-detail-image">
            <img class="detail-photo" src="<?= h(animalFotoSrc($animal['foto_url'] ?? '')) ?>" alt="Foto de <?= h($animal['nome_animal']) ?>">
            <span class="detail-image-caption">Cada encontro pode virar uma nova história ♡</span>
        </div>
        <div class="detail-content">
            <div class="detail-labels">
                <span class="animal-status"><?= h($animal['status_adocao']) ?></span>
                <?php if (!empty($animal['nome_categoria'])): ?><span class="detail-category"><?= h($animal['nome_categoria']) ?></span><?php endif; ?>
            </div>
            <span class="eyebrow">UM NOVO AMIGO PARA A VIDA</span>
            <h2><?= h($animal['nome_animal']) ?></h2>
            <p class="detail-lead"><?= h($animal['descricao']) ?></p>
            <div class="detail-facts">
                <div><span>Espécie</span><strong><?= h($animal['especie']) ?></strong></div>
                <div><span>Idade</span><strong><?= h($idadeTexto) ?></strong></div>
                <div><span>Porte</span><strong><?= h($animal['porte'] ?: 'Não informado') ?></strong></div>
                <div><span>Sexo</span><strong><?= h($animal['sexo']) ?></strong></div>
                <div><span>Raça</span><strong><?= h($animal['raca'] ?: 'Sem raça definida') ?></strong></div>
                <div><span>Cor</span><strong><?= h($animal['cor'] ?: 'Não informada') ?></strong></div>
                <div><span>Local</span><strong><?= h($animal['cidade'] ?: 'A confirmar') ?><?= !empty($animal['uf']) ? ' - ' . h($animal['uf']) : '' ?></strong></div>
                <div><span>Energia</span><strong><?= h($animal['nivel_energia'] ?: 'Não informada') ?></strong></div>
            </div>
            <?php if (strcasecmp((string)$animal['status_adocao'], 'Disponível') === 0): ?>
                <?php if (usuarioId() > 0): ?>
                    <form class="detail-actions" method="post" action="lista_interesse.php">
                        <input type="hidden" name="csrf" value="<?= h(csrfToken()) ?>">
                        <button class="btn btn-primary" type="submit" name="adicionar" value="<?= (int)$animal['id_animal'] ?>">Tenho interesse em <?= h($animal['nome_animal']) ?> ♡</button>
                        <a class="btn btn-outline" href="lista_interesse.php">Minha lista de interesse</a>
                        <?php if ($contatoHref !== ''): ?>
                            <a class="btn btn-outline" href="<?= h($contatoHref) ?>" <?= str_starts_with($contatoHref, 'https://wa.me/') ? 'target="_blank" rel="noopener noreferrer"' : '' ?>><?= h($contatoTexto) ?></a>
                        <?php else: ?>
                            <span class="form-help">O responsável ainda não cadastrou um contato direto. Você pode demonstrar interesse e pedir que ele entre em contato.</span>
                        <?php endif; ?>
                    </form>
                <?php else: ?>
                    <div class="detail-actions">
                        <a class="btn btn-primary" href="login.php?retorno=<?= urlencode('animal.php?id=' . (int)$animal['id_animal']) ?>">Entrar para demonstrar interesse ♡</a>
                        <a class="btn btn-outline" href="cadastro.php">Criar conta gratuita</a>
                        <?php if ($contatoHref !== ''): ?>
                            <a class="btn btn-outline" href="<?= h($contatoHref) ?>" <?= str_starts_with($contatoHref, 'https://wa.me/') ? 'target="_blank" rel="noopener noreferrer"' : '' ?>><?= h($contatoTexto) ?></a>
                        <?php else: ?>
                            <span class="form-help">O responsável ainda não cadastrou um contato direto.</span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="notice">Este animal está <?= h(strtolower((string)$animal['status_adocao'])) ?> no momento. Explore outros companheiros disponíveis.</div>
                <a class="btn btn-outline" href="catalogo.php">Voltar aos animais</a>
            <?php endif; ?>
        </div>
    </section>

    <section class="detail-information-grid">
        <article class="detail-info-card">
            <span class="eyebrow">SAÚDE E BEM-ESTAR</span>
            <h2>Cuidados registrados</h2>
            <div class="detail-check-list">
                <p><span class="<?= !empty($animal['castrado']) ? 'care-yes' : 'care-unknown' ?>"><?= !empty($animal['castrado']) ? '✓' : '•' ?></span><strong>Castrado</strong><span><?= !empty($animal['castrado']) ? 'Sim' : 'Não informado' ?></span></p>
                <p><span class="<?= !empty($animal['vacinado']) ? 'care-yes' : 'care-unknown' ?>"><?= !empty($animal['vacinado']) ? '✓' : '•' ?></span><strong>Vacinado</strong><span><?= !empty($animal['vacinado']) ? 'Sim' : 'Não informado' ?></span></p>
                <p><span class="<?= !empty($animal['vermifugado']) ? 'care-yes' : 'care-unknown' ?>"><?= !empty($animal['vermifugado']) ? '✓' : '•' ?></span><strong>Vermifugado</strong><span><?= !empty($animal['vermifugado']) ? 'Sim' : 'Não informado' ?></span></p>
            </div>
            <?php if (!empty($animal['condicao_saude']) && strcasecmp(trim((string)$animal['condicao_saude']), 'Não informado') !== 0): ?><p class="detail-health-note"><strong>Observação de saúde:</strong> <?= h($animal['condicao_saude']) ?></p><?php endif; ?>
            <p class="detail-disclaimer">Confirme o histórico de saúde e os cuidados necessários diretamente com o responsável pela adoção e com um veterinário.</p>
        </article>
        <article class="detail-info-card">
            <span class="eyebrow">O LAR IDEAL</span>
            <h2>Convivência e personalidade</h2>
            <div class="detail-compatibility">
                <div><span>Convívio com crianças</span><strong><?= h($animal['compatibilidade_criancas'] ?: 'Não informado') ?></strong></div>
                <div><span>Convívio com outros animais</span><strong><?= h($animal['compatibilidade_animais'] ?: 'Não informado') ?></strong></div>
                <div><span>Nível de energia</span><strong><?= h($animal['nivel_energia'] ?: 'Não informado') ?></strong></div>
            </div>
            <p class="detail-disclaimer">Cada animal tem seu próprio jeito. Converse com o protetor para saber mais sobre a rotina e a adaptação.</p>
        </article>
        <article class="detail-info-card detail-next-step">
            <span class="eyebrow">PRÓXIMO PASSO</span>
            <h2>Vamos conversar?</h2>
            <p>Ao demonstrar interesse, você pode salvar <?= h($animal['nome_animal']) ?> na sua lista e conversar com a pessoa responsável pelo animal.</p>
            <?php if (!empty($animal['responsavel_nome'])): ?><p><strong><?= ($animal['tipo_perfil'] ?? 'ong') === 'independente' ? 'Protetor responsável:' : 'ONG responsável:' ?></strong> <?= h($animal['responsavel_nome']) ?></p><?php endif; ?>
            <?php if ($contatoHref !== ''): ?><p><strong>Contato:</strong> <a href="<?= h($contatoHref) ?>" <?= str_starts_with($contatoHref, 'https://wa.me/') ? 'target="_blank" rel="noopener noreferrer"' : '' ?>><?= h($contatoTexto) ?></a></p><?php else: ?><p>Contato direto ainda não cadastrado.</p><?php endif; ?>
            <a class="text-link" href="catalogo.php">Ver outros animais <span aria-hidden="true">→</span></a>
        </article>
    </section>

    <?php if ($semelhantes): ?>
        <section class="similar-animals">
            <div class="home-section-heading"><div><span class="eyebrow">MAIS COMPANHEIROS</span><h2>Outros animais esperando um lar</h2></div><a class="text-link" href="catalogo.php">Ver catálogo completo →</a></div>
            <div class="home-animal-grid">
                <?php foreach ($semelhantes as $outro): ?>
                    <article class="home-animal-card">
                        <a class="home-animal-photo" href="animal.php?id=<?= (int)$outro['id_animal'] ?>"><img src="<?= h(animalFotoSrc($outro['foto_url'] ?? '')) ?>" alt="Conheça <?= h($outro['nome_animal']) ?>" loading="lazy"><span>Disponível para adoção</span></a>
                        <div class="home-animal-info"><div><h3><?= h($outro['nome_animal']) ?></h3><span><?= h($outro['sexo']) ?></span></div><p><?= h($outro['especie']) ?> · <?= h($outro['porte'] ?: 'Porte não informado') ?></p><a class="text-link" href="animal.php?id=<?= (int)$outro['id_animal'] ?>">Conhecer <span aria-hidden="true">→</span></a></div>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>
<?php endif; ?>
<?php rodape(); ?>
