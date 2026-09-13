<?php

declare(strict_types=1);

namespace ReportBrains\ReportDesigner\Filament\Resources\ReportTemplates;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Html;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Support\HtmlString;
use Livewire\Component as Livewire;
use ReportBrains\ReportDesigner\DataSources\DataSourceRegistry;
use ReportBrains\ReportDesigner\DataSources\Field;
use ReportBrains\ReportDesigner\Designer\DocumentFormMapper;
use ReportBrains\ReportDesigner\Designer\ReportPreview;
use ReportBrains\ReportDesigner\Expressions\ValueFormatter;
use ReportBrains\ReportDesigner\Filament\Resources\ReportTemplates\Pages\CreateReportTemplate;
use ReportBrains\ReportDesigner\Filament\Resources\ReportTemplates\Pages\EditReportTemplate;
use ReportBrains\ReportDesigner\Filament\Resources\ReportTemplates\Pages\ListReportTemplates;
use ReportBrains\ReportDesigner\Models\ReportTemplate;
use ReportBrains\ReportDesigner\ReportDesignerPlugin;
use ReportBrains\ReportDesigner\Rules\UniqueTemplateKey;
use ReportBrains\ReportDesigner\Schema\BandName;

class ReportTemplateResource extends Resource
{
    protected static ?string $model = ReportTemplate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentChartBar;

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Report')
                    ->columns(2)
                    ->schema([
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
                    ]),

                Grid::make(['default' => 1, 'xl' => 2])
                    ->schema([
                        Tabs::make('Designer')
                            ->tabs([
                                Tab::make('Layout')
                                    ->icon(Heroicon::OutlinedSquares2x2)
                                    ->schema(static::bandBuilders()),
                                Tab::make('Data')
                                    ->icon(Heroicon::OutlinedCircleStack)
                                    ->schema(static::dataComponents()),
                                Tab::make('Page')
                                    ->icon(Heroicon::OutlinedDocument)
                                    ->schema(static::pageComponents()),
                                Tab::make('JSON')
                                    ->icon(Heroicon::OutlinedCodeBracket)
                                    ->visible(fn (): bool => ReportDesignerPlugin::jsonEditorEnabled())
                                    ->schema([
                                        Html::make(fn (Livewire $livewire): HtmlString => new HtmlString(
                                            '<pre class="rb-json" style="white-space:pre-wrap;font-size:0.8rem">'
                                            .e(json_encode(static::documentFrom($livewire), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES))
                                            .'</pre>'
                                        )),
                                    ]),
                            ]),

                        Section::make('Preview')
                            ->description('Rendered against live data as you edit.')
                            ->schema([
                                Html::make(fn (Livewire $livewire): HtmlString => new HtmlString(
                                    static::previewStyles()
                                    .'<div class="rb-preview">'
                                    .app(ReportPreview::class)->html(static::documentFrom($livewire))
                                    .'</div>'
                                )),
                            ]),
                    ]),
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

    public static function getEloquentQuery(): EloquentBuilder
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

    /**
     * The document currently described by the page's form state.
     *
     * @return array<string, mixed>
     */
    public static function documentFrom(Livewire $livewire): array
    {
        $state = (array) ($livewire->data ?? []);

        return app(DocumentFormMapper::class)->toDocument($state, $state);
    }

    /**
     * @return array<int, Builder>
     */
    private static function bandBuilders(): array
    {
        $descriptions = [
            BandName::DocumentHeader->value => 'Shown once, at the top of the report.',
            BandName::PageHeader->value => 'Repeated at the top of every page. PDF only.',
            BandName::GroupHeader->value => 'Shown each time the grouping value changes.',
            BandName::Detail->value => 'The body. A table lists every row; other blocks repeat once per row.',
            BandName::GroupFooter->value => 'Shown at the end of each group — the place for subtotals.',
            BandName::PageFooter->value => 'Repeated at the bottom of every page. PDF only.',
            BandName::DocumentFooter->value => 'Shown once, at the end — the place for grand totals.',
        ];

        return array_map(
            fn (BandName $band): Builder => Builder::make(DocumentFormMapper::bandKey($band))
                ->label(str($band->value)->replace('_', ' ')->title()->toString())
                ->helperText($descriptions[$band->value])
                ->blocks(static::blocks())
                ->blockIcons()
                ->collapsible()
                ->reorderableWithDragAndDrop()
                ->addActionLabel('Add block')
                ->default([])
                ->live(),
            BandName::cases(),
        );
    }

    /**
     * @return array<int, Block>
     */
    private static function blocks(): array
    {
        $align = Select::make('align')
            ->options(['left' => 'Left', 'center' => 'Center', 'right' => 'Right'])
            ->placeholder('Left');

        return [
            Block::make('heading')
                ->icon(Heroicon::OutlinedH1)
                ->columns(3)
                ->schema([
                    static::contentInput()->columnSpan(2),
                    Select::make('level')
                        ->options(array_combine(range(1, 6), array_map(fn (int $level): string => "Heading {$level}", range(1, 6))))
                        ->default(1)
                        ->required()
                        ->live(),
                    (clone $align),
                ]),

            Block::make('text')
                ->icon(Heroicon::OutlinedBars3BottomLeft)
                ->columns(3)
                ->schema([
                    static::contentInput()->columnSpanFull(),
                    (clone $align),
                    Toggle::make('bold')->inline(false)->live(),
                    Toggle::make('italic')->inline(false)->live(),
                ]),

            Block::make('table')
                ->icon(Heroicon::OutlinedTableCells)
                ->schema([
                    Repeater::make('columns')
                        ->minItems(1)
                        ->defaultItems(1)
                        ->reorderableWithDragAndDrop()
                        ->columns(4)
                        ->addActionLabel('Add column')
                        ->live()
                        ->schema([
                            Select::make('field')
                                ->options(fn (Livewire $livewire): array => static::fieldOptions($livewire))
                                ->required()
                                ->searchable()
                                ->live()
                                ->afterStateUpdated(function (?string $state, Get $get, Set $set, Livewire $livewire): void {
                                    if (blank($get('label')) && $state !== null) {
                                        $set('label', static::fieldOptions($livewire)[$state] ?? $state);
                                    }
                                }),
                            TextInput::make('label')->live(onBlur: true),
                            (clone $align),
                            Select::make('format')
                                ->options(array_combine(ValueFormatter::available(), array_map(ucfirst(...), ValueFormatter::available())))
                                ->placeholder('None')
                                ->live(),
                        ]),
                ]),

            Block::make('divider')
                ->icon(Heroicon::OutlinedMinus)
                ->schema([]),

            Block::make('spacer')
                ->icon(Heroicon::OutlinedArrowsUpDown)
                ->schema([
                    TextInput::make('height')->numeric()->minValue(0)->maxValue(500)->suffix('px')->live(onBlur: true),
                ]),
        ];
    }

    /**
     * Text input for block content, with an action that inserts a field or a
     * total without the user having to know the placeholder syntax.
     */
    private static function contentInput(): TextInput
    {
        return TextInput::make('content')
            ->required()
            ->live(onBlur: true)
            ->suffixAction(
                Action::make('insertField')
                    ->label('Insert field')
                    ->icon(Heroicon::OutlinedVariable)
                    ->modalHeading('Insert a field')
                    ->modalSubmitActionLabel('Insert')
                    ->schema(fn (Livewire $livewire): array => [
                        Select::make('field')
                            ->options(static::fieldOptions($livewire))
                            ->required()
                            ->searchable(),
                        Select::make('aggregate')
                            ->label('Show')
                            ->options([
                                'sum' => 'Total (sum)',
                                'avg' => 'Average',
                                'count' => 'Count',
                                'min' => 'Lowest',
                                'max' => 'Highest',
                            ])
                            ->placeholder('The value on each row')
                            ->helperText('Totals are calculated over the rows in the band.'),
                        Select::make('format')
                            ->options(array_combine(ValueFormatter::available(), array_map(ucfirst(...), ValueFormatter::available())))
                            ->placeholder('None'),
                    ])
                    ->action(function (array $data, Get $get, Set $set): void {
                        $expression = filled($data['aggregate'] ?? null)
                            ? "{$data['aggregate']}({$data['field']})"
                            : $data['field'];

                        if (filled($data['format'] ?? null)) {
                            $expression .= " | {$data['format']}";
                        }

                        $set('content', trim(((string) $get('content')).' {{ '.$expression.' }}'));
                    }),
            );
    }

    /**
     * @return array<int, mixed>
     */
    private static function dataComponents(): array
    {
        return [
            Select::make('source')
                ->label('Data source')
                ->options(fn (): array => app(DataSourceRegistry::class)->options())
                ->required()
                ->live()
                ->helperText('Only data a developer has registered is available here.'),

            Select::make('group_by')
                ->label('Group rows by')
                ->options(fn (Livewire $livewire): array => static::fieldOptions($livewire))
                ->placeholder('No grouping')
                ->live(),

            Repeater::make('sort')
                ->label('Sort by')
                ->columns(2)
                ->default([])
                ->reorderableWithDragAndDrop()
                ->addActionLabel('Add sort')
                ->live()
                ->schema([
                    Select::make('field')
                        ->options(fn (Livewire $livewire): array => static::fieldOptions($livewire, includeRelations: false))
                        ->required()
                        ->live(),
                    Select::make('dir')
                        ->label('Direction')
                        ->options(['asc' => 'Ascending', 'desc' => 'Descending'])
                        ->default('asc')
                        ->required()
                        ->live(),
                ]),

            Repeater::make('filters')
                ->columns(3)
                ->default([])
                ->addActionLabel('Add filter')
                ->live()
                ->schema([
                    Select::make('field')
                        ->options(fn (Livewire $livewire): array => static::fieldOptions($livewire, includeRelations: false))
                        ->required()
                        ->live(),
                    Select::make('operator')
                        ->options(fn (Get $get, Livewire $livewire): array => static::operatorOptions($livewire, $get('field')))
                        ->default('=')
                        ->required()
                        ->live(),
                    TextInput::make('value')
                        ->helperText(fn (Get $get): ?string => in_array($get('operator'), ['in', 'not_in', 'between'], true)
                            ? 'Separate values with commas.'
                            : null)
                        ->live(onBlur: true),
                ]),
        ];
    }

    /**
     * @return array<int, mixed>
     */
    private static function pageComponents(): array
    {
        return [
            Grid::make(2)->schema([
                Select::make('page_size')
                    ->label('Paper size')
                    ->options(['A4' => 'A4', 'A5' => 'A5', 'A3' => 'A3', 'Letter' => 'Letter', 'Legal' => 'Legal'])
                    ->placeholder('Default'),
                Select::make('page_orientation')
                    ->label('Orientation')
                    ->options(['portrait' => 'Portrait', 'landscape' => 'Landscape'])
                    ->placeholder('Default'),
            ]),
            Grid::make(4)->schema(array_map(
                fn (string $side): TextInput => TextInput::make("margin_{$side}")
                    ->label(ucfirst($side).' margin')
                    ->numeric()
                    ->minValue(0)
                    ->suffix('mm'),
                ['top', 'right', 'bottom', 'left'],
            )),
        ];
    }

    /**
     * Fields of the currently selected source, labelled for people rather than
     * databases.
     *
     * @return array<string, string>
     */
    private static function fieldOptions(Livewire $livewire, bool $includeRelations = true): array
    {
        $key = $livewire->data['source'] ?? null;
        $registry = app(DataSourceRegistry::class);

        if (! is_string($key) || ! $registry->has($key)) {
            return [];
        }

        $fields = array_filter(
            $registry->get($key)->fields(),
            fn (Field $field): bool => $includeRelations || ! $field->isRelation(),
        );

        return array_map(fn (Field $field): string => $field->label, $fields);
    }

    /**
     * @return array<string, string>
     */
    private static function operatorOptions(Livewire $livewire, mixed $fieldName): array
    {
        $key = $livewire->data['source'] ?? null;
        $registry = app(DataSourceRegistry::class);
        $field = (is_string($key) && $registry->has($key) && is_string($fieldName))
            ? $registry->get($key)->field($fieldName)
            : null;

        $labels = [
            '=' => 'is', '!=' => 'is not', '<' => 'less than', '<=' => 'at most', '>' => 'greater than',
            '>=' => 'at least', 'contains' => 'contains', 'starts_with' => 'starts with',
            'ends_with' => 'ends with', 'in' => 'is one of', 'not_in' => 'is not one of', 'between' => 'between',
        ];

        if ($field === null) {
            return ['=' => $labels['=']];
        }

        return array_intersect_key($labels, array_flip($field->type->operators()));
    }

    private static function previewStyles(): string
    {
        return '<style>'
            .'.rb-preview table{border-collapse:collapse;width:100%;margin:.5rem 0;font-size:.875rem}'
            .'.rb-preview th,.rb-preview td{border:1px solid rgb(0 0 0 / .12);padding:.35rem .5rem}'
            .'.rb-preview th{font-weight:600}'
            .'.rb-preview h1{font-size:1.5rem;font-weight:700;margin:.5rem 0}'
            .'.rb-preview h2{font-size:1.25rem;font-weight:600;margin:.5rem 0}'
            .'.rb-preview h3{font-size:1.1rem;font-weight:600;margin:.5rem 0}'
            .'.rb-preview p{margin:.35rem 0}'
            .'.rb-preview hr{margin:.75rem 0;border-top:1px solid rgb(0 0 0 / .12)}'
            .'.rb-preview .rb-preview-message{padding:.75rem;border-radius:.5rem;background:rgb(0 0 0 / .04)}'
            .'.rb-preview .rb-preview-note{font-size:.75rem;opacity:.7}'
            .'</style>';
    }
}
