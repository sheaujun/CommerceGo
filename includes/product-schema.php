<?php

require_once __DIR__ . '/barcode-generator.php';

function ensureProductBarcodeSchema(mysqli $conn): void
{
    $productColumn = $conn->query("SHOW COLUMNS FROM products LIKE 'barcode'");
    if ($productColumn && $productColumn->num_rows === 0) {
        $conn->query("ALTER TABLE products ADD COLUMN barcode VARCHAR(100) NULL AFTER productName");
        $conn->query("CREATE INDEX idx_products_barcode ON products (barcode)");
    }
    if ($productColumn) {
        $productColumn->free();
    }

    $productBarcodeImageColumn = $conn->query("SHOW COLUMNS FROM products LIKE 'barcodeImagePath'");
    if ($productBarcodeImageColumn && $productBarcodeImageColumn->num_rows === 0) {
        $conn->query("ALTER TABLE products ADD COLUMN barcodeImagePath VARCHAR(255) NULL AFTER barcode");
    }
    if ($productBarcodeImageColumn) {
        $productBarcodeImageColumn->free();
    }

    $submissionColumn = $conn->query("SHOW COLUMNS FROM product_submissions LIKE 'barcode'");
    if ($submissionColumn && $submissionColumn->num_rows === 0) {
        $conn->query("ALTER TABLE product_submissions ADD COLUMN barcode VARCHAR(100) NULL AFTER productName");
        $conn->query("CREATE INDEX idx_product_submissions_barcode ON product_submissions (barcode)");
    }
    if ($submissionColumn) {
        $submissionColumn->free();
    }

    $submissionBarcodeImageColumn = $conn->query("SHOW COLUMNS FROM product_submissions LIKE 'barcodeImagePath'");
    if ($submissionBarcodeImageColumn && $submissionBarcodeImageColumn->num_rows === 0) {
        $conn->query("ALTER TABLE product_submissions ADD COLUMN barcodeImagePath VARCHAR(255) NULL AFTER barcode");
    }
    if ($submissionBarcodeImageColumn) {
        $submissionBarcodeImageColumn->free();
    }

    backfillBarcodeImages($conn, 'products', 'productID');
    backfillBarcodeImages($conn, 'product_submissions', 'submissionID');
}

function normalizeBarcode(?string $barcode): string
{
    return trim((string) $barcode);
}

function productBarcodeExists(mysqli $conn, string $barcode, int $excludeProductId = 0): bool
{
    $barcode = normalizeBarcode($barcode);
    if ($barcode === '') {
        return false;
    }

    $sql = 'SELECT productID FROM products WHERE barcode = ?';
    if ($excludeProductId > 0) {
        $sql .= ' AND productID <> ?';
    }
    $sql .= ' LIMIT 1';

    $stmt = $conn->prepare($sql);
    if ($excludeProductId > 0) {
        $stmt->bind_param('si', $barcode, $excludeProductId);
    } else {
        $stmt->bind_param('s', $barcode);
    }
    $stmt->execute();
    $exists = (bool) $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $exists;
}

function pendingSubmissionBarcodeExists(mysqli $conn, string $barcode, int $excludeSubmissionId = 0): bool
{
    $barcode = normalizeBarcode($barcode);
    if ($barcode === '') {
        return false;
    }

    $sql = 'SELECT submissionID FROM product_submissions WHERE barcode = ? AND status = "Pending"';
    if ($excludeSubmissionId > 0) {
        $sql .= ' AND submissionID <> ?';
    }
    $sql .= ' LIMIT 1';

    $stmt = $conn->prepare($sql);
    if ($excludeSubmissionId > 0) {
        $stmt->bind_param('si', $barcode, $excludeSubmissionId);
    } else {
        $stmt->bind_param('s', $barcode);
    }
    $stmt->execute();
    $exists = (bool) $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $exists;
}

function backfillBarcodeImages(mysqli $conn, string $table, string $idColumn): void
{
    $allowedTables = ['products', 'product_submissions'];
    if (!in_array($table, $allowedTables, true)) {
        return;
    }

    $result = $conn->query(
        "SELECT {$idColumn} AS id, barcode
         FROM {$table}
         WHERE barcode IS NOT NULL
           AND barcode <> ''
           AND (barcodeImagePath IS NULL OR barcodeImagePath = '')
         LIMIT 50"
    );

    if (!$result) {
        return;
    }

    $update = $conn->prepare("UPDATE {$table} SET barcodeImagePath = ? WHERE {$idColumn} = ?");
    while ($row = $result->fetch_assoc()) {
        try {
            $barcodeImagePath = saveBarcodeImage((string) $row['barcode']);
        } catch (Throwable $e) {
            continue;
        }

        if ($barcodeImagePath !== '') {
            $id = (int) $row['id'];
            $update->bind_param('si', $barcodeImagePath, $id);
            $update->execute();
        }
    }
    $update->close();
    $result->free();
}
