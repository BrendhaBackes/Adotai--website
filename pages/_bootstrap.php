<?php
declare(strict_types=1);
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
    session_start();
}
require_once __DIR__ . '/../config/database.php';
function db(): MysqliDatabase
{
    return getConnection();
}

function h($valor): string
{
    return htmlspecialchars((string)($valor ?? ''), ENT_QUOTES, 'UTF-8');
}

function usuarioId(): int
{
    return (int)($_SESSION['id_usuario'] ?? $_SESSION['usuario']['id_usuario'] ?? 0);
}

function tipoUsuario(): string
{
    return strtolower((string)($_SESSION['tipo_usuario'] ?? $_SESSION['usuario']['tipo_usuario'] ?? ''));
}

function exigirLogin(): void
{
    if (usuarioId() <= 0) {
        header('Location: login.php?retorno=' . urlencode($_SERVER['REQUEST_URI'] ?? 'index.php'));
        exit;
    }
}

function exigirTipo(array $tipos): void
{
    exigirLogin();
    if (!in_array(tipoUsuario(), $tipos, true)) {
        http_response_code(403);
        exit('Acesso não autorizado para este perfil.');
    }
}

function csrfToken(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function validarCsrf(): void
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !hash_equals(csrfToken(), (string)($_POST['csrf'] ?? ''))) {
        http_response_code(419);
        exit('Sessão expirada. Atualize a página e tente novamente.');
    }
}

function animalFotoSrc($caminho): string
{
    $relativo = trim((string)($caminho ?? ''));
    if ($relativo !== '' && preg_match('~^fotos/[a-zA-Z0-9_./-]+$~', $relativo) && !str_contains($relativo, '..')) {
        $arquivo = dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativo);
        if (is_file($arquivo) && @getimagesize($arquivo) !== false) {
            return '../' . $relativo;
        }
    }
    return '../images/animal-placeholder.svg';
}

function perfilFotoSrc($caminho): string
{
    $relativo = trim((string)($caminho ?? ''));
    if ($relativo !== '' && preg_match('~^fotos/(?:protetores|veterinarios)/[a-zA-Z0-9_-]+\.(?:jpg|png|webp)$~i', $relativo)) {
        $arquivo = dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativo);
        if (is_file($arquivo) && @getimagesize($arquivo) !== false) {
            return '../' . $relativo;
        }
    }
    return '';
}

function linkWhatsApp($telefone): string
{
    $digitos = preg_replace('/\D+/', '', (string)($telefone ?? '')) ?? '';
    if ($digitos !== '' && strlen($digitos) <= 11) {
        $digitos = '55' . $digitos;
    }
    return $digitos !== '' ? 'https://wa.me/' . $digitos : '';
}

function salvarFotoPerfil(array $arquivo, string $tipo): string
{
    if (!in_array($tipo, ['protetores', 'veterinarios'], true)) {
        throw new InvalidArgumentException('Tipo de perfil inválido.');
    }
    if ($arquivo['error'] !== UPLOAD_ERR_OK || $arquivo['size'] > 5 * 1024 * 1024) {
        throw new InvalidArgumentException('A foto deve ter até 5 MB e ser enviada corretamente.');
    }
    $info = new finfo(FILEINFO_MIME_TYPE);
    $mime = $info->file($arquivo['tmp_name']);
    $extensoes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!isset($extensoes[$mime]) || !getimagesize($arquivo['tmp_name'])) {
        throw new InvalidArgumentException('Envie uma imagem JPG, PNG ou WEBP válida.');
    }

    $diretorio = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'fotos' . DIRECTORY_SEPARATOR . $tipo;
    if (!is_dir($diretorio) && !mkdir($diretorio, 0755, true) && !is_dir($diretorio)) {
        throw new InvalidArgumentException('Não foi possível preparar a pasta de fotos.');
    }
    $nomeArquivo = bin2hex(random_bytes(16)) . '.' . $extensoes[$mime];
    if (!move_uploaded_file($arquivo['tmp_name'], $diretorio . DIRECTORY_SEPARATOR . $nomeArquivo)) {
        throw new InvalidArgumentException('Não foi possível salvar a foto enviada.');
    }
    return 'fotos/' . $tipo . '/' . $nomeArquivo;
}

function salvarFotoAnimal(array $arquivo): string
{
    $mensagensErro = [
        UPLOAD_ERR_INI_SIZE => 'A foto ultrapassa o limite de upload do servidor. Reduza o tamanho da imagem e tente novamente.',
        UPLOAD_ERR_FORM_SIZE => 'A foto ultrapassa o limite permitido de 5 MB.',
        UPLOAD_ERR_PARTIAL => 'A foto foi enviada parcialmente. Tente novamente.',
        UPLOAD_ERR_NO_TMP_DIR => 'O servidor não encontrou a pasta temporária para receber a foto.',
        UPLOAD_ERR_CANT_WRITE => 'O servidor não conseguiu gravar a foto enviada.',
        UPLOAD_ERR_EXTENSION => 'O servidor bloqueou o envio da foto.',
    ];
    $erro = (int)($arquivo['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($erro !== UPLOAD_ERR_OK) {
        throw new InvalidArgumentException($mensagensErro[$erro] ?? 'Não foi possível receber a foto. Tente novamente.');
    }
    if ((int)($arquivo['size'] ?? 0) > 5 * 1024 * 1024) {
        throw new InvalidArgumentException('A foto deve ter até 5 MB.');
    }

    $temporario = (string)($arquivo['tmp_name'] ?? '');
    if ($temporario === '' || !is_uploaded_file($temporario)) {
        throw new InvalidArgumentException('O arquivo enviado não é uma foto válida.');
    }

    $info = new finfo(FILEINFO_MIME_TYPE);
    $mime = $info->file($temporario);
    $extensoes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!isset($extensoes[$mime]) || @getimagesize($temporario) === false) {
        throw new InvalidArgumentException('Envie uma imagem JPG, PNG ou WEBP válida.');
    }

    $diretorio = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'fotos' . DIRECTORY_SEPARATOR . 'animais';
    if (!is_dir($diretorio) && !mkdir($diretorio, 0755, true) && !is_dir($diretorio)) {
        throw new InvalidArgumentException('Não foi possível preparar a pasta de fotos.');
    }

    $nomeArquivo = bin2hex(random_bytes(16)) . '.' . $extensoes[$mime];
    if (!move_uploaded_file($temporario, $diretorio . DIRECTORY_SEPARATOR . $nomeArquivo)) {
        throw new InvalidArgumentException('Não foi possível salvar a foto enviada.');
    }

    return 'fotos/animais/' . $nomeArquivo;
}

function cabecalho(string $titulo): void
{
    $contaHref = usuarioId() > 0 ? 'perfil.php' : 'login.php';
    $contaTexto = usuarioId() > 0 ? 'Minha conta' : 'Entrar';
    ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($titulo) ?> | Adotaí</title>
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
        <a href="catalogo.php">Animais</a>
        <a href="planos.php">Planos</a>
        <a href="veterinarios.php">Veterinários</a>
        <a href="<?= h($contaHref) ?>"><?= h($contaTexto) ?></a>
        <a href="#footer-contato">Contato</a>
    </nav>
    <a id="adoption-header" href="catalogo.php">Quero Adotar ♡</a>
</header>
<main class="pagina">
    <div class="pagina-titulo"><span class="eyebrow">CUIDADO, RESPEITO E AMOR</span><h1><?= h($titulo) ?></h1></div>
<?php
}

function rodape(): void
{
    ?>
</main>
<footer>
    <div id="footer-logo"><div class="footer-brand"><img src="../images/logo_img_footer2.png" alt=""><img src="../images/logo_text_footer.png" alt="Adotaí"></div><p>Somos uma organização sem fins lucrativos que resgata, cuida e encontra lares cheios de amor para animais abandonados.</p></div>
    <div id="footer-contato"><h3>Contato</h3><p>contato@adotai.org ✉</p><p>(99) 9 9999-9999 ☏</p><p>Curitiba - PR</p></div>
</footer>
</body>
</html>
<?php
}

function mensagemErro(Throwable $e): string
{
    error_log('Adotai: ' . $e->getMessage());
    return 'Não foi possível concluir a operação. Confira a conexão e se a migração do banco de dados foi executada.';
}
