/* Vitrine dinâmica da HOME: usa os mesmos animais públicos do banco PostgreSQL. */
(() => {
    const lista = document.getElementById('listaAnimais');
    if (!lista) return;

    const campoBusca = document.querySelector('.cp-campo-busca');
    const rotulosEspecie = { cao: 'Cão', gato: 'Gato', outro: 'Outro' };
    const rotulosIdade = { filhote: 'Filhote', adulto: 'Adulto', idoso: 'Idoso' };
    const limite = 6;
    let termo = '';
    let idsAtuais = '';
    let primeiraCarga = true;
    let requisicaoEmAndamento = false;

    const criar = (tag, classe, texto) => {
        const el = document.createElement(tag);
        if (classe) el.className = classe;
        if (texto !== undefined) el.textContent = texto;
        return el;
    };

    function criarCard(pet) {
        const nome = pet.nome_animal || 'Animal para adoção';
        const card = criar('article', 'card-animal');
        card.dataset.petId = String(pet.id);

        const imagem = criar('div', 'imagem-animal');
        const img = document.createElement('img');
        img.src = pet.foto;
        img.alt = 'Foto de ' + nome;
        img.loading = 'lazy';
        img.decoding = 'async';
        imagem.appendChild(img);

        const tags = criar('div', 'tags-animal');
        tags.appendChild(criar('span', 'tag', rotulosIdade[pet.idade] || 'Disponível'));
        tags.appendChild(criar('span', 'tag tipo', rotulosEspecie[pet.especie] || 'Animal'));
        imagem.appendChild(tags);

        const favorito = criar('button', 'botao-favorito');
        favorito.type = 'button';
        favorito.setAttribute('aria-label', 'Favoritar ' + nome);
        favorito.setAttribute('aria-pressed', 'false');
        favorito.appendChild(criar('i', 'fa-regular fa-heart'));
        window.Favoritos.ligarBotao(favorito, pet.id);
        imagem.appendChild(favorito);

        const dados = criar('div', 'dados-animal');
        const nomeIdade = criar('div', 'nome-idade');
        nomeIdade.appendChild(criar('h3', '', nome));
        nomeIdade.appendChild(criar('span', '', rotulosIdade[pet.idade] || ''));
        dados.appendChild(nomeIdade);

        const local = criar('p', 'localizacao');
        local.appendChild(criar('i', 'fa-solid fa-location-dot'));
        local.appendChild(document.createTextNode(' ' + (pet.localizacao || 'Localização não informada')));
        dados.appendChild(local);
        dados.appendChild(criar('p', 'descricao-animal', pet.descricao || 'Conheça este animal e saiba mais sobre sua história.'));

        const detalhes = criar('a', 'btn-card');
        detalhes.href = './animal.html?id=' + encodeURIComponent(pet.id);
        detalhes.appendChild(document.createTextNode('VER DETALHES '));
        detalhes.appendChild(criar('i', 'fa-solid fa-arrow-right'));
        dados.appendChild(detalhes);

        card.appendChild(imagem);
        card.appendChild(dados);
        return card;
    }

    function aplicarBusca() {
        const busca = termo.toLocaleLowerCase('pt-BR');
        lista.querySelectorAll('.card-animal').forEach(card => {
            card.hidden = busca !== '' && !card.textContent.toLocaleLowerCase('pt-BR').includes(busca);
        });
        lista.style.transform = 'translateX(0)';
        window.dispatchEvent(new Event('resize'));
    }

    async function carregar() {
        if (requisicaoEmAndamento || document.hidden) return;
        requisicaoEmAndamento = true;
        try {
            const resposta = await fetch('/backend/animais.php?pagina=1', {
                method: 'GET',
                cache: 'no-store',
                headers: { 'Accept': 'application/json' }
            });
            if (!resposta.ok) throw new Error('Falha ao consultar a vitrine.');
            const dados = await resposta.json();
            if (!dados.sucesso || !Array.isArray(dados.itens)) throw new Error('Resposta inválida da vitrine.');

            const animais = dados.itens.slice(0, limite);
            const novosIds = animais.map(p => p.id).join(',');
            if (novosIds !== idsAtuais || primeiraCarga) {
                idsAtuais = novosIds;
                primeiraCarga = false;
                lista.replaceChildren();
                if (animais.length) {
                    animais.forEach(pet => lista.appendChild(criarCard(pet)));
                } else {
                    const aviso = criar('p', 'vitrine-vazia', 'Ainda não há animais cadastrados. Seja o primeiro a cadastrar um amiguinho!');
                    lista.appendChild(aviso);
                }
                aplicarBusca();
            }
        } catch (erro) {
            console.error('Não foi possível atualizar os animais da HOME:', erro);
            if (!idsAtuais) {
                lista.replaceChildren(criar('p', 'vitrine-vazia', 'Não foi possível carregar os animais agora. Tente novamente em instantes.'));
            }
        } finally {
            requisicaoEmAndamento = false;
        }
    }

    campoBusca?.addEventListener('input', () => {
        termo = campoBusca.value.trim();
        aplicarBusca();
    });

    const buscaInicial = new URLSearchParams(window.location.search).get('busca');
    if (buscaInicial) {
        termo = buscaInicial.trim();
        if (campoBusca) campoBusca.value = termo;
    }

    carregar();
    window.setInterval(carregar, 20000);
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) carregar();
    });
})();
