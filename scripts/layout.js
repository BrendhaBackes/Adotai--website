// =====================================================
// Layout compartilhado: gera o header e o footer de todas as páginas.
//
// Como usar em cada página:
//   1) no <head>, ANTES do header_auth.js:
//        <script src="../scripts/layout.js" defer></script>      (index.html: ./scripts/layout.js)
//   2) no <body>, no lugar do header e do footer escritos à mão:
//        <header id="site-header"></header>
//        ...
//        <footer id="site-footer"></footer>
//
// O script descobre sozinho o caminho da raiz do site a partir do próprio
// endereço, então funciona igual em index.html e em pages/.
// A ordem importa: o header_auth.js procura o #adoption-header, que é criado aqui.
// =====================================================
(function () {
    const base = new URL('../', document.currentScript.src);   // raiz do site (pasta adotai/)
    const url = (caminho) => new URL(caminho, base).href;

    // ==================================================
    // MENU  (edite aqui!)
    // Para adicionar, mudar ou remover um link do menu, mexa só nesta lista.
    // "caminho" é a partir da RAIZ do site (a pasta adotai/).
    // ==================================================
    const MENU = [
        { texto: 'Inicio',       caminho: 'index.html' },
        { texto: 'Animais',      caminho: 'pages/catalogo.html' },
        { texto: 'Planos',       caminho: 'pages/catalogo_planos.html' },
        { texto: 'Veterinários', caminho: 'pages/catalogo_veterinario.html' },
        { texto: 'Contato',      caminho: '#footer-contato' },   // rola até o footer da própria página
    ];

    // ==================================================
    // Qual link do menu é a página atual? (ganha aria-current="page")
    // ==================================================
    function caminhoNormalizado(p) {
        return p.endsWith('/') ? p + 'index.html' : p;
    }
    const paginaAtual = caminhoNormalizado(location.pathname);

    function linkDoMenu(item) {
        if (item.caminho.startsWith('#')) {
            return `<a href="${item.caminho}">${item.texto}</a>`;
        }
        const destino = url(item.caminho);
        const atual = caminhoNormalizado(new URL(destino).pathname) === paginaAtual;
        return `<a href="${destino}"${atual ? ' aria-current="page"' : ''}>${item.texto}</a>`;
    }

    // ==================================================
    // HEADER
    // ==================================================
    const HEADER = `
        <a class="brand" href="${url('index.html')}">
            <img class="brand-icon" src="${url('images/logo_img.png')}" alt="Dog Logo">
            <img class="brand-text" src="${url('images/logo_text.png')}" alt="Dog Logo Text">
        </a>

        <!-- Botão hambúrguer (só aparece no celular/tablet) -->
        <input type="checkbox" id="menu-toggle" aria-label="Abrir menu">
        <label for="menu-toggle" class="menu-btn" aria-hidden="true">
            <span></span><span></span><span></span>
        </label>

        <nav>
            ${MENU.map(linkDoMenu).join('\n            ')}
        </nav>

        <a id="adoption-header" href="${url('pages/login.html')}">
            <img style="height: 30px;" src="${url('images/user_icon.png')}" alt="Entrar">
        </a>
    `;

    // ==================================================
    // FOOTER
    // ==================================================
    const FOOTER = `
        <div id="footer-logo">
            <div class="footer-brand">
                <img src="${url('images/logo_img_footer2.png')}" alt="">
                <img src="${url('images/logo_text_footer.png')}" alt="Adotaí">
            </div>
            <p>
                Somos uma organização, sem fins lucrativos
                que resgata, cuida e encontra lares cheios
                de amor para animais abandonados.
            </p>
        </div>
        <div id="footer-contato">
            <h3>Contato</h3>
            <p>contato@adotai.org ✉</p>
            <p>(99) 9 9999-9999 ☏</p>
            <p>Curitiba - PR 🖈</p>
        </div>
    `;

    // ==================================================
    // Insere nos marcadores da página
    // ==================================================
    const header = document.getElementById('site-header');
    const footer = document.getElementById('site-footer');

    if (header) header.innerHTML = HEADER;
    if (footer) footer.innerHTML = FOOTER;
})();
