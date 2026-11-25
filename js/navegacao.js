// Botões com data-href levam o usuário para outra página (substitui o onclick inline, bloqueado pela CSP).
document.querySelectorAll('[data-href]').forEach(function (el) {
    el.addEventListener('click', function () { window.location.href = el.dataset.href; });
});
