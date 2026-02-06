<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\DocumentTemplateResource\Pages;
use App\Models\DocumentTemplate;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;

class DocumentTemplateResource extends Resource
{
    protected static ?string $model = DocumentTemplate::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('name')
                    ->required()
                    ->maxLength(150),
                Textarea::make('description')
                    ->rows(3)
                    ->columnSpanFull(),
                Select::make('output_type')
                    ->required()
                    ->options([
                        'docs' => 'Docs (.docx)',
                        'excel' => 'Excel (.xlsx)',
                    ])
                    ->live(),
                FileUpload::make('template_path')
                    ->required()
                    ->disk('public')
                    ->directory('templates')
                    ->preserveFilenames()
                    ->acceptedFileTypes(fn (Get $get): array => match ($get('output_type')) {
                        'docs' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
                        'excel' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
                        default => [
                            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        ],
                    }),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable(),
                TextColumn::make('output_type')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $state === 'docs' ? 'Docs' : 'Excel')
                    ->colors([
                        'info' => 'docs',
                        'success' => 'excel',
                    ]),
                TextColumn::make('template_path')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')->dateTime()->since(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
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
            'index' => Pages\ListDocumentTemplates::route('/'),
            'create' => Pages\CreateDocumentTemplate::route('/create'),
            'edit' => Pages\EditDocumentTemplate::route('/{record}/edit'),
        ];
    }
}
