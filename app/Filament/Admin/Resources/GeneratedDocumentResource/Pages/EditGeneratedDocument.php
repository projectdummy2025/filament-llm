<?php

namespace App\Filament\Admin\Resources\GeneratedDocumentResource\Pages;

use App\Filament\Admin\Resources\GeneratedDocumentResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditGeneratedDocument extends EditRecord
{
    protected static string $resource = GeneratedDocumentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
