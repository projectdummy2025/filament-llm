<?php

namespace App\Services;

use App\Models\DocumentTemplate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory as SpreadsheetIOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory as WordIOFactory;
use PhpOffice\PhpWord\Element\Table;
use PhpOffice\PhpWord\Element\TextRun;

class DocumentGeneratorService
{
    public function __construct(
        protected GeminiClient $geminiClient
    ) {}

    public function generate(DocumentTemplate $template, string $prompt): string
    {
        Log::info("Generating document for template: {$template->name} ({$template->output_type})");

        $fileId = str()->uuid()->toString();

        return match ($template->output_type) {
            'docs' => $this->generateDocx($template->template_path, $fileId, $prompt),
            'excel' => $this->generateXlsx($template->template_path, $fileId, $prompt),
            default => throw new \RuntimeException('Invalid output_type.'),
        };
    }

    /**
     * Generate DOCX by reading template structure, sending to AI, and creating new document.
     */
    private function generateDocx(string $templatePath, string $fileId, string $userPrompt): string
    {
        $templateFullPath = Storage::disk('public')->path($templatePath);
        if (!is_file($templateFullPath)) {
            throw new \RuntimeException('DOCX template file not found.');
        }

        // Validate user prompt
        $userPrompt = trim($userPrompt);
        if (empty($userPrompt)) {
            throw new \RuntimeException('Instruksi tidak boleh kosong. Silakan jelaskan apa yang ingin Anda generate.');
        }

        // Step 1: Read and analyze template structure
        $templateStructure = $this->analyzeDocxTemplate($templateFullPath);
        Log::info("Template structure analyzed: " . count($templateStructure['elements']) . " elements found");
        Log::debug("Template structure:\n" . json_encode($templateStructure, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        // Step 2: Build prompt with template structure
        $systemPrompt = $this->buildDocxPrompt($templateStructure);
        
        // Emphasize user instructions
        $fullPrompt = $systemPrompt . "\n\n" . 
            "===========================================\n" .
            "## INSTRUKSI USER (WAJIB DIIKUTI):\n" .
            "===========================================\n" .
            $userPrompt . "\n" .
            "===========================================\n\n" .
            "Sekarang generate dokumen berdasarkan instruksi di atas. Output langsung tanpa penjelasan:";

        Log::info("User prompt: " . $userPrompt);
        Log::debug("Full prompt sent to AI:\n" . $fullPrompt);

        // Step 3: Call AI with higher token limit for complex documents
        $aiResponse = $this->geminiClient->generateText($fullPrompt, maxOutputTokens: 6000);
        Log::info("AI Response length: " . strlen($aiResponse));
        Log::debug("AI Response content:\n" . $aiResponse);

        // Step 4: Parse AI response
        $parsedContent = $this->parseAiResponse($aiResponse);
        Log::info("Parsed content: " . count($parsedContent) . " sections");

        // Step 5: Generate new document based on template structure with AI content
        $outRelPath = 'generated/' . $fileId . '.docx';
        $outFullPath = Storage::disk('public')->path($outRelPath);
        @mkdir(dirname($outFullPath), 0775, true);

        $this->createDocxFromStructure($templateStructure, $parsedContent, $outFullPath);

        Log::info("Document generated: {$outRelPath}");

        return $outRelPath;
    }

    /**
     * Analyze DOCX template and extract its structure.
     */
    private function analyzeDocxTemplate(string $filePath): array
    {
        $phpWord = WordIOFactory::load($filePath);
        
        $structure = [
            'elements' => [],
            'tables' => [],
        ];

        $elementIndex = 0;
        foreach ($phpWord->getSections() as $section) {
            foreach ($section->getElements() as $element) {
                $elementData = $this->extractElementInfo($element, $elementIndex);
                if ($elementData !== null) {
                    $structure['elements'][] = $elementData;
                    
                    if ($elementData['type'] === 'table') {
                        $structure['tables'][] = $elementData;
                    }
                    
                    $elementIndex++;
                }
            }
        }

        return $structure;
    }

    /**
     * Extract information from a document element.
     */
    private function extractElementInfo($element, int $index): ?array
    {
        // Handle Text/Paragraph
        if ($element instanceof TextRun || method_exists($element, 'getText')) {
            $text = $this->getElementText($element);
            if (trim($text) === '') {
                return null; // Skip empty elements
            }

            $style = $this->detectTextStyle($element);
            
            return [
                'index' => $index,
                'type' => 'paragraph',
                'content' => $text,
                'style' => $style,
            ];
        }

        // Handle Table
        if ($element instanceof Table) {
            $tableData = $this->extractTableStructure($element);
            return [
                'index' => $index,
                'type' => 'table',
                'headers' => $tableData['headers'],
                'sampleRows' => $tableData['rows'],
                'columns' => $tableData['columnCount'],
            ];
        }

        return null;
    }

    /**
     * Get text content from an element.
     */
    private function getElementText($element): string
    {
        if (method_exists($element, 'getText')) {
            return $element->getText();
        }
        
        if ($element instanceof TextRun) {
            $text = '';
            foreach ($element->getElements() as $child) {
                if (method_exists($child, 'getText')) {
                    $text .= $child->getText();
                }
            }
            return $text;
        }

        return '';
    }

    /**
     * Detect text style (heading, bold, etc.).
     */
    private function detectTextStyle($element): string
    {
        $isBold = false;
        $fontSize = 12;

        if ($element instanceof TextRun) {
            foreach ($element->getElements() as $child) {
                if (method_exists($child, 'getFontStyle')) {
                    $fontStyle = $child->getFontStyle();
                    if ($fontStyle && is_object($fontStyle)) {
                        if (method_exists($fontStyle, 'isBold') && $fontStyle->isBold()) {
                            $isBold = true;
                        }
                        if (method_exists($fontStyle, 'getSize') && $fontStyle->getSize()) {
                            $fontSize = $fontStyle->getSize();
                        }
                    }
                }
            }
        }

        if ($fontSize >= 14 || $isBold) {
            return 'heading';
        }

        return 'normal';
    }

    /**
     * Extract table structure.
     */
    private function extractTableStructure(Table $table): array
    {
        $headers = [];
        $rows = [];
        $columnCount = 0;
        $isFirstRow = true;

        foreach ($table->getRows() as $row) {
            $rowData = [];
            foreach ($row->getCells() as $cell) {
                $cellText = '';
                foreach ($cell->getElements() as $element) {
                    $cellText .= $this->getElementText($element);
                }
                $rowData[] = trim($cellText);
            }

            $columnCount = max($columnCount, count($rowData));

            if ($isFirstRow) {
                $headers = $rowData;
                $isFirstRow = false;
            } else {
                $rows[] = $rowData;
            }
        }

        return [
            'headers' => $headers,
            'rows' => $rows,
            'columnCount' => $columnCount,
        ];
    }

    /**
     * Detect content type hint based on text content.
     */
    private function detectContentHint(string $content): string
    {
        $contentLower = strtolower($content);
        
        if (str_contains($contentLower, 'kop') || str_contains($contentLower, 'perusahaan')) {
            return ' (ISI: nama/alamat perusahaan)';
        }
        if (str_contains($contentLower, 'nim')) {
            return ' (ISI: NIM dari instruksi user)';
        }
        if (str_contains($contentLower, 'nama') && !str_contains($contentLower, 'materi')) {
            return ' (ISI: Nama dari instruksi user)';
        }
        if (str_contains($contentLower, 'program studi') || str_contains($contentLower, 'prodi')) {
            return ' (ISI: Program studi)';
        }
        if (str_contains($contentLower, 'semester')) {
            return ' (ISI: Semester)';
        }
        if (str_contains($contentLower, 'perguruan tinggi') || str_contains($contentLower, 'universitas')) {
            return ' (ISI: Nama perguruan tinggi)';
        }
        if (str_contains($contentLower, 'pembimbing')) {
            return ' (ISI: Nama pembimbing)';
        }
        if (str_contains($contentLower, 'dosen')) {
            return ' (ISI: Nama dosen)';
        }
        if (str_contains($contentLower, 'waktu') || str_contains($contentLower, 'pelaksanaan')) {
            return ' (ISI: Periode waktu)';
        }
        if (str_contains($contentLower, 'tanggal') || preg_match('/\d+\s+(januari|februari|maret|april|mei|juni|juli|agustus|september|oktober|november|desember)/i', $content)) {
            return ' (ISI: Tanggal sesuai konteks)';
        }
        if (str_contains($contentLower, 'ttd') || str_contains($contentLower, 'tanda tangan')) {
            return ' (ISI: Keterangan tanda tangan)';
        }
        if (str_contains($contentLower, 'catatan')) {
            return ' (ISI: Catatan jika ada)';
        }
        
        return '';
    }

    /**
     * Build prompt for AI based on template structure.
     */
    private function buildDocxPrompt(array $structure): string
    {
        $structureDesc = "";
        $sectionIndex = 1;
        
        foreach ($structure['elements'] as $element) {
            if ($element['type'] === 'paragraph') {
                $styleLabel = $element['style'] === 'heading' ? 'HEADING' : 'PARAGRAF';
                // Tambahkan petunjuk tentang jenis konten
                $hint = $this->detectContentHint($element['content']);
                $structureDesc .= "BAGIAN-{$sectionIndex} [{$styleLabel}]{$hint}: \"{$element['content']}\"\n";
                $sectionIndex++;
            } elseif ($element['type'] === 'table') {
                $structureDesc .= "BAGIAN-{$sectionIndex} [TABEL]: Kolom = " . implode(', ', $element['headers']) . "\n";
                if (!empty($element['sampleRows'])) {
                    $structureDesc .= "   Contoh data: " . implode(' | ', $element['sampleRows'][0] ?? []) . "\n";
                }
                $sectionIndex++;
            }
        }

        return <<<PROMPT
Kamu adalah AI penghasil dokumen profesional.

## STRUKTUR TEMPLATE:
{$structureDesc}

## ATURAN PENTING:
1. WAJIB mengisi SEMUA bagian dengan konten BARU berdasarkan INSTRUKSI USER
2. JANGAN menyalin konten template - generate konten baru sesuai permintaan user
3. Untuk field seperti Nama, NIM, Tanggal - gunakan data dari instruksi user
4. Untuk tabel - generate data sesuai permintaan user
5. Pastikan format konsisten dengan template

## FORMAT OUTPUT:
Untuk teks:
[BAGIAN-n]
konten baru sesuai instruksi user
[/BAGIAN-n]

Untuk tabel (gunakan TAB sebagai pemisah):
[TABEL]
header1\theader2\theader3
data1\tdata2\tdata3
[/TABEL]

## CONTOH:
Jika user meminta "Buatkan transkrip untuk Budi dengan nilai Matematika 85":

[BAGIAN-1]
PT. NAMA PERUSAHAAN
[/BAGIAN-1]

[BAGIAN-5]
NIM                     : (dari instruksi user)
[/BAGIAN-5]

[BAGIAN-6]
Nama                    : Budi
[/BAGIAN-6]

[TABEL]
No.\tNama Materi\tNilai Angka
1\tMatematika\t85
[/TABEL]

JANGAN gunakan markdown. JANGAN beri penjelasan. Langsung output saja.

PROMPT;
    }

    /**
     * Parse AI response into structured content.
     */
    private function parseAiResponse(string $response): array
    {
        $content = [];
        
        // Parse BAGIAN sections
        $sectionPattern = '/\[BAGIAN-(\d+)\](.*?)\[\/BAGIAN-\1\]/s';
        if (preg_match_all($sectionPattern, $response, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $content[] = [
                    'type' => 'section',
                    'index' => (int)$match[1],
                    'content' => trim($match[2]),
                ];
            }
        }

        // Parse tables
        $tablePattern = '/\[TABEL\](.*?)\[\/TABEL\]/s';
        if (preg_match_all($tablePattern, $response, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $tableContent = trim($match[1]);
                $rows = array_filter(explode("\n", $tableContent), fn($line) => trim($line) !== '');
                
                $tableData = [];
                foreach ($rows as $row) {
                    if (str_contains($row, "\t")) {
                        $tableData[] = array_map('trim', explode("\t", $row));
                    } else {
                        $tableData[] = array_map('trim', str_getcsv($row));
                    }
                }
                
                if (!empty($tableData)) {
                    $content[] = [
                        'type' => 'table',
                        'headers' => $tableData[0] ?? [],
                        'rows' => array_slice($tableData, 1),
                    ];
                }
            }
        }

        // Fallback: if no structured content found, treat entire response as single section
        if (empty($content)) {
            Log::warning("No structured content found, using raw AI response");
            $content[] = [
                'type' => 'section',
                'index' => 1,
                'content' => trim($response),
            ];
        }

        // Sort by index
        usort($content, fn($a, $b) => ($a['index'] ?? 999) <=> ($b['index'] ?? 999));

        return $content;
    }

    /**
     * Create DOCX document from template structure and AI content.
     */
    private function createDocxFromStructure(array $templateStructure, array $aiContent, string $outputPath): void
    {
        $phpWord = new PhpWord();
        
        // Set default styles
        $phpWord->setDefaultFontName('Times New Roman');
        $phpWord->setDefaultFontSize(12);

        $section = $phpWord->addSection();

        // Build content map by section index
        $sectionMap = [];
        $tables = [];
        foreach ($aiContent as $item) {
            if ($item['type'] === 'section') {
                $sectionMap[$item['index']] = $item['content'];
            } elseif ($item['type'] === 'table') {
                $tables[] = $item;
            }
        }

        $sectionIndex = 1;
        $tableIndex = 0;

        // Generate document following template structure
        foreach ($templateStructure['elements'] as $element) {
            if ($element['type'] === 'paragraph') {
                // Use AI content for this section, or fallback to original
                $content = $sectionMap[$sectionIndex] ?? $element['content'];
                $this->addTextToSection($section, $content, $element['style']);
                $sectionIndex++;
                
            } elseif ($element['type'] === 'table') {
                // Use AI table data if available
                if (isset($tables[$tableIndex])) {
                    $this->addTableToSection(
                        $section, 
                        $tables[$tableIndex]['headers'], 
                        $tables[$tableIndex]['rows']
                    );
                } else {
                    // Use original table structure
                    $this->addTableToSection($section, $element['headers'], $element['sampleRows']);
                }
                $tableIndex++;
                $sectionIndex++;
            }
        }

        // Add any extra AI sections not in template
        $maxTemplateSection = count($templateStructure['elements']);
        foreach ($sectionMap as $idx => $content) {
            if ($idx > $maxTemplateSection) {
                $this->addTextToSection($section, $content, 'normal');
            }
        }

        // Add remaining tables
        while ($tableIndex < count($tables)) {
            $table = $tables[$tableIndex];
            $this->addTableToSection($section, $table['headers'], $table['rows']);
            $tableIndex++;
        }

        // Save document
        $writer = WordIOFactory::createWriter($phpWord, 'Word2007');
        $writer->save($outputPath);
    }

    /**
     * Add text to section with appropriate styling.
     */
    private function addTextToSection($section, string $content, string $style): void
    {
        $content = str_replace('\\n', "\n", $content);
        $lines = explode("\n", $content);

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                $section->addTextBreak();
                continue;
            }

            if ($style === 'heading') {
                $section->addText($line, ['bold' => true, 'size' => 14], ['spaceAfter' => 120]);
            } else {
                $section->addText($line, ['size' => 12], ['spaceAfter' => 80]);
            }
        }
        
        $section->addTextBreak();
    }

    /**
     * Add table to section.
     */
    private function addTableToSection($section, array $headers, array $rows): void
    {
        if (empty($headers)) {
            return;
        }

        $table = $section->addTable([
            'borderSize' => 6,
            'borderColor' => '000000',
            'cellMargin' => 80,
        ]);

        // Calculate cell width based on number of columns
        $cellWidth = (int)(9000 / max(1, count($headers)));

        // Header row
        $table->addRow();
        foreach ($headers as $header) {
            $cell = $table->addCell($cellWidth, ['bgColor' => 'E0E0E0']);
            $cell->addText((string)$header, ['bold' => true, 'size' => 11]);
        }

        // Data rows
        foreach ($rows as $row) {
            $table->addRow();
            $colIndex = 0;
            foreach ($row as $cellValue) {
                $cell = $table->addCell($cellWidth);
                $cell->addText((string)$cellValue, ['size' => 11]);
                $colIndex++;
            }
            // Fill empty cells if row has fewer columns than headers
            while ($colIndex < count($headers)) {
                $table->addCell($cellWidth)->addText('', ['size' => 11]);
                $colIndex++;
            }
        }

        $section->addTextBreak();
    }

    /**
     * Generate XLSX from template.
     */
    private function generateXlsx(string $templatePath, string $fileId, string $userPrompt): string
    {
        $templateFullPath = Storage::disk('public')->path($templatePath);
        if (!is_file($templateFullPath)) {
            throw new \RuntimeException('XLSX template file not found.');
        }

        // Read template structure
        $spreadsheet = SpreadsheetIOFactory::load($templateFullPath);
        $sheet = $spreadsheet->getActiveSheet();
        
        // Extract headers from first row
        $headers = [];
        $highestColumn = $sheet->getHighestColumn();
        $highestColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn);
        
        for ($col = 1; $col <= $highestColumnIndex; $col++) {
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
            $value = $sheet->getCell($colLetter . '1')->getValue();
            if ($value !== null && trim((string)$value) !== '') {
                $headers[] = trim((string)$value);
            }
        }

        Log::info("Excel template headers: " . implode(', ', $headers));

        // Build prompt
        $prompt = $this->buildExcelPrompt($headers, $userPrompt);
        
        // Call AI
        $aiResponse = $this->geminiClient->generateText($prompt, maxOutputTokens: 3000);
        Log::debug("AI Response for Excel:\n" . $aiResponse);

        // Parse response
        $rows = $this->parseTsvResponse($aiResponse);

        // Write to spreadsheet (start from row 2, after header)
        $startRow = 2;
        foreach ($rows as $rowIndex => $row) {
            foreach ($row as $colIndex => $value) {
                $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex + 1);
                $cellAddress = $colLetter . ($startRow + $rowIndex);
                $sheet->setCellValue($cellAddress, $value);
            }
        }

        // Save
        $outRelPath = 'generated/' . $fileId . '.xlsx';
        $outFullPath = Storage::disk('public')->path($outRelPath);
        @mkdir(dirname($outFullPath), 0775, true);

        $writer = new XlsxWriter($spreadsheet);
        $writer->save($outFullPath);

        Log::info("Excel generated: {$outRelPath}");

        return $outRelPath;
    }

    /**
     * Build prompt for Excel generation.
     */
    private function buildExcelPrompt(array $headers, string $userPrompt): string
    {
        $headerList = implode(', ', $headers);
        
        return <<<PROMPT
Kamu adalah AI yang menghasilkan data tabel.

KOLOM YANG TERSEDIA DI TEMPLATE: {$headerList}

TUGAS: Generate data untuk mengisi tabel berdasarkan instruksi user.

FORMAT OUTPUT:
- Output WAJIB format TSV (tab-separated values)
- TANPA menyertakan header (header sudah ada di template)
- TANPA markdown, TANPA penjelasan
- Langsung data saja, satu baris per record

CONTOH OUTPUT (jika kolom: Nama, Nilai, Keterangan):
Budi Santoso\t85\tLulus
Ani Wijaya\t92\tLulus dengan predikat baik
Candra\t78\tLulus

INSTRUKSI USER:
{$userPrompt}
PROMPT;
    }

    /**
     * Parse TSV response from AI.
     */
    private function parseTsvResponse(string $response): array
    {
        $response = str_replace(["\r\n", "\r"], "\n", trim($response));
        $lines = array_filter(explode("\n", $response), fn($line) => trim($line) !== '');

        $rows = [];
        foreach ($lines as $line) {
            $line = trim($line);
            // Skip comments or explanations
            if (str_starts_with($line, '#') || str_starts_with($line, '//') || str_starts_with($line, '```')) {
                continue;
            }
            
            if (str_contains($line, "\t")) {
                $rows[] = array_map('trim', explode("\t", $line));
            } else {
                $parsed = str_getcsv($line);
                if (!empty($parsed) && $parsed[0] !== '') {
                    $rows[] = array_map('trim', $parsed);
                }
            }
        }

        return $rows;
    }
}
