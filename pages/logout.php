<?php
declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validarCsrf();
    $_SESSION = [];
    session_regenerate_id(true);
    header('Location: login.php');
    exit;
}

exigirLogin();
cabecalho('Sair da conta');
?>
<div class="empty-state">
    <h2>Deseja sair da sua conta?</h2>
    <form method="post" action="logout.php">
        <input type="hidden" name="csrf" value="<?= h(csrfToken()) ?>">
        <button class="btn btn-primary" type="submit">Sair</button>
        <a class="btn btn-outline" href="perfil.php">Continuar conectado</a>
    </form>
</div>
<?php rodape(); ?>
