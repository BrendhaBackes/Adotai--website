<?php
declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';

if (in_array(tipoUsuario(), ['veterinario', 'veterinario_pendente'], true)) {
    header('Location: perfil_veterinario.php');
    exit;
}

$dados = [
    'nome' => '', 'crmv' => '', 'especialidade' => '', 'email' => '', 'telefone' => '',
    'cidade' => '', 'uf' => '', 'endereco' => '', 'descricao' => '', 'servicos' => '',
    'planos' => '', 'valor_consulta' => '', 'canal_contato' => 'whatsapp',
];
$erros = [];
validarCsrf();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($dados as $campo => $_) {
        $dados[$campo] = trim((string)($_POST[$campo] ?? ''));
    }
    $dados['uf'] = strtoupper($dados['uf']);
    $telefone = preg_replace('/\D+/', '', $dados['telefone']) ?? '';
    $senha = (string)($_POST['senha'] ?? '');
    $confirmacao = (string)($_POST['confirmacao'] ?? '');

    if ($dados['nome'] === '' || strlen($dados['nome']) > 100) {
        $erros[] = 'Informe seu nome (até 100 caracteres).';
    }
    if ($dados['crmv'] === '' || strlen($dados['crmv']) > 30) {
        $erros[] = 'Informe seu registro no CRMV (até 30 caracteres).';
    }
    if ($dados['especialidade'] === '' || strlen($dados['especialidade']) > 100) {
        $erros[] = 'Informe sua especialidade ou área de atuação (até 100 caracteres).';
    }
    if (!filter_var($dados['email'], FILTER_VALIDATE_EMAIL) || strlen($dados['email']) > 100) {
        $erros[] = 'Informe um e-mail válido com até 100 caracteres.';
    }
    if (strlen($telefone) < 10 || strlen($telefone) > 14) {
        $erros[] = 'Informe um telefone com DDD.';
    }
    if ($dados['cidade'] === '' || strlen($dados['cidade']) > 100 || !preg_match('/^[A-Z]{2}$/', $dados['uf'])) {
        $erros[] = 'Informe uma cidade e uma UF válida.';
    }
    if (!in_array($dados['canal_contato'], ['whatsapp', 'email'], true)) {
        $erros[] = 'Selecione WhatsApp ou e-mail como canal de contato.';
    }
    if (strlen($dados['endereco']) > 255 || strlen($dados['descricao']) > 3000 || strlen($dados['servicos']) > 3000 || strlen($dados['planos']) > 3000) {
        $erros[] = 'Endereço, apresentação, serviços e planos excedem o limite permitido.';
    }
    $valorTexto = str_replace(',', '.', $dados['valor_consulta']);
    if ($valorTexto !== '' && (!is_numeric($valorTexto) || (float)$valorTexto < 0 || (float)$valorTexto > 99999999)) {
        $erros[] = 'Informe um valor válido para a consulta ou deixe o campo vazio.';
    }
    if (strlen($senha) < 8) {
        $erros[] = 'A senha deve ter pelo menos 8 caracteres.';
    }
    if (!hash_equals($senha, $confirmacao)) {
        $erros[] = 'As senhas não conferem.';
    }

    if (!$erros) {
        $db = null;
        try {
            $db = db();
            $stmt = $db->prepare(
                'SELECT id_veterinario FROM veterinario
                 WHERE LOWER(email) = LOWER(:email) OR crmv = :crmv LIMIT 1'
            );
            $stmt->execute(['email' => $dados['email'], 'crmv' => $dados['crmv']]);
            if ($stmt->fetchColumn() !== false) {
                throw new InvalidArgumentException(
                    'Este perfil profissional já está cadastrado. Crie uma conta normal usando o e-mail do perfil e solicite o vínculo à equipe administrativa.'
                );
            }
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
            $idUsuario = (int)$db->lastInsertId();
            $stmt = $db->prepare(
                "INSERT INTO veterinario
                    (id_usuario, nome, crmv, especialidade, email, telefone, cidade, uf, endereco, descricao, servicos, planos, valor_consulta, canal_contato, status, data_cadastro)
                 VALUES
                    (:usuario, :nome, :crmv, :especialidade, :email, :telefone, :cidade, :uf, :endereco, :descricao, :servicos, :planos, :valor, :canal, 'Pendente', NOW())"
            );
            $stmt->execute([
                'usuario' => $idUsuario,
                'nome' => $dados['nome'],
                'crmv' => $dados['crmv'],
                'especialidade' => $dados['especialidade'],
                'email' => $dados['email'],
                'telefone' => $telefone,
                'cidade' => $dados['cidade'],
                'uf' => $dados['uf'],
                'endereco' => $dados['endereco'] ?: null,
                'descricao' => $dados['descricao'] ?: null,
                'servicos' => $dados['servicos'] ?: null,
                'planos' => $dados['planos'] ?: null,
                'valor' => $valorTexto !== '' ? $valorTexto : null,
                'canal' => $dados['canal_contato'],
            ]);
            $db->commit();

            session_regenerate_id(true);
            $_SESSION['id_usuario'] = $idUsuario;
            $_SESSION['tipo_usuario'] = 'veterinario_pendente';
            $_SESSION['usuario'] = [
                'id_usuario' => $idUsuario,
                'tipo_usuario' => 'veterinario_pendente',
                'nome' => $dados['nome'],
            ];
            header('Location: perfil_veterinario.php?cadastro=enviado');
            exit;
        } catch (Throwable $e) {
            if ($db instanceof MysqliDatabase && $db->inTransaction()) {
                $db->rollBack();
            }
            $erros[] = $e instanceof InvalidArgumentException ? $e->getMessage()
                : ($e instanceof mysqli_sql_exception && $e->getSqlState() === '23000'
                    ? 'Este e-mail ou registro já está cadastrado. Tente entrar na sua conta.'
                    : mensagemErro($e));
        }
    }
}

cabecalho('Cadastro de veterinário');
?>
<section class="auth-intro">
    <span class="auth-icon" aria-hidden="true">✚</span>
    <div><h2>Crie sua conta profissional</h2><p>Seu perfil só será exibido no diretório após a validação do cadastro pela equipe.</p></div>
    <span class="auth-free-label">Validação necessária</span>
</section>
<?php if (usuarioId() > 0): ?><div class="notice">Você já está conectado como outro perfil. Para criar uma conta profissional separada, use um e-mail ainda não cadastrado. Se seu perfil veterinário já estiver no diretório, crie uma conta normal com o e-mail daquele perfil e peça o vínculo à equipe.</div><?php endif; ?>
<?php if ($erros): ?>
    <div class="notice error"><strong>Confira as informações:</strong><ul><?php foreach ($erros as $erro): ?><li><?= h($erro) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>
<form class="form-card vet-registration-form" method="post" action="cadastro_veterinario.php">
    <input type="hidden" name="csrf" value="<?= h(csrfToken()) ?>">
    <h2>Conta e dados profissionais</h2>
    <div class="form-grid">
        <label>Nome completo<input name="nome" autocomplete="name" required maxlength="100" value="<?= h($dados['nome']) ?>"></label>
        <label>CRMV<input name="crmv" required maxlength="30" placeholder="Ex.: CRMV-PR 00000" value="<?= h($dados['crmv']) ?>"></label>
        <label>Especialidade ou área de atuação<input name="especialidade" required maxlength="100" placeholder="Ex.: Clínica geral" value="<?= h($dados['especialidade']) ?>"></label>
        <label>E-mail profissional<input type="email" name="email" autocomplete="email" required maxlength="100" value="<?= h($dados['email']) ?>"></label>
        <label>Telefone com DDD<input type="tel" name="telefone" autocomplete="tel" required maxlength="25" value="<?= h($dados['telefone']) ?>"></label>
        <label>Canal de contato público<select name="canal_contato"><option value="whatsapp" <?= $dados['canal_contato'] === 'whatsapp' ? 'selected' : '' ?>>WhatsApp</option><option value="email" <?= $dados['canal_contato'] === 'email' ? 'selected' : '' ?>>E-mail</option></select></label>
        <label>Cidade<input name="cidade" autocomplete="address-level2" required maxlength="100" value="<?= h($dados['cidade']) ?>"></label>
        <label>Estado (UF)<input name="uf" autocomplete="address-level1" required minlength="2" maxlength="2" placeholder="PR" value="<?= h($dados['uf']) ?>"></label>
        <label class="full">Endereço de atendimento (opcional)<input name="endereco" maxlength="255" value="<?= h($dados['endereco']) ?>"></label>
        <label class="full">Apresentação profissional (opcional)<textarea name="descricao" rows="3" maxlength="3000"><?= h($dados['descricao']) ?></textarea></label>
        <label class="full">Serviços oferecidos (opcional)<textarea name="servicos" rows="3" maxlength="3000" placeholder="Um serviço por linha"><?= h($dados['servicos']) ?></textarea></label>
        <label class="full">Planos e valores (opcional)<textarea name="planos" rows="3" maxlength="3000" placeholder="Descreva os planos, serviços incluídos e respectivos valores"><?= h($dados['planos']) ?></textarea></label>
        <label>Valor da consulta (R$, opcional)<input name="valor_consulta" type="number" min="0" step="0.01" value="<?= h($dados['valor_consulta']) ?>"></label>
        <label>Senha da conta<input type="password" name="senha" autocomplete="new-password" minlength="8" required></label>
        <label class="full">Confirme a senha<input type="password" name="confirmacao" autocomplete="new-password" minlength="8" required></label>
    </div>
    <p class="form-help">Os valores e condições são informados pelo profissional e devem ser confirmados diretamente com a clínica.</p>
    <div class="filter-actions"><button class="btn btn-primary" type="submit">Criar conta e enviar para análise</button><a class="btn btn-outline" href="veterinarios.php">Voltar aos profissionais</a></div>
</form>
<?php rodape(); ?>
