// =====================================================
// Painel do protetor:
//  - confere se quem está logado é protetor;
//  - troca entre as abas (Cadastrar animal / Meus animais);
//  - formulário de cadastro de animal: contadores de caracteres,
//    idade em anos + meses -> idade_meses, validação e envio.
// (As validações aqui são só conveniência: o PHP precisa validar de novo.)
// =====================================================
(function () {
    const base = new URL('../', document.currentScript.src);   // raiz do site (pasta adotai/)

    const URL_SESSAO   = new URL('api/sessao.php', base).href;
    const URL_CADASTRO = new URL('api/animal_cadastrar.php', base).href;   // ainda a criar (back-end)
    const URL_LOGIN    = new URL('pages/login.html', base).href;
    const URL_INICIO   = new URL('index.html', base).href;

    // true  = quem não for protetor logado é redirecionado.
    // Ponha false se quiser abrir a página para testar o visual sem login.
    const EXIGIR_PROTETOR = true;

    // ---------- Utilitários ----------
    function criar(tag, classe, texto) {
        const el = document.createElement(tag);
        if (classe) el.className = classe;
        if (texto !== undefined) el.textContent = texto;   // textContent: nunca interpreta HTML
        return el;
    }

    const $ = (id) => document.getElementById(id);

    // ==================================================
    // 1. SESSÃO: só protetor entra aqui
    // ==================================================
    fetch(URL_SESSAO, { credentials: 'same-origin', cache: 'no-store' })
        .then(r => (r.ok ? r.json() : null))
        .then(dados => {
            if (!EXIGIR_PROTETOR || !dados) return;   // sem resposta: não bloqueia a tela

            if (!dados.logado)              { location.replace(URL_LOGIN);  return; }
            if (dados.tipo !== 'protetor')  { location.replace(URL_INICIO); return; }

            if (dados.nome) $('painel-nome').textContent = dados.nome;
        })
        .catch(() => { /* sem resposta: mantém a página como está */ });

    // ==================================================
    // 2. ABAS
    // Para criar uma aba nova: botão .painel-aba (id "aba-xxx") + section
    // .painel-card (id "painel-xxx") no HTML e uma linha em ABAS aqui.
    // ==================================================
    const ABAS = {
        cadastro: { aba: $('aba-cadastro'), painel: $('painel-cadastro') },
        animais:  { aba: $('aba-animais'),  painel: $('painel-animais')  },
    };
    const ORDEM_ABAS = Object.keys(ABAS);

    function selecionarAba(nome, focar) {
        if (!ABAS[nome]) nome = 'cadastro';

        ORDEM_ABAS.forEach(chave => {
            const ativa = chave === nome;
            ABAS[chave].aba.setAttribute('aria-selected', String(ativa));
            ABAS[chave].aba.tabIndex = ativa ? 0 : -1;
            ABAS[chave].painel.hidden = !ativa;
        });

        if (focar) ABAS[nome].aba.focus();
        history.replaceState(null, '', '#' + nome);
    }

    ORDEM_ABAS.forEach((chave, i) => {
        const { aba } = ABAS[chave];

        aba.addEventListener('click', () => selecionarAba(chave));

        // setas do teclado navegam entre as abas
        aba.addEventListener('keydown', (e) => {
            const teclas = { ArrowDown: 1, ArrowRight: 1, ArrowUp: -1, ArrowLeft: -1 };
            if (e.key in teclas) {
                e.preventDefault();
                const prox = (i + teclas[e.key] + ORDEM_ABAS.length) % ORDEM_ABAS.length;
                selecionarAba(ORDEM_ABAS[prox], true);
            }
        });
    });

    // botões com data-ir-aba="cadastro" levam para a aba indicada
    document.querySelectorAll('[data-ir-aba]').forEach(btn => {
        btn.addEventListener('click', () => selecionarAba(btn.dataset.irAba, true));
    });

    selecionarAba(location.hash.replace('#', ''));

    // ==================================================
    // 3. FORMULÁRIO DE CADASTRO DE ANIMAL
    // ==================================================
    const form      = $('animal-form');
    const btnSalvar = $('btn-salvar');
    const avisoOk   = $('aviso-sucesso');
    const avisoErro = $('aviso-erro');

    const selCategoria = $('id_categoria');
    const inpEspecie   = $('especie');
    const inpAnos      = $('idade_anos');
    const inpMeses     = $('idade_meses_extra');
    const inpIdade     = $('idade_meses');     // hidden: vai para o banco

    const inpFoto      = $('foto');
    const fotoPrevia   = $('foto-previa');
    const fotoImg      = $('foto-img');
    const fotoVazio    = $('foto-vazio');
    const btnFotoRem   = $('foto-remover');

    // Regras da foto (o PHP também precisa conferir)
    const FOTO_TIPOS  = ['image/jpeg', 'image/png', 'image/webp'];
    const FOTO_MAX_MB = 5;

    // ---------- Contadores de caracteres ----------
    document.querySelectorAll('[data-contador-de]').forEach(contador => {
        const campo = $(contador.dataset.contadorDe);
        const max = campo.maxLength;

        const atualizar = () => {
            const total = campo.value.length;
            contador.textContent = `${total} / ${max}`;
            contador.classList.toggle('no-limite', total >= max);
        };

        campo.addEventListener('input', atualizar);
        form.addEventListener('reset', () => setTimeout(atualizar));
        atualizar();
    });

    // ---------- Categoria sugere a espécie ----------
    // Só preenche sozinho enquanto o protetor não tiver digitado a espécie.
    let especieDigitada = false;

    inpEspecie.addEventListener('input', () => {
        especieDigitada = inpEspecie.value.trim() !== '';
    });

    selCategoria.addEventListener('change', () => {
        if (especieDigitada) return;
        const opcao = selCategoria.selectedOptions[0];
        inpEspecie.value = opcao ? (opcao.dataset.especie || '') : '';
        limparErro('especie');
    });

    // ---------- Idade: anos + meses -> idade_meses ----------
    // Retorna: '' (não informada), um número (meses) ou null (valor inválido)
    function calcularIdadeMeses() {
        const anosTxt  = inpAnos.value.trim();
        const mesesTxt = inpMeses.value.trim();
        if (anosTxt === '' && mesesTxt === '') return '';

        const anos  = anosTxt  === '' ? 0 : Number(anosTxt);
        const meses = mesesTxt === '' ? 0 : Number(mesesTxt);

        const valido = Number.isInteger(anos)  && anos  >= 0 && anos  <= 50
                    && Number.isInteger(meses) && meses >= 0 && meses <= 11;
        return valido ? anos * 12 + meses : null;
    }

    [inpAnos, inpMeses].forEach(campo => campo.addEventListener('input', () => {
        const idade = calcularIdadeMeses();
        inpIdade.value = idade === null ? '' : idade;
        limparErro('idade');
    }));

    // ---------- Mensagens de erro por campo ----------
    function campoDe(nome) {
        return form.querySelector(`[data-campo="${nome}"]`);
    }

    function mostrarErro(nome, mensagem) {
        const bloco = campoDe(nome);
        const msg = $('erro-' + nome);
        if (!bloco || !msg) return;
        bloco.classList.add('tem-erro');
        bloco.querySelectorAll('input:not([type=hidden]), select, textarea')
             .forEach(el => el.setAttribute('aria-invalid', 'true'));
        msg.textContent = mensagem;
        msg.hidden = false;
    }

    function limparErro(nome) {
        const bloco = campoDe(nome);
        const msg = $('erro-' + nome);
        if (!bloco || !msg) return;
        bloco.classList.remove('tem-erro');
        bloco.querySelectorAll('[aria-invalid]').forEach(el => el.removeAttribute('aria-invalid'));
        msg.hidden = true;
    }

    // ---------- Foto: validação e prévia ----------
    function erroDaFoto(arquivo) {
        if (!arquivo) return '';
        if (!FOTO_TIPOS.includes(arquivo.type)) return 'Formato não aceito. Use JPG, PNG ou WebP.';
        if (arquivo.size > FOTO_MAX_MB * 1024 * 1024) return `A foto é muito grande. O limite é ${FOTO_MAX_MB} MB.`;
        return '';
    }

    function limparPrevia() {
        if (fotoImg.src.startsWith('blob:')) URL.revokeObjectURL(fotoImg.src);
        fotoImg.removeAttribute('src');
        fotoImg.hidden = true;
        fotoVazio.hidden = false;
        fotoPrevia.classList.remove('tem-foto');
        btnFotoRem.hidden = true;
    }

    inpFoto.addEventListener('change', () => {
        const arquivo = inpFoto.files[0];
        limparErro('foto');
        limparPrevia();
        if (!arquivo) return;

        const mensagem = erroDaFoto(arquivo);
        if (mensagem) {
            inpFoto.value = '';   // descarta o arquivo inválido
            mostrarErro('foto', mensagem);
            return;
        }

        fotoImg.src = URL.createObjectURL(arquivo);
        fotoImg.hidden = false;
        fotoVazio.hidden = true;
        fotoPrevia.classList.add('tem-foto');
        btnFotoRem.hidden = false;
    });

    btnFotoRem.addEventListener('click', () => {
        inpFoto.value = '';
        limparPrevia();
        limparErro('foto');
        inpFoto.focus();
    });

    // ---------- Validação ----------
    // Cada regra: [nome do campo, função que devolve a mensagem de erro ou '']
    const REGRAS = [
        ['nome_animal', () => {
            const v = $('nome_animal').value.trim();
            if (!v) return 'Informe o nome do animal.';
            return '';
        }],
        ['descricao', () => {
            const v = $('descricao').value.trim();
            if (!v) return 'Escreva uma descrição para o animal.';
            if (v.length < 20) return 'A descrição está muito curta. Conte um pouco mais (mínimo de 20 caracteres).';
            return '';
        }],
        ['id_categoria', () => (selCategoria.value ? '' : 'Selecione a categoria.')],
        ['especie', () => (inpEspecie.value.trim() ? '' : 'Informe a espécie do animal.')],
        ['idade', () => (calcularIdadeMeses() === null
            ? 'Use de 0 a 50 anos e de 0 a 11 meses.'
            : '')],
        ['foto', () => erroDaFoto(inpFoto.files[0])],   // foto é opcional
    ];

    // Limpa o erro de cada campo assim que o usuário volta a digitar nele
    REGRAS.forEach(([nome]) => {
        const bloco = campoDe(nome);
        if (!bloco || nome === 'foto') return;   // a foto limpa o erro no próprio handler
        bloco.addEventListener('input',  () => limparErro(nome));
        bloco.addEventListener('change', () => limparErro(nome));
    });

    function validar() {
        let primeiroComErro = null;

        REGRAS.forEach(([nome, regra]) => {
            const mensagem = regra();
            if (mensagem) {
                mostrarErro(nome, mensagem);
                if (!primeiroComErro) {
                    primeiroComErro = campoDe(nome).querySelector('input:not([type=hidden]), select, textarea');
                }
            } else {
                limparErro(nome);
            }
        });

        if (primeiroComErro) primeiroComErro.focus();
        return !primeiroComErro;
    }

    // ---------- Montagem dos dados (nomes = colunas da tabela animal) ----------
    function montarDados() {
        const dados = new FormData();
        const valor = (nome) => form.elements[nome].value.trim();
        const marcado = (nome) => (form.elements[nome].checked ? '1' : '0');   // TINYINT(1)

        dados.append('nome_animal', valor('nome_animal'));
        dados.append('descricao', valor('descricao'));
        dados.append('id_categoria', valor('id_categoria'));
        dados.append('especie', valor('especie'));
        dados.append('raca', valor('raca'));
        dados.append('idade_meses', inpIdade.value);   // '' = não informada (NULL no banco)
        dados.append('sexo', form.elements['sexo'].value);
        dados.append('porte', form.elements['porte'].value);   // '' = não informado (NULL)
        dados.append('cor', valor('cor'));
        dados.append('castrado', marcado('castrado'));
        dados.append('vacinado', marcado('vacinado'));
        dados.append('vermifugado', marcado('vermifugado'));
        dados.append('condicao_saude', valor('condicao_saude'));
        dados.append('compatibilidade_criancas', form.elements['compatibilidade_criancas'].value);
        dados.append('compatibilidade_animais', form.elements['compatibilidade_animais'].value);

        // Foto (opcional): o PHP salva o arquivo na pasta fotos/ e grava o caminho em foto_url
        if (inpFoto.files[0]) dados.append('foto', inpFoto.files[0]);

        // O id_protetor NÃO vai daqui: o PHP pega da sessão do usuário logado.
        return dados;
    }

    // ---------- Avisos ----------
    function esconderAvisos() {
        avisoOk.hidden = true;
        avisoErro.hidden = true;
    }

    function mostrarAviso(el, texto) {
        esconderAvisos();
        el.textContent = texto;
        el.hidden = false;
        el.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    function restaurarFormulario() {
        form.reset();
        limparPrevia();
        especieDigitada = false;
        inpIdade.value = '';
        REGRAS.forEach(([nome]) => limparErro(nome));
    }

    // ---------- Limpar formulário ----------
    form.addEventListener('reset', () => {
        limparPrevia();
        especieDigitada = false;
        inpIdade.value = '';
        REGRAS.forEach(([nome]) => limparErro(nome));
        esconderAvisos();
    });

    // ---------- Envio ----------
    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        esconderAvisos();

        if (!validar()) return;

        btnSalvar.disabled = true;
        btnSalvar.textContent = 'Salvando...';

        try {
            const resposta = await fetch(URL_CADASTRO, {
                method: 'POST',
                body: montarDados(),
                credentials: 'same-origin',
            });

            // Resposta do PHP: { ok, mensagem, ... }. Se não vier JSON válido, cai no catch abaixo
            const resultado = await resposta.json();

            if (resposta.ok && resultado.ok) {
                restaurarFormulario();
                mostrarAviso(avisoOk, resultado.mensagem || 'Animal cadastrado com sucesso!');
            } else {
                mostrarAviso(avisoErro, resultado.mensagem || 'Não foi possível cadastrar o animal. Tente novamente.');
            }
        } catch (erro) {
            mostrarAviso(avisoErro, 'Não foi possível se conectar ao servidor. Tente novamente em instantes.');
        } finally {
            btnSalvar.disabled = false;
            btnSalvar.textContent = 'Cadastrar animal';
        }
    });
})();
