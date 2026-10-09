const formulario = document.getElementById("formContato");

const mensagemSucesso = document.getElementById("mensagemSucesso");


formulario.addEventListener("submit", function (event) {

    event.preventDefault();


    const nome = document.getElementById("nome").value.trim();

    const email = document.getElementById("email").value.trim();

    const assunto = document.getElementById("assunto").value;

    const mensagem = document.getElementById("mensagem").value.trim();

    const privacidade = document.getElementById("privacidade").checked;


    if (
        nome === "" ||
        email === "" ||
        assunto === "" ||
        mensagem === "" ||
        !privacidade
    ) {

        mensagemSucesso.textContent =
            "Preencha todos os campos obrigatórios.";

        mensagemSucesso.style.color = "#c0392b";

        return;

    }


    mensagemSucesso.textContent =
        "Mensagem enviada com sucesso! Obrigado por entrar em contato.";

    mensagemSucesso.style.color = "#153A24";


    console.log("===== NOVA MENSAGEM =====");

    console.log("Nome:", nome);

    console.log("E-mail:", email);

    console.log("Assunto:", assunto);

    console.log("Mensagem:", mensagem);


    formulario.reset();

});