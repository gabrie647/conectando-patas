(function () {
    const estado = document.getElementById('estadoAnimal');
    const conteudo = document.getElementById('conteudoAnimal');
    const params = new URLSearchParams(window.location.search);
    const id = params.get('id');
    const especies = { cao: 'Cão', gato: 'Gato', outro: 'Outro animal' };
    const idades = { filhote: 'Filhote', adulto: 'Adulto', idoso: 'Idoso' };

    function texto(idElemento, valor) {
        document.getElementById(idElemento).textContent = valor || '';
    }

    function dataPtBr(valor) {
        if (!valor) return '';
        const data = new Date(String(valor).replace(' ', 'T') + 'Z');
        return Number.isNaN(data.getTime()) ? '' : 'Cadastrado em ' + data.toLocaleDateString('pt-BR');
    }

    async function carregar() {
        if (!id || !/^\d+$/.test(id)) {
            estado.textContent = 'Não encontramos esse animal. Volte à página de animais e escolha um card.';
            return;
        }
        try {
            const resposta = await fetch('/backend/animais.php?id=' + encodeURIComponent(id), { cache: 'no-store' });
            const dados = await resposta.json();
            if (!resposta.ok || !dados.sucesso || !dados.animal) {
                throw new Error(dados.mensagem || 'Este animal não está disponível no momento.');
            }
            const animal = dados.animal;
            const nome = animal.nome_animal || 'Animal para adoção';
            const foto = document.getElementById('fotoAnimal');
            foto.src = animal.foto;
            foto.alt = 'Foto de ' + nome;
            texto('idadeAnimal', idades[animal.idade] || 'Idade não informada');
            texto('especieAnimal', especies[animal.especie] || 'Espécie não informada');
            texto('nomeAnimal', nome);
            texto('localAnimal', animal.localizacao ? '⌖ ' + animal.localizacao : 'Localização não informada');
            texto('descricaoAnimal', animal.descricao || 'Entre em contato para saber mais sobre este animal.');
            texto('dataAnimal', dataPtBr(animal.criado_em));
            document.getElementById('btnAdotar').href = './solicitar-adocao.html?animal=' + encodeURIComponent(animal.id);
            document.title = nome + ' | Conectando Patas';
            estado.hidden = true;
            conteudo.hidden = false;
        } catch (erro) {
            estado.textContent = erro.message || 'Não foi possível carregar as informações. Tente novamente.';
        }
    }
    carregar();
})();
