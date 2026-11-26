// =====================================================
// Página "Lista de desejos" do adotante (pages/lista_desejos.html):
//  - confere se quem está logado é adotante;
//  - lista os animais da lista de interesses (api/lista_desejos.php) em cards,
//    com o mesmo visual e as mesmas informações do "Meus animais" do protetor;
//  - botão "Entrar em Contato": abre o WhatsApp com o telefone do protetor
//    e uma mensagem já preenchida.
// =====================================================
(function () {
    const base = new URL('../', document.currentScript.src);   // raiz do site (pasta adotai/)

    const URL_SESSAO  = new URL('api/sessao.php', base).href;
    const URL_LISTA   = new URL('api/lista_desejos.php', base).href;
    const URL_LOGIN   = new URL('pages/login.html', base).href;
    const URL_INICIO  = new URL('index.html', base).href;
    const FOTO_PADRAO = new URL('images/franky.jpeg', base).href;   // usada se o animal não tiver foto

    // true  = quem não for adotante logado é redirecionado.
    const EXIGIR_ADOTANTE = true;

    const $ = (id) => document.getElementById(id);

    function criar(tag, classe, texto) {
        const el = document.createElement(tag);
        if (classe) el.className = classe;
        if (texto !== undefined) el.textContent = texto;   // textContent: nunca interpreta HTML
        return el;
    }

    // ==================================================
    // 1. SESSÃO: só adotante entra aqui
    // ==================================================
    fetch(URL_SESSAO, { credentials: 'same-origin', cache: 'no-store' })
        .then(r => (r.ok ? r.json() : null))
        .then(dados => {
            if (!EXIGIR_ADOTANTE || !dados) return;

            if (!dados.logado)              { location.replace(URL_LOGIN);  return; }
            if (dados.tipo !== 'adotante')  { location.replace(URL_INICIO); return; }

            if (dados.nome) $('painel-nome').textContent = dados.nome;
        })
        .catch(() => { /* sem resposta: mantém a página como está */ });

    // ==================================================
    // 2. FORMATAÇÃO
    // ==================================================
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

    const soDigitos = (v) => String(v || '').replace(/\D/g, '');

    // 41990000001 -> (41) 99000-0001  (se não tiver 10 ou 11 dígitos, devolve como está)
    function formatarTelefone(valor) {
        const d = soDigitos(valor);
        if (d.length === 11) return `(${d.slice(0, 2)}) ${d.slice(2, 7)}-${d.slice(7)}`;
        if (d.length === 10) return `(${d.slice(0, 2)}) ${d.slice(2, 6)}-${d.slice(6)}`;
        return String(valor || '');
    }

    // Número no formato do WhatsApp (DDI + DDD + número). null se o telefone não servir.
    function numeroWhatsapp(telefone) {
        const d = soDigitos(telefone);
        if (d.length === 10 || d.length === 11) return '55' + d;                       // sem DDI: assume Brasil
        if ((d.length === 12 || d.length === 13) && d.startsWith('55')) return d;      // já com DDI
        return null;
    }

    function linkWhatsapp(a, numero) {
        const texto = `Olá! Vi o(a) ${a.nome_animal} no Adotaí e tenho interesse em adotá-lo(a). Podemos conversar?`;
        return `https://wa.me/${numero}?text=${encodeURIComponent(texto)}`;
    }

    // ==================================================
    // 3. CARD DO ANIMAL (mesma estrutura do card do "Meus animais")
    // ==================================================
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

    function iconeWhatsapp() {
        const ns = 'http://www.w3.org/2000/svg';
        const svg = document.createElementNS(ns, 'svg');
        svg.setAttribute('viewBox', '0 0 24 24');
        svg.setAttribute('width', '18');
        svg.setAttribute('height', '18');
        svg.setAttribute('fill', 'none');
        svg.setAttribute('stroke', 'currentColor');
        svg.setAttribute('stroke-width', '2');
        svg.setAttribute('stroke-linecap', 'round');
        svg.setAttribute('stroke-linejoin', 'round');
        svg.setAttribute('aria-hidden', 'true');
        const path = document.createElementNS(ns, 'path');
        // balão de conversa
        path.setAttribute('d', 'M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z');
        svg.appendChild(path);
        return svg;
    }

    // Área do protetor: nome, telefone e o botão "Entrar em Contato"
    function criarContato(a) {
        const area = criar('div', 'desejo-contato');

        const protetor = criar('div', 'desejo-protetor');
        protetor.appendChild(criar('span', '', 'Protetor'));
        protetor.appendChild(criar('strong', '', a.protetor_nome || 'Não informado'));
        if (a.telefone_protetor) protetor.appendChild(criar('span', '', formatarTelefone(a.telefone_protetor)));
        area.appendChild(protetor);

        const numero = numeroWhatsapp(a.telefone_protetor);
        const adotado = a.status_adocao === 'Adotado';

        let botao;
        if (adotado || !numero) {
            // sem link: botão desativado explicando o motivo
            botao = criar('button', 'btn-contato');
            botao.type = 'button';
            botao.disabled = true;
            botao.append(adotado ? 'Animal já adotado' : 'Contato não informado');
        } else {
            botao = criar('a', 'btn-contato');
            botao.href = linkWhatsapp(a, numero);
            botao.target = '_blank';
            botao.rel = 'noopener noreferrer';
            botao.append(iconeWhatsapp(), 'Entrar em Contato');
            botao.setAttribute('aria-label', `Entrar em contato com o protetor de ${a.nome_animal} pelo WhatsApp`);
        }
        area.appendChild(botao);

        return area;
    }

    function criarCardAnimal(a) {
        const info = criar('div', 'animal-info');

        // Nome + selo do andamento da adoção
        const topo = criar('div', 'animal-topo');
        topo.appendChild(criar('h3', 'animal-nome', a.nome_animal));
        if (a.status_adocao) topo.appendChild(selo(a.status_adocao, a.status_adocao));
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

        // Cor e convivência (só aparecem se informados)
        const extras = [
            a.cor ? `Cor: ${a.cor}` : null,
            ['Sim', 'Não'].includes(a.compatibilidade_criancas) ? `Crianças: ${a.compatibilidade_criancas}` : null,
            ['Sim', 'Não'].includes(a.compatibilidade_animais) ? `Outros animais: ${a.compatibilidade_animais}` : null,
        ].filter(Boolean).join(' · ');
        if (extras) info.appendChild(criar('p', 'animal-detalhes', extras));

        // Cuidados de saúde já feitos
        const cuidados = [['castrado', 'Castrado'], ['vacinado', 'Vacinado'], ['vermifugado', 'Vermifugado']]
            .filter(([campo]) => Number(a[campo]) === 1);
        if (cuidados.length) {
            const chips = criar('ul', 'animal-chips');
            cuidados.forEach(([, rotulo]) => chips.appendChild(criar('li', 'animal-chip', rotulo)));
            info.appendChild(chips);
        }

        // Textos longos: aparecem completos, com as quebras de linha digitadas
        if (a.descricao) {
            const bloco = criar('div', 'animal-bloco');
            bloco.append(criar('strong', 'animal-bloco-rotulo', 'Descrição'), criar('p', 'animal-texto', a.descricao));
            info.appendChild(bloco);
        }
        if (a.condicao_saude) {
            const bloco = criar('div', 'animal-bloco');
            bloco.append(criar('strong', 'animal-bloco-rotulo', 'Condição de saúde'), criar('p', 'animal-texto', a.condicao_saude));
            info.appendChild(bloco);
        }

        info.appendChild(criarContato(a));

        const card = criar('article', 'animal-item');
        card.append(criarFotoAnimal(a), info);
        return card;
    }

    // ==================================================
    // 4. LISTA
    // ==================================================
    const lista      = $('desejos-lista');
    const vazio      = $('desejos-vazio');
    const resumo     = $('desejos-resumo');
    const carregando = $('desejos-carregando');
    const erroBox    = $('desejos-erro');
    const erroTexto  = $('desejos-erro-texto');

    let pedidoAtual = 0;   // só a resposta do pedido mais recente é desenhada

    function mostrarAnimais(animais) {
        lista.replaceChildren(...animais.map(criarCardAnimal));
        vazio.hidden = animais.length > 0;
        resumo.hidden = animais.length === 0;
        resumo.textContent = animais.length === 1 ? '1 animal na lista' : `${animais.length} animais na lista`;
    }

    function mostrarErro(texto) {
        erroTexto.textContent = texto;
        erroBox.hidden = false;
    }

    async function carregarLista() {
        const meuPedido = ++pedidoAtual;

        erroBox.hidden = true;
        if (!lista.hasChildNodes()) {   // 1ª vez: avisa que está carregando
            vazio.hidden = true;
            carregando.hidden = false;
        }

        try {
            const resposta = await fetch(URL_LISTA, { credentials: 'same-origin', cache: 'no-store' });
            const corpo = await resposta.json().catch(() => null);

            if (meuPedido !== pedidoAtual) return;

            if (EXIGIR_ADOTANTE && resposta.status === 401) { location.replace(URL_LOGIN);  return; }
            if (EXIGIR_ADOTANTE && resposta.status === 403 && corpo && corpo.erro === 'tipo_nao_permitido') {
                location.replace(URL_INICIO);
                return;
            }

            if (!resposta.ok || !corpo || !corpo.ok || !Array.isArray(corpo.animais)) {
                mostrarErro((corpo && corpo.mensagem) || 'Não foi possível carregar a sua lista agora.');
                return;
            }

            mostrarAnimais(corpo.animais);
        } catch (erro) {
            if (meuPedido !== pedidoAtual) return;
            mostrarErro('Não foi possível se conectar ao servidor. Verifique sua conexão e tente de novo.');
        } finally {
            if (meuPedido === pedidoAtual) carregando.hidden = true;
        }
    }

    $('desejos-tentar').addEventListener('click', carregarLista);

    carregarLista();
})();
