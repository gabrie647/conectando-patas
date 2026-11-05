// =====================================================
// ANIMAÇÕES — Conectando Patas
// Revelar ao rolar, barra de progresso, patinhas, marquee e inclinação 3D.
// =====================================================
(function () {
    const root = document.documentElement;
    const semMovimento = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (semMovimento) return;               // quem prefere menos movimento não vê nada disso
    root.classList.add('js-anim');

    // ---------- Barra de progresso ----------
    const barra = document.createElement('div');
    barra.className = 'cp-progresso';
    document.body.appendChild(barra);
    const atualizar = () => {
        const max = root.scrollHeight - innerHeight;
        barra.style.transform = `scaleX(${max > 0 ? scrollY / max : 0})`;
    };
    addEventListener('scroll', atualizar, { passive: true });
    atualizar();

    // ---------- Patinhas flutuando no hero ----------
    const hero = document.querySelector('.banner-principal');
    if (hero) {
        for (let i = 0; i < 9; i++) {
            const p = document.createElement('i');
            p.className = 'fa-solid fa-paw patinha-flutuante';
            p.style.left = (5 + Math.random() * 90) + '%';
            p.style.fontSize = (16 + Math.random() * 30) + 'px';
            p.style.animationDuration = (9 + Math.random() * 9) + 's';
            p.style.animationDelay = (-Math.random() * 12) + 's';
            hero.appendChild(p);
        }

        // ---------- Faixa em movimento logo abaixo do hero ----------
        const faixa = document.createElement('div');
        faixa.className = 'faixa-marquee';
        faixa.setAttribute('aria-hidden', 'true');
        const frases = ['ADOTE', 'RESGATE', 'AME', 'CUIDE', 'TRANSFORME UMA VIDA'];
        const bloco = frases.map(f => `<span>${f}</span>`).join('');
        faixa.innerHTML = `<div class="trilho">${bloco}${bloco}${bloco}${bloco}</div>`;
        hero.insertAdjacentElement('afterend', faixa);
    }

    // ---------- Revelar ao rolar (com escalonamento) ----------
    // Cobre a home, a página de animais, contatos e termos.
    const SELETOR = [
        '.card-valor', '.cabecalho-secao', '.card-animal', '.bloco-citacao', '.coluna-rodape',
        '.cartao-animal', '.card-contato', '.redes', '.frase-contato', '.formulario-contato',
        '.chamada-pagina', '.barra-filtros', '.rodape-coluna', '.estado-vazio', '.conta-bloco', '.conta-resumo'
    ].join(',');

    const obs = new IntersectionObserver((entradas) => {
        entradas.forEach(e => {
            if (e.isIntersecting) { e.target.classList.add('visivel'); obs.unobserve(e.target); }
        });
    }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });

    let contador = 0;
    function registrar(el) {
        if (el.hasAttribute('data-revelar')) return;
        el.setAttribute('data-revelar', '');
        el.style.setProperty('--atraso', ((contador++ % 4) * 0.12) + 's');
        obs.observe(el);
    }
    document.querySelectorAll(SELETOR).forEach(registrar);

    // Cards criados depois pelo JS da página (ex.: lista de animais vinda do servidor)
    new MutationObserver(muts => {
        muts.forEach(m => m.addedNodes.forEach(n => {
            if (n.nodeType !== 1) return;
            if (n.matches && n.matches(SELETOR)) registrar(n);
            n.querySelectorAll && n.querySelectorAll(SELETOR).forEach(registrar);
        }));
    }).observe(document.body, { childList: true, subtree: true });

    // ---------- Inclinação 3D nos cards de animais (só com mouse) ----------
    if (matchMedia('(hover: hover)').matches) {
        const CARD = '.card-animal, .cartao-animal';
        let atual = null;
        document.addEventListener('pointermove', e => {
            const card = e.target.closest && e.target.closest(CARD);
            if (atual && atual !== card) { soltar(atual); }
            if (!card) { atual = null; return; }
            atual = card;
            const r = card.getBoundingClientRect();
            const x = (e.clientX - r.left) / r.width - 0.5;
            const y = (e.clientY - r.top) / r.height - 0.5;
            card.style.transition = 'box-shadow .3s';
            card.style.transform =
                `perspective(900px) rotateY(${x * 9}deg) rotateX(${-y * 9}deg) translateY(-6px)`;
        });
        function soltar(card) {
            card.style.transition = 'transform .5s ease, box-shadow .3s';
            card.style.transform = '';
        }
        document.addEventListener('pointerleave', () => { if (atual) soltar(atual); atual = null; });
    }
})();
