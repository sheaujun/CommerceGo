$ErrorActionPreference = 'Stop'

$root = Split-Path -Parent $PSScriptRoot
$outputPath = Join-Path $root 'staff-new-ai-products-with-images-import-20260708.xlsx'
$tempDir = Join-Path $root ("tmp_staff_products_xlsx_{0}" -f ([guid]::NewGuid().ToString('N')))

if (Test-Path -LiteralPath $outputPath) {
    $timestamp = Get-Date -Format 'yyyyMMdd-HHmmss'
    $outputPath = Join-Path $root "staff-new-ai-products-with-images-import-$timestamp.xlsx"
}

New-Item -ItemType Directory -Force -Path $tempDir | Out-Null
New-Item -ItemType Directory -Force -Path (Join-Path $tempDir '_rels') | Out-Null
New-Item -ItemType Directory -Force -Path (Join-Path $tempDir 'xl') | Out-Null
New-Item -ItemType Directory -Force -Path (Join-Path $tempDir 'xl\_rels') | Out-Null
New-Item -ItemType Directory -Force -Path (Join-Path $tempDir 'xl\drawings\_rels') | Out-Null
New-Item -ItemType Directory -Force -Path (Join-Path $tempDir 'xl\media') | Out-Null
New-Item -ItemType Directory -Force -Path (Join-Path $tempDir 'xl\worksheets') | Out-Null
New-Item -ItemType Directory -Force -Path (Join-Path $tempDir 'xl\worksheets\_rels') | Out-Null

$rows = @(
    @('productName', 'barcode', 'description', 'category', 'price', 'stock', 'imagePath', 'expiryDate', 'status', 'compliance', 'productType'),
    @('Aloe Vera Soothing Gel 100ml', '9559709720019', 'Cooling aloe vera gel for dry or irritated skin.', 'Skin Care', '16.90', '68', '', '2028-09-30', 'Active', 'Approved', 'Both'),
    @('Zinc Plus Tablets 60s', '9559709720026', 'Daily zinc supplement tablets for immune support.', 'Vitamins', '24.90', '54', '', '2028-10-31', 'Active', 'Approved', 'Both'),
    @('Cough Relief Syrup 100ml', '9559709720033', 'Soothing cough syrup for throat comfort and cough relief.', 'Cough & Cold', '15.50', '45', '', '2028-08-31', 'Active', 'Approved', 'Physical'),
    @('Wound Care Antiseptic Spray 100ml', '9559709720040', 'Convenient antiseptic spray for minor wound cleansing.', 'First Aid', '19.90', '62', '', '2028-11-30', 'Active', 'Approved', 'Both'),
    @('Baby Saline Nasal Drops 15ml', '9559709720057', 'Gentle saline nasal drops for babies and young children.', 'Child Care', '11.90', '80', '', '2028-12-31', 'Active', 'Approved', 'Online')
)

$embeddedImages = @(
    'staff-import-aloe-vera-gel.png',
    'staff-import-zinc-plus-tablets.png',
    'staff-import-cough-relief-syrup.png',
    'staff-import-wound-care-spray.png',
    'staff-import-baby-saline-drops.png'
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
[void] $sheetBuilder.Append('<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">')
[void] $sheetBuilder.Append('<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>')
[void] $sheetBuilder.Append('<sheetFormatPr defaultRowHeight="72"/>')
[void] $sheetBuilder.Append('<cols><col min="1" max="1" width="38" customWidth="1"/><col min="2" max="2" width="18" customWidth="1"/><col min="3" max="3" width="58" customWidth="1"/><col min="4" max="11" width="18" customWidth="1"/></cols>')
[void] $sheetBuilder.Append('<sheetData>')

for ($r = 0; $r -lt $rows.Count; $r++) {
    $rowIndex = $r + 1
    $style = if ($rowIndex -eq 1) { ' s="1"' } else { '' }
    $rowHeight = if ($rowIndex -eq 1) { ' ht="18" customHeight="1"' } else { ' ht="88" customHeight="1"' }
    [void] $sheetBuilder.Append("<row r=`"$rowIndex`"$rowHeight>")
    for ($c = 0; $c -lt $rows[$r].Count; $c++) {
        $cellRef = ConvertTo-CellRef $rowIndex ($c + 1)
        $text = Escape-Xml $rows[$r][$c]
        [void] $sheetBuilder.Append("<c r=`"$cellRef`" t=`"inlineStr`"$style><is><t>$text</t></is></c>")
    }
    [void] $sheetBuilder.Append('</row>')
}

[void] $sheetBuilder.Append("</sheetData><autoFilter ref=`"A1:K$($rows.Count)`"/><drawing r:id=`"rId1`"/></worksheet>")

$drawingBuilder = [System.Text.StringBuilder]::new()
[void] $drawingBuilder.Append('<?xml version="1.0" encoding="UTF-8" standalone="yes"?>')
[void] $drawingBuilder.Append('<xdr:wsDr xmlns:xdr="http://schemas.openxmlformats.org/drawingml/2006/spreadsheetDrawing" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main">')

$drawingRelsBuilder = [System.Text.StringBuilder]::new()
[void] $drawingRelsBuilder.Append('<?xml version="1.0" encoding="UTF-8" standalone="yes"?>')
[void] $drawingRelsBuilder.Append('<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">')

for ($i = 0; $i -lt $embeddedImages.Count; $i++) {
    $source = Join-Path $root "admin\uploads\products\$($embeddedImages[$i])"
    if (!(Test-Path -LiteralPath $source)) {
        throw "Missing product image: $source"
    }
    $mediaName = "image$($i + 1).png"
    Copy-Item -LiteralPath $source -Destination (Join-Path $tempDir "xl\media\$mediaName") -Force
    $relationshipId = "rId$($i + 1)"
    $rowAnchor = $i + 1
    [void] $drawingBuilder.Append("<xdr:twoCellAnchor editAs=`"oneCell`"><xdr:from><xdr:col>6</xdr:col><xdr:colOff>95250</xdr:colOff><xdr:row>$rowAnchor</xdr:row><xdr:rowOff>95250</xdr:rowOff></xdr:from><xdr:to><xdr:col>7</xdr:col><xdr:colOff>800000</xdr:colOff><xdr:row>$($rowAnchor + 1)</xdr:row><xdr:rowOff>650000</xdr:rowOff></xdr:to><xdr:pic><xdr:nvPicPr><xdr:cNvPr id=`"$($i + 2)`" name=`"Product Image $($i + 1)`"/><xdr:cNvPicPr><a:picLocks noChangeAspect=`"1`"/></xdr:cNvPicPr></xdr:nvPicPr><xdr:blipFill><a:blip xmlns:r=`"http://schemas.openxmlformats.org/officeDocument/2006/relationships`" r:embed=`"$relationshipId`"/><a:stretch><a:fillRect/></a:stretch></xdr:blipFill><xdr:spPr><a:xfrm><a:off x=`"0`" y=`"0`"/><a:ext cx=`"900000`" cy=`"760000`"/></a:xfrm><a:prstGeom prst=`"rect`"><a:avLst/></a:prstGeom></xdr:spPr></xdr:pic><xdr:clientData/></xdr:twoCellAnchor>")
    [void] $drawingRelsBuilder.Append("<Relationship Id=`"$relationshipId`" Type=`"http://schemas.openxmlformats.org/officeDocument/2006/relationships/image`" Target=`"../media/$mediaName`"/>")
}

[void] $drawingBuilder.Append('</xdr:wsDr>')
[void] $drawingRelsBuilder.Append('</Relationships>')

Set-Content -LiteralPath (Join-Path $tempDir '[Content_Types].xml') -Value @'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Default Extension="jpg" ContentType="image/jpeg"/>
  <Default Extension="png" ContentType="image/png"/>
  <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
  <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
  <Override PartName="/xl/drawings/drawing1.xml" ContentType="application/vnd.openxmlformats-officedocument.drawing+xml"/>
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
    <sheet name="Staff New Products" sheetId="1" r:id="rId1"/>
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

Set-Content -LiteralPath (Join-Path $tempDir 'xl\worksheets\_rels\sheet1.xml.rels') -Value @'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/drawing" Target="../drawings/drawing1.xml"/>
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
Set-Content -LiteralPath (Join-Path $tempDir 'xl\drawings\drawing1.xml') -Value $drawingBuilder.ToString()
Set-Content -LiteralPath (Join-Path $tempDir 'xl\drawings\_rels\drawing1.xml.rels') -Value $drawingRelsBuilder.ToString()

Add-Type -AssemblyName System.IO.Compression.FileSystem
[System.IO.Compression.ZipFile]::CreateFromDirectory($tempDir, $outputPath)
try {
    Get-ChildItem -LiteralPath $tempDir -Recurse -Force | ForEach-Object { $_.Attributes = 'Normal' }
    Remove-Item -LiteralPath $tempDir -Recurse -Force
} catch {
    Write-Warning "Created the Excel file, but could not remove temporary folder: $tempDir"
}

Write-Host "Created $outputPath"
