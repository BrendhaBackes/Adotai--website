<?php
declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';

exigirTipo(['protetor']);
validarCsrf();
$animais = [];
$erro = '';
$aviso = (string)($_SESSION['flash_animal'] ?? '');
unset($_SESSION['flash_animal']);
$protetorId = 0;

try {
    $db = db();
    $stmt = $db->prepare("SELECT id_protetor FROM protetor WHERE id_usuario = :usuario AND status_aprovacao = 'Aprovado' LIMIT 1");
    $stmt->execute(['usuario' => usuarioId()]);
    $protetorId = (int)$stmt->fetchColumn();
    if ($protetorId <= 0) {
        http_response_code(403);
        exit('Seu cadastro de organização ainda não foi aprovado.');
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'atualizar_disponibilidade') {
        $idAnimal = filter_var($_POST['id_animal'] ?? '', FILTER_VALIDATE_INT);
        $status = trim((string)($_POST['status_adocao'] ?? ''));
        if ($idAnimal === false || $idAnimal < 1 || !in_array($status, ['Disponível', 'Em processo', 'Adotado'], true)) {
            throw new InvalidArgumentException('Selecione um animal e uma disponibilidade válidos.');
        }
        $stmt = $db->prepare('UPDATE animal SET status_adocao = :status WHERE id_animal = :id AND id_protetor = :protetor');
        $stmt->execute(['status' => $status, 'id' => $idAnimal, 'protetor' => $protetorId]);
        if ($stmt->rowCount() === 0) {
            $stmt = $db->prepare('SELECT 1 FROM animal WHERE id_animal = :id AND id_protetor = :protetor');
            $stmt->execute(['id' => $idAnimal, 'protetor' => $protetorId]);
            if ($stmt->fetchColumn() === false) {
                throw new InvalidArgumentException('O animal não existe ou não pertence ao seu perfil.');
            }
        }
        $_SESSION['flash_animal'] = 'Disponibilidade atualizada.';
        header('Location: painel_protetor.php');
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'excluir_animal') {
        $idAnimal = filter_var($_POST['id_animal'] ?? '', FILTER_VALIDATE_INT);
        if ($idAnimal === false || $idAnimal < 0) {
            throw new InvalidArgumentException('Selecione um animal válido para excluir.');
        }
        $db->beginTransaction();
        $stmt = $db->prepare('SELECT id_animal FROM animal WHERE id_animal = :id AND id_protetor = :protetor LIMIT 1');
        $stmt->execute(['id' => $idAnimal, 'protetor' => $protetorId]);
        if ($stmt->fetchColumn() === false) {
            throw new InvalidArgumentException('O animal não existe ou não pertence à sua organização.');
        }
        $stmt = $db->prepare('DELETE la FROM lista_animal la WHERE la.id_animal = :animal');
        $stmt->execute(['animal' => $idAnimal]);
        $stmt = $db->prepare('DELETE FROM animal WHERE id_animal = :id AND id_protetor = :protetor');
        $stmt->execute(['id' => $idAnimal, 'protetor' => $protetorId]);
        $db->commit();
        $_SESSION['flash_animal'] = 'Animal excluído.';
        header('Location: painel_protetor.php');
        exit;
    }

    $stmt = $db->prepare('SELECT * FROM animal WHERE id_protetor = :protetor ORDER BY data_cadastro DESC, nome_animal');
    $stmt->execute(['protetor' => $protetorId]);
    $animais = $stmt->fetchAll();
} catch (Throwable $e) {
    if (isset($db) && $db instanceof MysqliDatabase && $db->inTransaction()) {
        $db->rollBack();
    }
    $erro = $e instanceof InvalidArgumentException ? $e->getMessage() : mensagemErro($e);
}

cabecalho('Painel de divulgação');
?>
<?php if ($erro): ?><div class="notice error"><?= h($erro) ?></div><?php endif; ?>
<?php if ($aviso): ?><div class="notice success"><?= h($aviso) ?></div><?php endif; ?>
<div class="panel-heading">
    <p>Cadastre e gerencie seus animais. Novos cadastros e alterações nos dados ficam ocultos até a aprovação; mudanças de disponibilidade são imediatas.</p>
    <div class="filter-actions"><a class="btn btn-outline" href="perfil_protetor.php">Editar perfil e contato</a><a class="btn btn-primary" href="animal_form.php">+ Cadastrar animal</a></div>
</div>
<?php if (!$erro && !$animais): ?>
    <div class="empty-state">
        <h2>Você ainda não cadastrou animais</h2>
        <p>Cadastre o primeiro animal. Ele só será publicado após a aprovação da equipe.</p>
    </div>
<?php elseif ($animais): ?>
    <div class="table-wrap">
        <table class="data-table">
            <thead><tr><th>Nome</th><th>Espécie</th><th>Publicação</th><th>Adoção</th><th>Ações</th></tr></thead>
            <tbody>
            <?php foreach ($animais as $animal): ?>
                <tr>
                    <td><?= h($animal['nome_animal']) ?></td>
                    <td><?= h($animal['especie']) ?></td>
                    <td><?= h($animal['status_aprovacao']) ?></td>
                    <td><form method="post" class="inline-form">
                        <input type="hidden" name="csrf" value="<?= h(csrfToken()) ?>">
                        <input type="hidden" name="acao" value="atualizar_disponibilidade">
                        <input type="hidden" name="id_animal" value="<?= (int)$animal['id_animal'] ?>">
                        <select name="status_adocao" aria-label="Disponibilidade de <?= h($animal['nome_animal']) ?>">
                            <?php foreach (['Disponível', 'Em processo', 'Adotado'] as $status): ?>
                                <option value="<?= h($status) ?>" <?= $animal['status_adocao'] === $status ? 'selected' : '' ?>><?= h($status) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button class="btn btn-outline" type="submit">Atualizar</button>
                    </form></td>
                    <td><div class="inline-form">
                        <a href="animal_form.php?id=<?= (int)$animal['id_animal'] ?>">Editar</a>
                        <a href="animal.php?id=<?= (int)$animal['id_animal'] ?>">Pré-visualizar</a>
                        <form method="post" onsubmit="return confirm('Excluir este animal e removê-lo das listas de interesse?')">
                            <input type="hidden" name="csrf" value="<?= h(csrfToken()) ?>">
                            <input type="hidden" name="acao" value="excluir_animal">
                            <input type="hidden" name="id_animal" value="<?= (int)$animal['id_animal'] ?>">
                            <button class="btn btn-outline" type="submit">Excluir</button>
                        </form>
                    </div></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
<?php rodape(); ?>
