<?php
require_once __DIR__ . '/_bootstrap.php';
exigirTipo(['admin', 'administrador']);
validarCsrf();
$db = null;
$erro = '';
$sucesso = (string)($_SESSION['flash_planos'] ?? '');
unset($_SESSION['flash_planos']);
$editando = null;

try {
    $db = db();
} catch (Throwable $e) {
    $erro = mensagemErro($e);
}

if ($db instanceof MysqliDatabase && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = (string)($_POST['acao'] ?? '');
    try {
        if ($acao === 'salvar') {
            $id = (int)($_POST['id_plano'] ?? 0);
            $nome = trim((string)($_POST['nome'] ?? ''));
            $descricao = trim((string)($_POST['descricao'] ?? ''));
            $publico = trim((string)($_POST['publico_alvo'] ?? ''));
            $beneficios = trim((string)($_POST['beneficios'] ?? ''));
            $valorTexto = str_replace(',', '.', trim((string)($_POST['valor'] ?? '0')));
            $periodicidade = trim((string)($_POST['periodicidade'] ?? ''));
            $ativo = isset($_POST['ativo']) ? 1 : 0;
            $destaque = isset($_POST['destaque']) ? 1 : 0;
            $ordem = max(0, (int)($_POST['ordem_exibicao'] ?? 0));
            if ($nome === '' || $descricao === '' || !is_numeric($valorTexto) || (float)$valorTexto < 0) {
                throw new InvalidArgumentException('Informe nome, descrição e um valor válido (zero para gratuito).');
            }
            if ($id > 0) {
                $stmt = $db->prepare('UPDATE plano SET nome=?, descricao=?, publico_alvo=?, beneficios=?, valor=?, periodicidade=?, ativo=?, destaque=?, ordem_exibicao=? WHERE id_plano=?');
                $stmt->execute([$nome, $descricao, $publico ?: null, $beneficios ?: null, $valorTexto, $periodicidade ?: null, $ativo, $destaque, $ordem, $id]);
                $sucesso = 'Plano atualizado.';
            } else {
                $stmt = $db->prepare('INSERT INTO plano (nome, descricao, publico_alvo, beneficios, valor, periodicidade, ativo, destaque, ordem_exibicao) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
                $stmt->execute([$nome, $descricao, $publico ?: null, $beneficios ?: null, $valorTexto, $periodicidade ?: null, $ativo, $destaque, $ordem]);
                $sucesso = 'Plano cadastrado.';
            }
        } elseif ($acao === 'excluir') {
            $id = (int)($_POST['id_plano'] ?? 0);
            if ($id <= 0) throw new InvalidArgumentException('Plano inválido.');
            $stmt = $db->prepare('DELETE FROM plano WHERE id_plano = ?');
            $stmt->execute([$id]);
            $sucesso = 'Plano excluído.';
        }
    } catch (Throwable $e) {
        $erro = $e instanceof InvalidArgumentException ? $e->getMessage() : mensagemErro($e);
    }
    if ($sucesso !== '' && $erro === '') {
        $_SESSION['flash_planos'] = $sucesso;
        header('Location: gerenciar_planos.php');
        exit;
    }
}

$planos = [];
if ($db instanceof MysqliDatabase) {
    try {
        if (isset($_GET['editar'])) {
            $stmt = $db->prepare('SELECT * FROM plano WHERE id_plano = ?');
            $stmt->execute([(int)$_GET['editar']]);
            $editando = $stmt->fetch(MYSQLI_ASSOC) ?: null;
        }
        $planos = $db->query('SELECT * FROM plano ORDER BY ordem_exibicao ASC, nome ASC')->fetchAll(MYSQLI_ASSOC);
    } catch (Throwable $e) {
        $erro = mensagemErro($e);
    }
}
cabecalho('Gerenciar planos');
?>
<div class="notice"><strong>Gerenciamento:</strong> os planos cadastrados aqui são os mesmos exibidos automaticamente em <a href="planos.php">Planos</a>. Somente planos ativos aparecem para o público.</div>
<?php if ($erro): ?><div class="notice error"><?= h($erro) ?></div><?php endif; ?>
<?php if ($sucesso): ?><div class="notice success"><?= h($sucesso) ?></div><?php endif; ?>
<?php if ($db instanceof MysqliDatabase): ?>
<section class="form-card">
    <h2><?= $editando ? 'Editar plano' : 'Cadastrar novo plano' ?></h2>
    <form method="post" class="form-grid">
        <input type="hidden" name="csrf" value="<?= h(csrfToken()) ?>">
        <input type="hidden" name="acao" value="salvar">
        <input type="hidden" name="id_plano" value="<?= h($editando['id_plano'] ?? 0) ?>">
        <div class="filter-field"><label for="nome">Nome do plano *</label><input id="nome" name="nome" required maxlength="120" value="<?= h($editando['nome'] ?? '') ?>"></div>
        <div class="filter-field"><label for="publico_alvo">Público-alvo</label><input id="publico_alvo" name="publico_alvo" maxlength="120" placeholder="Ex.: Protetores independentes" value="<?= h($editando['publico_alvo'] ?? '') ?>"></div>
        <div class="filter-field campo-largo"><label for="descricao">Descrição *</label><textarea id="descricao" name="descricao" rows="3" required><?= h($editando['descricao'] ?? '') ?></textarea></div>
        <div class="filter-field campo-largo"><label for="beneficios">Benefícios (um por linha)</label><textarea id="beneficios" name="beneficios" rows="4"><?= h($editando['beneficios'] ?? '') ?></textarea></div>
        <div class="filter-field"><label for="valor">Valor (R$) *</label><input id="valor" name="valor" type="number" min="0" step="0.01" required value="<?= h($editando['valor'] ?? '0.00') ?>"></div>
        <div class="filter-field"><label for="periodicidade">Periodicidade</label><select id="periodicidade" name="periodicidade"><option value="">Não se aplica</option><?php foreach (['único','mensal','anual'] as $p): ?><option value="<?= h($p) ?>" <?= (($editando['periodicidade'] ?? '') === $p) ? 'selected' : '' ?>><?= h(ucfirst($p)) ?></option><?php endforeach; ?></select></div>
        <div class="filter-field"><label for="ordem_exibicao">Ordem de exibição</label><input id="ordem_exibicao" name="ordem_exibicao" type="number" min="0" value="<?= h($editando['ordem_exibicao'] ?? 0) ?>"></div>
        <div class="filter-field checks-field"><label><input type="checkbox" name="ativo" value="1" <?= (!$editando || !empty($editando['ativo'])) ? 'checked' : '' ?>> Ativo (exibir no site)</label><label><input type="checkbox" name="destaque" value="1" <?= !empty($editando['destaque']) ? 'checked' : '' ?>> Destacar</label></div>
        <div class="filter-actions campo-largo"><button class="btn btn-primary" type="submit"><?= $editando ? 'Salvar alterações' : 'Cadastrar plano' ?></button><?php if ($editando): ?><a class="btn btn-outline" href="gerenciar_planos.php">Cancelar edição</a><?php endif; ?></div>
    </form>
</section>
<h2>Planos cadastrados</h2>
<?php if (!$planos): ?><div class="empty-state">Ainda não há planos cadastrados.</div><?php else: ?>
<div class="table-wrap"><table class="data-table"><thead><tr><th>Nome</th><th>Valor</th><th>Status</th><th>Destaque</th><th>Ações</th></tr></thead><tbody>
<?php foreach ($planos as $p): ?><tr><td><?= h($p['nome']) ?></td><td><?= (float)$p['valor'] <= 0 ? 'Gratuito' : 'R$ '.number_format((float)$p['valor'], 2, ',', '.') ?></td><td><?= !empty($p['ativo']) ? 'Ativo' : 'Inativo' ?></td><td><?= !empty($p['destaque']) ? 'Sim' : 'Não' ?></td><td><div class="inline-form"><a class="btn btn-outline" href="?editar=<?= (int)$p['id_plano'] ?>">Editar</a><form method="post" onsubmit="return confirm('Excluir este plano?')"><input type="hidden" name="csrf" value="<?= h(csrfToken()) ?>"><input type="hidden" name="acao" value="excluir"><input type="hidden" name="id_plano" value="<?= (int)$p['id_plano'] ?>"><button class="btn btn-outline" type="submit">Excluir</button></form></div></td></tr><?php endforeach; ?>
</tbody></table></div><?php endif; ?>
<?php endif; ?>
<?php rodape(); ?>
