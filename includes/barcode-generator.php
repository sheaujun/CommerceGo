<?php

function code128Patterns(): array
{
    return [
        '212222', '222122', '222221', '121223', '121322', '131222', '122213', '122312', '132212', '221213',
        '221312', '231212', '112232', '122132', '122231', '113222', '123122', '123221', '223211', '221132',
        '221231', '213212', '223112', '312131', '311222', '321122', '321221', '312212', '322112', '322211',
        '212123', '212321', '232121', '111323', '131123', '131321', '112313', '132113', '132311', '211313',
        '231113', '231311', '112133', '112331', '132131', '113123', '113321', '133121', '313121', '211331',
        '231131', '213113', '213311', '213131', '311123', '311321', '331121', '312113', '312311', '332111',
        '314111', '221411', '431111', '111224', '111422', '121124', '121421', '141122', '141221', '112214',
        '112412', '122114', '122411', '142112', '142211', '241211', '221114', '413111', '241112', '134111',
        '111242', '121142', '121241', '114212', '124112', '124211', '411212', '421112', '421211', '212141',
        '214121', '412121', '111143', '111341', '131141', '114113', '114311', '411113', '411311', '113141',
        '114131', '311141', '411131', '211412', '211214', '211232', '2331112',
    ];
}

function barcodeStorageDirectory(): string
{
    return dirname(__DIR__) . '/admin/uploads/barcodes';
}

function barcodeWebPath(string $barcode): string
{
    $safe = preg_replace('/[^A-Za-z0-9_-]+/', '-', $barcode);
    $safe = trim((string) $safe, '-');
    if ($safe === '') {
        $safe = 'barcode';
    }

    return 'uploads/barcodes/barcode_' . substr($safe, 0, 48) . '_' . substr(sha1($barcode), 0, 10) . '.svg';
}

function generateCode128Svg(string $barcode): string
{
    $patterns = code128Patterns();
    $codes = [104];
    $checksum = 104;

    for ($i = 0, $length = strlen($barcode); $i < $length; $i++) {
        $ord = ord($barcode[$i]);
        if ($ord < 32 || $ord > 126) {
            throw new InvalidArgumentException('Barcode contains unsupported characters.');
        }
        $value = $ord - 32;
        $codes[] = $value;
        $checksum += $value * ($i + 1);
    }

    $codes[] = $checksum % 103;
    $codes[] = 106;

    $module = 2;
    $quiet = 10 * $module;
    $barHeight = 72;
    $textHeight = 22;
    $width = $quiet * 2;
    foreach ($codes as $code) {
        foreach (str_split($patterns[$code]) as $part) {
            $width += (int) $part * $module;
        }
    }
    $height = $barHeight + $textHeight + 12;

    $x = $quiet;
    $rects = [];
    foreach ($codes as $code) {
        $black = true;
        foreach (str_split($patterns[$code]) as $part) {
            $w = (int) $part * $module;
            if ($black) {
                $rects[] = '<rect x="' . $x . '" y="8" width="' . $w . '" height="' . $barHeight . '"/>';
            }
            $x += $w;
            $black = !$black;
        }
    }

    $label = htmlspecialchars($barcode, ENT_QUOTES, 'UTF-8');

    return '<svg xmlns="http://www.w3.org/2000/svg" width="' . $width . '" height="' . $height . '" viewBox="0 0 ' . $width . ' ' . $height . '" role="img" aria-label="Barcode ' . $label . '">'
        . '<rect width="100%" height="100%" fill="#fff"/>'
        . '<g fill="#111827">' . implode('', $rects) . '</g>'
        . '<text x="50%" y="' . ($barHeight + 28) . '" text-anchor="middle" font-family="Arial, Helvetica, sans-serif" font-size="14" fill="#111827">' . $label . '</text>'
        . '</svg>';
}

function saveBarcodeImage(string $barcode): string
{
    $barcode = trim($barcode);
    if ($barcode === '') {
        return '';
    }

    $directory = barcodeStorageDirectory();
    if (!is_dir($directory) && !mkdir($directory, 0755, true)) {
        throw new RuntimeException('Unable to create barcode folder.');
    }

    $webPath = barcodeWebPath($barcode);
    $absolutePath = dirname(__DIR__) . '/admin/' . $webPath;
    if (!is_file($absolutePath)) {
        file_put_contents($absolutePath, generateCode128Svg($barcode));
    }

    return $webPath;
}
