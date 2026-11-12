<?php
declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';

exigirTipo(['protetor', 'admin', 'administrador']);
validarCsrf();

$db = null;
$idRecebido = $_SERVER['REQUEST_METHOD'] === 'POST' ? ($_POST['id_animal'] ?? null) : ($_GET['id'] ?? null);
$editando = $idRecebido !== null && ctype_digit((string)$idRecebido);
$id = $editando ? (int)$idRecebido : 0;
$isAdmin = in_array(tipoUsuario(), ['admin', 'administrador'], true);
$animal = [];
$animalOriginal = [];
$aviso = '';
$protetorId = 0;

try {
    $db = db();
    if (!$isAdmin) {
        $stmt = $db->prepare("SELECT id_protetor FROM protetor WHERE id_usuario = :id AND status_aprovacao = 'Aprovado' LIMIT 1");
        $stmt->execute(['id' => usuarioId()]);
        $protetorId = (int)$stmt->fetchColumn();
        if ($protetorId <= 0) {
            http_response_code(403);
            exit('Seu cadastro de protetor ainda não foi aprovado.');
        }
    }

    if ($editando) {
        $stmt = $db->prepare('SELECT * FROM animal WHERE id_animal = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $animal = $stmt->fetch() ?: [];
        $animalOriginal = $animal;
        if (!$animal || (!$isAdmin && (int)$animal['id_protetor'] !== $protetorId)) {
            http_response_code(404);
            exit('Animal não encontrado ou sem permissão para editá-lo.');
        }
    } elseif ($isAdmin) {
        http_response_code(403);
        exit('Somente um protetor aprovado pode cadastrar animais.');
    }

    $categorias = $db->query('SELECT id_categoria, nome_categoria FROM categoria ORDER BY nome_categoria')->fetchAll();
} catch (Throwable $e) {
    $aviso = mensagemErro($e);
    $categorias = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$aviso) {
    $dados = [
        'nome' => trim((string)($_POST['nome_animal'] ?? '')),
        'especie' => trim((string)($_POST['especie'] ?? '')),
        'sexo' => trim((string)($_POST['sexo'] ?? '')),
        'porte' => trim((string)($_POST['porte'] ?? '')),
        'raca' => trim((string)($_POST['raca'] ?? '')),
        'cor' => trim((string)($_POST['cor'] ?? '')),
        'idade' => ($_POST['idade_meses'] ?? '') !== '' ? filter_var($_POST['idade_meses'], FILTER_VALIDATE_INT) : null,
        'descricao' => trim((string)($_POST['descricao'] ?? '')),
        'condicao_saude' => trim((string)($_POST['condicao_saude'] ?? '')),
        'cidade' => trim((string)($_POST['cidade'] ?? '')),
        'uf' => strtoupper(trim((string)($_POST['uf'] ?? ''))),
        'categoria' => filter_var($_POST['id_categoria'] ?? '', FILTER_VALIDATE_INT),
        'status' => trim((string)($_POST['status_adocao'] ?? 'Disponível')),
        'nivel_energia' => trim((string)($_POST['nivel_energia'] ?? 'Moderado')),
        'compatibilidade_criancas' => trim((string)($_POST['compatibilidade_criancas'] ?? 'Não informado')),
        'compatibilidade_animais' => trim((string)($_POST['compatibilidade_animais'] ?? 'Não informado')),
        'castrado' => isset($_POST['castrado']) ? 1 : 0,
        'vacinado' => isset($_POST['vacinado']) ? 1 : 0,
        'vermifugado' => isset($_POST['vermifugado']) ? 1 : 0,
    ];

    foreach ($dados as $campo => $valor) {
        if (is_string($valor)) {
            $animal[$campo] = $valor;
        }
    }
    $animal['nome_animal'] = $dados['nome'];
    $animal['especie'] = $dados['especie'];
    $animal['sexo'] = $dados['sexo'];
    $animal['porte'] = $dados['porte'];
    $animal['raca'] = $dados['raca'];
    $animal['cor'] = $dados['cor'];
    $animal['idade_meses'] = $dados['idade'];
    $animal['descricao'] = $dados['descricao'];
    $animal['condicao_saude'] = $dados['condicao_saude'];
    $animal['cidade'] = $dados['cidade'];
    $animal['uf'] = $dados['uf'];
    $animal['id_categoria'] = $dados['categoria'];
    $animal['status_adocao'] = $dados['status'];
    $animal['nivel_energia'] = $dados['nivel_energia'];
    $animal['compatibilidade_criancas'] = $dados['compatibilidade_criancas'];
    $animal['compatibilidade_animais'] = $dados['compatibilidade_animais'];
    $animal['castrado'] = $dados['castrado'];
    $animal['vacinado'] = $dados['vacinado'];
    $animal['vermifugado'] = $dados['vermifugado'];

    if ($dados['nome'] === '' || strlen($dados['nome']) > 50) {
        $aviso = 'Informe um nome com até 50 caracteres.';
    } elseif ($dados['especie'] === '' || strlen($dados['especie']) > 30) {
        $aviso = 'Informe uma espécie com até 30 caracteres.';
    } elseif (!in_array($dados['sexo'], ['Macho', 'Fêmea', 'Não informado'], true)) {
        $aviso = 'Selecione um sexo válido.';
    } elseif (!in_array($dados['porte'], ['', 'Pequeno', 'Médio', 'Grande'], true)) {
        $aviso = 'Selecione um porte válido.';
    } elseif (strlen($dados['raca']) > 80 || strlen($dados['cor']) > 80) {
        $aviso = 'Raça e cor devem ter até 80 caracteres.';
    } elseif ($dados['idade'] !== null && ($dados['idade'] === false || $dados['idade'] < 0 || $dados['idade'] > 65535)) {
        $aviso = 'Informe uma idade válida em meses.';
    } elseif ($dados['descricao'] === '' || strlen($dados['descricao']) > 1000) {
        $aviso = 'Informe uma descrição com até 1.000 caracteres.';
    } elseif (strlen($dados['condicao_saude']) > 500) {
        $aviso = 'As observações de saúde devem ter até 500 caracteres.';
    } elseif (!$dados['categoria'] || (int)$dados['categoria'] <= 0) {
        $aviso = 'Selecione uma categoria.';
    } elseif ($dados['cidade'] !== '' && strlen($dados['cidade']) > 80) {
        $aviso = 'A cidade deve ter até 80 caracteres.';
    } elseif ($dados['uf'] !== '' && !preg_match('/^[A-Z]{2}$/', $dados['uf'])) {
        $aviso = 'Informe uma UF válida com duas letras.';
    } elseif (!in_array($dados['status'], ['Disponível', 'Em processo', 'Adotado'], true)) {
        $aviso = 'Selecione um status válido.';
    } elseif (!in_array($dados['nivel_energia'], ['Baixo', 'Moderado', 'Alto'], true)) {
        $aviso = 'Selecione um nível de energia válido.';
    } elseif (!in_array($dados['compatibilidade_criancas'], ['Sim', 'Não', 'Não informado'], true)
        || !in_array($dados['compatibilidade_animais'], ['Sim', 'Não', 'Não informado'], true)) {
        $aviso = 'Selecione opções válidas de convivência.';
    } else {
        try {
            $foto = (string)($animal['foto_url'] ?? '');
            $arquivoFoto = $_FILES['foto'] ?? null;
            if ($arquivoFoto && $arquivoFoto['error'] !== UPLOAD_ERR_NO_FILE) {
                if ($arquivoFoto['error'] !== UPLOAD_ERR_OK || $arquivoFoto['size'] > 5 * 1024 * 1024) {
                    throw new InvalidArgumentException('A foto deve ter até 5 MB e ser enviada corretamente.');
                }
                $info = new finfo(FILEINFO_MIME_TYPE);
                $mime = $info->file($arquivoFoto['tmp_name']);
                $extensoes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
                if (!isset($extensoes[$mime]) || !getimagesize($arquivoFoto['tmp_name'])) {
                    throw new InvalidArgumentException('Envie uma imagem JPG, PNG ou WEBP válida.');
                }

                $diretorio = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'fotos' . DIRECTORY_SEPARATOR . 'animais';
                if (!is_dir($diretorio) && !mkdir($diretorio, 0755, true) && !is_dir($diretorio)) {
                    throw new InvalidArgumentException('Não foi possível preparar a pasta de fotos.');
                }
                $nomeArquivo = bin2hex(random_bytes(16)) . '.' . $extensoes[$mime];
                if (!move_uploaded_file($arquivoFoto['tmp_name'], $diretorio . DIRECTORY_SEPARATOR . $nomeArquivo)) {
                    throw new InvalidArgumentException('Não foi possível salvar a foto enviada.');
                }
                $foto = 'fotos/animais/' . $nomeArquivo;
            }

            $valores = [
                'nome' => $dados['nome'],
                'especie' => $dados['especie'],
                'sexo' => $dados['sexo'],
                'porte' => $dados['porte'] !== '' ? $dados['porte'] : null,
                'raca' => $dados['raca'] !== '' ? $dados['raca'] : null,
                'cor' => $dados['cor'] !== '' ? $dados['cor'] : null,
                'idade' => $dados['idade'],
                'descricao' => $dados['descricao'],
                'condicao_saude' => $dados['condicao_saude'] !== '' ? $dados['condicao_saude'] : null,
                'castrado' => $dados['castrado'],
                'vacinado' => $dados['vacinado'],
                'vermifugado' => $dados['vermifugado'],
                'compatibilidade_criancas' => $dados['compatibilidade_criancas'],
                'compatibilidade_animais' => $dados['compatibilidade_animais'],
                'nivel_energia' => $dados['nivel_energia'],
                'cidade' => $dados['cidade'] !== '' ? $dados['cidade'] : null,
                'uf' => $dados['uf'] !== '' ? $dados['uf'] : null,
                'categoria' => (int)$dados['categoria'],
                'status' => $dados['status'],
                'foto' => $foto !== '' ? $foto : null,
            ];

            $revisarEdicao = false;
            if ($editando) {
                if (!$isAdmin) {
                    $camposRevisao = [
                        'nome' => 'nome_animal',
                        'especie' => 'especie',
                        'sexo' => 'sexo',
                        'porte' => 'porte',
                        'raca' => 'raca',
                        'cor' => 'cor',
                        'idade' => 'idade_meses',
                        'descricao' => 'descricao',
                        'condicao_saude' => 'condicao_saude',
                        'castrado' => 'castrado',
                        'vacinado' => 'vacinado',
                        'vermifugado' => 'vermifugado',
                        'compatibilidade_criancas' => 'compatibilidade_criancas',
                        'compatibilidade_animais' => 'compatibilidade_animais',
                        'nivel_energia' => 'nivel_energia',
                        'cidade' => 'cidade',
                        'uf' => 'uf',
                        'categoria' => 'id_categoria',
                        'foto' => 'foto_url',
                    ];
                    foreach ($camposRevisao as $campo => $coluna) {
                        if ((string)($valores[$campo] ?? '') !== (string)($animalOriginal[$coluna] ?? '')) {
                            $revisarEdicao = true;
                            break;
                        }
                    }
                }
                $sql = 'UPDATE animal SET nome_animal=:nome, especie=:especie, sexo=:sexo, porte=:porte, raca=:raca, cor=:cor, idade_meses=:idade, descricao=:descricao, condicao_saude=:condicao_saude, castrado=:castrado, vacinado=:vacinado, vermifugado=:vermifugado, compatibilidade_criancas=:compatibilidade_criancas, compatibilidade_animais=:compatibilidade_animais, nivel_energia=:nivel_energia, cidade=:cidade, uf=:uf, id_categoria=:categoria, status_adocao=:status, foto_url=:foto'
                    . ($revisarEdicao ? ", status_aprovacao='Pendente', observacao_admin=NULL" : '')
                    . ' WHERE id_animal=:id';
                $valores['id'] = $id;
            } else {
                $sql = "INSERT INTO animal (nome_animal, especie, sexo, porte, raca, cor, idade_meses, descricao, condicao_saude, castrado, vacinado, vermifugado, compatibilidade_criancas, compatibilidade_animais, nivel_energia, cidade, uf, id_categoria, status_adocao, foto_url, id_protetor, data_cadastro, status_aprovacao) VALUES (:nome, :especie, :sexo, :porte, :raca, :cor, :idade, :descricao, :condicao_saude, :castrado, :vacinado, :vermifugado, :compatibilidade_criancas, :compatibilidade_animais, :nivel_energia, :cidade, :uf, :categoria, :status, :foto, :protetor, CURRENT_DATE, 'Pendente')";
                $valores['protetor'] = $protetorId;
            }
            $stmt = $db->prepare($sql);
            $stmt->execute($valores);
            if (!$isAdmin) {
                $_SESSION['flash_animal'] = $editando && $revisarEdicao
                    ? 'Alterações enviadas para nova aprovação. O perfil ficará oculto até a revisão.'
                    : ($editando ? 'Dados do animal atualizados.' : 'Animal cadastrado e enviado para aprovação. Ele aparecerá no site após a revisão.');
            }
            header('Location: ' . ($isAdmin ? 'painel_admin.php' : 'painel_protetor.php'));
            exit;
        } catch (Throwable $e) {
            $aviso = $e instanceof InvalidArgumentException ? $e->getMessage() : mensagemErro($e);
        }
    }
}

cabecalho($editando ? 'Editar animal' : 'Cadastrar animal');
?>
<?php if ($aviso): ?><div class="notice error"><?= h($aviso) ?></div><?php endif; ?>
<?php if ($editando && !empty($animal['status_aprovacao']) && $animal['status_aprovacao'] !== 'Aprovado'): ?>
    <div class="notice">Este animal não está publicado. Situação da revisão: <strong><?= h($animal['status_aprovacao']) ?></strong>.</div>
<?php endif; ?>
<form class="form-card" method="post" enctype="multipart/form-data">
    <input type="hidden" name="csrf" value="<?= h(csrfToken()) ?>">
    <input type="hidden" name="id_animal" value="<?= $editando ? $id : '' ?>">
    <div class="form-grid">
        <label>Nome
            <input name="nome_animal" maxlength="50" required value="<?= h($animal['nome_animal'] ?? '') ?>">
        </label>
        <label>Espécie
            <input name="especie" maxlength="30" required placeholder="Cachorro, gato..." value="<?= h($animal['especie'] ?? '') ?>">
        </label>
        <label>Sexo
            <select name="sexo" required>
                <?php foreach (['Macho', 'Fêmea', 'Não informado'] as $opcao): ?>
                    <option value="<?= h($opcao) ?>" <?= ($animal['sexo'] ?? 'Não informado') === $opcao ? 'selected' : '' ?>><?= h($opcao) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Porte
            <select name="porte">
                <option value="">Não informado</option>
                <?php foreach (['Pequeno', 'Médio', 'Grande'] as $opcao): ?>
                    <option value="<?= h($opcao) ?>" <?= ($animal['porte'] ?? '') === $opcao ? 'selected' : '' ?>><?= h($opcao) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Idade em meses
            <input type="number" min="0" max="65535" name="idade_meses" value="<?= h($animal['idade_meses'] ?? '') ?>">
        </label>
        <label>Raça
            <input name="raca" maxlength="80" value="<?= h($animal['raca'] ?? '') ?>">
        </label>
        <label>Cor
            <input name="cor" maxlength="80" value="<?= h($animal['cor'] ?? '') ?>">
        </label>
        <label>Categoria
            <select name="id_categoria" required>
                <option value="">Selecione</option>
                <?php foreach ($categorias as $categoria): ?>
                    <option value="<?= (int)$categoria['id_categoria'] ?>" <?= (int)($animal['id_categoria'] ?? 0) === (int)$categoria['id_categoria'] ? 'selected' : '' ?>><?= h($categoria['nome_categoria']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Cidade
            <input name="cidade" maxlength="80" value="<?= h($animal['cidade'] ?? '') ?>">
        </label>
        <label>UF
            <input name="uf" maxlength="2" value="<?= h($animal['uf'] ?? 'PR') ?>">
        </label>
        <label>Status
            <select name="status_adocao">
                <?php foreach (['Disponível', 'Em processo', 'Adotado'] as $opcao): ?>
                    <option value="<?= h($opcao) ?>" <?= ($animal['status_adocao'] ?? 'Disponível') === $opcao ? 'selected' : '' ?>><?= h($opcao) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Nível de energia
            <select name="nivel_energia">
                <?php foreach (['Baixo', 'Moderado', 'Alto'] as $opcao): ?>
                    <option value="<?= h($opcao) ?>" <?= ($animal['nivel_energia'] ?? 'Moderado') === $opcao ? 'selected' : '' ?>><?= h($opcao) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Convívio com crianças
            <select name="compatibilidade_criancas">
                <?php foreach (['Não informado', 'Sim', 'Não'] as $opcao): ?>
                    <option value="<?= h($opcao) ?>" <?= ($animal['compatibilidade_criancas'] ?? 'Não informado') === $opcao ? 'selected' : '' ?>><?= h($opcao) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Convívio com outros animais
            <select name="compatibilidade_animais">
                <?php foreach (['Não informado', 'Sim', 'Não'] as $opcao): ?>
                    <option value="<?= h($opcao) ?>" <?= ($animal['compatibilidade_animais'] ?? 'Não informado') === $opcao ? 'selected' : '' ?>><?= h($opcao) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <div class="checks-field">
            <label><input type="checkbox" name="castrado" value="1" <?= !empty($animal['castrado']) ? 'checked' : '' ?>> Castrado</label>
            <label><input type="checkbox" name="vacinado" value="1" <?= !empty($animal['vacinado']) ? 'checked' : '' ?>> Vacinado</label>
            <label><input type="checkbox" name="vermifugado" value="1" <?= !empty($animal['vermifugado']) ? 'checked' : '' ?>> Vermifugado</label>
        </div>
        <label>Foto (JPG, PNG ou WEBP; até 5 MB)
            <input type="file" name="foto" accept="image/jpeg,image/png,image/webp">
        </label>
        <label class="full">Descrição
            <textarea name="descricao" rows="5" maxlength="1000" required><?= h($animal['descricao'] ?? '') ?></textarea>
        </label>
        <label class="full">Condição de saúde ou observações
            <textarea name="condicao_saude" rows="3" maxlength="500"><?= h($animal['condicao_saude'] ?? '') ?></textarea>
        </label>
    </div>
    <p class="form-help">A foto atual será mantida se nenhum novo arquivo for selecionado.</p>
    <button class="btn btn-primary" type="submit">Salvar animal</button>
</form>
<?php rodape(); ?>
