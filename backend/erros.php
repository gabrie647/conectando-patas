<?php
// Garante que erros do PHP NUNCA apareçam misturados na resposta JSON.
// Os detalhes vão para os logs (no Render: aba Logs do serviço).

ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

// Guarda toda a saída; se houver erro fatal, descarta o HTML parcial e responde JSON.
ob_start();

register_shutdown_function(function () {
    $erro = error_get_last();
    $fatais = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];

    if ($erro && in_array($erro['type'], $fatais, true)) {
        error_log("Erro fatal PHP: {$erro['message']} em {$erro['file']}:{$erro['line']}");
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: application/json; charset=UTF-8');
        }
        echo json_encode([
            'sucesso' => false,
            'mensagem' => 'Erro interno no servidor. Verifique os logs do serviço.'
        ]);
        return;
    }

    if (ob_get_level() > 0) {
        ob_end_flush();
    }
});
