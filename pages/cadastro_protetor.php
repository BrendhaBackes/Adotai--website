<?php
declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';

exigirLogin();
validarCsrf();

$aviso = '';
$status = false;
$dados = [
    'tipo_perfil' => 'ong',
    'nome_organizacao' => '',
    'canal_contato' => 'whatsapp',
    'email_contato' => '',
    'telefone_contato' => '',
    'cidade' => '',
    'uf' => '',
    'endereco' => '',
    'descricao' => '',
];

try {
    $db = db();
    $stmt = $db->prepare('SELECT status_aprovacao FROM protetor WHERE id_usuario = :id LIMIT 1');
    $stmt->execute(['id' => usuarioId()]);
    $status = $stmt->fetchColumn();

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $status === false) {
        foreach ($dados as $campo => $_) {
            $dados[$campo] = trim((string)($_POST[$campo] ?? ''));
        }
        if (!in_array($dados['tipo_perfil'], ['ong', 'independente'], true)) {
            throw new InvalidArgumentException('Selecione ONG ou protetor independente.');
        }
        if (!in_array($dados['canal_contato'], ['whatsapp', 'email'], true)) {
            throw new InvalidArgumentException('Selecione WhatsApp ou e-mail como contato público.');
        }
        $dados['uf'] = strtoupper($dados['uf']);
        $telefone = preg_replace('/\D+/', '', $dados['telefone_contato']) ?? '';
        if ($dados['nome_organizacao'] === '' || strlen($dados['nome_organizacao']) > 150) {
            throw new InvalidArgumentException('Informe o nome da ONG ou do responsável.');
        }
        if ($dados['email_contato'] !== '' && (!filter_var($dados['email_contato'], FILTER_VALIDATE_EMAIL) || strlen($dados['email_contato']) > 150)) {
            throw new InvalidArgumentException('Informe um e-mail de contato válido.');
        }
        if ($dados['telefone_contato'] !== '' && (strlen($telefone) < 10 || strlen($telefone) > 14)) {
            throw new InvalidArgumentException('Informe o telefone público com DDD.');
        }
        if (($dados['canal_contato'] === 'email' && $dados['email_contato'] === '')
            || ($dados['canal_contato'] === 'whatsapp' && $telefone === '')) {
            throw new InvalidArgumentException('Informe o endereço do canal de contato público escolhido.');
        }
        if (strlen($dados['descricao']) > 3000 || strlen($dados['endereco']) > 255 || strlen($dados['cidade']) > 100
            || ($dados['uf'] !== '' && !preg_match('/^[A-Z]{2}$/', $dados['uf']))) {
            throw new InvalidArgumentException('Confira a cidade, UF e os limites de texto dos campos.');
        }
        $stmt = $db->prepare(
            "INSERT INTO protetor
                (id_usuario, status_aprovacao, tipo_perfil, canal_contato, nome_organizacao, email_contato, telefone_contato, cidade, uf, endereco, descricao)
             VALUES
                (:id, 'Pendente', :tipo, :canal, :organizacao, :email, :telefone, :cidade, :uf, :endereco, :descricao)"
        );
        $stmt->execute([
            'id' => usuarioId(), 'tipo' => $dados['tipo_perfil'], 'canal' => $dados['canal_contato'],
            'organizacao' => $dados['nome_organizacao'],
            'email' => $dados['email_contato'] ?: null, 'telefone' => $telefone ?: null,
            'cidade' => $dados['cidade'] ?: null, 'uf' => $dados['uf'] ?: null,
            'endereco' => $dados['endereco'] ?: null, 'descricao' => $dados['descricao'] ?: null,
        ]);
        $_SESSION['tipo_usuario'] = 'protetor_pendente';
        $_SESSION['usuario']['tipo_usuario'] = 'protetor_pendente';
        $status = 'Pendente';
        $aviso = 'Solicitação enviada. Você poderá cadastrar animais após a aprovação da equipe.';
    } elseif ($status !== false) {
        $aviso = 'Seu cadastro de protetor está ' . strtolower((string)$status) . '.';
    }
} catch (Throwable $e) {
    $aviso = $e instanceof InvalidArgumentException ? $e->getMessage() : mensagemErro($e);
}

cabecalho('Cadastro de protetor');
?>
<div class="empty-state">
    <h2>Divulgue animais para adoção</h2>
    <p>Cadastre-se como ONG ou protetor independente. Após a aprovação da equipe, poderá publicar animais e editar disponibilidade e contato.</p>
    <?php if ($aviso): ?><div class="notice"><?= h($aviso) ?></div><?php endif; ?>
    <?php if ($status === false): ?>
        <form class="form-card" method="post">
            <input type="hidden" name="csrf" value="<?= h(csrfToken()) ?>">
            <div class="form-grid">
                <label>Tipo de perfil
                    <select name="tipo_perfil" id="tipo-perfil">
                        <option value="ong">ONG / organização</option>
                        <option value="independente">Protetor independente</option>
                    </select>
                </label>
                <label>Nome da ONG ou responsável<input name="nome_organizacao" required maxlength="150"></label>
                <label>Canal de contato público
                    <select name="canal_contato">
                        <option value="whatsapp">WhatsApp</option>
                        <option value="email">E-mail</option>
                    </select>
                </label>
                <label>E-mail público<input type="email" name="email_contato" maxlength="150"></label>
                <label>WhatsApp público com DDD<input type="tel" name="telefone_contato" maxlength="25"></label>
                <label>Cidade<input name="cidade" maxlength="100"></label>
                <label>UF<input name="uf" minlength="2" maxlength="2" placeholder="PR"></label>
                <label class="full">Endereço / local de atendimento<input name="endereco" maxlength="255"></label>
                <label class="full">Sobre a organização<textarea name="descricao" rows="3" maxlength="3000"></textarea></label>
            </div>
            <p class="form-help">O contato selecionado precisa estar preenchido. A divulgação dos animais só começa após a aprovação da equipe.</p>
            <button class="btn btn-primary" type="submit">Enviar perfil para aprovação</button>
        </form>
    <?php else: ?>
        <a class="btn btn-outline" href="perfil.php">Voltar ao perfil</a>
    <?php endif; ?>
</div>
<?php rodape(); ?>
