// =====================================================
// Painel do veterinário (pages/cadastrar_plano.html):
//  - confere se quem está logado é veterinário;
//  - troca entre as abas (Cadastrar plano / Meus planos);
//  - formulário de cadastro de plano: contadores de caracteres,
//    coberturas + benefícios extras -> "beneficios", validação e envio.
// (As validações aqui são só conveniência: o PHP precisa validar de novo.)
// =====================================================
(function () {
    const base = new URL('../', document.currentScript.src);   // raiz do site (pasta adotai/)

    const URL_SESSAO   = new URL('api/sessao.php', base).href;
    const URL_CADASTRO = new URL('api/plano_cadastrar.php', base).href;
    const URL_LISTAR   = new URL('api/plano_listar.php', base).href;
    const URL_LOGIN    = new URL('pages/login.html', base).href;
    const URL_INICIO   = new URL('index.html', base).href;

    // true  = quem não for veterinário logado é redirecionado.
    // Ponha false se quiser abrir a página para testar o visual sem login.
    const EXIGIR_VETERINARIO = true;

    // Limites dos benefícios (o PHP confere os mesmos valores)
    const MAX_BENEFICIOS      = 15;
    const MAX_CHARS_BENEFICIO = 100;

    // ---------- Utilitários ----------
    function criar(tag, classe, texto) {
        const el = document.createElement(tag);
        if (classe) el.className = classe;
        if (texto !== undefined) el.textContent = texto;   // textContent: nunca interpreta HTML
        return el;
    }

    const $ = (id) => document.getElementById(id);

    // ==================================================
    // 1. SESSÃO: só veterinário entra aqui
    // ==================================================
    fetch(URL_SESSAO, { credentials: 'same-origin', cache: 'no-store' })
        .then(r => (r.ok ? r.json() : null))
        .then(dados => {
            if (!EXIGIR_VETERINARIO || !dados) return;   // sem resposta: não bloqueia a tela

            if (!dados.logado)                 { location.replace(URL_LOGIN);  return; }
            if (dados.tipo !== 'veterinario')  { location.replace(URL_INICIO); return; }

            if (dados.nome) $('painel-nome').textContent = dados.nome;
        })
        .catch(() => { /* sem resposta: mantém a página como está */ });

    // ==================================================
    // 1b. MEUS PLANOS (aba "Meus planos")
    // Busca os planos do veterinário logado em api/plano_listar.php e monta um cartão
    // para cada um. A lista é recarregada toda vez que a aba é aberta, então um plano
    // recém-cadastrado já aparece. (Fica ANTES das abas porque a página pode abrir
    // direto em #planos.)
    // ==================================================
    const listaPlanos = $('meus-planos-lista');
    const vazioPlanos = $('meus-planos-vazio');

    const ROTULO_PUBLICO = {
        adotante: 'Adotantes',
        protetor: 'Protetores',
        ambos:    'Adotantes e protetores',
    };
    const SUFIXO_COBRANCA = {
        'Mensal':          '/ mês',
        'Trimestral':      '/ trimestre',
        'Semestral':       '/ semestre',
        'Anual':           '/ ano',
        'Pagamento único': 'pagamento único',
    };
    const moeda = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });

    let carregandoPlanos = false;

    function criarCartaoPlano(plano) {
        const cartao = criar('article', 'plano-item' + (plano.destaque ? ' em-destaque' : ''));

        // topo: nome + selos (público, destaque, status)
        const topo = criar('div', 'plano-item-topo');
        topo.append(criar('h2', 'plano-item-nome', plano.nome));

        const selos = criar('div', 'plano-selos');
        selos.append(criar('span', 'selo', ROTULO_PUBLICO[plano.publico_alvo] || ROTULO_PUBLICO.ambos));
        if (plano.destaque) selos.append(criar('span', 'selo selo-destaque', 'Em destaque'));
        selos.append(criar('span', 'selo ' + (plano.status === 'Ativo' ? 'selo-ativo' : 'selo-inativo'), plano.status));
        topo.append(selos);
        cartao.append(topo);

        // preço: "R$ 49,90 / mês"  (valor 0 = "Gratuito")
        const preco = criar('p', 'plano-preco');
        const valor = Number(plano.valor);
        if (valor === 0) {
            preco.textContent = 'Gratuito';
        } else {
            preco.append(moeda.format(valor));
            const sufixo = SUFIXO_COBRANCA[plano.periodicidade];
            if (sufixo) preco.append(criar('small', '', ' ' + sufixo));
        }
        cartao.append(preco);

        if (plano.descricao) cartao.append(criar('p', 'plano-descricao', plano.descricao));

        if (plano.beneficios.length) {
            const ul = criar('ul', 'plano-beneficios');
            plano.beneficios.forEach(b => ul.append(criar('li', '', b)));
            cartao.append(ul);
        }

        return cartao;
    }

    function mostrarMensagemLista(texto, comBotaoNovamente) {
        vazioPlanos.hidden = true;
        const aviso = criar('p', 'aviso ' + (comBotaoNovamente ? 'aviso-erro' : 'plano-carregando'), texto);
        if (comBotaoNovamente) aviso.setAttribute('role', 'alert');
        listaPlanos.replaceChildren(aviso);

        if (comBotaoNovamente) {
            const btn = criar('button', 'btn-secundario', 'Tentar novamente');
            btn.type = 'button';
            btn.addEventListener('click', carregarPlanos);
            listaPlanos.append(btn);
        }
    }

    async function carregarPlanos() {
        if (carregandoPlanos) return;
        carregandoPlanos = true;
        mostrarMensagemLista('Carregando seus planos...', false);

        try {
            const resposta = await fetch(URL_LISTAR, { credentials: 'same-origin', cache: 'no-store' });
            const dados = await resposta.json();   // se não vier JSON válido, cai no catch

            if (!resposta.ok || !dados.ok) {
                mostrarMensagemLista(dados.mensagem || 'Não foi possível carregar os seus planos.', true);
                return;
            }

            listaPlanos.replaceChildren(...dados.planos.map(criarCartaoPlano));
            vazioPlanos.hidden = dados.planos.length > 0;   // sem planos: mostra o aviso "Nenhum plano cadastrado ainda"
        } catch (erro) {
            mostrarMensagemLista('Não foi possível se conectar ao servidor. Tente novamente em instantes.', true);
        } finally {
            carregandoPlanos = false;
        }
    }

    // ==================================================
    // 2. ABAS
    // Para criar uma aba nova: botão .painel-aba (id "aba-xxx") + section
    // .painel-card (id "painel-xxx") no HTML e uma linha em ABAS aqui.
    // ==================================================
    const ABAS = {
        cadastro: { aba: $('aba-cadastro'), painel: $('painel-cadastro') },
        planos:   { aba: $('aba-planos'),   painel: $('painel-planos')   },
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

        if (nome === 'planos') carregarPlanos();   // atualiza a lista a cada vez que a aba é aberta
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
    // 3. FORMULÁRIO DE CADASTRO DE PLANO
    // ==================================================
    const form      = $('plano-form');
    const btnSalvar = $('btn-salvar');
    const avisoOk   = $('aviso-sucesso');
    const avisoErro = $('aviso-erro');

    const inpValor     = $('valor');
    const inpExtras    = $('beneficios_extra');

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

    // ---------- Benefícios: coberturas marcadas + linhas do campo "outros" ----------
    // Devolve a lista final, sem linhas vazias nem repetidas (ignorando maiúsculas).
    function coletarBeneficios() {
        const marcadas = [...form.querySelectorAll('input[name="cobertura"]:checked')].map(i => i.value);
        const extras   = inpExtras.value.split('\n').map(l => l.trim()).filter(Boolean);

        const vistos = new Set();
        return [...marcadas, ...extras].filter(b => {
            const chave = b.toLowerCase();
            if (vistos.has(chave)) return false;
            vistos.add(chave);
            return true;
        });
    }

    // ---------- Validação ----------
    // Cada regra: [nome do campo, função que devolve a mensagem de erro ou '']
    const REGRAS = [
        ['nome', () => {
            const v = $('nome').value.trim();
            if (!v) return 'Informe o nome do plano.';
            return '';
        }],
        ['descricao', () => {
            const v = $('descricao').value.trim();
            if (!v) return 'Escreva uma descrição para o plano.';
            if (v.length < 20) return 'A descrição está muito curta. Conte um pouco mais (mínimo de 20 caracteres).';
            return '';
        }],
        ['valor', () => {
            const v = inpValor.value.trim();
            if (v === '') return 'Informe o valor do plano (use 0,00 se for gratuito).';
            // até 8 dígitos antes da vírgula e 2 casas decimais (DECIMAL(10,2))
            if (!/^\d{1,8}(\.\d{1,2})?$/.test(v)) return 'Use um valor válido, com até 2 casas decimais (ex.: 49,90).';
            return '';
        }],
        ['periodicidade', () => ($('periodicidade').value ? '' : 'Selecione a forma de cobrança.')],
        ['beneficios', () => {
            const lista = coletarBeneficios();
            if (lista.length === 0) return 'Marque o que o plano cobre ou escreva ao menos um benefício.';
            if (lista.length > MAX_BENEFICIOS) return `Use no máximo ${MAX_BENEFICIOS} benefícios (você informou ${lista.length}).`;
            if (lista.some(b => b.length > MAX_CHARS_BENEFICIO)) return `Cada benefício deve ter no máximo ${MAX_CHARS_BENEFICIO} caracteres.`;
            return '';
        }],
    ];

    // Limpa o erro de cada campo assim que o usuário volta a mexer nele
    REGRAS.forEach(([nome]) => {
        const bloco = campoDe(nome);
        if (!bloco) return;
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

    // ---------- Montagem dos dados (nomes = colunas da tabela plano_de_assinatura) ----------
    function montarDados() {
        const dados = new FormData();
        const valor = (nome) => form.elements[nome].value.trim();

        dados.append('nome', valor('nome'));
        dados.append('descricao', valor('descricao'));
        dados.append('publico_alvo', form.elements['publico_alvo'].value);
        dados.append('valor', inpValor.value.trim());               // já vem com ponto decimal (ex.: 49.90)
        dados.append('periodicidade', form.elements['periodicidade'].value);
        dados.append('beneficios', coletarBeneficios().join('\n')); // um benefício por linha (como no banco)
        dados.append('destaque', form.elements['destaque'].checked ? '1' : '0');   // TINYINT(1)

        // O id_veterinario NÃO vai daqui: o PHP pega da sessão do usuário logado.
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
        REGRAS.forEach(([nome]) => limparErro(nome));
    }

    // ---------- Limpar formulário ----------
    form.addEventListener('reset', () => {
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
                mostrarAviso(avisoOk, resultado.mensagem || 'Plano cadastrado com sucesso!');
            } else {
                mostrarAviso(avisoErro, resultado.mensagem || 'Não foi possível cadastrar o plano. Tente novamente.');
            }
        } catch (erro) {
            mostrarAviso(avisoErro, 'Não foi possível se conectar ao servidor. Tente novamente em instantes.');
        } finally {
            btnSalvar.disabled = false;
            btnSalvar.textContent = 'Cadastrar plano';
        }
    });
})();
