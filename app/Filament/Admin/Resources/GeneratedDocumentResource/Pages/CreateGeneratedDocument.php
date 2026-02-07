<?php

namespace App\Filament\Admin\Resources\GeneratedDocumentResource\Pages;

use App\Filament\Admin\Resources\GeneratedDocumentResource;
use App\Models\GeneratedDocument;
use App\Services\DocumentGeneratorService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateGeneratedDocument extends CreateRecord
{
    protected static string $resource = GeneratedDocumentResource::class;

    protected ?string $prompt = null;

    protected function handleRecordCreation(array $data): Model
    {
        $prompt = (string) ($data['prompt'] ?? '');
        unset($data['prompt']);

        $record = new GeneratedDocument($data);
        $record->save();

        try {
            $record->refresh(); // Load relationships if needed, or just ID

            // Perform generation synchronously
            $service = app(DocumentGeneratorService::class);
            $template = $record->template;
             
            if (!$template) {
                 throw new \RuntimeException('Template not found');
            }
            
            // Pass source file path if available
            $sourceFilePath = $record->source_file_path ?? null;
            $resultPath = $service->generate($template, $prompt, $sourceFilePath);

            $record->update([
                'result_file_path' => $resultPath,
            ]);

            Notification::make()
                ->title('Document generated successfully')
                ->success()
                ->send();

        } catch (\Throwable $e) {
            $record->update([
                'error_message' => $e->getMessage(),
            ]);
            
            Notification::make()
                ->title('Generation failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
            
            // Re-throw so Filament knows it failed? 
            // Actually if we throw, the record transaction might rollback if we didn't save it first.
            // But we already saved it.
        }

        return $record;
    }
}
