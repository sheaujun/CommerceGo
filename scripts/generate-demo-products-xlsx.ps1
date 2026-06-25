$ErrorActionPreference = 'Stop'

$root = Split-Path -Parent $PSScriptRoot
$outputPath = Join-Path $root 'demo-products-import.xlsx'
$tempDir = Join-Path $root ("tmp_demo_products_xlsx_{0}" -f ([guid]::NewGuid().ToString('N')))

if (Test-Path -LiteralPath $outputPath) {
    $timestamp = Get-Date -Format 'yyyyMMdd-HHmmss'
    $outputPath = Join-Path $root "demo-products-import-$timestamp.xlsx"
}
New-Item -ItemType Directory -Force -Path $tempDir | Out-Null
New-Item -ItemType Directory -Force -Path (Join-Path $tempDir '_rels') | Out-Null
New-Item -ItemType Directory -Force -Path (Join-Path $tempDir 'xl') | Out-Null
New-Item -ItemType Directory -Force -Path (Join-Path $tempDir 'xl\_rels') | Out-Null
New-Item -ItemType Directory -Force -Path (Join-Path $tempDir 'xl\worksheets') | Out-Null

$rows = @(
    @('productName', 'barcode', 'description', 'category', 'price', 'stock', 'imagePath', 'expiryDate', 'status', 'compliance', 'productType'),
    @('Panadol Actifast 500mg 20s', '9556006000011', 'Fast-acting paracetamol tablets for fever and mild pain relief.', 'Pain Relief', '12.90', '120', 'uploads/products/panadol-actifast.jpg', '2027-08-31', 'Active', 'Approved', 'Both'),
    @('Hurixs Fever Patch Kids 6s', '9556006000028', 'Cooling gel patches suitable for children during fever care.', 'Child Care', '9.50', '80', 'uploads/products/hurixs-fever-patch.jpg', '2027-11-15', 'Active', 'Approved', 'Physical'),
    @('Blackmores Bio C 1000 60s', '9556006000035', 'Vitamin C supplement with rose hips for daily immune support.', 'Vitamins', '48.00', '45', 'uploads/products/blackmores-bio-c.jpg', '2028-02-28', 'Active', 'Approved', 'Both'),
    @('Hansaplast Elastic Plaster 20s', '9556006000042', 'Flexible adhesive plasters for minor cuts and scrapes.', 'First Aid', '7.90', '150', 'uploads/products/hansaplast-elastic.jpg', '2028-01-10', 'Active', 'Approved', 'Both'),
    @('Betadine Antiseptic Solution 120ml', '9556006000059', 'Antiseptic solution for wound cleansing and skin disinfection.', 'First Aid', '18.50', '70', 'uploads/products/betadine-solution.jpg', '2027-06-30', 'Active', 'Approved', 'Physical'),
    @('Cetaphil Gentle Skin Cleanser 125ml', '9556006000066', 'Soap-free gentle cleanser for sensitive and dry skin.', 'Skin Care', '29.90', '55', 'uploads/products/cetaphil-cleanser.jpg', '2028-04-20', 'Active', 'Approved', 'Both'),
    @('KoolFever Adult Cooling Gel 4s', '9556006000073', 'Cooling gel sheets for temporary relief during fever.', 'Health Care', '8.80', '90', 'uploads/products/koolfever-adult.jpg', '2027-10-05', 'Active', 'Approved', 'Online'),
    @('Difflam Forte Throat Spray 15ml', '9556006000080', 'Throat spray for sore throat relief.', 'Cough & Cold', '32.50', '38', 'uploads/products/difflam-forte.jpg', '2027-12-01', 'Active', 'Pending', 'Both'),
    @('Optrex Eye Wash 110ml', '9556006000097', 'Sterile eye wash for tired or irritated eyes.', 'Eye Care', '21.90', '34', 'uploads/products/optrex-eye-wash.jpg', '2027-09-12', 'Active', 'Approved', 'Physical'),
    @('Demo Discontinued Multivitamin 30s', '9556006000103', 'Inactive demo product for showing disabled catalogue items.', 'Vitamins', '19.90', '0', 'uploads/products/demo-discontinued.jpg', '2026-12-31', 'Inactive', 'Rejected', 'Online')
)

function ConvertTo-CellRef([int] $rowIndex, [int] $columnIndex) {
    $letters = ''
    $column = $columnIndex
    while ($column -gt 0) {
        $mod = ($column - 1) % 26
        $letters = [char](65 + $mod) + $letters
        $column = [math]::Floor(($column - $mod) / 26)
    }
    return "$letters$rowIndex"
}

function Escape-Xml([string] $value) {
    return [System.Security.SecurityElement]::Escape($value)
}

$sheetBuilder = [System.Text.StringBuilder]::new()
[void] $sheetBuilder.Append('<?xml version="1.0" encoding="UTF-8" standalone="yes"?>')
[void] $sheetBuilder.Append('<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">')
[void] $sheetBuilder.Append('<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>')
[void] $sheetBuilder.Append('<sheetFormatPr defaultRowHeight="18"/>')
[void] $sheetBuilder.Append('<cols><col min="1" max="1" width="34" customWidth="1"/><col min="2" max="2" width="18" customWidth="1"/><col min="3" max="3" width="58" customWidth="1"/><col min="4" max="11" width="18" customWidth="1"/></cols>')
[void] $sheetBuilder.Append('<sheetData>')

for ($r = 0; $r -lt $rows.Count; $r++) {
    $rowIndex = $r + 1
    $style = if ($rowIndex -eq 1) { ' s="1"' } else { '' }
    [void] $sheetBuilder.Append("<row r=`"$rowIndex`">")
    for ($c = 0; $c -lt $rows[$r].Count; $c++) {
        $cellRef = ConvertTo-CellRef $rowIndex ($c + 1)
        $text = Escape-Xml $rows[$r][$c]
        [void] $sheetBuilder.Append("<c r=`"$cellRef`" t=`"inlineStr`"$style><is><t>$text</t></is></c>")
    }
    [void] $sheetBuilder.Append('</row>')
}

[void] $sheetBuilder.Append('</sheetData><autoFilter ref="A1:K11"/></worksheet>')

Set-Content -LiteralPath (Join-Path $tempDir '[Content_Types].xml') -Value @'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
  <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
  <Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
</Types>
'@

Set-Content -LiteralPath (Join-Path $tempDir '_rels\.rels') -Value @'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>
'@

Set-Content -LiteralPath (Join-Path $tempDir 'xl\workbook.xml') -Value @'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <sheets>
    <sheet name="Demo Products" sheetId="1" r:id="rId1"/>
  </sheets>
</workbook>
'@

Set-Content -LiteralPath (Join-Path $tempDir 'xl\_rels\workbook.xml.rels') -Value @'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
  <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
</Relationships>
'@

Set-Content -LiteralPath (Join-Path $tempDir 'xl\styles.xml') -Value @'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <fonts count="2">
    <font><sz val="11"/><name val="Calibri"/></font>
    <font><b/><sz val="11"/><name val="Calibri"/></font>
  </fonts>
  <fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>
  <borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>
  <cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>
  <cellXfs count="2">
    <xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>
    <xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>
  </cellXfs>
</styleSheet>
'@

Set-Content -LiteralPath (Join-Path $tempDir 'xl\worksheets\sheet1.xml') -Value $sheetBuilder.ToString()

Add-Type -AssemblyName System.IO.Compression.FileSystem
[System.IO.Compression.ZipFile]::CreateFromDirectory($tempDir, $outputPath)
try {
    Get-ChildItem -LiteralPath $tempDir -Recurse -Force | ForEach-Object { $_.Attributes = 'Normal' }
    Remove-Item -LiteralPath $tempDir -Recurse -Force
} catch {
    Write-Warning "Created the Excel file, but could not remove temporary folder: $tempDir"
}

Write-Host "Created $outputPath"
