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
    return spreadsheetRowsToProducts($rows);
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
