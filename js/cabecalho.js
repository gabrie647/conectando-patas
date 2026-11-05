// Comportamento comum da barra do site: campo "Buscar animais".
// Na home, filtra os cards na hora; nas outras páginas, leva para a home com a busca aplicada.
(function () {
    const proprio = document.currentScript || document.querySelector('script[src$="cabecalho.js"]');
    const raiz = proprio ? new URL('../', proprio.src).href : './';
    const campos = document.querySelectorAll('.cp-campo-busca');
    if (!campos.length) return;

    const paginaHome = !!document.getElementById('listaAnimais');
    const parametros = new URLSearchParams(location.search);
    const termoInicial = parametros.get('busca') || '';

    campos.forEach(campo => {
        campo.value = termoInicial;
        campo.addEventListener('keydown', evento => {
            if (evento.key !== 'Enter') return;
            evento.preventDefault();
            const termo = campo.value.trim();
            if (paginaHome) {
                filtrar(termo);
            } else {
                location.href = raiz + 'index.html' +
                    (termo ? '?busca=' + encodeURIComponent(termo) : '') + '#animais';
            }
        });
        campo.addEventListener('input', () => {
            if (paginaHome) filtrar(campo.value.trim());
        });
    });

    function filtrar(termo) {
        const cards = document.querySelectorAll('.card-animal');
        cards.forEach(card => {
            card.hidden = !card.textContent.toLocaleLowerCase('pt-BR')
                .includes(termo.toLocaleLowerCase('pt-BR'));
        });
        const lista = document.getElementById('listaAnimais');
        if (lista) {
            lista.style.transform = 'translateX(0)';
            lista.style.transition = 'none';
        }
    }

    if (paginaHome && termoInicial) filtrar(termoInicial);
})();
