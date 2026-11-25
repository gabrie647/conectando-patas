<?php
// Valida e recodifica a foto enviada.
// - O tipo REAL do conteúdo precisa ser o mesmo que foi declarado (evita arquivo disfarçado).
// - Limites de tamanho e de pixels (evita imagens que estouram a memória ao decodificar).
// - Recodificar garante que o arquivo salvo é uma imagem de verdade e remove metadados
//   (como a localização GPS do celular).

const IMAGEM_LADO_MAX = 6000;          // pixels em cada lado
const IMAGEM_PIXELS_MAX = 16000000;    // ~16 megapixels: limita a memória usada ao decodificar

function imagem_segura(string $caminho, string $mime): ?string
{
    if (!function_exists('imagecreatefromstring') || !function_exists('getimagesizefromstring')) {
        return null;
    }

    $bruto = @file_get_contents($caminho);
    if ($bruto === false || $bruto === '') {
        return null;
    }

    $info = @getimagesizefromstring($bruto);
    if ($info === false) {
        return null;
    }

    // Tipo real do conteúdo deve bater com o tipo declarado.
    if (($info['mime'] ?? '') !== $mime) {
        return null;
    }

    [$largura, $altura] = $info;
    if ($largura < 1 || $altura < 1 || $largura > IMAGEM_LADO_MAX || $altura > IMAGEM_LADO_MAX) {
        return null;
    }
    if ($largura * $altura > IMAGEM_PIXELS_MAX) {
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
        'image/webp' => function_exists('imagewebp') ? imagewebp($imagem, null, 85) : false,
        'image/gif' => imagegif($imagem),
        default => false,
    };
    $saida = ob_get_clean();

    return ($ok && $saida !== '') ? $saida : null;
}
