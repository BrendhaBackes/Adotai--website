// =====================================================
// Painel do protetor:
//  - confere se quem está logado é protetor;
//  - troca entre as abas (Cadastrar animal / Meus animais) e abre a aba
//    indicada no endereço (ex.: protetor_painel.html#animais);
//  - aba "Meus animais": lista os animais do protetor logado (api/meus_animais.php);
//  - formulário de cadastro de animal: contadores de caracteres,
//    idade em anos + meses -> idade_meses, validação e envio.
// (As validações aqui são só conveniência: o PHP precisa validar de novo.)
// =====================================================
(function () {
    const base = new URL('../', document.currentScript.src);   // raiz do site (pasta adotai/)

    const URL_SESSAO   = new URL('api/sessao.php', base).href;
    const URL_CADASTRO = new URL('api/animal_cadastrar.php', base).href;
    const URL_LISTA    = new URL('api/meus_animais.php', base).href;
    const FOTO_PADRAO  = new URL('images/franky.jpeg', base).href;   // usada se o animal não tiver foto
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

        if (nome === 'animais') carregarMeusAnimais();   // sempre mostra a lista atualizada
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

    // ==================================================
    // 3. ABA "MEUS ANIMAIS"
    // Busca em api/meus_animais.php os animais ligados ao protetor logado
    // (o servidor descobre o protetor pela sessão: nenhum id é enviado daqui)
    // e desenha um card para cada um.
    // ==================================================
    const listaAnimais   = $('meus-animais-lista');
    const vazioAnimais   = $('meus-animais-vazio');
    const resumoAnimais  = $('meus-animais-resumo');
    const carregandoAnim = $('meus-animais-carregando');
    const erroAnimais    = $('meus-animais-erro');
    const erroAnimaisTxt = $('meus-animais-erro-texto');

    let pedidoAtual = 0;   // só a resposta do pedido mais recente é desenhada

    // Texto sem acento, minúsculo e com hífens: "Em processo" -> "em-processo" (sufixo das classes .selo-*)
    function paraClasse(texto) {
        return String(texto).normalize('NFD').replace(/[\u0300-\u036f]/g, '')
            .toLowerCase().replace(/[^a-z0-9]+/g, '-');
    }

    // 18 -> "1 ano e 6 meses" | 8 -> "8 meses" | 24 -> "2 anos"
    function formatarIdade(meses) {
        if (meses === null || meses === undefined) return 'Idade não informada';
        const anos = Math.floor(meses / 12);
        const resto = meses % 12;
        const partes = [];
        if (anos > 0)  partes.push(anos === 1 ? '1 ano' : `${anos} anos`);
        if (resto > 0 || anos === 0) partes.push(resto === 1 ? '1 mês' : `${resto} meses`);
        return partes.join(' e ');
    }

    // "2026-11-10" -> "10/11/2026" (sem passar por Date: evita erro de fuso horário)
    function formatarData(iso) {
        const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(iso || '');
        return m ? `${m[3]}/${m[2]}/${m[1]}` : null;
    }

    function selo(texto, status) {
        return criar('span', `selo selo-${paraClasse(status)}`, texto);
    }

    function criarFotoAnimal(a) {
        const img = criar('img', 'animal-foto');
        img.loading = 'lazy';
        img.alt = `Foto de ${a.nome_animal}`;
        img.src = a.foto_url ? new URL(a.foto_url, base).href : FOTO_PADRAO;
        img.addEventListener('error', () => {
            if (img.src !== FOTO_PADRAO) img.src = FOTO_PADRAO;   // foto não encontrada
        }, { once: true });
        return img;
    }

    function criarCardAnimal(a) {
        const info = criar('div', 'animal-info');

        // Nome + selos: aprovação do admin e, se já está no catálogo, o andamento da adoção
        const topo = criar('div', 'animal-topo');
        topo.appendChild(criar('h3', 'animal-nome', a.nome_animal));

        if (a.status_aprovacao === 'Aprovado') {
            topo.appendChild(selo('Publicado no catálogo', 'aprovado'));
            if (a.status_adocao) topo.appendChild(selo(a.status_adocao, a.status_adocao));
        } else if (a.status_aprovacao === 'Pendente') {
            topo.appendChild(selo('Aguardando aprovação', 'pendente'));
        } else if (a.status_aprovacao === 'Recusado') {
            topo.appendChild(selo('Recusado', 'recusado'));
        } else {
            topo.appendChild(selo(a.status_aprovacao || 'Sem status', 'pendente'));
        }
        info.appendChild(topo);

        // Espécie · raça · sexo · porte · idade
        const detalhes = [a.especie, a.raca, a.sexo !== 'Não informado' ? a.sexo : null, a.porte, formatarIdade(a.idade_meses)]
            .filter(Boolean).join(' · ');
        info.appendChild(criar('p', 'animal-detalhes', detalhes));

        // Local · data do cadastro
        const local = [a.cidade ? (a.uf ? `${a.cidade} - ${a.uf}` : a.cidade) : null,
                       formatarData(a.data_cadastro) ? `Cadastrado em ${formatarData(a.data_cadastro)}` : null]
            .filter(Boolean).join(' · ');
        if (local) info.appendChild(criar('p', 'animal-detalhes', local));

        // Cuidados de saúde já feitos
        const cuidados = [['castrado', 'Castrado'], ['vacinado', 'Vacinado'], ['vermifugado', 'Vermifugado']]
            .filter(([campo]) => Number(a[campo]) === 1);
        if (cuidados.length) {
            const chips = criar('ul', 'animal-chips');
            cuidados.forEach(([, rotulo]) => chips.appendChild(criar('li', 'animal-chip', rotulo)));
            info.appendChild(chips);
        }

        // Recados do administrador
        if (a.status_aprovacao === 'Pendente') {
            info.appendChild(criar('p', 'animal-nota animal-nota-pendente',
                'O administrador ainda vai analisar este cadastro. Enquanto isso, ele não aparece no catálogo.'));
        } else if (a.status_aprovacao === 'Recusado') {
            const nota = criar('p', 'animal-nota animal-nota-recusado');
            nota.appendChild(criar('strong', null, 'Motivo da recusa: '));
            nota.appendChild(document.createTextNode(a.observacao_admin || 'não informado pelo administrador.'));
            info.appendChild(nota);
        } else if (a.observacao_admin) {
            const nota = criar('p', 'animal-nota animal-nota-pendente');
            nota.appendChild(criar('strong', null, 'Observação do administrador: '));
            nota.appendChild(document.createTextNode(a.observacao_admin));
            info.appendChild(nota);
        }

        const card = criar('article', 'animal-item');
        card.append(criarFotoAnimal(a), info);
        return card;
    }

    // "3 animais cadastrados · 1 aguardando aprovação · 1 recusado"
    function textoResumo(animais) {
        const conta = (status) => animais.filter(a => a.status_aprovacao === status).length;
        const pendentes = conta('Pendente');
        const recusados = conta('Recusado');
        const partes = [animais.length === 1 ? '1 animal cadastrado' : `${animais.length} animais cadastrados`];
        if (pendentes) partes.push(pendentes === 1 ? '1 aguardando aprovação' : `${pendentes} aguardando aprovação`);
        if (recusados) partes.push(recusados === 1 ? '1 recusado' : `${recusados} recusados`);
        return partes.join(' · ');
    }

    function mostrarMeusAnimais(animais) {
        listaAnimais.replaceChildren(...animais.map(criarCardAnimal));
        vazioAnimais.hidden = animais.length > 0;
        resumoAnimais.hidden = animais.length === 0;
        resumoAnimais.textContent = animais.length ? textoResumo(animais) : '';
    }

    function mostrarErroAnimais(texto) {
        erroAnimaisTxt.textContent = texto;
        erroAnimais.hidden = false;
    }

    async function carregarMeusAnimais() {
        const meuPedido = ++pedidoAtual;

        erroAnimais.hidden = true;
        if (!listaAnimais.hasChildNodes()) {   // 1ª vez: avisa que está carregando
            vazioAnimais.hidden = true;
            carregandoAnim.hidden = false;
        }

        try {
            const resposta = await fetch(URL_LISTA, { credentials: 'same-origin', cache: 'no-store' });
            const corpo = await resposta.json().catch(() => null);

            if (meuPedido !== pedidoAtual) return;   // chegou um pedido mais novo: ignora este

            if (EXIGIR_PROTETOR && resposta.status === 401) { location.replace(URL_LOGIN);  return; }
            if (EXIGIR_PROTETOR && resposta.status === 403 && corpo && corpo.erro === 'tipo_nao_permitido') {
                location.replace(URL_INICIO);
                return;
            }

            if (!resposta.ok || !corpo || !corpo.ok || !Array.isArray(corpo.animais)) {
                mostrarErroAnimais((corpo && corpo.mensagem) || 'Não foi possível carregar os seus animais agora.');
                return;
            }

            mostrarMeusAnimais(corpo.animais);
        } catch (erro) {
            if (meuPedido !== pedidoAtual) return;
            mostrarErroAnimais('Não foi possível se conectar ao servidor. Verifique sua conexão e tente de novo.');
        } finally {
            if (meuPedido === pedidoAtual) carregandoAnim.hidden = true;
        }
    }

    $('meus-animais-tentar').addEventListener('click', carregarMeusAnimais);

    // ==================================================
    // 4. FORMULÁRIO DE CADASTRO DE ANIMAL
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

    // ==================================================
    // 5. ABA INICIAL
    // Fica por último de propósito: abrir o endereço com "#animais" já dispara o
    // carregamento da lista, que usa elementos e constantes definidos acima.
    // ==================================================
    selecionarAba(location.hash.replace('#', ''));

    // Mudou só o "#..." do endereço (link para #animais, botão voltar): troca a aba
    window.addEventListener('hashchange', () => selecionarAba(location.hash.replace('#', '')));
})();
