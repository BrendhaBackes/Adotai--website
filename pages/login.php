<?php
declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';

if (usuarioId() > 0) {
    header('Location: index.php');
    exit;
}

$erro = '';
$email = '';
$retorno = (string)($_GET['retorno'] ?? $_POST['retorno'] ?? '');
validarCsrf();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim((string)($_POST['email'] ?? ''));
    $senha = (string)($_POST['senha'] ?? '');

    try {
        $stmt = db()->prepare('SELECT id, nome, email, senha, status_conta FROM usuario WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => $email]);
        $usuario = $stmt->fetch();

        $senhaValida = $usuario && password_verify($senha, (string)$usuario['senha']);
        if ($usuario && !$senhaValida && hash_equals((string)$usuario['senha'], $senha)) {
            $senhaValida = true;
            $stmt = db()->prepare('UPDATE usuario SET senha = :senha WHERE id = :id');
            $stmt->execute(['senha' => password_hash($senha, PASSWORD_DEFAULT), 'id' => $usuario['id']]);
        }

        if (!$senhaValida || strcasecmp((string)$usuario['status_conta'], 'Ativa') !== 0) {
            $erro = 'E-mail ou senha inválidos, ou conta inativa.';
        } else {
            $db = db();
            $stmt = $db->prepare('SELECT status_aprovacao FROM protetor WHERE id_usuario = :id LIMIT 1');
            $stmt->execute(['id' => $usuario['id']]);
            $statusProtetor = $stmt->fetchColumn();

            $stmt = $db->prepare('SELECT 1 FROM admin WHERE id_usuario = ? LIMIT 1');
            $stmt->execute([$usuario['id']]);
            if ($stmt->fetchColumn()) {
                $tipo = 'admin';
            } else {
                $stmt = $db->prepare('SELECT status FROM veterinario WHERE id_usuario = :id LIMIT 1');
                $stmt->execute(['id' => $usuario['id']]);
                $statusVeterinario = $stmt->fetchColumn();
                if ($statusVeterinario !== false) {
                    $tipo = strcasecmp((string)$statusVeterinario, 'Ativo') === 0 ? 'veterinario' : 'veterinario_pendente';
                } else {
                    $tipo = $statusProtetor === false ? 'adotante' : (strcasecmp((string)$statusProtetor, 'Aprovado') === 0 ? 'protetor' : 'protetor_pendente');
                }
            }

            session_regenerate_id(true);
            $_SESSION['id_usuario'] = (int)$usuario['id'];
            $_SESSION['tipo_usuario'] = $tipo;
            $_SESSION['usuario'] = [
                'id_usuario' => (int)$usuario['id'],
                'tipo_usuario' => $tipo,
                'nome' => (string)$usuario['nome'],
            ];
            $destino = $retorno !== '' ? $retorno
                : (in_array($tipo, ['veterinario', 'veterinario_pendente'], true) ? 'painel_veterinario.php' : 'index.php');
            $partes = parse_url($destino);
            if (
                $destino === ''
                || str_starts_with($destino, '//')
                || str_contains($destino, '\\')
                || isset($partes['scheme'])
                || isset($partes['host'])
                || preg_match('/[\r\n]/', $destino)
                || str_contains((string)($partes['path'] ?? ''), '..')
            ) {
                $destino = 'index.php';
            }
            header('Location: ' . $destino);
            exit;
        }
    } catch (Throwable $e) {
        $erro = mensagemErro($e);
    }
}

cabecalho('Entrar');
?>
<section class="auth-intro">
    <span class="auth-icon" aria-hidden="true">♡</span>
    <div><h2>Que bom ter você de volta</h2>    <p>Acesse sua conta para guardar favoritos, acompanhar consultas ou gerenciar seu perfil de protetor e veterinário.</p></div>
    <a class="text-link" href="catalogo.php">Explorar animais sem entrar →</a>
</section>
<?php if ($erro): ?><div class="notice error"><?= h($erro) ?></div><?php endif; ?>
<form class="form-card" method="post" action="login.php">
    <input type="hidden" name="csrf" value="<?= h(csrfToken()) ?>">
    <input type="hidden" name="retorno" value="<?= h($retorno) ?>">
    <div class="form-grid">
        <label class="full">E-mail
            <input type="email" name="email" autocomplete="email" required maxlength="100" value="<?= h($email) ?>">
        </label>
        <label class="full">Senha
            <input type="password" name="senha" autocomplete="current-password" required>
        </label>
    </div>
    <button class="btn btn-primary" type="submit">Entrar</button>
    <p class="form-help">Ainda não tem uma conta? <a href="cadastro.php">Cadastre-se gratuitamente</a>.</p>
</form>
<div class="auth-assurance"><span aria-hidden="true">🔒</span><p><strong>Sua conta, do seu jeito.</strong><br>Usamos seus dados para oferecer os recursos da plataforma. Sua senha é protegida e nunca exibida.</p></div>
<?php rodape(); ?>
