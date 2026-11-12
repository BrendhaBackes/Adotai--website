<?php
declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';

exigirTipo(['protetor', 'protetor_pendente']);
validarCsrf();

$db = null;
$perfil = [];
$usuario = [];
$erro = '';
$aviso = '';
$idProtetor = 0;

try {
    $db = db();
    $stmt = $db->prepare(
        'SELECT p.*, u.nome AS nome_conta, u.email AS email_conta, u.telefone AS telefone_conta
         FROM protetor p INNER JOIN usuario u ON u.id = p.id_usuario
         WHERE p.id_usuario = :id LIMIT 1'
    );
    $stmt->execute(['id' => usuarioId()]);
    $perfil = $stmt->fetch() ?: [];
    if (!$perfil) {
        http_response_code(404);
        exit('Perfil de protetor não encontrado.');
    }
    $idProtetor = (int)$perfil['id_protetor'];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $nome = trim((string)($_POST['nome'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $telefone = preg_replace('/\D+/', '', (string)($_POST['telefone'] ?? '')) ?? '';
        $organizacao = trim((string)($_POST['nome_organizacao'] ?? ''));
        $tipoPerfil = trim((string)($_POST['tipo_perfil'] ?? ''));
        $canalContato = trim((string)($_POST['canal_contato'] ?? ''));
        $descricao = trim((string)($_POST['descricao'] ?? ''));
        $emailContato = trim((string)($_POST['email_contato'] ?? ''));
        $telefoneContato = preg_replace('/\D+/', '', (string)($_POST['telefone_contato'] ?? '')) ?? '';
        $endereco = trim((string)($_POST['endereco'] ?? ''));
        $cidade = trim((string)($_POST['cidade'] ?? ''));
        $uf = strtoupper(trim((string)($_POST['uf'] ?? '')));

        if ($nome === '' || strlen($nome) > 120 || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 100) {
            throw new InvalidArgumentException('Informe um nome e um e-mail de conta válidos.');
        }
        if (strlen($telefone) < 10 || strlen($telefone) > 14) {
            throw new InvalidArgumentException('Informe um telefone da conta com DDD.');
        }
        if ($organizacao === '' || strlen($organizacao) > 150 || strlen($descricao) > 3000 || strlen($endereco) > 255) {
            throw new InvalidArgumentException('Informe o nome da ONG ou responsável e confira o limite dos campos.');
        }
        if (!in_array($tipoPerfil, ['ong', 'independente'], true) || !in_array($canalContato, ['whatsapp', 'email'], true)) {
            throw new InvalidArgumentException('Selecione um tipo de perfil e canal de contato válidos.');
        }
        if ($emailContato !== '' && (!filter_var($emailContato, FILTER_VALIDATE_EMAIL) || strlen($emailContato) > 150)) {
            throw new InvalidArgumentException('Informe um e-mail público válido ou deixe o campo vazio.');
        }
        if ($telefoneContato !== '' && (strlen($telefoneContato) < 10 || strlen($telefoneContato) > 14)) {
            throw new InvalidArgumentException('O telefone público precisa incluir DDD.');
        }
        if (($canalContato === 'email' && $emailContato === '') || ($canalContato === 'whatsapp' && $telefoneContato === '')) {
            throw new InvalidArgumentException('Preencha o contato público selecionado.');
        }
        if (($cidade !== '' && strlen($cidade) > 100) || ($uf !== '' && !preg_match('/^[A-Z]{2}$/', $uf))) {
            throw new InvalidArgumentException('Confira a cidade e a UF.');
        }

        $foto = (string)($perfil['foto_url'] ?? '');
        $arquivoFoto = $_FILES['foto'] ?? null;
        if ($arquivoFoto && $arquivoFoto['error'] !== UPLOAD_ERR_NO_FILE) {
            $foto = salvarFotoPerfil($arquivoFoto, 'protetores');
        }

        $db->beginTransaction();
        $stmt = $db->prepare('UPDATE usuario SET nome = :nome, email = :email, telefone = :telefone WHERE id = :id');
        $stmt->execute(['nome' => $nome, 'email' => $email, 'telefone' => $telefone, 'id' => usuarioId()]);
        $stmt = $db->prepare(
            'UPDATE protetor SET nome_organizacao = :organizacao, tipo_perfil = :tipo, canal_contato = :canal, descricao = :descricao,
                email_contato = :email_contato, telefone_contato = :telefone_contato,
                endereco = :endereco, cidade = :cidade, uf = :uf, foto_url = :foto
             WHERE id_protetor = :id'
        );
        $stmt->execute([
            'organizacao' => $organizacao, 'tipo' => $tipoPerfil, 'canal' => $canalContato,
            'descricao' => $descricao ?: null,
            'email_contato' => $emailContato ?: null, 'telefone_contato' => $telefoneContato ?: null,
            'endereco' => $endereco ?: null, 'cidade' => $cidade ?: null, 'uf' => $uf ?: null,
            'foto' => $foto ?: null, 'id' => $idProtetor,
        ]);
        $db->commit();
        $_SESSION['usuario']['nome'] = $nome;
        $aviso = 'Perfil de protetor atualizado.';
        $stmt = $db->prepare(
            'SELECT p.*, u.nome AS nome_conta, u.email AS email_conta, u.telefone AS telefone_conta
             FROM protetor p INNER JOIN usuario u ON u.id = p.id_usuario WHERE p.id_usuario = :id LIMIT 1'
        );
        $stmt->execute(['id' => usuarioId()]);
        $perfil = $stmt->fetch() ?: $perfil;
    }
} catch (Throwable $e) {
    if ($db instanceof MysqliDatabase && $db->inTransaction()) {
        $db->rollBack();
    }
    $erro = $e instanceof InvalidArgumentException ? $e->getMessage()
        : ($e instanceof mysqli_sql_exception && $e->getSqlState() === '23000'
            ? 'Este e-mail já está sendo usado por outra conta.'
            : mensagemErro($e));
}

cabecalho('Perfil do protetor');
?>
<?php if ($erro): ?><div class="notice error"><?= h($erro) ?></div><?php endif; ?>
<?php if ($aviso): ?><div class="notice success"><?= h($aviso) ?></div><?php endif; ?>
<div class="notice <?= ($perfil['status_aprovacao'] ?? '') === 'Aprovado' ? 'success' : '' ?>">
    <strong>Cadastro do perfil: <?= h($perfil['status_aprovacao'] ?? 'Pendente') ?>.</strong>
    <?= ($perfil['status_aprovacao'] ?? '') === 'Aprovado' ? 'Você pode cadastrar e gerenciar animais.' : 'A equipe precisa aprovar seu perfil antes da publicação dos animais.' ?>
</div>
<form class="form-card" method="post" enctype="multipart/form-data">
    <input type="hidden" name="csrf" value="<?= h(csrfToken()) ?>">
    <h2>Conta e dados públicos do perfil</h2>
    <div class="form-grid">
        <label>Responsável pela conta<input name="nome" required maxlength="120" value="<?= h($perfil['nome_conta'] ?? '') ?>"></label>
        <label>E-mail da conta<input type="email" name="email" required maxlength="100" value="<?= h($perfil['email_conta'] ?? '') ?>"></label>
        <label>Telefone da conta<input type="tel" name="telefone" required maxlength="20" value="<?= h($perfil['telefone_conta'] ?? '') ?>"></label>
        <label>Tipo de perfil<select name="tipo_perfil"><option value="ong" <?= ($perfil['tipo_perfil'] ?? 'ong') === 'ong' ? 'selected' : '' ?>>ONG / organização</option><option value="independente" <?= ($perfil['tipo_perfil'] ?? '') === 'independente' ? 'selected' : '' ?>>Protetor independente</option></select></label>
        <label>Nome da ONG ou responsável<input name="nome_organizacao" required maxlength="150" value="<?= h($perfil['nome_organizacao'] ?? '') ?>"></label>
        <label>Canal de contato público<select name="canal_contato"><option value="whatsapp" <?= ($perfil['canal_contato'] ?? 'whatsapp') === 'whatsapp' ? 'selected' : '' ?>>WhatsApp</option><option value="email" <?= ($perfil['canal_contato'] ?? '') === 'email' ? 'selected' : '' ?>>E-mail</option></select></label>
        <label>E-mail público<input type="email" name="email_contato" maxlength="150" value="<?= h($perfil['email_contato'] ?? '') ?>"></label>
        <label>WhatsApp público com DDD<input type="tel" name="telefone_contato" maxlength="25" value="<?= h($perfil['telefone_contato'] ?? '') ?>"></label>
        <label>Cidade<input name="cidade" maxlength="100" value="<?= h($perfil['cidade'] ?? '') ?>"></label>
        <label>UF<input name="uf" minlength="2" maxlength="2" value="<?= h($perfil['uf'] ?? '') ?>"></label>
        <label class="full">Endereço / local de atendimento<input name="endereco" maxlength="255" value="<?= h($perfil['endereco'] ?? '') ?>"></label>
        <label class="full">Sobre o perfil<textarea name="descricao" rows="4" maxlength="3000"><?= h($perfil['descricao'] ?? '') ?></textarea></label>
        <label class="full">Foto ou logotipo (JPG, PNG ou WEBP, até 5 MB)<input type="file" name="foto" accept="image/jpeg,image/png,image/webp"></label>
    </div>
    <?php if (!empty($perfil['foto_url']) && perfilFotoSrc($perfil['foto_url'])): ?><p><img src="<?= h(perfilFotoSrc($perfil['foto_url'])) ?>" alt="Foto atual da organização" width="120"></p><?php endif; ?>
    <p class="form-help">Preencha o contato correspondente ao canal escolhido. Seus dados públicos serão usados nos anúncios aprovados.</p>
    <div class="filter-actions"><button class="btn btn-primary" type="submit">Salvar perfil e contato</button><a class="btn btn-outline" href="painel_protetor.php">Gerenciar animais</a></div>
</form>
<?php rodape(); ?>
