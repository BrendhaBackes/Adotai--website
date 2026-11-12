<?php
declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';

exigirTipo(['veterinario', 'veterinario_pendente']);
validarCsrf();

$db = null;
$perfil = [];
$usuario = [];
$erro = '';
$aviso = '';
$idVeterinario = 0;

try {
    $db = db();
    $stmt = $db->prepare(
        'SELECT v.*, u.nome AS nome_conta, u.email AS email_conta, u.telefone AS telefone_conta
         FROM veterinario v
         INNER JOIN usuario u ON u.id = v.id_usuario
         WHERE v.id_usuario = :id LIMIT 1'
    );
    $stmt->execute(['id' => usuarioId()]);
    $perfil = $stmt->fetch() ?: [];
    if (!$perfil) {
        http_response_code(404);
        exit('Perfil veterinário não encontrado.');
    }
    $idVeterinario = (int)$perfil['id_veterinario'];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $nome = trim((string)($_POST['nome'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $telefone = preg_replace('/\D+/', '', (string)($_POST['telefone'] ?? '')) ?? '';
        $canalContato = trim((string)($_POST['canal_contato'] ?? ''));
        $especialidade = trim((string)($_POST['especialidade'] ?? ''));
        $cidade = trim((string)($_POST['cidade'] ?? ''));
        $uf = strtoupper(trim((string)($_POST['uf'] ?? '')));
        $endereco = trim((string)($_POST['endereco'] ?? ''));
        $descricao = trim((string)($_POST['descricao'] ?? ''));
        $servicos = trim((string)($_POST['servicos'] ?? ''));
        $planos = trim((string)($_POST['planos'] ?? ''));
        $valorTexto = str_replace(',', '.', trim((string)($_POST['valor_consulta'] ?? '')));

        if ($nome === '' || strlen($nome) > 100 || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 100) {
            throw new InvalidArgumentException('Informe um nome e um e-mail válidos.');
        }
        if (strlen($telefone) < 10 || strlen($telefone) > 14 || $especialidade === '' || strlen($especialidade) > 100) {
            throw new InvalidArgumentException('Informe telefone com DDD e uma especialidade de até 100 caracteres.');
        }
        if (!in_array($canalContato, ['whatsapp', 'email'], true)) {
            throw new InvalidArgumentException('Selecione WhatsApp ou e-mail como canal de contato.');
        }
        if ($cidade === '' || strlen($cidade) > 100 || !preg_match('/^[A-Z]{2}$/', $uf)) {
            throw new InvalidArgumentException('Informe cidade e UF válidas.');
        }
        if (strlen($endereco) > 255 || strlen($descricao) > 3000 || strlen($servicos) > 3000 || strlen($planos) > 3000) {
            throw new InvalidArgumentException('Confira o limite de caracteres dos dados profissionais.');
        }
        if ($valorTexto !== '' && (!is_numeric($valorTexto) || (float)$valorTexto < 0 || (float)$valorTexto > 99999999)) {
            throw new InvalidArgumentException('Informe um valor válido para a consulta ou deixe o campo vazio.');
        }

        $foto = (string)($perfil['foto_url'] ?? '');
        $arquivoFoto = $_FILES['foto'] ?? null;
        if ($arquivoFoto && $arquivoFoto['error'] !== UPLOAD_ERR_NO_FILE) {
            $foto = salvarFotoPerfil($arquivoFoto, 'veterinarios');
        }

        $db->beginTransaction();
        $stmt = $db->prepare('UPDATE usuario SET nome = :nome, email = :email, telefone = :telefone WHERE id = :id');
        $stmt->execute(['nome' => $nome, 'email' => $email, 'telefone' => $telefone, 'id' => usuarioId()]);
        $stmt = $db->prepare(
            'UPDATE veterinario
             SET nome = :nome, email = :email, telefone = :telefone, especialidade = :especialidade,
                 cidade = :cidade, uf = :uf, endereco = :endereco, descricao = :descricao,
                 servicos = :servicos, planos = :planos, valor_consulta = :valor, foto_url = :foto,
                 canal_contato = :canal
             WHERE id_veterinario = :id'
        );
        $stmt->execute([
            'nome' => $nome, 'email' => $email, 'telefone' => $telefone, 'especialidade' => $especialidade,
            'cidade' => $cidade, 'uf' => $uf, 'endereco' => $endereco ?: null, 'descricao' => $descricao ?: null,
            'servicos' => $servicos ?: null, 'planos' => $planos ?: null,
            'valor' => $valorTexto !== '' ? $valorTexto : null, 'foto' => $foto ?: null,
            'canal' => $canalContato, 'id' => $idVeterinario,
        ]);
        $db->commit();
        $_SESSION['usuario']['nome'] = $nome;
        $aviso = 'Perfil profissional atualizado.';
        $stmt = $db->prepare(
            'SELECT v.*, u.nome AS nome_conta, u.email AS email_conta, u.telefone AS telefone_conta
             FROM veterinario v INNER JOIN usuario u ON u.id = v.id_usuario WHERE v.id_usuario = :id LIMIT 1'
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

cabecalho('Meu perfil veterinário');
?>
<?php if ($erro): ?><div class="notice error"><?= h($erro) ?></div><?php endif; ?>
<?php if ($aviso): ?><div class="notice success"><?= h($aviso) ?></div><?php endif; ?>
<div class="notice <?= ($perfil['status'] ?? '') === 'Ativo' ? 'success' : '' ?>">
    <strong>Status do perfil: <?= h($perfil['status'] ?? 'Pendente') ?>.</strong>
    <?php if (($perfil['status'] ?? '') === 'Ativo'): ?>Seu perfil está visível no diretório.
    <?php elseif (($perfil['status'] ?? '') === 'Pendente'): ?>Seu perfil ainda não aparece publicamente; aguarde a análise da equipe.
    <?php else: ?>Seu perfil está oculto. Entre em contato com a equipe para entender a situação do cadastro.<?php endif; ?>
</div>
<form class="form-card" method="post" enctype="multipart/form-data">
    <input type="hidden" name="csrf" value="<?= h(csrfToken()) ?>">
    <h2>Dados profissionais e contato</h2>
    <div class="form-grid">
        <label>Nome<input name="nome" required maxlength="100" value="<?= h($perfil['nome_conta'] ?? '') ?>"></label>
        <label>E-mail de contato<input type="email" name="email" required maxlength="100" value="<?= h($perfil['email_conta'] ?? '') ?>"></label>
        <label>Telefone com DDD<input type="tel" name="telefone" required maxlength="20" value="<?= h($perfil['telefone_conta'] ?? '') ?>"></label>
        <label>Canal de contato público<select name="canal_contato"><option value="whatsapp" <?= ($perfil['canal_contato'] ?? 'whatsapp') === 'whatsapp' ? 'selected' : '' ?>>WhatsApp</option><option value="email" <?= ($perfil['canal_contato'] ?? '') === 'email' ? 'selected' : '' ?>>E-mail</option></select></label>
        <label>Especialidade<input name="especialidade" required maxlength="100" value="<?= h($perfil['especialidade'] ?? '') ?>"></label>
        <label>Cidade<input name="cidade" required maxlength="100" value="<?= h($perfil['cidade'] ?? '') ?>"></label>
        <label>UF<input name="uf" required minlength="2" maxlength="2" value="<?= h($perfil['uf'] ?? '') ?>"></label>
        <label class="full">Endereço de atendimento<input name="endereco" maxlength="255" value="<?= h($perfil['endereco'] ?? '') ?>"></label>
        <label class="full">Apresentação profissional<textarea name="descricao" rows="3" maxlength="3000"><?= h($perfil['descricao'] ?? '') ?></textarea></label>
        <label class="full">Serviços oferecidos<textarea name="servicos" rows="3" maxlength="3000" placeholder="Um serviço por linha"><?= h($perfil['servicos'] ?? '') ?></textarea></label>
        <label class="full">Planos e valores<textarea name="planos" rows="4" maxlength="3000" placeholder="Informe serviços incluídos, condições e valores"><?= h($perfil['planos'] ?? '') ?></textarea></label>
        <label>Valor da consulta (R$)<input name="valor_consulta" type="number" min="0" step="0.01" value="<?= h($perfil['valor_consulta'] ?? '') ?>"></label>
        <label>Foto de perfil (JPG, PNG ou WEBP, até 5 MB)<input type="file" name="foto" accept="image/jpeg,image/png,image/webp"></label>
    </div>
    <?php if (!empty($perfil['foto_url']) && perfilFotoSrc($perfil['foto_url'])): ?><p><img src="<?= h(perfilFotoSrc($perfil['foto_url'])) ?>" alt="Foto atual do perfil" width="120"></p><?php endif; ?>
    <p class="form-help">Os planos, serviços e preços são informados pelo profissional. Confirme valores e condições diretamente antes de contratar.</p>
    <div class="filter-actions"><button class="btn btn-primary" type="submit">Salvar perfil</button><a class="btn btn-outline" href="painel_veterinario.php">Meu painel</a><a class="btn btn-outline" href="gerenciar_planos_veterinario.php">Cadastrar planos</a><a class="btn btn-outline" href="veterinarios.php">Ver diretório</a><a class="btn btn-outline" href="logout.php">Sair da conta</a></div>
</form>
<?php rodape(); ?>
