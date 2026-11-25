// Proteção CSRF: envia o token da sessão em todo pedido de alteração (POST) feito ao próprio site.
// Carregado no <head> de todas as páginas, antes dos demais scripts. Não altera a interface.
(function () {
    const URL_TOKEN = '/backend/csrf.php';
    const fetchOriginal = window.fetch.bind(window);
    let tokenAtual = null;

    async function buscarToken() {
        const resposta = await fetchOriginal(URL_TOKEN, { credentials: 'same-origin', cache: 'no-store' });
        const dados = await resposta.json();
        tokenAtual = dados.token || null;
        return tokenAtual;
    }

    async function obterToken() {
        return tokenAtual || buscarToken();
    }

    window.fetch = async function (entrada, opcoes) {
        opcoes = opcoes || {};
        const url = typeof entrada === 'string' ? entrada : entrada.url;
        const metodo = (opcoes.method || (typeof entrada === 'object' && entrada.method) || 'GET').toUpperCase();
        const mesmaOrigem = new URL(url, location.href).origin === location.origin;

        if (metodo === 'GET' || metodo === 'HEAD' || !mesmaOrigem) {
            return fetchOriginal(entrada, opcoes);
        }

        async function enviar() {
            const token = await obterToken();
            const cabecalhos = new Headers(opcoes.headers || {});
            cabecalhos.set('X-CSRF-Token', token || '');
            return fetchOriginal(entrada, Object.assign({}, opcoes, { headers: cabecalhos }));
        }

        let resposta = await enviar();
        // 419 = token expirado (sessão renovada ou encerrada): busca um novo e tenta de novo uma vez.
        if (resposta.status === 419) {
            tokenAtual = null;
            resposta = await enviar();
        }
        return resposta;
    };
})();
