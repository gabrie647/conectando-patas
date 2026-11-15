<?php
// Valida e recodifica a foto enviada. Recodificar garante que o arquivo salvo é uma imagem
// de verdade (sem código escondido) e remove metadados, como a localização GPS do celular.
function imagem_segura(string $caminho, string $mime): ?string
{
    if (!function_exists('imagecreatefromstring')) {
        return null;
    }
    $bruto = file_get_contents($caminho);
    if ($bruto === false) {
        return null;
    }
    $info = @getimagesizefromstring($bruto);
    if ($info === false || $info[0] < 1 || $info[1] < 1 || $info[0] > 6000 || $info[1] > 6000) {
        return null;
    }
    $imagem = @imagecreatefromstring($bruto);
    if ($imagem === false) {
        return null;
    }

    ob_start();
    $ok = match ($mime) {
        'image/jpeg' => imagejpeg($imagem, null, 85),
        'image/png' => imagepng($imagem),
        'image/webp' => imagewebp($imagem, null, 85),
        'image/gif' => imagegif($imagem),
        default => false,
    };
    $saida = ob_get_clean();

    return ($ok && $saida !== '') ? $saida : null;
}
