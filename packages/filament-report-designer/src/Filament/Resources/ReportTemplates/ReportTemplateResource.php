<?php

declare(strict_types=1);

namespace ReportBrains\ReportDesigner\Filament\Resources\ReportTemplates;

use BackedEnum;
use Filament\Forms\Components\CodeEditor;
use Filament\Forms\Components\CodeEditor\Enums\Language;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use ReportBrains\ReportDesigner\Filament\Resources\ReportTemplates\Pages\CreateReportTemplate;
use ReportBrains\ReportDesigner\Filament\Resources\ReportTemplates\Pages\EditReportTemplate;
use ReportBrains\ReportDesigner\Filament\Resources\ReportTemplates\Pages\ListReportTemplates;
use ReportBrains\ReportDesigner\Models\ReportTemplate;
use ReportBrains\ReportDesigner\Rules\UniqueTemplateKey;
use ReportBrains\ReportDesigner\Rules\ValidReportSchema;

class ReportTemplateResource extends Resource
{
    protected static ?string $model = ReportTemplate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentChartBar;

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')
                ->required()
                ->maxLength(255)
                ->live(onBlur: true),

            TextInput::make('key')
                ->helperText('Lowercase, hyphen-separated. This is how the report is called from code.')
                ->required()
                ->maxLength(255)
                ->rule('regex:/^[a-z0-9]+(-[a-z0-9]+)*$/')
                ->rule(fn (?ReportTemplate $record): UniqueTemplateKey => new UniqueTemplateKey($record))
                ->disabledOn('edit')
                ->dehydrated(),

            Textarea::make('description')
                ->rows(2)
                ->maxLength(1000)
                ->columnSpanFull(),

            // The visual editor arrives in M4. Until then the document is edited
            // as JSON, which keeps the storage layer honest: the same validation
            // runs whether a human or the future editor produced it.
            CodeEditor::make('schema')
                ->language(Language::Json)
                ->rule(new ValidReportSchema)
                ->required()
                ->columnSpanFull()
                // The column is cast to array; the editor works in text. Convert
                // in both directions or the cast re-encodes an already-encoded
                // string and the document is stored double-escaped.
                ->formatStateUsing(fn (mixed $state): string => is_array($state)
                    ? (json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '')
                    : (string) $state)
                ->dehydrateStateUsing(fn (mixed $state): mixed => is_string($state)
                    ? json_decode($state, associative: true)
                    : $state),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->searchable()->sortable(),
                TextColumn::make('key')->searchable()->badge()->color('gray'),
                TextColumn::make('schema_version')->label('Schema')->badge(),
                TextColumn::make('updated_at')->dateTime()->sortable()->since(),
            ])
            ->defaultSort('updated_at', 'desc');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->visible();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListReportTemplates::route('/'),
            'create' => CreateReportTemplate::route('/create'),
            'edit' => EditReportTemplate::route('/{record}/edit'),
        ];
    }
}
