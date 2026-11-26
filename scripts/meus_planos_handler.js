// =====================================================
// Página "Meus planos" do adotante (pages/meus_planos.html):
//  - confere se quem está logado é adotante;
//  - lista os planos assinados (api/assinatura_listar.php) em cards,
//    com o mesmo visual do "Meus animais" do painel do protetor;
//  - botão "Cancelar assinatura" (api/assinatura_cancelar.php), com confirmação no card.
// A assinatura é alegórica: não há pagamento nem estorno.
// =====================================================
(function () {
    const base = new URL('../', document.currentScript.src);   // raiz do site (pasta adotai/)

    const URL_SESSAO   = new URL('api/sessao.php', base).href;
    const URL_LISTA    = new URL('api/assinatura_listar.php', base).href;
    const URL_CANCELAR = new URL('api/assinatura_cancelar.php', base).href;
    const URL_LOGIN    = new URL('pages/login.html', base).href;
    const URL_INICIO   = new URL('index.html', base).href;

    // true  = quem não for adotante/protetor logado é redirecionado.
    const EXIGIR_ADOTANTE = true;

    const $ = (id) => document.getElementById(id);

    function criar(tag, classe, texto) {
        const el = document.createElement(tag);
        if (classe) el.className = classe;
        if (texto !== undefined) el.textContent = texto;   // textContent: nunca interpreta HTML
        return el;
    }

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

    // "2026-11-10" -> "10/11/2026" (sem passar por Date: evita erro de fuso horário)
    function formatarData(iso) {
        const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(iso || '');
        return m ? `${m[3]}/${m[2]}/${m[1]}` : null;
    }

    // ==================================================
    // 1. SESSÃO: só adotante entra aqui
    // ==================================================
    fetch(URL_SESSAO, { credentials: 'same-origin', cache: 'no-store' })
        .then(r => (r.ok ? r.json() : null))
        .then(dados => {
            if (!EXIGIR_ADOTANTE || !dados) return;

            if (!dados.logado)              { location.replace(URL_LOGIN);  return; }
            if (!['adotante', 'protetor'].includes(dados.tipo)) { location.replace(URL_INICIO); return; }

            if (dados.nome) $('painel-nome').textContent = dados.nome;
        })
        .catch(() => { /* sem resposta: mantém a página como está */ });

    // ==================================================
    // 2. ELEMENTOS
    // ==================================================
    const lista      = $('planos-lista');
    const vazio      = $('planos-vazio');
    const resumo     = $('planos-resumo');
    const carregando = $('planos-carregando');
    const erroBox    = $('planos-erro');
    const erroTexto  = $('planos-erro-texto');
    const sucesso    = $('planos-sucesso');

    let pedidoAtual = 0;   // só a resposta do pedido mais recente é desenhada
    let timerSucesso = null;

    function avisarSucesso(texto) {
        clearTimeout(timerSucesso);
        sucesso.textContent = texto;
        sucesso.hidden = false;
        timerSucesso = setTimeout(() => { sucesso.hidden = true; }, 6000);
    }

    // ==================================================
    // 3. CANCELAR ASSINATURA
    // ==================================================
    // Devolve o JSON ou lança Error com a mensagem para a tela.
    async function cancelarAssinatura(idPlano) {
        let resposta, corpo;
        try {
            resposta = await fetch(URL_CANCELAR, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'fetch' },   // o PHP exige este cabeçalho
                body: new URLSearchParams({ id_plano: idPlano }),
            });
            corpo = await resposta.json();
        } catch (e) {
            throw new Error('Não foi possível se conectar ao servidor. Tente novamente.');
        }

        if (resposta.status === 401) { location.replace(URL_LOGIN); throw new Error('Sessão expirada.'); }
        if (!resposta.ok || !corpo.ok) throw new Error(corpo.mensagem || 'Não foi possível cancelar a assinatura.');
        return corpo;
    }

    function criarGerenciar(p) {
        const area = criar('div', 'animal-gerenciar');
        const msg = criar('p', 'animal-gerenciar-erro');
        msg.setAttribute('role', 'alert');
        msg.hidden = true;
        const erro = (texto) => { msg.textContent = texto; msg.hidden = !texto; };

        const acoes = criar('div', 'animal-gerenciar-acoes');
        const btnCancelar = criar('button', 'btn-mini btn-mini-perigo', 'Cancelar assinatura');
        btnCancelar.type = 'button';

        const confirmacao = criar('div', 'animal-gerenciar-confirma');
        confirmacao.hidden = true;
        const pergunta = criar('span', '', `Cancelar a assinatura do plano ${p.nome}?`);
        const btnSim = criar('button', 'btn-mini btn-mini-perigo-cheio', 'Sim, cancelar');
        btnSim.type = 'button';
        const btnNao = criar('button', 'btn-mini', 'Manter plano');
        btnNao.type = 'button';
        confirmacao.append(pergunta, btnSim, btnNao);

        btnCancelar.addEventListener('click', () => {
            erro('');
            btnCancelar.hidden = true;
            confirmacao.hidden = false;
            btnNao.focus();
        });
        btnNao.addEventListener('click', () => {
            confirmacao.hidden = true;
            btnCancelar.hidden = false;
            btnCancelar.focus();
        });
        btnSim.addEventListener('click', async () => {
            btnSim.disabled = btnNao.disabled = true;
            btnSim.textContent = 'Cancelando...';
            try {
                await cancelarAssinatura(p.id_plano);
                await carregarPlanos();   // o plano some da lista
                avisarSucesso(`A assinatura do plano ${p.nome} foi cancelada.`);
            } catch (e) {
                erro(e.message);
                btnSim.disabled = btnNao.disabled = false;
                btnSim.textContent = 'Sim, cancelar';
            }
        });

        acoes.append(btnCancelar, confirmacao);
        area.append(acoes, msg);
        return area;
    }

    // ==================================================
    // 4. CARD DA ASSINATURA (mesma estrutura do card do "Meus animais")
    // ==================================================
    function criarIcone() {
        const ns = 'http://www.w3.org/2000/svg';
        const caixa = criar('div', 'plano-icone');
        caixa.setAttribute('aria-hidden', 'true');
        const svg = document.createElementNS(ns, 'svg');
        svg.setAttribute('viewBox', '0 0 24 24');
        svg.setAttribute('fill', 'none');
        svg.setAttribute('stroke', 'currentColor');
        svg.setAttribute('stroke-width', '2');
        svg.setAttribute('stroke-linecap', 'round');
        svg.setAttribute('stroke-linejoin', 'round');
        const path = document.createElementNS(ns, 'path');
        // escudo com "visto"
        path.setAttribute('d', 'M12 3l8 3v6c0 4.5-3.2 8.2-8 9-4.8-.8-8-4.5-8-9V6l8-3z M8.5 12l2.5 2.5 4.5-5');
        svg.appendChild(path);
        caixa.appendChild(svg);
        return caixa;
    }

    function criarCardPlano(p) {
        const info = criar('div', 'animal-info');

        // Nome + selos
        const topo = criar('div', 'animal-topo');
        topo.appendChild(criar('h3', 'animal-nome', p.nome));
        topo.appendChild(criar('span', 'selo selo-ativo', 'Assinatura ativa'));
        topo.appendChild(criar('span', 'selo selo-publico', ROTULO_PUBLICO[p.publico_alvo] || ROTULO_PUBLICO.ambos));
        info.appendChild(topo);

        // Preço: "R$ 49,90 / mês" (valor 0 = "Gratuito")
        const preco = criar('p', 'plano-preco-linha');
        if (Number(p.valor) === 0) {
            preco.textContent = 'Gratuito';
        } else {
            preco.append(moeda.format(p.valor));
            const sufixo = SUFIXO_COBRANCA[p.periodicidade];
            if (sufixo) preco.append(criar('small', '', sufixo));
        }
        info.appendChild(preco);

        // Quem oferece · local · vencimento
        const oferta = p.veterinario ? `Oferecido por ${p.veterinario}` : 'Oferecido pela Adotaí';
        const local = [p.cidade, p.uf].filter(Boolean).join(' - ');
        info.appendChild(criar('p', 'animal-detalhes', [oferta, local].filter(Boolean).join(' · ')));

        const venc = formatarData(p.vencimento);
        info.appendChild(criar('p', 'animal-detalhes', venc ? `Próximo vencimento: ${venc}` : 'Sem data de vencimento'));

        // Cobertura / benefícios
        if (p.beneficios.length) {
            const chips = criar('ul', 'animal-chips');
            p.beneficios.forEach(b => chips.appendChild(criar('li', 'animal-chip', b)));
            info.appendChild(chips);
        }

        if (p.descricao) {
            const bloco = criar('div', 'animal-bloco');
            bloco.append(criar('strong', 'animal-bloco-rotulo', 'Descrição'), criar('p', 'animal-texto', p.descricao));
            info.appendChild(bloco);
        }

        info.appendChild(criarGerenciar(p));

        const card = criar('article', 'animal-item');
        card.append(criarIcone(), info);
        return card;
    }

    // ==================================================
    // 5. LISTA
    // ==================================================
    function mostrarPlanos(planos) {
        lista.replaceChildren(...planos.map(criarCardPlano));
        vazio.hidden = planos.length > 0;
        resumo.hidden = planos.length === 0;
        resumo.textContent = planos.length === 1 ? '1 plano assinado' : `${planos.length} planos assinados`;
    }

    function mostrarErro(texto) {
        erroTexto.textContent = texto;
        erroBox.hidden = false;
    }

    async function carregarPlanos() {
        const meuPedido = ++pedidoAtual;

        erroBox.hidden = true;
        if (!lista.hasChildNodes()) {   // 1ª vez: avisa que está carregando
            vazio.hidden = true;
            carregando.hidden = false;
        }

        try {
            const resposta = await fetch(URL_LISTA, { credentials: 'same-origin', cache: 'no-store' });
            const corpo = await resposta.json().catch(() => null);

            if (meuPedido !== pedidoAtual) return;   // chegou um pedido mais novo: ignora este

            if (EXIGIR_ADOTANTE && resposta.status === 401) { location.replace(URL_LOGIN);  return; }
            if (EXIGIR_ADOTANTE && resposta.status === 403 && corpo && corpo.erro === 'tipo_nao_permitido') {
                location.replace(URL_INICIO);
                return;
            }

            if (!resposta.ok || !corpo || !corpo.ok || !Array.isArray(corpo.assinaturas)) {
                mostrarErro((corpo && corpo.mensagem) || 'Não foi possível carregar os seus planos agora.');
                return;
            }

            mostrarPlanos(corpo.assinaturas);
        } catch (erro) {
            if (meuPedido !== pedidoAtual) return;
            mostrarErro('Não foi possível se conectar ao servidor. Verifique sua conexão e tente de novo.');
        } finally {
            if (meuPedido === pedidoAtual) carregando.hidden = true;
        }
    }

    $('planos-tentar').addEventListener('click', carregarPlanos);

    carregarPlanos();
})();
