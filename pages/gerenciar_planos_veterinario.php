<?php
declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';

exigirTipo(['veterinario']);
validarCsrf();

$db = null;
$perfil = [];
$planos = [];
$editando = null;
$erro = '';
$sucesso = (string)($_SESSION['flash_plano_veterinario'] ?? '');
unset($_SESSION['flash_plano_veterinario']);

try {
    $db = db();
    $stmt = $db->prepare('SELECT id_veterinario, status FROM veterinario WHERE id_usuario = :usuario LIMIT 1');
    $stmt->execute(['usuario' => usuarioId()]);
    $perfil = $stmt->fetch() ?: [];
    if (!$perfil || $perfil['status'] !== 'Ativo') {
        http_response_code(403);
        exit('Seu perfil precisa estar aprovado para gerenciar planos.');
    }
    $idVeterinario = (int)$perfil['id_veterinario'];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $acao = (string)($_POST['acao'] ?? '');
        $idPlano = filter_var($_POST['id_plano'] ?? '0', FILTER_VALIDATE_INT);
        if ($idPlano === false || $idPlano < 0) {
            throw new InvalidArgumentException('Selecione um plano válido.');
        }

        if ($acao === 'salvar') {
            $nome = trim((string)($_POST['nome'] ?? ''));
            $descricao = trim((string)($_POST['descricao'] ?? ''));
            $beneficios = trim((string)($_POST['beneficios'] ?? ''));
            $valorTexto = str_replace(',', '.', trim((string)($_POST['valor'] ?? '')));
            $periodicidade = trim((string)($_POST['periodicidade'] ?? ''));
            if ($nome === '' || strlen($nome) > 120 || $descricao === '' || strlen($descricao) > 3000 || strlen($beneficios) > 3000) {
                throw new InvalidArgumentException('Informe nome e descrição, respeitando os limites dos campos.');
            }
            if (!is_numeric($valorTexto) || (float)$valorTexto < 0 || (float)$valorTexto > 99999999) {
                throw new InvalidArgumentException('Informe um valor válido. Use zero para um plano gratuito.');
            }
            if (!in_array($periodicidade, ['', 'único', 'mensal', 'anual'], true)) {
                throw new InvalidArgumentException('Selecione uma periodicidade válida.');
            }

            if ($idPlano > 0) {
                $stmt = $db->prepare(
                    'UPDATE plano_veterinario
                     SET nome = :nome, descricao = :descricao, beneficios = :beneficios,
                         valor = :valor, periodicidade = :periodicidade, ativo = :ativo
                     WHERE id_plano = :plano AND id_veterinario = :veterinario'
                );
                $stmt->execute([
                    'nome' => $nome, 'descricao' => $descricao, 'beneficios' => $beneficios ?: null,
                    'valor' => $valorTexto, 'periodicidade' => $periodicidade ?: null,
                    'ativo' => isset($_POST['ativo']) ? 1 : 0, 'plano' => $idPlano, 'veterinario' => $idVeterinario,
                ]);
                if ($stmt->rowCount() === 0) {
                    $stmt = $db->prepare('SELECT 1 FROM plano_veterinario WHERE id_plano = :plano AND id_veterinario = :veterinario');
                    $stmt->execute(['plano' => $idPlano, 'veterinario' => $idVeterinario]);
                    if ($stmt->fetchColumn() === false) {
                        throw new InvalidArgumentException('Esse plano não existe ou não pertence à sua conta.');
                    }
                }
                $sucesso = 'Plano atualizado.';
            } else {
                $stmt = $db->prepare(
                    'INSERT INTO plano_veterinario (id_veterinario, nome, descricao, beneficios, valor, periodicidade, ativo)
                     VALUES (:veterinario, :nome, :descricao, :beneficios, :valor, :periodicidade, :ativo)'
                );
                $stmt->execute([
                    'veterinario' => $idVeterinario, 'nome' => $nome, 'descricao' => $descricao,
                    'beneficios' => $beneficios ?: null, 'valor' => $valorTexto,
                    'periodicidade' => $periodicidade ?: null, 'ativo' => isset($_POST['ativo']) ? 1 : 0,
                ]);
                $sucesso = 'Plano cadastrado.';
            }
        } elseif ($acao === 'excluir') {
            if ($idPlano < 1) {
                throw new InvalidArgumentException('Plano inválido.');
            }
            $stmt = $db->prepare('DELETE FROM plano_veterinario WHERE id_plano = :plano AND id_veterinario = :veterinario');
            $stmt->execute(['plano' => $idPlano, 'veterinario' => $idVeterinario]);
            if ($stmt->rowCount() !== 1) {
                throw new InvalidArgumentException('Esse plano não existe ou não pertence à sua conta.');
            }
            $sucesso = 'Plano excluído.';
        } else {
            throw new InvalidArgumentException('Ação inválida.');
        }

        $_SESSION['flash_plano_veterinario'] = $sucesso;
        header('Location: gerenciar_planos_veterinario.php');
        exit;
    }

    if (isset($_GET['editar'])) {
        $stmt = $db->prepare('SELECT * FROM plano_veterinario WHERE id_plano = :plano AND id_veterinario = :veterinario');
        $stmt->execute(['plano' => (int)$_GET['editar'], 'veterinario' => $idVeterinario]);
        $editando = $stmt->fetch() ?: null;
    }
    $stmt = $db->prepare('SELECT * FROM plano_veterinario WHERE id_veterinario = :veterinario ORDER BY data_cadastro DESC, nome');
    $stmt->execute(['veterinario' => $idVeterinario]);
    $planos = $stmt->fetchAll();
} catch (Throwable $e) {
    $erro = $e instanceof InvalidArgumentException ? $e->getMessage() : mensagemErro($e);
}

cabecalho('Meus planos veterinários');
?>
<?php if ($erro): ?><div class="notice error"><?= h($erro) ?></div><?php endif; ?>
<?php if ($sucesso): ?><div class="notice success"><?= h($sucesso) ?></div><?php endif; ?>
<div class="notice">Cadastre os planos oferecidos por você ou pela sua clínica. Somente planos ativos aparecem na página pública de <a href="planos.php#planos-veterinarios">Planos veterinários</a>.</div>
<?php if ($db instanceof MysqliDatabase && $perfil): ?>
<section class="form-card">
    <h2><?= $editando ? 'Editar plano' : 'Cadastrar plano veterinário' ?></h2>
    <form method="post" class="form-grid">
        <input type="hidden" name="csrf" value="<?= h(csrfToken()) ?>">
        <input type="hidden" name="acao" value="salvar">
        <input type="hidden" name="id_plano" value="<?= (int)($editando['id_plano'] ?? 0) ?>">
        <label>Nome do plano *<input name="nome" required maxlength="120" value="<?= h($editando['nome'] ?? '') ?>"></label>
        <label>Valor (R$) *<input name="valor" type="number" min="0" step="0.01" required value="<?= h($editando['valor'] ?? '0.00') ?>"></label>
        <label>Periodicidade<select name="periodicidade"><option value="">Não se aplica</option><?php foreach (['único', 'mensal', 'anual'] as $periodo): ?><option value="<?= h($periodo) ?>" <?= ($editando['periodicidade'] ?? '') === $periodo ? 'selected' : '' ?>><?= h(ucfirst($periodo)) ?></option><?php endforeach; ?></select></label>
        <label class="full">Descrição *<textarea name="descricao" rows="3" maxlength="3000" required><?= h($editando['descricao'] ?? '') ?></textarea></label>
        <label class="full">O que está incluído (um item por linha)<textarea name="beneficios" rows="4" maxlength="3000"><?= h($editando['beneficios'] ?? '') ?></textarea></label>
        <div class="checks-field"><label><input type="checkbox" name="ativo" value="1" <?= (!$editando || !empty($editando['ativo'])) ? 'checked' : '' ?>> Publicar este plano</label></div>
        <div class="filter-actions campo-largo">
            <button class="btn btn-primary" type="submit"><?= $editando ? 'Salvar alterações' : 'Cadastrar plano' ?></button>
            <?php if ($editando): ?><a class="btn btn-outline" href="gerenciar_planos_veterinario.php">Cancelar edição</a><?php endif; ?>
            <a class="btn btn-outline" href="painel_veterinario.php">Voltar ao painel</a>
        </div>
    </form>
</section>
<h2 class="section-heading">Planos cadastrados</h2>
<?php if (!$planos): ?><div class="empty-state">Você ainda não cadastrou planos.</div><?php else: ?>
<div class="table-wrap"><table class="data-table">
    <thead><tr><th>Plano</th><th>Valor</th><th>Status</th><th>Ações</th></tr></thead>
    <tbody><?php foreach ($planos as $plano): ?><tr>
        <td><?= h($plano['nome']) ?></td>
        <td><?= (float)$plano['valor'] <= 0 ? 'Gratuito' : 'R$ ' . number_format((float)$plano['valor'], 2, ',', '.') ?><?= !empty($plano['periodicidade']) ? ' / ' . h($plano['periodicidade']) : '' ?></td>
        <td><?= !empty($plano['ativo']) ? 'Publicado' : 'Oculto' ?></td>
        <td><div class="inline-form"><a class="btn btn-outline" href="?editar=<?= (int)$plano['id_plano'] ?>">Editar</a>
            <form method="post" onsubmit="return confirm('Excluir este plano?')">
                <input type="hidden" name="csrf" value="<?= h(csrfToken()) ?>">
                <input type="hidden" name="acao" value="excluir">
                <input type="hidden" name="id_plano" value="<?= (int)$plano['id_plano'] ?>">
                <button class="btn btn-outline" type="submit">Excluir</button>
            </form></div></td>
    </tr><?php endforeach; ?></tbody>
</table></div>
<?php endif; ?>
<?php endif; ?>
<?php rodape(); ?>
