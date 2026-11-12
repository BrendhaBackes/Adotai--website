<?php
require_once __DIR__ . '/_bootstrap.php';
$contaHref = usuarioId() > 0 ? 'perfil.php' : 'login.php';
$contaTexto = usuarioId() > 0 ? 'Minha conta' : 'Entrar';
$animaisDestaque = [];
$resumo = ['animais' => 0, 'veterinarios' => 0];
$erroInicio = '';
try {
    $db = db();
    $resumo['animais'] = (int)$db->query("SELECT COUNT(*) FROM animal WHERE status_aprovacao = 'Aprovado' AND status_adocao = 'Disponível'")->fetchColumn();
    $resumo['veterinarios'] = (int)$db->query("SELECT COUNT(*) FROM veterinario WHERE status = 'Ativo'")->fetchColumn();
    $animaisDestaque = $db->query(
        "SELECT id_animal, nome_animal, especie, idade_meses, porte, cidade, uf, foto_url, sexo
         FROM animal
         WHERE status_aprovacao = 'Aprovado' AND status_adocao = 'Disponível'
         ORDER BY data_cadastro DESC, nome_animal ASC
         LIMIT 4"
    )->fetchAll();
} catch (Throwable $e) {
    $erroInicio = mensagemErro($e);
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Adotaí | Um amigo para a vida toda</title>
    <link rel="stylesheet" href="../styles/style.css">
</head>
<body>
    <header>
        <a class="brand" href="index.php">
            <img class="brand-icon" src="../images/logo_img.png" alt="">
            <img class="brand-text" src="../images/logo_text.png" alt="Adotaí">
        </a>

        <!-- Botão hambúrguer (só aparece no celular/tablet) -->
        <input type="checkbox" id="menu-toggle" aria-label="Abrir menu">
        <label for="menu-toggle" class="menu-btn" aria-hidden="true">
            <span></span><span></span><span></span>
        </label>

        <nav>
            <a href="index.php">Início</a>
            <a href="catalogo.php">Animais</a>
            <a href="planos.php">Planos</a>
            <a href="veterinarios.php">Veterinários</a>
            <a href="<?= h($contaHref) ?>"><?= h($contaTexto) ?></a>
            <?php if (usuarioId() === 0): ?><a href="cadastro.php">Criar conta</a><?php endif; ?>
            <a href="#footer-contato">Contato</a>
        </nav>

        <a id="adoption-header" href="catalogo.php">Quero Adotar ♡</a>
    </header>

    <main>
        <section id="hero">
            <img id="dog-hero" src="../images/cachorro_hero.png" alt="Cachorro sorrindo">
            <img id="banner-transparency" src="../images/banner_trasnparency.png" alt="">

            <div id="hero-card">
                <h1>
                    Um amigo para a vida toda está esperando
                    <span class="destaque">por você</span>
                </h1>
                <p>
                    Adotar é um ato de amor que transforma
                    vidas, a deles e a sua também.
                </p>
                <div class="hero-trust"><span aria-hidden="true">♡</span><span>Encontros responsáveis, histórias para a vida toda.</span></div>
                <div class="hero-buttons">
                    <a class="btn btn-primary" href="catalogo.php">Ver animais para a adoção</a>
                    <a class="btn btn-outline" href="#como-adotar">Como funciona</a>
                </div>
            </div>
        </section>

        <section class="home-impact" aria-label="Adotaí em números">
            <div><strong><?= number_format($resumo['animais'], 0, ',', '.') ?></strong><span>companheiros disponíveis</span></div>
            <div><strong><?= number_format($resumo['veterinarios'], 0, ',', '.') ?></strong><span>profissionais parceiros</span></div>
            <div><strong>Gratuito</strong><span>para criar sua conta de adotante</span></div>
        </section>

        <section class="home-featured">
            <div class="home-section-heading">
                <div><span class="eyebrow">NOVAS HISTÓRIAS COMEÇAM AQUI</span><h2>Quem está esperando por você</h2></div>
                <a class="text-link" href="catalogo.php">Ver todos os animais <span aria-hidden="true">→</span></a>
            </div>
            <?php if ($erroInicio): ?>
                <div class="database-message error"><?= h($erroInicio) ?></div>
            <?php elseif (!$animaisDestaque): ?>
                <div class="empty-state"><h2>Novos amigos em breve</h2><p>Estamos preparando novos encontros. Volte para conhecer os animais disponíveis.</p></div>
            <?php else: ?>
                <div class="home-animal-grid">
                    <?php foreach ($animaisDestaque as $animal): ?>
                        <?php
                        $idadeMeses = $animal['idade_meses'] === null ? null : (int)$animal['idade_meses'];
                        $idadeTexto = $idadeMeses === null
                            ? 'Idade não informada'
                            : ($idadeMeses < 12
                                ? $idadeMeses . ($idadeMeses === 1 ? ' mês' : ' meses')
                                : intdiv($idadeMeses, 12) . (intdiv($idadeMeses, 12) === 1 ? ' ano' : ' anos'));
                        ?>
                        <article class="home-animal-card">
                            <a class="home-animal-photo" href="animal.php?id=<?= (int)$animal['id_animal'] ?>">
                                <img src="<?= h(animalFotoSrc($animal['foto_url'] ?? '')) ?>" alt="Conheça <?= h($animal['nome_animal']) ?>" loading="lazy">
                                <span>Disponível para adoção</span>
                            </a>
                            <div class="home-animal-info">
                                <div><h3><?= h($animal['nome_animal']) ?></h3><span><?= h($animal['sexo']) ?></span></div>
                                <p><?= h($animal['especie']) ?> · <?= h($idadeTexto) ?></p>
                                <p><?= h($animal['cidade'] ?: 'Local a confirmar') ?><?= !empty($animal['uf']) ? ' - ' . h($animal['uf']) : '' ?></p>
                                <a class="text-link" href="animal.php?id=<?= (int)$animal['id_animal'] ?>">Conhecer história <span aria-hidden="true">→</span></a>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <section id="como-adotar">
            <span class="eyebrow">UM PASSO DE CADA VEZ</span>
            <h1>Como funciona a adoção</h1>
            <p class="home-section-description">Cada encontro é feito com carinho, transparência e respeito ao tempo da família e do animal.</p>
            <div id="como-adotar-cards">
                <div class="step_card">
                    <img src="../images/lupa_img.png" alt="">
                    <span>1. Escolha seu amigo</span>
                    <p>Veja os animais disponiveis e encontre o que combina com você.</p>
                </div>
                <div class="step_card">
                    <img src="../images/clipboard_img.png" alt="">
                    <span>2. Preencha o formulário</span>
                    <p>Conte um pouco sobre você e o lar que você pode oferecer.</p>
                </div>
                <div class="step_card">
                    <img src="../images/house_img.png" alt="">
                    <span>3. Entrevista e visita</span>
                    <p>Faremos uma conversa e, se necessário, uma visita à sua casa.</p>
                </div>
                <div class="step_card">
                    <img src="../images/heart_img.png" alt="">
                    <span>4. Adoção realizada!</span>
                    <p>Tudo certo? Então é hora de dar boas-vindas ao seu novo melhor amigo.</p>
                </div>
            </div>
        </section>

        <section class="home-care">
            <div class="home-care-copy">
                <span class="eyebrow">CUIDADO QUE CONTINUA</span>
                <h2>Saúde e bem-estar também fazem parte da adoção</h2>
                <p>Encontre profissionais veterinários parceiros e consulte opções de atendimento para acompanhar seu novo companheiro em cada fase da vida.</p>
                <div class="hero-buttons">
                    <a class="btn btn-primary" href="veterinarios.php">Conhecer veterinários</a>
                    <a class="btn btn-outline" href="planos.php#planos-veterinarios">Ver planos de cuidado</a>
                </div>
                <small>Valores, disponibilidade e condutas são confirmados diretamente com cada profissional.</small>
            </div>
            <div class="home-care-points">
                <div><span>01</span><p><strong>Prevenção</strong><br>Converse sobre vacinas e cuidados preventivos.</p></div>
                <div><span>02</span><p><strong>Acompanhamento</strong><br>Organize retornos e a rotina de saúde do seu pet.</p></div>
                <div><span>03</span><p><strong>Orientação profissional</strong><br>Escolha o atendimento adequado com apoio veterinário.</p></div>
            </div>
        </section>

        <section class="home-responsible">
            <div>
                <span class="eyebrow">UM COMPROMISSO PARA A VIDA</span>
                <h2>Adotar é acolher uma história inteira</h2>
                <p>Antes de escolher, pense no tempo, espaço, rotina e orçamento que você pode oferecer. O vínculo cresce com cuidado diário, paciência e acompanhamento veterinário.</p>
            </div>
            <a class="btn btn-primary" href="catalogo.php">Encontrar meu companheiro</a>
        </section>
    </main>

    <footer>
        <div id="footer-logo">
            <div class="footer-brand">
                <img src="../images/logo_img_footer2.png" alt="">
                <img src="../images/logo_text_footer.png" alt="Adotaí">
            </div>
            <p>
                Somos uma organização, sem fins lucrativos
                que resgata, cuida e encontra lares cheios
                de amor para animais abandonados.
            </p>
        </div>
        <div id="footer-contato">
            <h3>Contato</h3>
            <p>contato@adotai.org ✉</p>
            <p>(99) 9 9999-9999 ☏</p>
            <p>Curitiba - PR 🖈</p>
        </div>
    </footer>
</body>
</html>
