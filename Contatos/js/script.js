const formulario = document.getElementById("formContato");
const mensagemSucesso = document.getElementById("mensagemSucesso");
const btnEnviar = formulario.querySelector(".btn-enviar");

// Vindo da página de animais: ?assunto=adocao&animal=12
(function preencherPelaUrl() {
    const params = new URLSearchParams(window.location.search);
    const assunto = params.get("assunto");
    const animal = params.get("animal");
    const campoAssunto = document.getElementById("assunto");
    if (assunto && campoAssunto.querySelector('option[value="' + assunto + '"]')) {
        campoAssunto.value = assunto;
    }
    if (animal && /^\d+$/.test(animal)) {
        document.getElementById("mensagem").value = "Olá! Tenho interesse no animal #" + animal + " (veja em animais.html). ";
    }
})();

formulario.addEventListener("submit", async function (event) {
    event.preventDefault();

    const nome = document.getElementById("nome").value.trim();
    const email = document.getElementById("email").value.trim();
    const assunto = document.getElementById("assunto").value;
    const mensagem = document.getElementById("mensagem").value.trim();
    const privacidade = document.getElementById("privacidade").checked;

    if (nome === "" || email === "" || assunto === "" || mensagem === "" || !privacidade) {
        mensagemSucesso.textContent = "Preencha todos os campos obrigatórios.";
        mensagemSucesso.style.color = "#c0392b";
        return;
    }

    const dados = new FormData();
    dados.append("nome", nome);
    dados.append("email", email);
    dados.append("assunto", assunto);
    dados.append("mensagem", mensagem);
    dados.append("privacidade", String(privacidade));
    dados.append("website", document.getElementById("website").value);

    const textoOriginal = btnEnviar.innerHTML;
    btnEnviar.disabled = true;
    btnEnviar.textContent = "ENVIANDO...";
    mensagemSucesso.textContent = "";

    try {
        const resposta = await fetch("/backend/contato.php", {
            method: "POST",
            body: dados
        });

        const texto = await resposta.text();
        let resultado;
        try {
            resultado = JSON.parse(texto);
        } catch (erroJson) {
            console.error("Resposta não JSON do servidor:", texto);
            throw new Error("O servidor retornou uma resposta inválida.");
        }

        if (!resposta.ok || !resultado.sucesso) {
            throw new Error(resultado.mensagem || "Falha HTTP " + resposta.status + ".");
        }

        mensagemSucesso.textContent = resultado.mensagem;
        mensagemSucesso.style.color = "#153A24";
        formulario.reset();
    } catch (erro) {
        console.error("Erro ao enviar contato:", erro);
        mensagemSucesso.textContent = erro.message || "Não foi possível conectar ao servidor.";
        mensagemSucesso.style.color = "#c0392b";
    } finally {
        btnEnviar.disabled = false;
        btnEnviar.innerHTML = textoOriginal;
    }
});
