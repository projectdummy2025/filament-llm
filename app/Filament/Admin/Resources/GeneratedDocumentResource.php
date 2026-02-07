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
                \Filament\Forms\Components\Section::make('Generation Request')
                    ->description('Create a new document generation task')
                    ->schema([
                        Hidden::make('user_id')
                            ->default(fn (): ?int => auth()->id())
                            ->required(),
                        \Filament\Forms\Components\Grid::make(2)
                            ->schema([
                                Select::make('document_template_id')
                                    ->label('Template')
                                    ->relationship('template', 'name')
                                    ->required()
                                    ->native(false)
                                    ->searchable()
                                    ->preload(),
                                FileUpload::make('source_file_path')
                                    ->label('Source File (Optional)')
                                    ->helperText('Upload a reference file if needed')
                                    ->disk('public')
                                    ->directory('sources')
                                    ->preserveFilenames()
                                    ->downloadable(),
                            ]),
                        Textarea::make('prompt')
                            ->label('AI Instructions')
                            ->helperText('Jelaskan secara detail apa yang ingin Anda generate. Contoh: "Buatkan transkrip untuk mahasiswa bernama Budi Santoso, NIM 123456, dengan nilai: Pemrograman Web A, Database B+, Jaringan A-"')
                            ->required()
                            ->rows(6)
                            ->columnSpanFull()
                            ->placeholder('Contoh: Buatkan dokumen transkrip magang untuk mahasiswa bernama [nama], dengan nilai mata kuliah [daftar nilai]...')
                            ->dehydrated(true),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('template.name')
                    ->label('Template')
                    ->sortable()
                    ->searchable()
                    ->weight('bold'),
                TextColumn::make('user.name')
                    ->label('User')
                    ->sortable()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->label('Created At'),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->since()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Action::make('download')
                    ->label('Download Result')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->visible(fn (GeneratedDocument $record): bool => filled($record->result_file_path))
                    ->action(function (GeneratedDocument $record) {
                        if (! Storage::disk('public')->exists($record->result_file_path)) {
                            \Filament\Notifications\Notification::make()
                                ->title('File not found')
                                ->danger()
                                ->send();
                            return null;
                        }

                        $ext = pathinfo($record->result_file_path, PATHINFO_EXTENSION);
                        $filename = str($record->template->name)->slug() . '-generated-' . $record->id . '.' . $ext;
                        
                        return Storage::disk('public')->download($record->result_file_path, $filename);
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
