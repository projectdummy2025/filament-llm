<?php

namespace App\Jobs;

use App\Models\GeneratedDocument;
use App\Services\GeminiClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory as SpreadsheetIOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use PhpOffice\PhpWord\TemplateProcessor;

class GenerateDocumentJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 120;

    public int $tries = 3;

    /**
     * @var array<int, int>
     */
    public array $backoff = [10, 30, 60];

    public function __construct(
        public readonly int $generatedDocumentId,
        public readonly string $prompt,
    ) {
    }

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $generated = GeneratedDocument::query()->with('template')->findOrFail($this->generatedDocumentId);

        $generated->update([
            'status' => 'processing',
            'error_message' => null,
        ]);

        try {
            $template = $generated->template;

            if ($template === null) {
                throw new \RuntimeException('Template not found.');
            }

            $systemRules = $this->buildSystemRules($template->output_type);
            $fullPrompt = $systemRules . "\n\n" . trim($this->prompt);

            $aiText = app(GeminiClient::class)->generateText($fullPrompt, maxOutputTokens: 1200);

            $resultPath = match ($template->output_type) {
                'docs' => $this->renderDocx($template->template_path, $generated->id, $aiText),
                'excel' => $this->renderXlsx($template->template_path, $generated->id, $aiText),
                default => throw new \RuntimeException('Invalid output_type.'),
            };

            $generated->update([
                'status' => 'completed',
                'result_file_path' => $resultPath,
            ]);
        } catch (\Throwable $e) {
            $generated->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    private function buildSystemRules(string $outputType): string
    {
        if ($outputType === 'docs') {
            return <<<TXT
Kamu adalah generator konten dokumen.
Output WAJIB format KEY=VALUE (1 baris per key), TANPA markdown, TANPA penjelasan.
Gunakan key berikut saja: judul, ringkasan, isi
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

    /**
     * @return array<string, string>
     */
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

    /**
     * @return array<int, array<int, string>>
     */
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

    private function renderDocx(string $templatePath, int $generatedId, string $aiText): string
    {
        $templateFullPath = Storage::disk('public')->path($templatePath);
        if (!is_file($templateFullPath)) {
            throw new \RuntimeException('DOCX template file not found.');
        }

        $map = $this->parseKeyValue($aiText);

        $processor = new TemplateProcessor($templateFullPath);
        foreach ($map as $key => $value) {
            $processor->setValue($key, str_replace('\\n', "\n", $value));
        }

        $outRelPath = 'generated/' . $generatedId . '.docx';
        $outFullPath = Storage::disk('public')->path($outRelPath);
        @mkdir(dirname($outFullPath), 0775, true);

        $processor->saveAs($outFullPath);

        return $outRelPath;
    }

    private function renderXlsx(string $templatePath, int $generatedId, string $aiText): string
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

        $outRelPath = 'generated/' . $generatedId . '.xlsx';
        $outFullPath = Storage::disk('public')->path($outRelPath);
        @mkdir(dirname($outFullPath), 0775, true);

        $writer = new XlsxWriter($spreadsheet);
        $writer->save($outFullPath);

        return $outRelPath;
    }
}
