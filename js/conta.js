// =====================================================
// PÁGINA DA CONTA — lê e atualiza os dados via /backend/conta.php
// Todo texto vindo do servidor entra na página com textContent (nunca innerHTML).
// =====================================================
(function () {
    const API = '/backend/conta.php';
    const $ = (id) => document.getElementById(id);

    const aviso = $('aviso');
    let dados = null;

    function criar(tag, classe, texto) {
        const el = document.createElement(tag);
        if (classe) el.className = classe;
        if (texto !== undefined) el.textContent = texto;
        return el;
    }

    function mostrarAviso(texto, ok) {
        aviso.textContent = texto;
        aviso.classList.toggle('ok', !!ok);
        aviso.hidden = false;
        aviso.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
    function esconderAviso() { aviso.hidden = true; }

    function formatarData(texto) {
        const d = new Date(String(texto).replace(' ', 'T') + 'Z');
        return isNaN(d) ? '' : d.toLocaleDateString('pt-BR', { day: '2-digit', month: 'long', year: 'numeric' });
    }

    function irParaLogin() {
        window.location.href = './login/index.html?next=' + encodeURIComponent('/conta.html');
    }

    async function chamar(opcoes) {
        const resposta = await fetch(API, Object.assign({ credentials: 'same-origin' }, opcoes));
        let corpo = {};
        try { corpo = await resposta.json(); } catch (e) { /* resposta sem JSON */ }
        if (resposta.status === 401) { irParaLogin(); throw new Error('Sessão expirada.'); }
        if (!resposta.ok || corpo.sucesso === false) {
            throw new Error(corpo.mensagem || 'Não foi possível concluir a operação.');
        }
        return corpo;
    }

    function enviar(acao, formulario) {
        const corpo = new FormData(formulario);
        corpo.set('acao', acao);
        return chamar({ method: 'POST', body: corpo });
    }

    function ocupar(botao, ocupado, textoOcupado) {
        if (ocupado) {
            botao.dataset.rotulo = botao.innerHTML;
            botao.disabled = true;
            botao.textContent = textoOcupado;
        } else {
            botao.disabled = false;
            if (botao.dataset.rotulo) botao.innerHTML = botao.dataset.rotulo;
        }
    }

    // ---------- Exibição ----------
    function preencher() {
        const u = dados.usuario;
        const nomeCompleto = (u.nome + ' ' + u.sobrenome).trim();

        $('nomeBanner').textContent = u.nome;
        $('nomeCompleto').textContent = nomeCompleto;
        $('emailConta').textContent = u.email;
        const desde = formatarData(u.criado_em);
        $('desdeConta').textContent = desde ? 'Membro desde ' + desde : '';
        $('avatar').textContent = (u.nome.charAt(0) + u.sobrenome.charAt(0)).toUpperCase() || '?';

        ['nome', 'sobrenome', 'email', 'cidade', 'bairro', 'numero'].forEach((campo) => {
            $(campo).value = u[campo] || '';
        });
        $('linkPainel').hidden = !dados.admin;
        listarPets();
    }

    function listarPets() {
        const pets = dados.pets || [];
        $('totalPets').textContent = pets.length;
        $('totalAprovados').textContent = pets.filter((p) => p.status === 'aprovado').length;
        $('totalPendentes').textContent = pets.filter((p) => p.status === 'pendente').length;

        const lista = $('listaPets');
        lista.replaceChildren();
        $('semPets').hidden = pets.length > 0;

        const rotulos = { pendente: 'Em análise', aprovado: 'Publicado', recusado: 'Não aprovado' };

        pets.forEach((pet) => {
            const item = criar('article', 'item-painel');

            if (pet.foto) {
                const img = criar('img', 'miniatura');
                img.src = pet.foto;
                img.alt = 'Foto do animal registrado';
                img.loading = 'lazy';
                item.appendChild(img);
            } else {
                const vazio = criar('div', 'sem-foto');
                const icone = criar('i', 'fa-solid fa-paw');
                icone.setAttribute('aria-hidden', 'true');
                vazio.appendChild(icone);
                item.appendChild(vazio);
            }

            const info = criar('div');
            const titulo = criar('h3', null, 'Registro #' + pet.id + ' ');
            titulo.appendChild(criar('span', 'selo ' + pet.status, rotulos[pet.status] || pet.status));
            info.appendChild(titulo);
            info.appendChild(criar('p', 'info', pet.localizacao + ' • enviado em ' + formatarData(pet.criado_em)));
            info.appendChild(criar('p', 'texto', pet.descricao));

            if (pet.status === 'aprovado') {
                const acoes = criar('div', 'acoes');
                const link = criar('a', 'btn btn-contorno btn-pequeno', 'Ver na lista de animais');
                link.href = './animais.html';
                acoes.appendChild(link);
                info.appendChild(acoes);
            }
            item.appendChild(info);
            lista.appendChild(item);
        });
    }

    // ---------- Carregar ----------
    async function carregar() {
        try {
            dados = await chamar({ method: 'GET' });
            preencher();
            $('carregandoConta').hidden = true;
            $('conteudoConta').hidden = false;
        } catch (erro) {
            $('carregandoConta').hidden = true;
            if (erro.message !== 'Sessão expirada.') mostrarAviso(erro.message, false);
        }
    }

    // ---------- Dados pessoais ----------
    $('formPerfil').addEventListener('submit', async (e) => {
        e.preventDefault();
        esconderAviso();
        const form = e.currentTarget;
        const obrigatorios = ['nome', 'sobrenome', 'cidade', 'bairro', 'numero'];
        let faltando = false;
        obrigatorios.forEach((id) => {
            const vazio = $(id).value.trim() === '';
            $(id).classList.toggle('invalido', vazio);
            if (vazio) faltando = true;
        });
        if (faltando) { mostrarAviso('Preencha todos os campos.', false); return; }

        const botao = $('btnSalvarPerfil');
        ocupar(botao, true, 'Salvando...');
        try {
            const r = await enviar('perfil', form);
            ['nome', 'sobrenome', 'cidade', 'bairro', 'numero'].forEach((c) => { dados.usuario[c] = $(c).value.trim(); });
            preencher();
            mostrarAviso(r.mensagem, true);
        } catch (erro) {
            mostrarAviso(erro.message, false);
        } finally {
            ocupar(botao, false);
        }
    });

    // ---------- Senha ----------
    $('formSenha').addEventListener('submit', async (e) => {
        e.preventDefault();
        esconderAviso();
        const form = e.currentTarget;
        if (!$('senhaAtual').value) { mostrarAviso('Digite sua senha atual.', false); return; }
        if ($('novaSenha').value.length < 8) { mostrarAviso('A nova senha precisa ter pelo menos 8 caracteres.', false); return; }
        if ($('novaSenha').value !== $('confirmarSenha').value) { mostrarAviso('As senhas não são iguais.', false); return; }

        const botao = $('btnSalvarSenha');
        ocupar(botao, true, 'Alterando...');
        try {
            const r = await enviar('senha', form);
            form.reset();
            mostrarAviso(r.mensagem, true);
        } catch (erro) {
            mostrarAviso(erro.message, false);
        } finally {
            ocupar(botao, false);
        }
    });

    // ---------- Excluir conta ----------
    $('formExcluir').addEventListener('submit', async (e) => {
        e.preventDefault();
        esconderAviso();
        if (!$('senhaExcluir').value) { mostrarAviso('Digite sua senha para confirmar.', false); return; }
        if (!window.confirm('Tem certeza? Sua conta e os animais que você registrou serão apagados para sempre.')) return;

        const botao = $('btnExcluir');
        ocupar(botao, true, 'Excluindo...');
        try {
            await enviar('excluir', e.currentTarget);
            window.location.href = './index.html';
        } catch (erro) {
            mostrarAviso(erro.message, false);
            ocupar(botao, false);
        }
    });

    // ---------- Sair ----------
    $('btnSair').addEventListener('click', async () => {
        $('btnSair').disabled = true;
        try { await fetch('/backend/logout.php', { credentials: 'same-origin' }); } catch (e) { /* segue para a home */ }
        window.location.href = './index.html';
    });

    carregar();
})();
