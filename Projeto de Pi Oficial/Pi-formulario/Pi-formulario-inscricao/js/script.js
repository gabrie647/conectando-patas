// =====================================================
// ELEMENTOS DO FORMULÁRIO
// =====================================================

const cidade = document.getElementById("inputCidade");
const bairro = document.getElementById("inputBairro");
const formulario = document.querySelector(".formulario");


// Verifica se os elementos existem
console.log("Cidade:", cidade);
console.log("Bairro:", bairro);
console.log("Formulário:", formulario);


// =====================================================
// VERIFICAR SE O FORMULÁRIO EXISTE
// =====================================================

if (cidade && bairro && formulario) {

    // =================================================
    // CIDADES → BAIRROS
    // =================================================

    cidade.addEventListener("change", function () {

        // Limpa os bairros anteriores
        bairro.innerHTML = "";

        // Libera o campo Bairro
        bairro.disabled = false;


        // =================================================
        // CAÇADOR
        // =================================================

        if (cidade.value === "cacador") {

            const bairros = [

                "Adolfo Konder",
                "Aeroporto",
                "Alto Bonito",
                "Bello",
                "Berger",
                "Bom Jesus",
                "Bom Sucesso",
                "Castello Branco",
                "Centro",
                "Champagnat",
                "D.E.R.",
                "Figueroa",
                "Gioppo",
                "Industrial",
                "Kurtz",
                "Martello",
                "Nossa Senhora Salete",
                "Paraíso",
                "Rancho Fundo",
                "Reunidas",
                "Santa Catarina",
                "São Cristóvão",
                "Sorgatto"

            ];

            adicionarBairros(bairros);
        }


        // =================================================
        // RIO DAS ANTAS
        // =================================================

        else if (cidade.value === "rio-das-antas") {

            const bairros = [

                "Centro",
                "Novo Horizonte",
                "Bela Vista",
                "São José do Rio Preto",
                "Gramado",
                "Ipoméia"

            ];

            adicionarBairros(bairros);
        }


        // =================================================
        // VIDEIRA
        // =================================================

        else if (cidade.value === "videira") {

            const bairros = [

                "Aeroporto",
                "Água Verde",
                "Alvorada",
                "Amarante",
                "Anta Gorda",
                "Bom Sucesso",
                "Campina Bela",
                "Campo Experimental",
                "Carboni",
                "Carelli",
                "Centro",
                "Cetrevi",
                "Cibrazém",
                "Cidade Alta",
                "Dois Pinheiros",
                "Farroupilha",
                "Floresta",
                "Lourdes",
                "Marafon",
                "Matriz",
                "Morada do Sol",
                "Nossa Senhora Aparecida",
                "Oficina",
                "Panazzolo",
                "Portal das Videiras",
                "Rio das Pedras",
                "Santa Gema",
                "Santa Lucia",
                "Santa Tereza",
                "Santos Dumont",
                "São Cristovão",
                "São Francisco",
                "Sesi",
                "Universitário",
                "Vila de Carli",
                "Vila Verde"

            ];

            adicionarBairros(bairros);
        }

        else {

            bairro.disabled = true;

            bairro.innerHTML = `
                <option value="" selected>
                    Selecione ...
                </option>
            `;

        }

    });


    // =====================================================
    // FUNÇÃO PARA ADICIONAR OS BAIRROS
    // =====================================================

    function adicionarBairros(listaBairros) {

        const opcao = document.createElement("option");

        opcao.value = "";
        opcao.textContent = "Selecione o bairro...";
        opcao.disabled = true;
        opcao.selected = true;

        bairro.appendChild(opcao);


        listaBairros.forEach(function (nomeBairro) {

            const option = document.createElement("option");

            option.value = nomeBairro;
            option.textContent = nomeBairro;

            bairro.appendChild(option);

        });

    }


    // =====================================================
    // CAPTURAR OS DADOS DO FORMULÁRIO
    // =====================================================

    formulario.addEventListener("submit", async function (event) {

        event.preventDefault();


        // =================================================
        // PEGAR OS VALORES
        // =================================================

        const nome =
            document.getElementById("inputNome").value.trim();

        const sobrenome =
            document.getElementById("inputSobrenome").value.trim();

        const email =
            document.getElementById("inputEmail").value.trim();

        const senha =
            document.getElementById("inputSenha").value;

        const confirmarSenha =
            document.getElementById("inputConfirmarSenha").value;

        const cidadeSelecionada =
            cidade.value;

        const bairroSelecionado =
            bairro.value;

        const numero =
            document.getElementById("inputNumero").value.trim();

        const termos =
            document.getElementById("gridCheck").checked;


        // =================================================
        // VERIFICAR NOME
        // =================================================

        if (nome === "") {

            alert("Digite seu nome.");

            document.getElementById("inputNome").focus();

            return;

        }


        // =================================================
        // VERIFICAR SOBRENOME
        // =================================================

        if (sobrenome === "") {

            alert("Digite seu sobrenome.");

            document.getElementById("inputSobrenome").focus();

            return;

        }


        // =================================================
        // VERIFICAR E-MAIL
        // =================================================

        if (email === "") {

            alert("Digite seu e-mail.");

            document.getElementById("inputEmail").focus();

            return;

        }


        // =================================================
        // VERIFICAR SENHA
        // =================================================

        if (senha === "") {

            alert("Digite uma senha.");

            document.getElementById("inputSenha").focus();

            return;

        }


        // =================================================
        // VERIFICAR CONFIRMAÇÃO DA SENHA
        // =================================================

        if (confirmarSenha === "") {

            alert("Confirme sua senha.");

            document.getElementById("inputConfirmarSenha").focus();

            return;

        }


        // =================================================
        // VERIFICAR SE AS SENHAS SÃO IGUAIS
        // =================================================

        if (senha !== confirmarSenha) {

            alert("As senhas não são iguais!");

            document.getElementById("inputConfirmarSenha").focus();

            return;

        }


        // =================================================
        // VERIFICAR CIDADE
        // =================================================

        if (cidadeSelecionada === "") {

            alert("Selecione uma cidade.");

            cidade.focus();

            return;

        }


        // =================================================
        // VERIFICAR BAIRRO
        // =================================================

        if (bairroSelecionado === "") {

            alert("Selecione um bairro.");

            bairro.focus();

            return;

        }


        // =================================================
        // VERIFICAR NÚMERO
        // =================================================

        if (numero === "") {

            alert("Digite o número.");

            document.getElementById("inputNumero").focus();

            return;

        }


        // =================================================
        // VERIFICAR OS TERMOS
        // =================================================

        if (!termos) {

            alert("Você precisa aceitar os termos de uso.");

            return;

        }


        // =================================================
        // CRIAR OBJETO COM TODOS OS DADOS
        // =================================================

        const dadosUsuario = {

            nome: nome,

            sobrenome: sobrenome,

            email: email,

            cidade: cidadeSelecionada,

            bairro: bairroSelecionado,

            numero: numero,

            termosAceitos: termos

        };


        // =================================================
        // MOSTRAR OS DADOS NO CONSOLE
        // =================================================

        console.log("================================");
        console.log("DADOS DO USUÁRIO");
        console.log("================================");
        console.log(dadosUsuario);
        console.log("================================");


        // =================================================
        // PREPARAR DADOS PARA O PHP
        // =================================================

        const dados = new FormData();

        dados.append("nome", nome);
        dados.append("sobrenome", sobrenome);
        dados.append("email", email);
        dados.append("senha", senha);
        dados.append("confirmarSenha", confirmarSenha);
        dados.append("cidade", cidadeSelecionada);
        dados.append("bairro", bairroSelecionado);
        dados.append("numero", numero);
        dados.append("termos", termos);


        // =================================================
        // ENVIAR DADOS PARA O PHP
        // =================================================

        try {

            const resposta = await fetch(
                "/backend/cadastro.php",
                {
                    method: "POST",
                    body: dados
                }
            );


            // =================================================
            // VERIFICAR RESPOSTA DO SERVIDOR
            // =================================================

            const resultado = await resposta.json();


            console.log("Resposta do servidor:");
            console.log(resultado);


            // =================================================
            // CADASTRO REALIZADO
            // =================================================

            if (resultado.sucesso) {

                alert(resultado.mensagem);


                console.log("================================");
                console.log("CADASTRO SALVO NO BANCO");
                console.log("================================");

                console.log(dadosUsuario);

                console.log("================================");


                // =================================================
                // LIMPAR FORMULÁRIO
                // =================================================

                formulario.reset();


                // =================================================
                // VOLTAR O CAMPO BAIRRO PARA DESABILITADO
                // =================================================

                bairro.disabled = true;

                bairro.innerHTML = `
                    <option value="" selected>
                        Selecione ...
                    </option>
                `;

            }

            // =================================================
            // ERRO RETORNADO PELO PHP
            // =================================================

            else {

                alert(resultado.mensagem);

            }

        }

        // =================================================
        // ERRO DE CONEXÃO
        // =================================================

        catch (erro) {

            console.error(
                "Erro ao enviar cadastro:",
                erro
            );

            alert(
                "Não foi possível conectar ao servidor."
            );

        }

    });

}