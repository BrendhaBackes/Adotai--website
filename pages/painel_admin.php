<?php
declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';

exigirTipo(['admin', 'administrador']);
validarCsrf();

$protetores = [];
$animais = [];
$erro = '';
$aviso = (string)($_SESSION['flash_admin'] ?? '');
unset($_SESSION['flash_admin']);

try {
    $db = db();
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $acao = (string)($_POST['tipo_acao'] ?? '');
        $status = (string)($_POST['status'] ?? '');
        if ($acao === 'protetor') {
            $idUsuario = filter_var($_POST['id_usuario'] ?? '', FILTER_VALIDATE_INT);
            if (!$idUsuario || $idUsuario < 1 || !in_array($status, ['Pendente', 'Aprovado', 'Rejeitado'], true)) {
                throw new InvalidArgumentException('Selecione uma organização e uma situação válidas.');
            }
            $stmt = $db->prepare('UPDATE protetor SET status_aprovacao = :status WHERE id_usuario = :usuario');
            $stmt->execute(['status' => $status, 'usuario' => $idUsuario]);
            $aviso = 'Situação da organização atualizada.';
        } elseif ($acao === 'animal') {
            $idAnimal = filter_var($_POST['id_animal'] ?? '', FILTER_VALIDATE_INT);
            if ($idAnimal === false || $idAnimal < 0 || !in_array($status, ['Pendente', 'Aprovado', 'Rejeitado'], true)) {
                throw new InvalidArgumentException('Selecione um animal e uma situação de aprovação válidos.');
            }
            $stmt = $db->prepare('UPDATE animal SET status_aprovacao = :status WHERE id_animal = :id');
            $stmt->execute(['status' => $status, 'id' => $idAnimal]);
            $aviso = 'Revisão do animal atualizada.';
        } else {
            throw new InvalidArgumentException('Ação administrativa inválida.');
        }
        $_SESSION['flash_admin'] = $aviso;
        header('Location: painel_admin.php');
        exit;
    }

    $protetores = $db->query(
        'SELECT p.id_usuario, p.status_aprovacao, p.tipo_perfil, p.nome_organizacao, p.cidade, p.uf, u.nome, u.email, u.telefone
         FROM protetor p
         INNER JOIN usuario u ON u.id = p.id_usuario
         ORDER BY CASE p.status_aprovacao WHEN \'Pendente\' THEN 0 WHEN \'Aprovado\' THEN 1 ELSE 2 END, u.nome'
    )->fetchAll();
    $animais = $db->query(
        'SELECT a.id_animal, a.nome_animal, a.especie, a.status_adocao, a.status_aprovacao, p.nome_organizacao
         FROM animal a LEFT JOIN protetor p ON p.id_protetor = a.id_protetor
         ORDER BY CASE a.status_aprovacao WHEN \'Pendente\' THEN 0 WHEN \'Rejeitado\' THEN 1 ELSE 2 END, a.data_cadastro DESC, a.nome_animal'
    )->fetchAll();
} catch (Throwable $e) {
    if ($e instanceof InvalidArgumentException) {
        $aviso = $e->getMessage();
    } else {
        $erro = mensagemErro($e);
    }
}

cabecalho('Painel administrativo');
?>
<?php if ($erro): ?><div class="notice error"><?= h($erro) ?></div><?php endif; ?>
<?php if ($aviso): ?><div class="notice success"><?= h($aviso) ?></div><?php endif; ?>
<div class="notice"><strong>Gerenciamento:</strong> <a href="gerenciar_veterinarios.php">Veterinários</a> · <a href="gerenciar_planos.php">Planos gerais</a>. Os planos profissionais são cadastrados pelos próprios veterinários.</div>
<div class="stats-grid">
    <article class="stat-card"><span>ONGs e protetores cadastrados</span><strong><?= count($protetores) ?></strong></article>
    <article class="stat-card"><span>Animais cadastrados</span><strong><?= count($animais) ?></strong></article>
</div>
<h2 class="section-heading">ONGs e protetores independentes</h2>
<?php if (!$protetores): ?>
    <div class="empty-state">Nenhum protetor cadastrado.</div>
<?php else: ?>
    <div class="table-wrap">
        <table class="data-table">
            <thead><tr><th>Nome</th><th>Contato</th><th>Status</th><th>Ação</th></tr></thead>
            <tbody>
            <?php foreach ($protetores as $protetor): ?>
                <tr>
                    <td><?= h($protetor['nome_organizacao'] ?: $protetor['nome']) ?><br><small><?= ($protetor['tipo_perfil'] ?? 'ong') === 'independente' ? 'Protetor independente' : 'ONG / organização' ?> · <?= h($protetor['nome']) ?></small></td>
                    <td><?= h($protetor['email']) ?><br><?= h($protetor['telefone']) ?><?php if (!empty($protetor['cidade'])): ?><br><?= h($protetor['cidade']) ?><?= !empty($protetor['uf']) ? ' - ' . h($protetor['uf']) : '' ?><?php endif; ?></td>
                    <td><?= h($protetor['status_aprovacao']) ?></td>
                    <td>
                        <form method="post" class="inline-form">
                            <input type="hidden" name="csrf" value="<?= h(csrfToken()) ?>">
                            <input type="hidden" name="tipo_acao" value="protetor">
                            <input type="hidden" name="id_usuario" value="<?= (int)$protetor['id_usuario'] ?>">
                            <select name="status" aria-label="Situação de <?= h($protetor['nome_organizacao'] ?: $protetor['nome']) ?>">
                                <?php foreach (['Pendente', 'Aprovado', 'Rejeitado'] as $status): ?>
                                    <option value="<?= h($status) ?>" <?= $protetor['status_aprovacao'] === $status ? 'selected' : '' ?>><?= h($status) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button class="btn btn-primary" type="submit">Salvar</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
<h2 class="section-heading">Animais aguardando ou publicados</h2>
<?php if (!$animais): ?>
    <div class="empty-state">Nenhum animal cadastrado.</div>
<?php else: ?>
    <div class="table-wrap">
        <table class="data-table">
            <thead><tr><th>Nome</th><th>Responsável</th><th>Adoção</th><th>Revisão</th><th>Ação</th></tr></thead>
            <tbody>
            <?php foreach ($animais as $animal): ?>
                <tr>
                    <td><?= h($animal['nome_animal']) ?><br><small><?= h($animal['especie']) ?></small></td>
                    <td><?= h($animal['nome_organizacao'] ?: 'Organização') ?></td>
                    <td><?= h($animal['status_adocao']) ?></td>
                    <td><?= h($animal['status_aprovacao']) ?></td>
                    <td>
                        <form method="post" class="inline-form">
                            <input type="hidden" name="csrf" value="<?= h(csrfToken()) ?>">
                            <input type="hidden" name="tipo_acao" value="animal">
                            <input type="hidden" name="id_animal" value="<?= (int)$animal['id_animal'] ?>">
                            <select name="status" aria-label="Revisão de <?= h($animal['nome_animal']) ?>">
                                <?php foreach (['Pendente', 'Aprovado', 'Rejeitado'] as $status): ?>
                                    <option value="<?= h($status) ?>" <?= $animal['status_aprovacao'] === $status ? 'selected' : '' ?>><?= h($status) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button class="btn btn-primary" type="submit">Revisar</button>
                            <a href="animal_form.php?id=<?= (int)$animal['id_animal'] ?>">Editar</a>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
<?php rodape(); ?>
