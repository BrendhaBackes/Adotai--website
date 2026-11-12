<?php
declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';

exigirTipo(['admin', 'administrador']);
validarCsrf();

$mensagem = (string)($_SESSION['flash_veterinario_admin'] ?? '');
unset($_SESSION['flash_veterinario_admin']);
$erro = '';
$lista = [];

try {
    $db = db();
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $id = filter_var($_POST['id_veterinario'] ?? '', FILTER_VALIDATE_INT);
        $tipoAcao = (string)($_POST['tipo_acao'] ?? 'status');
        if ($id === false || $id < 1) {
            throw new InvalidArgumentException('Selecione um profissional válido.');
        }
        if ($tipoAcao === 'vincular_conta') {
            $db->beginTransaction();
            $stmt = $db->prepare('SELECT id_usuario, email FROM veterinario WHERE id_veterinario = :id FOR UPDATE');
            $stmt->execute(['id' => $id]);
            $profissional = $stmt->fetch();
            if (!$profissional || $profissional['id_usuario'] !== null) {
                throw new InvalidArgumentException('Esse perfil não existe ou já está vinculado a uma conta.');
            }
            $stmt = $db->prepare('SELECT id FROM usuario WHERE LOWER(email) = LOWER(:email) LIMIT 1');
            $stmt->execute(['email' => $profissional['email']]);
            $idUsuario = $stmt->fetchColumn();
            if ($idUsuario === false) {
                throw new InvalidArgumentException('Não foi encontrada uma conta com o mesmo e-mail profissional.');
            }
            $stmt = $db->prepare('SELECT 1 FROM veterinario WHERE id_usuario = :usuario LIMIT 1');
            $stmt->execute(['usuario' => $idUsuario]);
            if ($stmt->fetchColumn() !== false) {
                throw new InvalidArgumentException('Essa conta já está vinculada a outro perfil veterinário.');
            }
            $stmt = $db->prepare('UPDATE veterinario SET id_usuario = :usuario WHERE id_veterinario = :id AND id_usuario IS NULL');
            $stmt->execute(['usuario' => $idUsuario, 'id' => $id]);
            if ($stmt->rowCount() !== 1) {
                throw new InvalidArgumentException('A conta profissional não pôde ser vinculada.');
            }
            $db->commit();
            $_SESSION['flash_veterinario_admin'] = 'Conta vinculada ao perfil profissional.';
        } elseif ($tipoAcao === 'status') {
            $status = (string)($_POST['acao'] ?? '');
            if (!in_array($status, ['Ativo', 'Inativo', 'Pendente'], true)) {
                throw new InvalidArgumentException('Selecione uma situação válida.');
            }
            $stmt = $db->prepare('UPDATE veterinario SET status = :status WHERE id_veterinario = :id');
            $stmt->execute(['status' => $status, 'id' => $id]);
            if ($stmt->rowCount() === 0) {
                $stmt = $db->prepare('SELECT 1 FROM veterinario WHERE id_veterinario = :id LIMIT 1');
                $stmt->execute(['id' => $id]);
                if ($stmt->fetchColumn() === false) {
                    throw new InvalidArgumentException('Esse cadastro de veterinário não foi encontrado.');
                }
            }
            $_SESSION['flash_veterinario_admin'] = 'Situação do cadastro atualizada.';
        } else {
            throw new InvalidArgumentException('Ação administrativa inválida.');
        }

        header('Location: gerenciar_veterinarios.php');
        exit;
    }

    $lista = $db->query(
        "SELECT v.id_veterinario, v.id_usuario, v.nome, v.crmv, v.especialidade, v.email,
                v.telefone, v.cidade, v.uf, v.status, v.data_cadastro, u.id AS conta_correspondente
         FROM veterinario v
         LEFT JOIN usuario u ON LOWER(u.email) = LOWER(v.email)
            AND NOT EXISTS (SELECT 1 FROM veterinario vinculado WHERE vinculado.id_usuario = u.id)
         ORDER BY CASE v.status WHEN 'Pendente' THEN 0 WHEN 'Ativo' THEN 1 ELSE 2 END, v.nome"
    )->fetchAll();
} catch (Throwable $e) {
    if (isset($db) && $db instanceof MysqliDatabase && $db->inTransaction()) {
        $db->rollBack();
    }
    if ($e instanceof InvalidArgumentException) {
        $erro = $e->getMessage();
    } else {
        $erro = mensagemErro($e);
    }
}

cabecalho('Gerenciar veterinários');
?>
<?php if ($mensagem): ?><div class="notice success"><?= h($mensagem) ?></div><?php endif; ?>
<?php if ($erro): ?><div class="notice error"><?= h($erro) ?></div><?php endif; ?>
<section class="intro-box">
    <h2>Cadastros enviados pela rede veterinária</h2>
    <p>Revise os dados profissionais e aprove os perfis que poderão aparecer no diretório público.</p>
</section>
<?php if (!$erro && !$lista): ?>
    <div class="empty-state">Nenhum cadastro recebido até o momento.</div>
<?php elseif ($lista): ?>
    <div class="table-wrap">
        <table class="data-table">
            <thead><tr><th>Profissional</th><th>Contato</th><th>Local</th><th>Conta</th><th>Status</th><th>Ação</th></tr></thead>
            <tbody>
            <?php foreach ($lista as $veterinario): ?>
                <tr>
                    <td>
                        <strong><?= h($veterinario['nome']) ?></strong>
                        <?php if (!empty($veterinario['crmv'])): ?><br><?= h($veterinario['crmv']) ?><?php endif; ?>
                        <?php if (!empty($veterinario['especialidade'])): ?><br><?= h($veterinario['especialidade']) ?><?php endif; ?>
                    </td>
                    <td>
                        <?= h($veterinario['email'] ?: 'E-mail não informado') ?>
                        <?php if (!empty($veterinario['telefone'])): ?><br><?= h($veterinario['telefone']) ?><?php endif; ?>
                    </td>
                    <td><?= h($veterinario['cidade'] ?: 'Não informada') ?><?= !empty($veterinario['uf']) ? ' / ' . h($veterinario['uf']) : '' ?></td>
                    <td>
                        <?php if (!empty($veterinario['id_usuario'])): ?>
                            Vinculada
                        <?php elseif (!empty($veterinario['conta_correspondente'])): ?>
                            <form method="post">
                                <input type="hidden" name="csrf" value="<?= h(csrfToken()) ?>">
                                <input type="hidden" name="tipo_acao" value="vincular_conta">
                                <input type="hidden" name="id_veterinario" value="<?= (int)$veterinario['id_veterinario'] ?>">
                                <button class="btn btn-outline" type="submit">Vincular conta</button>
                            </form>
                        <?php else: ?>
                            Crie a conta com este e-mail
                        <?php endif; ?>
                    </td>
                    <td><?= h($veterinario['status']) ?></td>
                    <td>
                        <form method="post" class="inline-form">
                            <input type="hidden" name="csrf" value="<?= h(csrfToken()) ?>">
                            <input type="hidden" name="tipo_acao" value="status">
                            <input type="hidden" name="id_veterinario" value="<?= (int)$veterinario['id_veterinario'] ?>">
                            <select name="acao" aria-label="Novo status de <?= h($veterinario['nome']) ?>">
                                <?php foreach (['Ativo', 'Pendente', 'Inativo'] as $status): ?>
                                    <option value="<?= h($status) ?>" <?= $veterinario['status'] === $status ? 'selected' : '' ?>><?= $status === 'Ativo' ? 'Aprovar / Ativo' : h($status) ?></option>
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
<?php rodape(); ?>
