// Minha conta: animais favoritos, com link para adotar e botão para remover.
// Todo texto vindo do servidor entra na página com textContent (nunca innerHTML).
(function () {
    const lista = document.getElementById('listaFavoritos');
    const vazio = document.getElementById('semFavoritos');
    if (!lista || !vazio) return;

    function criar(tag, classe, texto) {
        const el = document.createElement(tag);
        if (classe) el.className = classe;
        if (texto !== undefined) el.textContent = texto;
        return el;
    }

    function cartao(item) {
        const bloco = criar('div', 'item-painel');
        const titulo = item.nome_animal || (item.descricao.length > 60 ? item.descricao.slice(0, 59) + '…' : item.descricao);
        bloco.appendChild(criar('h3', '', titulo));
        bloco.appendChild(criar('p', 'info', item.localizacao || 'Localização não informada'));

        const disponivel = item.status === 'aprovado';
        bloco.appendChild(criar('p', 'info', disponivel ? 'Disponível para adoção' : 'Já foi adotado'));

        const acoes = criar('div', 'acoes-painel');
        if (disponivel) {
            const link = criar('a', 'btn btn-laranja btn-pequeno', 'Quero adotar');
            link.href = './solicitar-adocao.html?animal=' + encodeURIComponent(item.id);
            acoes.appendChild(link);
        }
        const remover = criar('button', 'btn btn-contorno btn-pequeno', 'Remover dos favoritos');
        remover.type = 'button';
        remover.addEventListener('click', async () => {
            remover.disabled = true;
            const resultado = await window.Favoritos.alternar(item.id, false);
            if (!resultado.ok) {
                alert(resultado.mensagem || 'Não foi possível remover.');
                remover.disabled = false;
                return;
            }
            carregar();
        });
        acoes.appendChild(remover);
        bloco.appendChild(acoes);
        return bloco;
    }

    async function carregar() {
        try {
            const resposta = await fetch('/backend/favoritos.php?acao=listar', { cache: 'no-store', credentials: 'same-origin' });
            const dados = await resposta.json().catch(() => ({}));
            if (!resposta.ok || !dados.sucesso) throw new Error(dados.mensagem || 'Não foi possível carregar seus favoritos.');
            lista.replaceChildren();
            dados.itens.forEach((item) => lista.appendChild(cartao(item)));
            vazio.hidden = dados.itens.length > 0;
        } catch (erro) {
            lista.replaceChildren();
            vazio.hidden = false;
            vazio.textContent = erro.message;
        }
    }

    carregar();
})();
