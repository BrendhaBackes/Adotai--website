// =====================================================
// Catálogo de planos: busca os planos no banco (api/planos.php),
// gera os cards, aplica os filtros por público (adotantes / protetores)
// e permite ASSINAR o plano (api/assinatura_criar.php).
//
// O botão "Assinar plano" respeita o público escolhido na criação do plano:
//   - visitante (sem login): o botão aparece; ao clicar, leva ao login;
//   - adotante / protetor: assina se o plano for do seu público (ou "ambos");
//   - demais casos: o botão fica desativado explicando o motivo.
// (O PHP confere tudo de novo: estas regras aqui são só conveniência.)
// =====================================================
(function () {
    const base = new URL('../', document.currentScript.src);   // raiz do site (pasta adotai/)

    const URL_API    = new URL('api/planos.php', base).href;
    const URL_SESSAO = new URL('api/sessao.php', base).href;
    const URL_ASSINAR = new URL('api/assinatura_criar.php', base).href;
    const URL_LOGIN  = new URL('pages/login.html', base).href;
    const URL_MEUS   = new URL('pages/meus_planos.html', base).href;

    const moeda = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });

    // Tipos de usuário que podem assinar (o mesmo do api/assinatura_criar.php)
    const TIPOS_QUE_ASSINAM = ['adotante', 'protetor'];

    // ==================================================
    // FILTROS  (a chave é o "value" do radio no catalogo_planos.html)
    // Um plano "ambos" aparece tanto em Adotantes quanto em Protetores.
    // Para criar um filtro novo: adicione o radio no HTML e uma linha aqui.
    // ==================================================
    const FILTROS = {
        'todos':    ()  => true,
        'adotante': (p) => p.publico_alvo === 'adotante' || p.publico_alvo === 'ambos',
        'protetor': (p) => p.publico_alvo === 'protetor' || p.publico_alvo === 'ambos',
    };

    const ROTULO_PUBLICO = {
        adotante: 'Adotantes',
        protetor: 'Protetores',
        ambos:    'Adotantes e protetores',
    };

    // Texto que acompanha o preço, conforme a cobrança do plano
    const SUFIXO_PERIODO = {
        'Mensal':          '/ mês',
        'Trimestral':      '/ trimestre',
        'Semestral':       '/ semestre',
        'Anual':           '/ ano',
        'Pagamento único': 'pagamento único',
    };

    const lista = document.getElementById('plano-card-list');
    const aviso = document.getElementById('planos-aviso');

    let planos = [];
    let usuario = null;               // { nome, tipo } se houver alguém logado; senão null
    const assinados = new Set();      // ids dos planos assinados nesta página

    // ---------- Utilitários ----------
    function criar(tag, classe, texto) {
        const el = document.createElement(tag);
        if (classe) el.className = classe;
        if (texto !== undefined) el.textContent = texto;   // textContent: nunca interpreta HTML
        return el;
    }

    function mostrarAviso(texto) {
        aviso.textContent = texto;
        aviso.hidden = !texto;
    }

    const localDoPlano = p => [p.cidade, p.uf].filter(Boolean).join(' - ');

    // ---------- Card do plano ----------
    function criarPreco(p) {
        const preco = criar('p', 'plano-card-preco');

        if (!p.valor) {
            preco.textContent = 'Gratuito';
            return preco;
        }

        preco.textContent = moeda.format(p.valor);
        const sufixo = SUFIXO_PERIODO[p.periodicidade];
        if (sufixo) preco.appendChild(criar('small', '', sufixo));
        return preco;
    }

    function criarOferta(p) {
        const oferta = criar('p', 'plano-card-oferta');

        if (!p.veterinario) {
            oferta.textContent = 'Oferecido pela Adotaí';
            return oferta;
        }

        oferta.append('Oferecido por ', criar('strong', '', p.veterinario));
        const local = localDoPlano(p);
        if (local) oferta.append(' • ' + local);
        return oferta;
    }

    // ==================================================
    // ASSINATURA
    // ==================================================
    // Define o texto do botão e se ele está ativo, conforme quem está logado e o público do plano
    function estadoDoBotao(p) {
        if (assinados.has(p.id_plano)) return { texto: 'Assinatura ativa ✓', ativo: false };

        // visitante: deixa clicar (o clique leva ao login)
        if (!usuario) return { texto: 'Assinar plano', ativo: true };

        if (!TIPOS_QUE_ASSINAM.includes(usuario.tipo)) {
            return { texto: 'Apenas para adotantes e protetores', ativo: false };
        }
        if (p.publico_alvo !== 'ambos' && p.publico_alvo !== usuario.tipo) {
            const so = (ROTULO_PUBLICO[p.publico_alvo] || '').toLowerCase();
            return { texto: `Exclusivo para ${so}`, ativo: false };
        }
        return { texto: 'Assinar plano', ativo: true };
    }

    // "Assinatura ativa. Ver em Meus planos"
    function mostrarAssinado(mensagem, textoInicial) {
        const link = criar('a', '', 'Ver em Meus planos');
        link.href = URL_MEUS;
        mensagem.replaceChildren(textoInicial + ' ', link);
    }

    async function assinar(p, botao, mensagem) {
        botao.disabled = true;
        botao.textContent = 'Assinando...';
        mensagem.textContent = '';

        try {
            const resposta = await fetch(URL_ASSINAR, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'fetch' },   // o PHP exige este cabeçalho
                body: new URLSearchParams({ id_plano: p.id_plano }),
            });
            const dados = await resposta.json().catch(() => null);

            if (dados && dados.ok) {
                assinados.add(p.id_plano);
                botao.textContent = 'Assinatura ativa ✓';
                mostrarAssinado(mensagem, 'Plano assinado!');
                return;   // botão continua desativado
            }

            if (dados && dados.erro === 'nao_logado') {
                botao.textContent = 'Assinar plano';
                mensagem.textContent = 'Entre na sua conta para assinar. Levando você ao login...';
                setTimeout(() => { window.location.href = URL_LOGIN; }, 1500);
                return;
            }

            if (dados && dados.erro === 'ja_assinado') {
                // já existia uma assinatura ativa (ex.: feita em outra aba)
                assinados.add(p.id_plano);
                botao.textContent = 'Assinatura ativa ✓';
                mostrarAssinado(mensagem, 'Você já assina este plano.');
                return;
            }

            mensagem.textContent = (dados && dados.mensagem) || 'Não foi possível assinar agora. Tente novamente.';
        } catch (e) {
            mensagem.textContent = 'Não foi possível se conectar ao servidor. Tente novamente.';
        }

        botao.textContent = 'Assinar plano';
        botao.disabled = false;
    }

    function criarAssinar(p) {
        const bloco = criar('div', 'plano-card-assinar');

        const estado = estadoDoBotao(p);
        const botao = criar('button', 'btn-assinar', estado.texto);
        botao.type = 'button';
        botao.disabled = !estado.ativo;

        const mensagem = criar('p', 'plano-card-msg');
        mensagem.setAttribute('role', 'status');
        if (assinados.has(p.id_plano)) mostrarAssinado(mensagem, 'Plano assinado!');

        botao.addEventListener('click', () => assinar(p, botao, mensagem));

        bloco.append(botao, mensagem);
        return bloco;
    }

    function criarCard(p) {
        const card = criar('article', 'plano-card' + (p.destaque ? ' em-destaque' : ''));

        const topo = criar('div', 'plano-card-topo');
        topo.appendChild(criar('h2', '', p.nome));
        if (p.destaque) topo.appendChild(criar('span', 'plano-selo destaque', 'Em destaque'));
        topo.appendChild(criar('span', 'plano-selo', ROTULO_PUBLICO[p.publico_alvo] || ROTULO_PUBLICO.ambos));

        card.append(topo, criarPreco(p));

        if (p.descricao) card.appendChild(criar('p', 'plano-card-desc', p.descricao));

        if (p.beneficios.length) {
            const itens = criar('ul', 'plano-card-beneficios');
            p.beneficios.forEach(b => itens.appendChild(criar('li', '', b)));
            card.appendChild(itens);
        }

        card.appendChild(criarOferta(p));
        card.appendChild(criarAssinar(p));
        return card;
    }

    // ---------- Desenha a lista de acordo com o filtro escolhido ----------
    function desenhar() {
        const escolhido = document.querySelector('input[name="publico"]:checked');
        const filtro = FILTROS[escolhido ? escolhido.value : 'todos'] || FILTROS.todos;

        const visiveis = planos.filter(filtro);
        lista.replaceChildren(...visiveis.map(criarCard));

        mostrarAviso(visiveis.length ? '' : 'Nenhum plano encontrado nesta categoria.');
    }

    document.querySelectorAll('input[name="publico"]').forEach(radio =>
        radio.addEventListener('change', desenhar)
    );

    // ---------- Busca os dados (sessão e planos juntos) ----------
    mostrarAviso('Carregando planos...');

    const pedidoSessao = fetch(URL_SESSAO, { credentials: 'same-origin', cache: 'no-store' })
        .then(r => (r.ok ? r.json() : null))
        .catch(() => null);   // sem resposta: trata como visitante

    const pedidoPlanos = fetch(URL_API, { cache: 'no-store' })
        .then(r => (r.ok ? r.json() : Promise.reject(new Error('HTTP ' + r.status))))
        .then(dados => {
            if (!dados.ok) throw new Error(dados.erro || 'Resposta inválida');
            return dados.planos;
        });

    Promise.all([pedidoSessao, pedidoPlanos])
        .then(([sessao, lista]) => {
            usuario = sessao && sessao.logado ? { nome: sessao.nome, tipo: sessao.tipo } : null;
            planos = lista;
            desenhar();
        })
        .catch(() => mostrarAviso('Não foi possível carregar os planos agora. Tente novamente em instantes.'));
})();
