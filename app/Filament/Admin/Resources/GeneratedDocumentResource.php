<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\GeneratedDocumentResource\Pages;
use App\Jobs\GenerateDocumentJob;
use App\Models\GeneratedDocument;
use Filament\Forms\Form;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Support\Facades\Storage;

class GeneratedDocumentResource extends Resource
{
    protected static ?string $model = GeneratedDocument::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Hidden::make('user_id')
                    ->default(fn (): ?int => auth()->id())
                    ->required(),
                Select::make('document_template_id')
                    ->label('Template')
                    ->relationship('template', 'name')
                    ->required(),
                Textarea::make('prompt')
                    ->label('Prompt')
                    ->helperText('Tidak disimpan ke database. Hanya dipakai untuk generate.')
                    ->required()
                    ->rows(6)
                    ->dehydrated(false),
                FileUpload::make('source_file_path')
                    ->label('Source File (opsional)')
                    ->disk('public')
                    ->directory('sources')
                    ->preserveFilenames(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('template.name')->label('Template')->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->colors([
                        'gray' => 'pending',
                        'warning' => 'processing',
                        'success' => 'completed',
                        'danger' => 'failed',
                    ]),
                TextColumn::make('created_at')->dateTime()->since(),
                TextColumn::make('updated_at')->dateTime()->since()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Action::make('download')
                    ->visible(fn (GeneratedDocument $record): bool => $record->status === 'completed' && filled($record->result_file_path))
                    ->action(function (GeneratedDocument $record) {
                        return Storage::disk('public')->download($record->result_file_path);
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListGeneratedDocuments::route('/'),
            'create' => Pages\CreateGeneratedDocument::route('/create'),
        ];
    }
}
