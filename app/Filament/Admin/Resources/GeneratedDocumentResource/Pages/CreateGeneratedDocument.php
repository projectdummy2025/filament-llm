<?php

namespace App\Filament\Admin\Resources\GeneratedDocumentResource\Pages;

use App\Filament\Admin\Resources\GeneratedDocumentResource;
use App\Jobs\GenerateDocumentJob;
use Filament\Resources\Pages\CreateRecord;

class CreateGeneratedDocument extends CreateRecord
{
    protected static string $resource = GeneratedDocumentResource::class;

    protected ?string $prompt = null;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->prompt = (string) ($data['prompt'] ?? '');
        unset($data['prompt']);

        return $data;
    }

    protected function afterCreate(): void
    {
        if ($this->record === null) {
            return;
        }

        GenerateDocumentJob::dispatch(
            generatedDocumentId: $this->record->getKey(),
            prompt: $this->prompt ?? '',
        );
    }
}
