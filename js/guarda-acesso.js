// Guarda de acesso: páginas restritas exigem login.
// Oculta a página até confirmar a sessão em /backend/sessao.php.
// Sem login, mostra o aviso "Faça login para continuar".
(function () {
    var raiz = document.documentElement;
    raiz.style.visibility = 'hidden';

    function mostrarPagina() {
        raiz.style.visibility = '';
    }

    function criarAviso() {
        var caixa = document.createElement('div');
        caixa.id = 'cp-aviso-login';
        caixa.style.cssText = 'position:fixed;inset:0;z-index:99999;display:flex;align-items:center;justify-content:center;background:#F9F5E9;padding:24px;visibility:visible;font-family:Poppins,system-ui,sans-serif;';

        var cartao = document.createElement('div');
        cartao.style.cssText = 'max-width:440px;width:100%;background:#fff;border:1px solid #DED9CA;border-radius:20px;box-shadow:0 12px 32px rgba(21,58,36,.10);padding:36px 28px;text-align:center;color:#153A24;';

        var titulo = document.createElement('h2');
        titulo.textContent = 'Faça login para continuar';
        titulo.style.cssText = 'font-family:"Playfair Display",Georgia,serif;font-size:28px;margin:0 0 12px;';

        var texto = document.createElement('p');
        texto.textContent = 'Esta página é exclusiva para usuários cadastrados. Entre na sua conta para acessar.';
        texto.style.cssText = 'color:#4A5F52;font-size:15px;line-height:1.5;margin:0 0 24px;';

        function botao(rotulo, destino, principal) {
            var a = document.createElement('a');
            a.textContent = rotulo;
            a.href = destino;
            a.style.cssText = 'display:block;padding:13px 20px;margin:0 0 10px;border-radius:28px;font-weight:600;font-size:14px;text-decoration:none;' +
                (principal ? 'background:#153A24;color:#fff;' : 'background:#F1E8D4;color:#153A24;');
            return a;
        }

        cartao.appendChild(titulo);
        cartao.appendChild(texto);
        cartao.appendChild(botao('Fazer login', '/login/index.html?next=' + encodeURIComponent(location.pathname), true));
        cartao.appendChild(botao('Criar conta', '/Pi-formulario/Pi-formulario-inscricao/index.html', false));
        cartao.appendChild(botao('Voltar para a home', '/index.html', false));
        caixa.appendChild(cartao);
        document.body.appendChild(caixa);
    }

    function pedirLogin() {
        if (document.body) criarAviso();
        else document.addEventListener('DOMContentLoaded', criarAviso);
    }

    fetch('/backend/sessao.php', { credentials: 'same-origin', cache: 'no-store' })
        .then(function (r) { return r.json(); })
        .then(function (dados) { if (dados && dados.logado) mostrarPagina(); else pedirLogin(); })
        .catch(pedirLogin);
})();
