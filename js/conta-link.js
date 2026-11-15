// Quando a pessoa está logada, o lugar do botão "CADASTRE-SE" passa a mostrar a conta
// dela (avatar com iniciais + nome) e leva para a página da conta. O item "CADASTRO"
// do menu e o botão "LOGIN", que não servem mais a quem já tem conta, saem da barra.
// Sem login, nada muda. Só mexe em links do cabeçalho; nenhuma função das páginas é alterada.
(async function () {
    const proprio = document.currentScript || document.querySelector('script[src$="conta-link.js"]');
    if (!proprio) return;
    const destino = new URL('../conta.html', proprio.src).href;

    let sessao = { logado: false };
    try {
        const r = await fetch('/backend/sessao.php', { credentials: 'same-origin' });
        sessao = await r.json();
    } catch (e) { return; }
    if (!sessao.logado || !sessao.usuario) return;

    const nome = String(sessao.usuario.nome || '').trim();
    const sobrenome = String(sessao.usuario.sobrenome || '').trim();
    const iniciais = ((nome.charAt(0) + sobrenome.charAt(0)).toUpperCase()) || '?';

    // Item "CADASTRO" do menu: já tem conta, então sai.
    document.querySelectorAll('.cp-nav a').forEach((a) => {
        if (a.textContent.trim().toUpperCase() === 'CADASTRO') a.remove();
    });

    // Botão "LOGIN": quem já está logado não precisa dele.
    document.querySelectorAll('.cp-login').forEach((a) => a.remove());

    // Botão "CADASTRE-SE" -> conta do usuário
    document.querySelectorAll('.cp-cadastrar').forEach((botao) => {
        const chip = document.createElement('a');
        chip.className = 'cp-conta-usuario';
        chip.href = destino;
        chip.title = 'Minha conta';
        chip.setAttribute('aria-label', 'Minha conta' + (nome ? ' — ' + nome : ''));

        const avatar = document.createElement('span');
        avatar.className = 'cp-avatar';
        avatar.setAttribute('aria-hidden', 'true');
        avatar.textContent = iniciais;

        const rotulo = document.createElement('span');
        rotulo.className = 'cp-conta-nome';
        rotulo.textContent = nome || 'Minha conta';

        chip.append(avatar, rotulo);
        botao.replaceWith(chip);
    });
})();
