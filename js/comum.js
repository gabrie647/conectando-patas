// Comportamentos comuns do cabeçalho nas páginas internas:
// mostra "Sair" quando há login, o link do painel para administradores
// e leva o botão "Cadastre-se" para o formulário de inscrição.
(async function () {
    const raiz = document.body.dataset.raiz || './';
    const nav = document.querySelector('.navegacao');
    const acoes = document.querySelector('.acoes-cabecalho');
    const botao = acoes ? acoes.querySelector('.btn-principal') : null;
    if (!botao) return;

    let sessao = { logado: false };
    try {
        const resposta = await fetch('/backend/sessao.php', { credentials: 'same-origin' });
        sessao = await resposta.json();
    } catch (erro) {
        console.warn('Não foi possível verificar a sessão:', erro);
    }

    if (sessao.logado) {
        const saudacao = document.createElement('span');
        saudacao.className = 'saudacao';
        saudacao.textContent = 'Olá, ' + (sessao.usuario && sessao.usuario.nome ? sessao.usuario.nome : 'tutor(a)');
        acoes.insertBefore(saudacao, botao);

        botao.textContent = 'SAIR';
        botao.addEventListener('click', async function () {
            botao.disabled = true;
            try { await fetch('/backend/logout.php', { credentials: 'same-origin' }); } catch (e) { /* segue para a home */ }
            window.location.href = raiz + 'index.html';
        });

        if (sessao.admin && nav && !nav.querySelector('a[href$="admin/index.html"]')) {
            const link = document.createElement('a');
            link.href = raiz + 'admin/index.html';
            link.textContent = 'PAINEL';
            nav.appendChild(link);
        }
    } else {
        botao.textContent = 'CADASTRE-SE';
        botao.addEventListener('click', function () {
            window.location.href = raiz + 'Pi-formulario/Pi-formulario-inscricao/index.html';
        });
    }
})();
