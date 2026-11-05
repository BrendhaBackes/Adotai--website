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
            {
                texto: 'Painel',
                caminho: 'pages/painel.html'
            },
        ],
        protetor: [
            { 
                texto: 'Cadastrar Animais',
                caminho: 'pages/cadastrar_animal.html'
            },
        ],
        veterinario: [
            { 
                texto: 'Cadastrar Planos', 
                caminho: 'pages/cadastrar_plano.html'
            },
        ],
        adotante: [
            // por enquanto só o "Sair"
        ],
    };

    // Aparece no final do dropdown de TODOS os tipos
    const ITEM_SAIR = { 
        texto: 'Sair', 
        caminho: 'api/logout.php'
    };

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

        menu.append(saudacao, botao, lista);
        iconeOriginal.replaceWith(menu);

        // abrir / fechar
        function definir(aberto) {
            menu.classList.toggle('aberto', aberto);
            botao.setAttribute('aria-expanded', String(aberto));
        }

        botao.addEventListener('click', () => definir(!menu.classList.contains('aberto')));

        document.addEventListener('click', e => {
            if (!menu.contains(e.target)) definir(false);
        });

        document.addEventListener('keydown', e => {
            if (e.key === 'Escape' && menu.classList.contains('aberto')) {
                definir(false);
                botao.focus();
            }
        });
    }
})();
