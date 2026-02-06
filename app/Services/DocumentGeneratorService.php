<?php

namespace App\Services;

use App\Models\DocumentTemplate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory as SpreadsheetIOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use PhpOffice\PhpWord\TemplateProcessor;

class DocumentGeneratorService
{
    public function __construct(
        protected GeminiClient $geminiWithGemini
    ) {}

    public function generate(DocumentTemplate $template, string $prompt): string
    {
        $systemRules = $this->buildSystemRules($template->output_type);
        $fullPrompt = $systemRules . "\n\n" . trim($prompt);

        Log::info("Generating document for template: {$template->name} ({$template->output_type})");

        $aiText = $this->geminiWithGemini->generateText($fullPrompt, maxOutputTokens: 2000);
        
        Log::info("AI Response length: " . strlen($aiText));
        Log::debug("AI Response content:\n" . $aiText);

        // Generate ID for filename
        $fileId = str()->uuid()->toString();

        return match ($template->output_type) {
            'docs' => $this->renderDocx($template->template_path, $fileId, $aiText),
            'excel' => $this->renderXlsx($template->template_path, $fileId, $aiText),
            default => throw new \RuntimeException('Invalid output_type.'),
        };
    }

    private function buildSystemRules(string $outputType): string
    {
        if ($outputType === 'docs') {
            return <<<TXT
Kamu adalah generator konten dokumen.
Output WAJIB format KEY=VALUE (1 baris per key), TANPA markdown, TANPA penjelasan.
Contoh:
judul=Laporan Kegiatan
deskripsi=Laporan ini berisi...

Sesuaikan key dengan placeholder yang diminta user (jika ada).
Jika butuh paragraf baru, gunakan literal \\n (backslash-n).
TXT;
        }

        if ($outputType === 'excel') {
            return <<<TXT
Kamu adalah generator data tabel.
Output WAJIB TSV (tab-separated), TANPA markdown, TANPA penjelasan.
Baris pertama adalah header.
TXT;
        }

        throw new \RuntimeException('Invalid output_type rules.');
    }

    private function parseKeyValue(string $text): array
    {
        $text = str_replace(["\r\n", "\r"], "\n", trim($text));
        $lines = array_values(array_filter(explode("\n", $text), fn ($line) => trim($line) !== ''));

        $result = [];
        foreach ($lines as $line) {
            $pos = strpos($line, '=');
            if ($pos === false) {
                continue;
            }

            $key = trim(substr($line, 0, $pos));
            $value = trim(substr($line, $pos + 1));

            if ($key === '') {
                continue;
            }

            $result[$key] = $value;
        }

        if ($result === []) {
            throw new \RuntimeException('AI output is not valid KEY=VALUE.');
        }

        return $result;
    }

    private function parseTsvOrCsv(string $text): array
    {
        $text = str_replace(["\r\n", "\r"], "\n", trim($text));
        $lines = array_values(array_filter(explode("\n", $text), fn ($line) => trim($line) !== ''));

        if ($lines === []) {
            throw new \RuntimeException('AI output is empty.');
        }

        $rows = [];
        foreach ($lines as $line) {
            if (str_contains($line, "\t")) {
                $rows[] = array_map('trim', explode("\t", $line));
            } else {
                $rows[] = array_map('trim', str_getcsv($line));
            }
        }

        if (count($rows) < 2) {
            throw new \RuntimeException('AI output table must include header + at least 1 row.');
        }

        return $rows;
    }

    private function renderDocx(string $templatePath, string $fileId, string $aiText): string
    {
        $templateFullPath = Storage::disk('public')->path($templatePath);
        if (!is_file($templateFullPath)) {
            throw new \RuntimeException('DOCX template file not found.');
        }

        $map = $this->parseKeyValue($aiText);

        Log::info("Parsed Keys from AI: " . implode(', ', array_keys($map)));

        $processor = new TemplateProcessor($templateFullPath);
        
        // Get all variables in the template (requires reading internal XML, PHPWord template processor doesn't expose getVariables easily publically in older versions, but let's try just setting values)
        // Check if we can log available variables? PHPWord TemplateProcessor getVariables() returns array.
        try {
            $variables = $processor->getVariables();
            Log::info("Template Placeholders: " . implode(', ', $variables));
        } catch (\Throwable $e) {
            Log::warning("Could not list template variables: " . $e->getMessage());
        }

        foreach ($map as $key => $value) {
            $processor->setValue($key, str_replace('\\n', "\n", $value));
        }

        $outRelPath = 'generated/' . $fileId . '.docx';
        $outFullPath = Storage::disk('public')->path($outRelPath);
        @mkdir(dirname($outFullPath), 0775, true);

        $processor->saveAs($outFullPath);

        return $outRelPath;
    }

    private function renderXlsx(string $templatePath, string $fileId, string $aiText): string
    {
        $templateFullPath = Storage::disk('public')->path($templatePath);
        if (!is_file($templateFullPath)) {
            throw new \RuntimeException('XLSX template file not found.');
        }

        $rows = $this->parseTsvOrCsv($aiText);

        $spreadsheet = SpreadsheetIOFactory::load($templateFullPath);
        $sheet = $spreadsheet->getActiveSheet();

        $startRow = 1;
        $startCol = 1;

        foreach ($rows as $rowIndex => $row) {
            foreach ($row as $colIndex => $value) {
                $sheet->setCellValueByColumnAndRow($startCol + $colIndex, $startRow + $rowIndex, $value);
            }
        }

        $outRelPath = 'generated/' . $fileId . '.xlsx';
        $outFullPath = Storage::disk('public')->path($outRelPath);
        @mkdir(dirname($outFullPath), 0775, true);

        $writer = new XlsxWriter($spreadsheet);
        $writer->save($outFullPath);

        return $outRelPath;
    }
}
