// Busca do cabeçalho e navegação por botões com data-href.
// Movido de <script> inline por causa da CSP.
(function () {
    const campos = document.querySelectorAll('.cp-campo-busca');
    const caminho = location.pathname;
    const prefixo = caminho.includes('/Pi-formulario/') ? '../../' :
        (caminho.includes('/Contatos/') || caminho.includes('/login/') ? '../' : './');
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
                location.href = prefixo + 'index.html' +
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

// Botões que levam a outra página (substitui o onclick inline).
document.querySelectorAll('[data-href]').forEach(function (el) {
    el.addEventListener('click', function () { window.location.href = el.dataset.href; });
});
