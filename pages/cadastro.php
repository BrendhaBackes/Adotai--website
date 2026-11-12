<?php
declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';

if (usuarioId() > 0) {
    header('Location: perfil.php');
    exit;
}

$erro = '';
$dados = ['nome' => '', 'email' => '', 'telefone' => ''];
validarCsrf();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($dados as $campo => $_) {
        $dados[$campo] = trim((string)($_POST[$campo] ?? ''));
    }
    $senha = (string)($_POST['senha'] ?? '');
    $confirmacao = (string)($_POST['confirmacao'] ?? '');
    $telefone = preg_replace('/\D+/', '', $dados['telefone']) ?? '';

    if ($dados['nome'] === '' || strlen($dados['nome']) > 120) {
        $erro = 'Informe seu nome (até 120 caracteres).';
    } elseif (!filter_var($dados['email'], FILTER_VALIDATE_EMAIL) || strlen($dados['email']) > 100) {
        $erro = 'Informe um e-mail válido.';
    } elseif (strlen($telefone) < 10 || strlen($telefone) > 14) {
        $erro = 'Informe um telefone com DDD.';
    } elseif (strlen($senha) < 8) {
        $erro = 'A senha deve ter pelo menos 8 caracteres.';
    } elseif (!hash_equals($senha, $confirmacao)) {
        $erro = 'As senhas não conferem.';
    } else {
        try {
            $db = db();
            $db->beginTransaction();
            $stmt = $db->prepare(
                "INSERT INTO usuario (nome, status_conta, telefone, email, senha, data_criacao_conta)
                 VALUES (:nome, 'Ativa', :telefone, :email, :senha, CURRENT_DATE)"
            );
            $stmt->execute([
                'nome' => $dados['nome'],
                'telefone' => $telefone,
                'email' => $dados['email'],
                'senha' => password_hash($senha, PASSWORD_DEFAULT),
            ]);
            $id = (int)$db->lastInsertId();
            $stmt = $db->prepare('INSERT INTO adotante (id_usuario) VALUES (:id)');
            $stmt->execute(['id' => $id]);
            $db->commit();

            session_regenerate_id(true);
            $_SESSION['id_usuario'] = $id;
            $_SESSION['tipo_usuario'] = 'adotante';
            $_SESSION['usuario'] = ['id_usuario' => $id, 'tipo_usuario' => 'adotante', 'nome' => $dados['nome']];
            header('Location: index.php');
            exit;
        } catch (Throwable $e) {
            if (isset($db) && $db->inTransaction()) {
                $db->rollBack();
            }
            $erro = $e instanceof mysqli_sql_exception && $e->getSqlState() === '23000'
                ? 'Este e-mail já está cadastrado. Entre na sua conta ou use outro e-mail.'
                : mensagemErro($e);
        }
    }
}

cabecalho('Criar conta');
?>
<section class="auth-intro">
    <span class="auth-icon" aria-hidden="true">✦</span>
    <div><h2>Seu próximo encontro começa aqui</h2><p>Crie sua conta gratuita para salvar favoritos, conversar com a equipe de adoção e organizar seus cuidados.</p></div>
    <span class="auth-free-label">Gratuito · sem mensalidade</span>
</section>
<?php if ($erro): ?><div class="notice error"><?= h($erro) ?></div><?php endif; ?>
<form class="form-card" method="post" action="cadastro.php">
    <input type="hidden" name="csrf" value="<?= h(csrfToken()) ?>">
    <div class="form-grid">
        <label>Nome completo
            <input name="nome" autocomplete="name" required maxlength="120" value="<?= h($dados['nome']) ?>">
        </label>
        <label>E-mail
            <input type="email" name="email" autocomplete="email" required maxlength="100" value="<?= h($dados['email']) ?>">
        </label>
        <label>Telefone com DDD
            <input type="tel" name="telefone" autocomplete="tel" required maxlength="20" value="<?= h($dados['telefone']) ?>">
        </label>
        <label>Senha
            <input type="password" name="senha" autocomplete="new-password" minlength="8" required aria-describedby="senha-ajuda">
            <small id="senha-ajuda" class="field-help">Use pelo menos 8 caracteres.</small>
        </label>
        <label class="full">Confirme sua senha
            <input type="password" name="confirmacao" autocomplete="new-password" minlength="8" required>
        </label>
    </div>
    <p class="form-help">Ao criar sua conta, você poderá salvar animais na sua lista de interesse.</p>
    <div class="signup-benefits"><span>♡ Lista de favoritos</span><span>✚ Pedidos de consulta</span><span>⌂ Acesso ao seu perfil</span></div>
    <button class="btn btn-primary" type="submit">Criar conta</button>
    <p class="form-help">Já tem uma conta? <a href="login.php">Entre aqui</a>.</p>
</form>
<div class="auth-assurance"><span aria-hidden="true">✓</span><p><strong>Simples e transparente.</strong><br>Você escolhe quando demonstrar interesse e pode atualizar seus dados no perfil.</p></div>
<?php rodape(); ?>
