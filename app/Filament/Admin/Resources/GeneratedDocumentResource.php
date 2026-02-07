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
                \Filament\Forms\Components\Group::make()
                    ->schema([
                        \Filament\Forms\Components\Section::make('Document Configuration')
                            ->description('Configure the main parameters for your document.')
                            ->icon('heroicon-m-document-text')
                            ->schema([
                                Hidden::make('user_id')
                                    ->default(fn (): ?int => auth()->id())
                                    ->required(),
                                    
                                Select::make('document_template_id')
                                    ->label('Choose Template')
                                    ->relationship('template', 'name')
                                    ->required()
                                    ->native(false)
                                    ->searchable()
                                    ->preload()
                                    ->columnSpanFull()
                                    ->helperText('Select the structure (DOCX/XLSX) you want to use.'),

                                Textarea::make('prompt')
                                    ->label('Instructions for AI')
                                    ->helperText('Deskripsikan isi dokumen yang ingin Anda buat secara detail.')
                                    ->required()
                                    ->rows(8)
                                    ->columnSpanFull()
                                    ->placeholder("Contoh:\nBuatkan transkrip untuk Budi Santoso (NIM 12345).\nNilai:\n- Pemrograman Web: A\n- Basis Data: B+\n...")
                                    ->dehydrated(true),
                            ]),
                    ])
                    ->columnSpan(['lg' => 2]),

                \Filament\Forms\Components\Group::make()
                    ->schema([
                        \Filament\Forms\Components\Section::make('Context Source')
                            ->description('Optional reference file')
                            ->icon('heroicon-m-paper-clip')
                            ->schema([
                                FileUpload::make('source_file_path')
                                    ->label('Upload Source File')
                                    ->disk('public')
                                    ->directory('sources')
                                    ->preserveFilenames()
                                    ->downloadable()
                                    ->columnSpanFull()
                                    ->helperText('Supported: PDF, DOCX, XLSX. The AI will use this file as data source.'),
                            ])
                            ->collapsible(),
                    ])
                    ->columnSpan(['lg' => 1]),
            ])
            ->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
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
