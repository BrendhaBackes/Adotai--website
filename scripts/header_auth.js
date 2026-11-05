// =====================================================
// Header: se o usuário estiver logado, troca o ícone que levava
// ao login por "Olá, Nome" + um dropdown (Painel / Sair).
// Se não estiver logado, não mexe em nada.
//
// Este script descobre sozinho o caminho da raiz do site a partir
// do próprio endereço, então funciona igual em index.html e em pages/.
// =====================================================
(function () {
    const base = new URL('../', document.currentScript.src);   // raiz do site

    // ==================================================
    // ITENS DO DROPDOWN POR TIPO DE USUÁRIO  (edite aqui!)
    //
    // A chave é o tipo que vem de $_SESSION['usuario_tipo']:
    //   'admin' | 'protetor' | 'veterinario' | 'adotante'
    //
    // Cada item tem:
    //   texto   -> o que aparece no menu
    //   caminho -> a página, a partir da RAIZ do site (a pasta adotai/)
    //
    // Para ADICIONAR um item:  inclua uma linha { texto: '...', caminho: '...' }
    // Para MUDAR o destino:    altere só o "caminho" da linha
    // Para REMOVER:            apague a linha
    // Para um tipo novo:       crie uma chave nova com sua lista
    // ==================================================
    const ITENS_POR_TIPO = {
        admin: [
            { texto: 'Painel',            caminho: 'pages/painel.html' },
        ],
        protetor: [
            { texto: 'Cadastrar Animais', caminho: 'pages/cadastrar_animal.html' },
        ],
        veterinario: [
            { texto: 'Cadastrar Planos',  caminho: 'pages/cadastrar_plano.html' },
        ],
        adotante: [
            // por enquanto só o "Sair"
        ],
    };

    // Aparece no final do dropdown de TODOS os tipos
    const ITEM_SAIR = { texto: 'Sair', caminho: 'api/logout.php' };

    // Tipos de usuário que têm o ícone "Lista de interesses" no header.
    // Mantenha igual ao TIPOS_PERMITIDOS do api/interesse.php.
    const TIPOS_COM_INTERESSES = ['adotante'];

    const URL_INTERESSES = new URL('api/interesses.php', base).href;
    const URL_CATALOGO   = new URL('pages/catalogo.html', base).href;
    const FOTO_PADRAO    = new URL('images/franky.jpeg', base).href;   // se o animal não tiver foto
    const PASTA_FOTOS    = base;                                       // mesma pasta usada no catálogo

    const URL_SESSAO = new URL('api/sessao.php', base).href;
    const URL_CSS    = new URL('styles/header_user.css', base).href;

    const iconeOriginal = document.getElementById('adoption-header');
    if (!iconeOriginal) return;

    fetch(URL_SESSAO, { credentials: 'same-origin', cache: 'no-store' })
        .then(r => (r.ok ? r.json() : null))
        .then(dados => { if (dados && dados.logado) montarMenu(dados); })
        .catch(() => { /* sem resposta: mantém o header de visitante */ });

    function criar(tag, classe, texto) {
        const el = document.createElement(tag);
        if (classe) el.className = classe;
        if (texto) el.textContent = texto;   // textContent: nunca interpreta HTML
        return el;
    }

    // Cria um <li><a> a partir de { texto, caminho }
    function itemLista(item) {
        const li = document.createElement('li');
        const a = criar('a', '', item.texto);
        a.href = new URL(item.caminho, base).href;   // caminho relativo à raiz do site
        li.appendChild(a);
        return li;
    }

    // Ícone de coração (SVG pronto, sem dados do usuário)
    function iconeCoracao() {
        const ns = 'http://www.w3.org/2000/svg';
        const svg = document.createElementNS(ns, 'svg');
        svg.setAttribute('viewBox', '0 0 24 24');
        svg.setAttribute('width', '26');
        svg.setAttribute('height', '26');
        svg.setAttribute('fill', 'none');
        svg.setAttribute('stroke', 'currentColor');
        svg.setAttribute('stroke-width', '2');
        svg.setAttribute('stroke-linecap', 'round');
        svg.setAttribute('stroke-linejoin', 'round');
        svg.setAttribute('aria-hidden', 'true');
        const path = document.createElementNS(ns, 'path');
        path.setAttribute('d', 'M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z');
        svg.appendChild(path);
        return svg;
    }

    // ==================================================
    // LISTA DE INTERESSES (ícone de coração + dropdown)
    // Mostra versão simplificada (foto, nome, espécie e status) dos animais
    // em que o usuário clicou "Quero adotar".
    // aoAbrir: função chamada quando este dropdown abre (para fechar o outro).
    // ==================================================
    function criarInteresses(aoAbrir) {
        let itens = [];
        let estado = 'carregando';   // 'carregando' | 'ok' | 'erro'

        const wrap = criar('div', 'interesses-wrap');

        const botao = criar('button', 'interesses-btn');
        botao.type = 'button';
        botao.setAttribute('aria-haspopup', 'true');
        botao.setAttribute('aria-expanded', 'false');
        botao.setAttribute('aria-controls', 'interesses-dropdown');
        const contador = criar('span', 'interesses-badge', '0');
        contador.hidden = true;
        botao.append(iconeCoracao(), criar('span', 'interesses-btn-texto', 'Lista de interesses'), contador);

        const painel = criar('div', 'interesses-dropdown');
        painel.id = 'interesses-dropdown';
        const lista = criar('ul', 'interesses-lista');
        const rodape = criar('a', 'interesses-rodape', 'Ver mais animais');
        rodape.href = URL_CATALOGO;
        painel.append(criar('h3', 'interesses-titulo', 'Lista de interesses'), lista, rodape);

        wrap.append(botao, painel);

        function urlFoto(a) {
            return a.foto_url ? new URL(a.foto_url, PASTA_FOTOS).href : FOTO_PADRAO;
        }

        function criarItem(a) {
            const li = criar('li', 'interesse-item');

            const img = criar('img', 'interesse-foto');
            img.alt = '';
            img.loading = 'lazy';
            img.src = urlFoto(a);
            img.addEventListener('error', () => {
                if (img.src !== FOTO_PADRAO) img.src = FOTO_PADRAO;
            }, { once: true });

            const texto = criar('div', 'interesse-texto');
            texto.append(
                criar('strong', 'interesse-nome', a.nome_animal),
                criar('span', 'interesse-sub', [a.especie, a.status_adocao].filter(Boolean).join(' • '))
            );

            li.append(img, texto);
            return li;
        }

        function desenhar() {
            contador.textContent = String(itens.length);
            contador.hidden = itens.length === 0;
            botao.setAttribute('aria-label', `Lista de interesses (${itens.length})`);

            if (estado === 'ok' && itens.length) {
                lista.replaceChildren(...itens.map(criarItem));
                return;
            }

            const mensagem = estado === 'carregando' ? 'Carregando...'
                : estado === 'erro' ? 'Não foi possível carregar sua lista agora.'
                : 'Você ainda não demonstrou interesse em nenhum animal.';
            lista.replaceChildren(criar('li', 'interesses-vazio', mensagem));
        }

        function definir(aberto) {
            wrap.classList.toggle('aberto', aberto);
            botao.setAttribute('aria-expanded', String(aberto));
        }

        botao.addEventListener('click', () => {
            const abrir = !wrap.classList.contains('aberto');
            if (abrir) aoAbrir();
            definir(abrir);
        });

        // Chamado quando o catálogo dispara "Quero adotar" com sucesso
        function adicionar(a) {
            if (!a || itens.some(i => i.id_animal === a.id_animal)) return;
            itens.push(a);
            itens.sort((x, y) => String(x.nome_animal).localeCompare(String(y.nome_animal), 'pt-BR'));
            estado = 'ok';
            desenhar();

            // pequena animação no ícone para chamar atenção
            botao.classList.remove('pulsar');
            void botao.offsetWidth;   // reinicia a animação
            botao.classList.add('pulsar');
        }

        window.addEventListener('interesse:registrado', e => adicionar(e.detail));

        // Carrega a lista que já está salva no banco
        desenhar();
        fetch(URL_INTERESSES, { credentials: 'same-origin', cache: 'no-store' })
            .then(r => (r.ok ? r.json() : Promise.reject(new Error('HTTP ' + r.status))))
            .then(dados => {
                if (!dados.ok) throw new Error('Resposta inválida');
                // junta com o que já foi adicionado nesta página, sem repetir
                const ids = new Set(dados.animais.map(a => a.id_animal));
                itens = [...dados.animais, ...itens.filter(i => !ids.has(i.id_animal))];
                estado = 'ok';
                desenhar();
            })
            .catch(() => {
                estado = itens.length ? 'ok' : 'erro';
                desenhar();
            });

        return {
            elemento: wrap,
            botao,
            fechar: () => definir(false),
            aberto: () => wrap.classList.contains('aberto'),
        };
    }

    // ==================================================
    // MENU DO USUÁRIO
    // ==================================================
    function montarMenu(dados) {
        // CSS do menu logado (só é carregado quando precisa)
        const css = criar('link');
        css.rel = 'stylesheet';
        css.href = URL_CSS;
        document.head.appendChild(css);

        const primeiroNome = (dados.nome || '').trim().split(/\s+/)[0] || 'usuário';

        const menu = criar('div', 'user-menu');

        const saudacao = criar('span', 'user-saudacao', 'Olá, ' + primeiroNome);

        // botão com o mesmo ícone de usuário que já estava no header
        const botao = criar('button', 'user-btn');
        botao.type = 'button';
        botao.setAttribute('aria-label', 'Menu do usuário');
        botao.setAttribute('aria-haspopup', 'true');
        botao.setAttribute('aria-expanded', 'false');
        botao.setAttribute('aria-controls', 'user-dropdown');
        const imgOriginal = iconeOriginal.querySelector('img');
        if (imgOriginal) {
            const img = criar('img');
            img.src = imgOriginal.src;
            img.alt = '';
            botao.appendChild(img);
        }

        const lista = criar('ul', 'user-dropdown');
        lista.id = 'user-dropdown';

        // itens do tipo de usuário (se o tipo não estiver na lista, fica só o "Sair")
        const itensDoTipo = ITENS_POR_TIPO[dados.tipo] || [];
        [...itensDoTipo, ITEM_SAIR].forEach(item => lista.appendChild(itemLista(item)));

        // abrir / fechar o dropdown do usuário
        function definir(aberto) {
            menu.classList.toggle('aberto', aberto);
            botao.setAttribute('aria-expanded', String(aberto));
        }

        // ícone de interesses (só para os tipos permitidos)
        const interesses = TIPOS_COM_INTERESSES.includes(dados.tipo)
            ? criarInteresses(() => definir(false))
            : null;

        menu.append(saudacao, ...(interesses ? [interesses.elemento] : []), botao, lista);
        iconeOriginal.replaceWith(menu);

        botao.addEventListener('click', () => {
            const abrir = !menu.classList.contains('aberto');
            if (abrir && interesses) interesses.fechar();
            definir(abrir);
        });

        // clicar fora fecha os dropdowns
        document.addEventListener('click', e => {
            if (!botao.contains(e.target) && !lista.contains(e.target)) definir(false);
            if (interesses && !interesses.elemento.contains(e.target)) interesses.fechar();
        });

        document.addEventListener('keydown', e => {
            if (e.key !== 'Escape') return;

            if (menu.classList.contains('aberto')) {
                definir(false);
                botao.focus();
            }
            if (interesses && interesses.aberto()) {
                interesses.fechar();
                interesses.botao.focus();
            }
        });
    }
})();
