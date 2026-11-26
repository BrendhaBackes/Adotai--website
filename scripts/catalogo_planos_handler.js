// =====================================================
// Catálogo de planos: busca os planos no banco (api/planos.php),
// gera os cards e aplica os filtros por público (adotantes / protetores).
// =====================================================
(function () {
    const base = new URL('../', document.currentScript.src);   // raiz do site (pasta adotai/)

    const URL_API = new URL('api/planos.php', base).href;

    const moeda = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });

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

    // ---------- Busca os dados ----------
    mostrarAviso('Carregando planos...');

    fetch(URL_API, { cache: 'no-store' })
        .then(r => (r.ok ? r.json() : Promise.reject(new Error('HTTP ' + r.status))))
        .then(dados => {
            if (!dados.ok) throw new Error(dados.erro || 'Resposta inválida');
            planos = dados.planos;
            desenhar();
        })
        .catch(() => mostrarAviso('Não foi possível carregar os planos agora. Tente novamente em instantes.'));
})();
