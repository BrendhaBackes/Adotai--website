<?php
declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';

$busca = trim((string)($_GET['busca'] ?? ''));
$filtroEspecie = trim((string)($_GET['especie'] ?? ''));
$filtroIdade = (string)($_GET['idade'] ?? '');
$filtroCategoria = (int)($_GET['categoria'] ?? 0);
$animais = $categorias = $especies = [];
$erroBanco = '';

try {
    $db = getConnection();
    $categorias = $db->query("SELECT id_categoria, nome_categoria FROM categoria ORDER BY nome_categoria")->fetchAll(MYSQLI_ASSOC);
    $especies = $db->query("SELECT DISTINCT especie FROM animal WHERE status_aprovacao = 'Aprovado' AND status_adocao = 'Disponível' AND especie IS NOT NULL AND especie <> '' ORDER BY especie")->fetchAll(DB_FETCH_COLUMN);

    $sql = "SELECT animal.*, categoria.nome_categoria
            FROM animal
            INNER JOIN categoria ON categoria.id_categoria = animal.id_categoria
            WHERE animal.status_aprovacao = 'Aprovado' AND animal.status_adocao = 'Disponível'";
    $params = [];
    if ($busca !== '') {
        $sql .= " AND animal.nome_animal LIKE :busca";
        $params['busca'] = '%' . $busca . '%';
    }
    if ($filtroEspecie !== '') {
        $sql .= " AND animal.especie = :especie";
        $params['especie'] = $filtroEspecie;
    }
    if ($filtroCategoria > 0) {
        $sql .= " AND animal.id_categoria = :categoria";
        $params['categoria'] = $filtroCategoria;
    }
    if ($filtroIdade === 'filhote') {
        $sql .= " AND animal.idade_meses IS NOT NULL AND animal.idade_meses < 12";
    } elseif ($filtroIdade === 'adulto') {
        $sql .= " AND animal.idade_meses >= 12 AND animal.idade_meses <= 96";
    } elseif ($filtroIdade === 'idoso') {
        $sql .= " AND animal.idade_meses > 96";
    }
    $sql .= " ORDER BY animal.data_cadastro DESC, animal.nome_animal ASC";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $animais = $stmt->fetchAll(MYSQLI_ASSOC);
} catch (Throwable $e) {
    error_log('Adotai: falha ao consultar catálogo: ' . $e->getMessage());
    $erroBanco = 'Não foi possível carregar o catálogo. Confira a conexão e se as tabelas animal e categoria estão disponíveis.';
}
function e($valor): string { return htmlspecialchars((string)($valor ?? ''), ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Animais disponíveis | Adotaí</title>
    <link rel="stylesheet" href="../styles/style.css">
    <link rel="stylesheet" href="../styles/paginas.css">
</head>
<body>
<header>
    <a class="brand" href="index.php"><img class="brand-icon" src="../images/logo_img.png" alt=""><img class="brand-text" src="../images/logo_text.png" alt="Adotaí"></a>
    <input type="checkbox" id="menu-toggle" aria-label="Abrir menu">
    <label for="menu-toggle" class="menu-btn" aria-hidden="true"><span></span><span></span><span></span></label>
    <nav>
        <a href="index.php">Início</a>
        <a href="catalogo.php" aria-current="page">Animais</a>
        <a href="planos.php">Planos</a>
        <a href="veterinarios.php">Veterinários</a>
        <a href="<?= usuarioId() > 0 ? 'perfil.php' : 'login.php' ?>"><?= usuarioId() > 0 ? 'Minha conta' : 'Entrar' ?></a>
        <?php if (usuarioId() === 0): ?><a href="cadastro.php">Criar conta</a><?php endif; ?>
        <a href="#footer-contato">Contato</a>
    </nav>
    <a id="adoption-header" href="catalogo.php">Quero Adotar ♡</a>
</header>
<main class="pagina" id="animais-disponiveis">
    <div class="pagina-titulo"><span class="eyebrow">ENCONTRE SEU NOVO COMPANHEIRO</span><h1>ANIMAIS DISPONÍVEIS</h1></div>
    <p class="catalog-intro">Conheça os animais cadastrados e encontre um novo companheiro para sua família.</p>
    <form class="animal-filters" method="get" action="catalogo.php">
        <div class="filter-field"><label for="busca-animal">Buscar por nome</label><input id="busca-animal" type="search" name="busca" placeholder="Ex.: Thor, Luna..." value="<?= e($busca) ?>"></div>
        <div class="filter-field"><label for="filtro-categoria">Categoria</label><select id="filtro-categoria" name="categoria"><option value="">Todas</option><?php foreach ($categorias as $categoria): ?><option value="<?= (int)$categoria['id_categoria'] ?>" <?= $filtroCategoria === (int)$categoria['id_categoria'] ? 'selected' : '' ?>><?= e($categoria['nome_categoria']) ?></option><?php endforeach; ?></select></div>
        <div class="filter-field"><label for="filtro-especie">Espécie</label><select id="filtro-especie" name="especie"><option value="">Todas</option><?php foreach ($especies as $especie): ?><option value="<?= e($especie) ?>" <?= $filtroEspecie === $especie ? 'selected' : '' ?>><?= e($especie) ?></option><?php endforeach; ?></select></div>
        <div class="filter-field"><label for="filtro-idade">Faixa etária</label><select id="filtro-idade" name="idade"><option value="">Todas as idades</option><option value="filhote" <?= $filtroIdade === 'filhote' ? 'selected' : '' ?>>Filhote (menos de 1 ano)</option><option value="adulto" <?= $filtroIdade === 'adulto' ? 'selected' : '' ?>>Adulto (1 a 8 anos)</option><option value="idoso" <?= $filtroIdade === 'idoso' ? 'selected' : '' ?>>Idoso (acima de 8 anos)</option></select></div>
        <div class="filter-actions"><button class="btn btn-primary" type="submit">Filtrar animais</button><a class="btn btn-outline" href="catalogo.php">Limpar</a></div>
    </form>
    <?php if ($erroBanco): ?>
        <div class="database-message error"><strong>Não foi possível carregar os animais.</strong><br><?= e($erroBanco) ?></div>
    <?php elseif (!$animais): ?>
        <div class="database-message">Nenhum animal encontrado com esses filtros. Tente alterar a busca.</div>
    <?php else: ?>
        <p class="results-count"><?= count($animais) ?> animal(is) encontrado(s)</p>
        <div id="animal-card-list">
        <?php foreach ($animais as $animal):
            $fotoSrc = animalFotoSrc($animal['foto_url'] ?? '');
            $idadeMeses = $animal['idade_meses'] === null ? null : (int)$animal['idade_meses'];
            if ($idadeMeses === null) $idadeTexto = 'Idade não informada';
            elseif ($idadeMeses < 12) $idadeTexto = $idadeMeses . ($idadeMeses === 1 ? ' mês' : ' meses');
            else { $anos = intdiv($idadeMeses, 12); $meses = $idadeMeses % 12; $idadeTexto = $anos . ($anos === 1 ? ' ano' : ' anos') . ($meses ? ' e ' . $meses . ($meses === 1 ? ' mês' : ' meses') : ''); }
        ?>
            <article class="animal-card">
                <img src="<?= e($fotoSrc) ?>" alt="Foto de <?= e($animal['nome_animal']) ?>" loading="lazy">
                <div class="animal-title"><h2><?= e($animal['nome_animal']) ?></h2><span class="animal-sex"><?= e($animal['sexo']) ?></span></div>
                <p class="animal-category"><?= e($animal['nome_categoria']) ?></p>
                <p><?= e($animal['especie']) ?> · <?= e($idadeTexto) ?> · Porte <?= e($animal['porte'] ?: 'não informado') ?></p>
                <p><?= e($animal['descricao']) ?></p>
                <p class="animal-location"><?= e($animal['cidade'] ?: 'Local não informado') ?><?= !empty($animal['uf']) ? ' - ' . e($animal['uf']) : '' ?></p>
                <span class="animal-status"><?= e($animal['status_adocao']) ?></span>
                <a class="btn btn-primary animal-details" href="animal.php?id=<?= (int)$animal['id_animal'] ?>">Ver detalhes</a>
            </article>
        <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>
<footer>
    <div id="footer-logo"><div class="footer-brand"><img src="../images/logo_img_footer2.png" alt=""><img src="../images/logo_text_footer.png" alt="Adotaí"></div><p>Somos uma organização sem fins lucrativos que resgata, cuida e encontra lares cheios de amor para animais abandonados.</p></div>
    <div id="footer-contato"><h3>Contato</h3><p>contato@adotai.org ✉</p><p>(99) 9 9999-9999 ☏</p><p>Curitiba - PR</p></div>
</footer>
</body>
</html>
