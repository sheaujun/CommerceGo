<?php

require_once __DIR__ . '/product-schema.php';

function normalizeImportHeader(string $header): string
{
    return preg_replace('/[^a-z0-9]+/', '', strtolower(trim($header)));
}

function parseImportDate($value): ?string
{
    if ($value === null || trim((string) $value) === '') {
        return null;
    }

    if (is_numeric($value)) {
        $timestamp = ((float) $value - 25569) * 86400;
        return gmdate('Y-m-d', (int) $timestamp);
    }

    $timestamp = strtotime((string) $value);
    return $timestamp ? date('Y-m-d', $timestamp) : null;
}

function normalizeProductImagePath(string $path): string
{
    $path = trim(str_replace('\\', '/', $path));
    if ($path === '') {
        return '';
    }
    if (strpos($path, '://') !== false || str_starts_with($path, '/')) {
        return $path;
    }

    $path = preg_replace('#/+#', '/', $path);
    if (str_starts_with($path, 'admin/uploads/')) {
        return substr($path, strlen('admin/'));
    }

    return ltrim($path, '/');
}

function resolveProductImageUrl(string $path, string $currentArea = 'admin'): string
{
    $path = normalizeProductImagePath($path);
    if ($path === '' || strpos($path, '://') !== false || str_starts_with($path, '/')) {
        return $path;
    }

    if ($currentArea === 'staff') {
        return '../admin/' . ltrim($path, '/');
    }

    return $path;
}

function productImageStorageDirectory(): string
{
    return dirname(__DIR__) . '/admin/uploads/products';
}

function productImageWebPath(string $productName, string $barcode = ''): string
{
    $safe = preg_replace('/[^A-Za-z0-9_-]+/', '-', strtolower($productName));
    $safe = trim((string) $safe, '-');
    if ($safe === '') {
        $safe = 'product';
    }

    $hashSource = $productName . '|' . $barcode;
    return 'uploads/products/import_' . substr($safe, 0, 42) . '_' . substr(sha1($hashSource), 0, 10) . '.svg';
}

function uploadedProductImageWebPath(string $productName, string $barcode, string $extension, string $content): string
{
    $safe = preg_replace('/[^A-Za-z0-9_-]+/', '-', strtolower($productName));
    $safe = trim((string) $safe, '-');
    if ($safe === '') {
        $safe = 'product';
    }

    return 'uploads/products/imported_' . substr($safe, 0, 38) . '_' . substr(sha1($barcode . '|' . $content), 0, 10) . '.' . $extension;
}

function extensionFromImagePath(string $path): string
{
    $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    return in_array($extension, ['jpg', 'jpeg', 'png', 'gif'], true) ? $extension : '';
}

function saveEmbeddedProductImage(string $productName, string $barcode, string $imagePath, string $content): string
{
    $extension = extensionFromImagePath($imagePath);
    if ($extension === 'jpeg') {
        $extension = 'jpg';
    }
    if ($extension === '') {
        return '';
    }

    $directory = productImageStorageDirectory();
    if (!is_dir($directory) && !mkdir($directory, 0755, true)) {
        throw new RuntimeException('Unable to create product image folder.');
    }

    $webPath = uploadedProductImageWebPath($productName, $barcode, $extension, $content);
    $absolutePath = dirname(__DIR__) . '/admin/' . $webPath;
    if (!is_file($absolutePath)) {
        file_put_contents($absolutePath, $content);
    }

    return $webPath;
}

function shouldGenerateProductImage(string $path): bool
{
    $path = normalizeProductImagePath($path);
    if ($path === '') {
        return true;
    }
    if (strpos($path, '://') !== false || str_starts_with($path, '/')) {
        return false;
    }

    return !is_file(dirname(__DIR__) . '/admin/' . ltrim($path, '/'));
}

function productImageLines(string $productName): array
{
    $words = preg_split('/\s+/', trim($productName));
    $lines = [];
    $line = '';

    foreach ($words as $word) {
        $next = $line === '' ? $word : $line . ' ' . $word;
        if (strlen($next) > 21 && $line !== '') {
            $lines[] = $line;
            $line = $word;
            continue;
        }
        $line = $next;
    }

    if ($line !== '') {
        $lines[] = $line;
    }

    return array_slice($lines, 0, 3);
}

function productImagePalette(string $category): array
{
    $key = strtolower($category);
    if (str_contains($key, 'cough') || str_contains($key, 'cold')) {
        return ['#ec4899', '#fce7f3', '#831843'];
    }
    if (str_contains($key, 'equipment')) {
        return ['#0ea5e9', '#e0f2fe', '#075985'];
    }
    if (str_contains($key, 'first')) {
        return ['#ef4444', '#fee2e2', '#7f1d1d'];
    }
    if (str_contains($key, 'personal') || str_contains($key, 'skin')) {
        return ['#14b8a6', '#ccfbf1', '#134e4a'];
    }
    if (str_contains($key, 'vitamin') || str_contains($key, 'supplement')) {
        return ['#f59e0b', '#fef3c7', '#78350f'];
    }
    if (str_contains($key, 'eye')) {
        return ['#6366f1', '#e0e7ff', '#312e81'];
    }
    if (str_contains($key, 'child')) {
        return ['#8b5cf6', '#ede9fe', '#4c1d95'];
    }

    return ['#10b981', '#d1fae5', '#064e3b'];
}

function productImageShape(string $productName, string $category, array $palette): string
{
    [$accent, $soft, $dark] = $palette;
    $name = strtolower($productName . ' ' . $category);

    if (str_contains($name, 'thermometer')) {
        return '<g transform="translate(108 42)">'
            . '<rect x="42" y="12" width="18" height="98" rx="9" fill="' . $soft . '" stroke="' . $accent . '" stroke-width="5"/>'
            . '<circle cx="51" cy="118" r="24" fill="' . $soft . '" stroke="' . $accent . '" stroke-width="5"/>'
            . '<rect x="48" y="34" width="6" height="78" rx="3" fill="' . $accent . '"/>'
            . '<circle cx="51" cy="118" r="12" fill="' . $accent . '"/>'
            . '</g>';
    }

    if (str_contains($name, 'syrup') || str_contains($name, 'spray') || str_contains($name, 'sanitizer') || str_contains($name, 'drops')) {
        return '<g transform="translate(105 38)">'
            . '<rect x="34" y="0" width="38" height="24" rx="6" fill="' . $dark . '"/>'
            . '<rect x="28" y="20" width="50" height="112" rx="18" fill="' . $soft . '" stroke="' . $accent . '" stroke-width="5"/>'
            . '<rect x="38" y="58" width="30" height="34" rx="8" fill="#ffffff" opacity="0.9"/>'
            . '<path d="M45 75h16M53 67v16" stroke="' . $accent . '" stroke-width="5" stroke-linecap="round"/>'
            . '</g>';
    }

    if (str_contains($name, 'bandage') || str_contains($name, 'patch') || str_contains($name, 'plaster')) {
        return '<g transform="translate(86 67) rotate(-12 66 38)">'
            . '<rect x="0" y="12" width="132" height="52" rx="26" fill="' . $soft . '" stroke="' . $accent . '" stroke-width="5"/>'
            . '<rect x="50" y="18" width="34" height="40" rx="10" fill="#ffffff" opacity="0.9"/>'
            . '<circle cx="59" cy="30" r="3" fill="' . $accent . '"/><circle cx="74" cy="30" r="3" fill="' . $accent . '"/>'
            . '<circle cx="59" cy="46" r="3" fill="' . $accent . '"/><circle cx="74" cy="46" r="3" fill="' . $accent . '"/>'
            . '</g>';
    }

    return '<g transform="translate(100 38)">'
        . '<rect x="24" y="18" width="64" height="104" rx="14" fill="' . $soft . '" stroke="' . $accent . '" stroke-width="5"/>'
        . '<rect x="36" y="0" width="40" height="24" rx="8" fill="' . $dark . '"/>'
        . '<rect x="34" y="52" width="44" height="34" rx="8" fill="#ffffff" opacity="0.9"/>'
        . '<path d="M48 69h16M56 61v16" stroke="' . $accent . '" stroke-width="5" stroke-linecap="round"/>'
        . '</g>';
}

function generateProductImageSvg(string $productName, string $category): string
{
    $palette = productImagePalette($category);
    [$accent, $soft, $dark] = $palette;
    $label = htmlspecialchars($productName, ENT_QUOTES, 'UTF-8');
    $categoryLabel = htmlspecialchars($category, ENT_QUOTES, 'UTF-8');
    $lines = productImageLines($productName);
    $text = '';
    $startY = 178 - ((count($lines) - 1) * 15);

    foreach ($lines as $index => $line) {
        $text .= '<text x="160" y="' . ($startY + ($index * 24)) . '" text-anchor="middle" font-family="Arial, Helvetica, sans-serif" font-size="19" font-weight="700" fill="' . $dark . '">'
            . htmlspecialchars($line, ENT_QUOTES, 'UTF-8') . '</text>';
    }

    return '<svg xmlns="http://www.w3.org/2000/svg" width="320" height="240" viewBox="0 0 320 240" role="img" aria-label="' . $label . '">'
        . '<rect width="320" height="240" rx="28" fill="#f8fafc"/>'
        . '<rect x="18" y="18" width="284" height="204" rx="24" fill="' . $soft . '"/>'
        . '<circle cx="52" cy="54" r="28" fill="#ffffff" opacity="0.72"/>'
        . '<circle cx="268" cy="64" r="34" fill="#ffffff" opacity="0.5"/>'
        . productImageShape($productName, $category, $palette)
        . '<rect x="56" y="194" width="208" height="24" rx="12" fill="#ffffff" opacity="0.72"/>'
        . $text
        . '<text x="160" y="211" text-anchor="middle" font-family="Arial, Helvetica, sans-serif" font-size="11" font-weight="700" letter-spacing="1" fill="' . $accent . '">' . $categoryLabel . '</text>'
        . '</svg>';
}

function saveGeneratedProductImage(string $productName, string $category, string $barcode = ''): string
{
    $directory = productImageStorageDirectory();
    if (!is_dir($directory) && !mkdir($directory, 0755, true)) {
        throw new RuntimeException('Unable to create product image folder.');
    }

    $webPath = productImageWebPath($productName, $barcode);
    $absolutePath = dirname(__DIR__) . '/admin/' . $webPath;
    if (!is_file($absolutePath)) {
        file_put_contents($absolutePath, generateProductImageSvg($productName, $category));
    }

    return $webPath;
}

function readZipEntry(string $zipPath, string $entryName)
{
    $data = file_get_contents($zipPath);
    if ($data === false) {
        return false;
    }

    $eocdSignature = pack('V', 0x06054b50);
    $eocdOffset = strrpos($data, $eocdSignature);
    if ($eocdOffset === false) {
        return false;
    }

    $eocd = unpack('vdisk/vstartDisk/ventriesDisk/ventries/VcentralSize/VcentralOffset/vcommentLength', substr($data, $eocdOffset + 4, 18));
    if (!$eocd || !isset($eocd['centralOffset'], $eocd['centralSize'])) {
        return false;
    }

    $offset = (int) $eocd['centralOffset'];
    $end = $offset + (int) $eocd['centralSize'];
    $centralSignature = pack('V', 0x02014b50);
    $localSignature = pack('V', 0x04034b50);

    while ($offset < $end && substr($data, $offset, 4) === $centralSignature) {
        $header = unpack(
            'vversionMade/vversionNeeded/vflags/vmethod/vtime/vdate/Vcrc/VcompressedSize/VuncompressedSize/vnameLength/vextraLength/vcommentLength/vdiskStart/vinternalAttr/VexternalAttr/VlocalOffset',
            substr($data, $offset + 4, 42)
        );
        if (!$header) {
            return false;
        }

        $nameLength = (int) $header['nameLength'];
        $extraLength = (int) $header['extraLength'];
        $commentLength = (int) $header['commentLength'];
        $name = str_replace('\\', '/', substr($data, $offset + 46, $nameLength));

        if ($name === $entryName) {
            $localOffset = (int) $header['localOffset'];
            if (substr($data, $localOffset, 4) !== $localSignature) {
                return false;
            }

            $local = unpack('vversion/vflags/vmethod/vtime/vdate/Vcrc/VcompressedSize/VuncompressedSize/vnameLength/vextraLength', substr($data, $localOffset + 4, 26));
            if (!$local) {
                return false;
            }

            $contentOffset = $localOffset + 30 + (int) $local['nameLength'] + (int) $local['extraLength'];
            $compressed = substr($data, $contentOffset, (int) $header['compressedSize']);

            if ((int) $header['method'] === 0) {
                return $compressed;
            }
            if ((int) $header['method'] === 8) {
                $inflated = gzinflate($compressed);
                return $inflated === false ? false : $inflated;
            }
            return false;
        }

        $offset += 46 + $nameLength + $extraLength + $commentLength;
    }

    return false;
}

function listZipEntries(string $zipPath): array
{
    $data = file_get_contents($zipPath);
    if ($data === false) {
        return [];
    }

    $eocdSignature = pack('V', 0x06054b50);
    $eocdOffset = strrpos($data, $eocdSignature);
    if ($eocdOffset === false) {
        return [];
    }

    $eocd = unpack('vdisk/vstartDisk/ventriesDisk/ventries/VcentralSize/VcentralOffset/vcommentLength', substr($data, $eocdOffset + 4, 18));
    if (!$eocd || !isset($eocd['centralOffset'], $eocd['centralSize'])) {
        return [];
    }

    $entries = [];
    $offset = (int) $eocd['centralOffset'];
    $end = $offset + (int) $eocd['centralSize'];
    $centralSignature = pack('V', 0x02014b50);

    while ($offset < $end && substr($data, $offset, 4) === $centralSignature) {
        $header = unpack(
            'vversionMade/vversionNeeded/vflags/vmethod/vtime/vdate/Vcrc/VcompressedSize/VuncompressedSize/vnameLength/vextraLength/vcommentLength/vdiskStart/vinternalAttr/VexternalAttr/VlocalOffset',
            substr($data, $offset + 4, 42)
        );
        if (!$header) {
            break;
        }

        $nameLength = (int) $header['nameLength'];
        $extraLength = (int) $header['extraLength'];
        $commentLength = (int) $header['commentLength'];
        $entries[] = str_replace('\\', '/', substr($data, $offset + 46, $nameLength));
        $offset += 46 + $nameLength + $extraLength + $commentLength;
    }

    return $entries;
}

function readXlsxEntry(string $tmpPath, string $entryName)
{
    if (class_exists('ZipArchive')) {
        $zip = new ZipArchive();
        if ($zip->open($tmpPath) !== true) {
            return false;
        }
        $content = $zip->getFromName($entryName);
        $zip->close();
        return $content;
    }

    return readZipEntry($tmpPath, $entryName);
}

function listXlsxEntries(string $tmpPath): array
{
    if (class_exists('ZipArchive')) {
        $zip = new ZipArchive();
        if ($zip->open($tmpPath) !== true) {
            return [];
        }
        $entries = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if ($name !== false) {
                $entries[] = str_replace('\\', '/', $name);
            }
        }
        $zip->close();
        return $entries;
    }

    return listZipEntries($tmpPath);
}

function resolveXlsxTargetPath(string $sourcePath, string $target): string
{
    if (str_starts_with($target, '/')) {
        return ltrim($target, '/');
    }

    $parts = explode('/', dirname($sourcePath) . '/' . $target);
    $resolved = [];
    foreach ($parts as $part) {
        if ($part === '' || $part === '.') {
            continue;
        }
        if ($part === '..') {
            array_pop($resolved);
            continue;
        }
        $resolved[] = $part;
    }

    return implode('/', $resolved);
}

function parseXlsxRelationships($xml): array
{
    if ($xml === false || trim((string) $xml) === '') {
        return [];
    }

    $relsXml = simplexml_load_string($xml);
    if (!$relsXml) {
        return [];
    }

    $relationships = [];
    $relationshipNodes = $relsXml->xpath('//*[local-name()="Relationship"]') ?: [];
    foreach ($relationshipNodes as $relationship) {
        $id = (string) $relationship['Id'];
        $target = (string) $relationship['Target'];
        if ($id !== '' && $target !== '') {
            $relationships[$id] = $target;
        }
    }

    return $relationships;
}

function extractXlsxEmbeddedImagesByRow(string $tmpPath): array
{
    $sheetRels = parseXlsxRelationships(readXlsxEntry($tmpPath, 'xl/worksheets/_rels/sheet1.xml.rels'));
    $drawingPath = '';
    foreach ($sheetRels as $target) {
        if (str_contains($target, 'drawing')) {
            $drawingPath = resolveXlsxTargetPath('xl/worksheets/sheet1.xml', $target);
            break;
        }
    }
    if ($drawingPath === '') {
        return [];
    }

    $drawingXmlText = readXlsxEntry($tmpPath, $drawingPath);
    if ($drawingXmlText === false) {
        return [];
    }

    $drawingRelsPath = dirname($drawingPath) . '/_rels/' . basename($drawingPath) . '.rels';
    $drawingRels = parseXlsxRelationships(readXlsxEntry($tmpPath, $drawingRelsPath));
    $imagesByRow = [];
    preg_match_all('#<[^>]*:?(?:twoCellAnchor|oneCellAnchor)\b.*?</[^>]*:?(?:twoCellAnchor|oneCellAnchor)>#s', $drawingXmlText, $anchors);

    foreach ($anchors[0] as $anchorXml) {
        if (!preg_match('#<[^>]*:?from>.*?<[^>]*:?row>(\d+)</[^>]*:?row>.*?</[^>]*:?from>#s', $anchorXml, $rowMatch)) {
            continue;
        }
        if (!preg_match('#(?:r:embed|embed)="([^"]+)"#', $anchorXml, $embedMatch)) {
            continue;
        }

        $rowNumber = ((int) $rowMatch[1]) + 1;
        $relationshipId = $embedMatch[1];
        if ($relationshipId === '' || empty($drawingRels[$relationshipId])) {
            continue;
        }

        $mediaPath = resolveXlsxTargetPath($drawingPath, $drawingRels[$relationshipId]);
        $content = readXlsxEntry($tmpPath, $mediaPath);
        if ($content === false) {
            continue;
        }

        $imagesByRow[$rowNumber] = [
            'path' => $mediaPath,
            'content' => $content,
        ];
    }

    return $imagesByRow;
}

function readSpreadsheetRows(string $tmpPath, string $extension): array
{
    if ($extension === 'csv') {
        $rows = [];
        $handle = fopen($tmpPath, 'r');
        if (!$handle) {
            throw new RuntimeException('Unable to read the uploaded CSV file.');
        }
        while (($row = fgetcsv($handle)) !== false) {
            $rows[] = $row;
        }
        fclose($handle);
        return $rows;
    }

    if ($extension !== 'xlsx') {
        throw new RuntimeException('Only .xlsx and .csv files are supported.');
    }

    $sharedStrings = [];
    $sharedXml = readXlsxEntry($tmpPath, 'xl/sharedStrings.xml');
    if ($sharedXml !== false) {
        $xml = simplexml_load_string($sharedXml);
        if ($xml) {
            foreach ($xml->si as $item) {
                if (isset($item->t)) {
                    $sharedStrings[] = (string) $item->t;
                    continue;
                }
                $parts = [];
                foreach ($item->r as $run) {
                    $parts[] = (string) $run->t;
                }
                $sharedStrings[] = implode('', $parts);
            }
        }
    }

    $sheetXml = readXlsxEntry($tmpPath, 'xl/worksheets/sheet1.xml');

    if ($sheetXml === false) {
        throw new RuntimeException('The Excel file does not contain a first worksheet.');
    }

    $xml = simplexml_load_string($sheetXml);
    if (!$xml || !isset($xml->sheetData)) {
        throw new RuntimeException('Unable to read rows from the Excel file.');
    }

    $rows = [];
    foreach ($xml->sheetData->row as $rowNode) {
        $row = [];
        foreach ($rowNode->c as $cell) {
            $ref = (string) $cell['r'];
            $letters = preg_replace('/[^A-Z]/', '', strtoupper($ref));
            $column = 0;
            for ($i = 0; $i < strlen($letters); $i++) {
                $column = ($column * 26) + (ord($letters[$i]) - 64);
            }
            $index = max(0, $column - 1);
            $type = (string) $cell['t'];
            $value = isset($cell->v) ? (string) $cell->v : '';

            if ($type === 's') {
                $value = $sharedStrings[(int) $value] ?? '';
            } elseif ($type === 'inlineStr' && isset($cell->is->t)) {
                $value = (string) $cell->is->t;
            }

            $row[$index] = $value;
        }
        if (!empty($row)) {
            ksort($row);
            $rows[] = $row;
        }
    }

    return $rows;
}

function spreadsheetRowsToProducts(array $rows): array
{
    if (count($rows) < 2) {
        throw new RuntimeException('The import file must include a header row and at least one product row.');
    }

    $headerAliases = [
        'name' => 'productName',
        'productname' => 'productName',
        'product' => 'productName',
        'barcode' => 'barcode',
        'barcodenumber' => 'barcode',
        'barcodevalue' => 'barcode',
        'ean' => 'barcode',
        'upc' => 'barcode',
        'description' => 'productDescription',
        'productdescription' => 'productDescription',
        'category' => 'category',
        'price' => 'price',
        'stock' => 'stockQuantity',
        'stockquantity' => 'stockQuantity',
        'quantity' => 'stockQuantity',
        'image' => 'imagePath',
        'imagepath' => 'imagePath',
        'imageurl' => 'imagePath',
        'expiry' => 'expiryDate',
        'expirydate' => 'expiryDate',
        'expirationdate' => 'expiryDate',
        'status' => 'status',
        'compliance' => 'complianceStatus',
        'compliancestatus' => 'complianceStatus',
        'producttype' => 'productType',
        'type' => 'productType',
    ];

    $headers = [];
    foreach ($rows[0] as $index => $header) {
        $key = normalizeImportHeader((string) $header);
        if (isset($headerAliases[$key])) {
            $headers[$index] = $headerAliases[$key];
        }
    }

    if (!in_array('productName', $headers, true) || !in_array('barcode', $headers, true) || !in_array('category', $headers, true) || !in_array('price', $headers, true)) {
        throw new RuntimeException('The import file must include productName, barcode, category, and price columns.');
    }

    $products = [];
    for ($i = 1; $i < count($rows); $i++) {
        $raw = $rows[$i];
        $allEmpty = true;
        foreach ($raw as $cell) {
            if (trim((string) $cell) !== '') {
                $allEmpty = false;
                break;
            }
        }
        if ($allEmpty) {
            continue;
        }

        $product = [
            'productName' => '',
            'barcode' => '',
            'productDescription' => '',
            'category' => '',
            'price' => 0,
            'stockQuantity' => 0,
            'imagePath' => '',
            'expiryDate' => null,
            'status' => 'Active',
            'complianceStatus' => 'Approved',
            'productType' => 'Both',
        ];

        foreach ($headers as $index => $field) {
            $product[$field] = isset($raw[$index]) ? trim((string) $raw[$index]) : '';
        }

        $product['price'] = (float) str_replace(',', '', (string) $product['price']);
        $product['barcode'] = normalizeBarcode($product['barcode']);
        $product['imagePath'] = normalizeProductImagePath($product['imagePath']);
        $product['stockQuantity'] = max(0, (int) $product['stockQuantity']);
        $product['expiryDate'] = parseImportDate($product['expiryDate']);
        $product['status'] = $product['status'] === 'Inactive' ? 'Inactive' : 'Active';
        $product['complianceStatus'] = in_array($product['complianceStatus'], ['Pending', 'Approved', 'Rejected'], true) ? $product['complianceStatus'] : 'Approved';
        $product['productType'] = in_array($product['productType'], ['Physical', 'Online', 'Both'], true) ? $product['productType'] : 'Both';

        if ($product['productName'] === '' || $product['barcode'] === '' || $product['category'] === '' || $product['price'] < 0) {
            throw new RuntimeException('Row ' . ($i + 1) . ' has missing or invalid product data.');
        }

        $products[] = $product;
    }

    if (empty($products)) {
        throw new RuntimeException('No product rows were found in the import file.');
    }

    return $products;
}

function attachEmbeddedImagesToProducts(array $products, array $imagesByRow): array
{
    foreach ($products as $index => $product) {
        $rowNumber = $index + 2;
        if (!empty($product['imagePath']) || empty($imagesByRow[$rowNumber])) {
            continue;
        }

        $image = $imagesByRow[$rowNumber];
        $imagePath = saveEmbeddedProductImage(
            (string) $product['productName'],
            (string) $product['barcode'],
            (string) ($image['path'] ?? ''),
            (string) ($image['content'] ?? '')
        );

        if ($imagePath !== '') {
            $products[$index]['imagePath'] = $imagePath;
        }
    }

    return $products;
}

function productsFromUploadedSpreadsheet(array $file): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        throw new RuntimeException('Please choose an Excel or CSV file to import.');
    }
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Failed to upload the import file. Please try again.');
    }

    $extension = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
    $rows = readSpreadsheetRows((string) $file['tmp_name'], $extension);
    $products = spreadsheetRowsToProducts($rows);
    if ($extension === 'xlsx') {
        $products = attachEmbeddedImagesToProducts($products, extractXlsxEmbeddedImagesByRow((string) $file['tmp_name']));
    }

    return $products;
}

function insertProducts(mysqli $conn, array $products): int
{
    ensureProductBarcodeSchema($conn);

    $stmt = $conn->prepare(
        'INSERT INTO products (productName, barcode, barcodeImagePath, productDescription, category, price, stockQuantity, physicalStock, onlineStock, productType, complianceStatus, status, imagePath, expiryDate)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );

    $inserted = 0;
    foreach ($products as $product) {
        $productName = $product['productName'];
        $barcode = $product['barcode'];
        $productDescription = $product['productDescription'];
        $category = $product['category'];
        $price = (float) $product['price'];
        $stockQuantity = (int) $product['stockQuantity'];
        $physicalStock = $stockQuantity;
        $onlineStock = $stockQuantity;
        $productType = $product['productType'];
        $complianceStatus = $product['complianceStatus'];
        $status = $product['status'];
        $imagePath = $product['imagePath'];
        $expiryDate = $product['expiryDate'];

        if ($barcode !== '' && productBarcodeExists($conn, $barcode)) {
            throw new RuntimeException('Barcode ' . $barcode . ' is already assigned to another product.');
        }
        $barcodeImagePath = saveBarcodeImage($barcode);
        if (shouldGenerateProductImage($imagePath)) {
            $imagePath = saveGeneratedProductImage($productName, $category, $barcode);
        }

        $stmt->bind_param(
            'sssssdiiisssss',
            $productName,
            $barcode,
            $barcodeImagePath,
            $productDescription,
            $category,
            $price,
            $stockQuantity,
            $physicalStock,
            $onlineStock,
            $productType,
            $complianceStatus,
            $status,
            $imagePath,
            $expiryDate
        );
        $stmt->execute();
        $inserted++;
    }
    $stmt->close();

    return $inserted;
}

function insertProductSubmissions(mysqli $conn, array $products, int $userId): int
{
    ensureProductBarcodeSchema($conn);

    $stmt = $conn->prepare(
        'INSERT INTO product_submissions
         (submissionID, userID, productName, barcode, barcodeImagePath, productDescription, category, price, stockQuantity, imagePath, expiryDate, status)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );

    $inserted = 0;
    foreach ($products as $product) {
        $nextId = 1;
        $maxResult = $conn->query('SELECT COALESCE(MAX(submissionID), 0) + 1 AS nextID FROM product_submissions');
        if ($maxResult) {
            $row = $maxResult->fetch_assoc();
            $nextId = (int) ($row['nextID'] ?? 1);
            $maxResult->free();
        }
        $productName = $product['productName'];
        $barcode = $product['barcode'];
        $productDescription = $product['productDescription'];
        $category = $product['category'];
        $price = (float) $product['price'];
        $stockQuantity = (int) $product['stockQuantity'];
        $imagePath = $product['imagePath'];
        $expiryDate = $product['expiryDate'];
        $status = 'Pending';
        if ($barcode !== '' && (productBarcodeExists($conn, $barcode) || pendingSubmissionBarcodeExists($conn, $barcode))) {
            throw new RuntimeException('Barcode ' . $barcode . ' is already assigned to another product or pending submission.');
        }
        $barcodeImagePath = saveBarcodeImage($barcode);
        if (shouldGenerateProductImage($imagePath)) {
            $imagePath = saveGeneratedProductImage($productName, $category, $barcode);
        }

        $stmt->bind_param(
            'iisssssdisss',
            $nextId,
            $userId,
            $productName,
            $barcode,
            $barcodeImagePath,
            $productDescription,
            $category,
            $price,
            $stockQuantity,
            $imagePath,
            $expiryDate,
            $status
        );
        $stmt->execute();
        $inserted++;
    }
    $stmt->close();

    return $inserted;
}
