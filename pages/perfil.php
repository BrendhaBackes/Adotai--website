<?php
declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';

exigirLogin();
if (in_array(tipoUsuario(), ['veterinario', 'veterinario_pendente'], true)) {
    header('Location: painel_veterinario.php');
    exit;
}
validarCsrf();

$aviso = '';
$erro = '';
$usuario = [];
$statusProtetor = null;

try {
    $db = db();
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $nome = trim((string)($_POST['nome'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $telefone = preg_replace('/\D+/', '', (string)($_POST['telefone'] ?? '')) ?? '';
        if ($nome === '' || strlen($nome) > 120 || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 100) {
            $aviso = 'Informe um nome e um e-mail válidos.';
        } elseif (strlen($telefone) < 10 || strlen($telefone) > 14) {
            $aviso = 'Informe um telefone com DDD.';
        } else {
            $stmt = $db->prepare('UPDATE usuario SET nome = :nome, email = :email, telefone = :telefone WHERE id = :id');
            $stmt->execute(['nome' => $nome, 'email' => $email, 'telefone' => $telefone, 'id' => usuarioId()]);
            $_SESSION['usuario']['nome'] = $nome;
            $aviso = 'Perfil atualizado.';
        }
    }

    $stmt = $db->prepare('SELECT nome, email, telefone FROM usuario WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => usuarioId()]);
    $usuario = $stmt->fetch() ?: [];
    $stmt = $db->prepare('SELECT status_aprovacao FROM protetor WHERE id_usuario = :id LIMIT 1');
    $stmt->execute(['id' => usuarioId()]);
    $statusProtetor = $stmt->fetchColumn();
} catch (Throwable $e) {
    $erro = mensagemErro($e);
}

cabecalho('Meu perfil');
?>
<?php if ($erro): ?><div class="notice error"><?= h($erro) ?></div><?php endif; ?>
<?php if ($aviso): ?><div class="notice success"><?= h($aviso) ?></div><?php endif; ?>
<form class="form-card" method="post">
    <input type="hidden" name="csrf" value="<?= h(csrfToken()) ?>">
    <div class="form-grid">
        <label>Nome completo
            <input name="nome" required maxlength="120" value="<?= h($usuario['nome'] ?? '') ?>">
        </label>
        <label>E-mail
            <input type="email" name="email" required maxlength="100" value="<?= h($usuario['email'] ?? '') ?>">
        </label>
        <label>Telefone
            <input type="tel" name="telefone" required maxlength="20" value="<?= h($usuario['telefone'] ?? '') ?>">
        </label>
        <label>Perfil
            <input value="<?= h(tipoUsuario() ?: 'adotante') ?>" disabled>
        </label>
    </div>
    <button class="btn btn-primary" type="submit">Salvar alterações</button>
</form>
<div class="vet-top-action">
    <a class="btn btn-outline" href="lista_interesse.php">Minha lista de interesse</a>
    <a class="btn btn-outline" href="minhas_consultas.php">Minhas consultas</a>
    <?php if (tipoUsuario() === 'protetor'): ?>
        <a class="btn btn-outline" href="perfil_protetor.php">Perfil e contato público</a>
        <a class="btn btn-primary" href="painel_protetor.php">Painel de animais</a>
    <?php elseif (in_array(tipoUsuario(), ['veterinario', 'veterinario_pendente'], true)): ?>
        <a class="btn btn-outline" href="perfil_veterinario.php">Perfil profissional</a>
        <a class="btn btn-primary" href="painel_veterinario.php">Painel veterinário</a>
    <?php elseif (in_array(tipoUsuario(), ['admin', 'administrador'], true)): ?>
        <a class="btn btn-primary" href="painel_admin.php">Painel administrativo</a>
    <?php elseif ($statusProtetor === false): ?>
        <a class="btn btn-primary" href="cadastro_protetor.php">Quero divulgar animais</a>
    <?php else: ?>
        <span class="form-help">Cadastro de protetor: <?= h($statusProtetor) ?>. A aprovação é necessária para publicar animais.</span>
        <?php if (tipoUsuario() === 'protetor_pendente'): ?><a class="btn btn-outline" href="perfil_protetor.php">Editar contato e perfil da ONG</a><?php endif; ?>
    <?php endif; ?>
    <a class="btn btn-outline" href="logout.php">Sair da conta</a>
</div>
<?php rodape(); ?>
