<?php

declare(strict_types=1);

namespace ReportBrains\ReportDesigner\Filament\Resources\ReportTemplates\Concerns;

use Filament\Actions\Action;
use Filament\Forms\Components\CodeEditor;
use Filament\Forms\Components\CodeEditor\Enums\Language;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Group;
use Filament\Support\Icons\Heroicon;
use JsonException;
use ReportBrains\ReportDesigner\DataSources\DataSourceRegistry;
use ReportBrains\ReportDesigner\DataSources\ReportQueryFactory;
use ReportBrains\ReportDesigner\Designer\DocumentFormMapper;
use ReportBrains\ReportDesigner\Exceptions\InvalidExpression;
use ReportBrains\ReportDesigner\Exceptions\InvalidReportParameters;
use ReportBrains\ReportDesigner\Exceptions\InvalidReportSchema;
use ReportBrains\ReportDesigner\Exceptions\UnknownDataSource;
use ReportBrains\ReportDesigner\Exceptions\UnknownField;
use ReportBrains\ReportDesigner\Exceptions\UnknownParameter;
use ReportBrains\ReportDesigner\Exceptions\UnsupportedFeature;
use ReportBrains\ReportDesigner\Filament\Resources\ReportTemplates\ReportTemplateResource;
use ReportBrains\ReportDesigner\Renderers\Contracts\Renderer;
use ReportBrains\ReportDesigner\Renderers\HtmlRenderer;
use ReportBrains\ReportDesigner\Renderers\MarkdownRenderer;
use ReportBrains\ReportDesigner\Renderers\PdfRenderer;
use ReportBrains\ReportDesigner\ReportDesignerPlugin;
use ReportBrains\ReportDesigner\ReportRunner;
use ReportBrains\ReportDesigner\Schema\ReportSchema;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Shared behaviour for the pages that create and edit templates: turning the
 * designer's form state into a document, refusing to save one that is invalid,
 * and letting developers import a document as JSON.
 */
trait DesignsReports
{
    /**
     * Build and validate the document, halting the save with a notification
     * when it is not valid.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed> Attributes for the model.
     */
    protected function prepareTemplateAttributes(array $data): array
    {
        $identity = [
            'key' => $data['key'] ?? $this->record?->key ?? null,
            'title' => $data['title'] ?? null,
        ];

        $document = app(DocumentFormMapper::class)->toDocument($data, $identity);

        try {
            app(ReportSchema::class)->validate($document);
            app(ReportQueryFactory::class)->validateBindings($document);
        } catch (InvalidReportSchema $exception) {
            $this->refuseToSave($exception->summary());
        } catch (UnknownDataSource|UnknownField|UnknownParameter $exception) {
            $this->refuseToSave($exception->getMessage());
        }

        return [
            ...array_intersect_key($data, array_flip(['key', 'title', 'description'])),
            'schema' => $document,
        ];
    }

    protected function importJsonAction(): Action
    {
        return Action::make('importJson')
            ->label('Import JSON')
            ->icon(Heroicon::OutlinedArrowUpTray)
            ->color('gray')
            ->visible(fn (): bool => ReportDesignerPlugin::jsonEditorEnabled())
            ->modalHeading('Import a report document')
            ->modalDescription('Replaces the current design with the pasted document. Nothing is saved until you save the template.')
            ->modalSubmitActionLabel('Replace design')
            ->schema([
                CodeEditor::make('json')
                    ->label('Document')
                    ->language(Language::Json)
                    ->required(),
            ])
            ->action(function (array $data): void {
                try {
                    $document = json_decode((string) $data['json'], associative: true, flags: JSON_THROW_ON_ERROR);
                } catch (JsonException $exception) {
                    $this->notifyImportFailed('The document is not valid JSON: '.$exception->getMessage());

                    return;
                }

                if (! is_array($document)) {
                    $this->notifyImportFailed('The document must be a JSON object.');

                    return;
                }

                $this->form->fill([
                    ...$this->data,
                    ...app(DocumentFormMapper::class)->toFormState($document),
                    'title' => $document['title'] ?? ($this->data['title'] ?? null),
                ]);

                Notification::make()
                    ->success()
                    ->title('Design replaced')
                    ->body('Review the preview, then save the template to keep it.')
                    ->send();
            });
    }

    /**
     * Run the saved template against live data and download it.
     */
    protected function exportAction(): Action
    {
        return Action::make('export')
            ->label('Export')
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->modalHeading('Export report')
            ->modalDescription('Runs the saved template against live data. Unsaved changes are not included.')
            ->modalSubmitActionLabel('Download')
            ->schema(fn (): array => [
                ToggleButtons::make('format')
                    ->options(['pdf' => 'PDF', 'html' => 'HTML', 'markdown' => 'Markdown'])
                    ->default('pdf')
                    ->inline()
                    ->required(),
                Group::make()
                    ->statePath('parameters')
                    ->columns(2)
                    ->schema($this->exportParameterInputs()),
            ])
            ->action(function (array $data): ?StreamedResponse {
                $template = $this->getRecord();

                $renderer = match ($data['format'] ?? 'pdf') {
                    'html' => new HtmlRenderer,
                    'markdown' => new MarkdownRenderer,
                    default => app(PdfRenderer::class),
                };

                try {
                    $content = app(ReportRunner::class)->render(
                        (array) $template->schema,
                        $renderer,
                        (array) ($data['parameters'] ?? []),
                    );
                } catch (InvalidReportParameters|UnknownDataSource|UnknownField|UnknownParameter|InvalidExpression|UnsupportedFeature $exception) {
                    $this->notifyExportFailed('The report could not be exported', $exception->getMessage());

                    return null;
                } catch (Throwable $exception) {
                    if (! $renderer instanceof PdfRenderer) {
                        throw $exception;
                    }

                    // PDF drivers fail for environmental reasons — no Chrome, a
                    // sandbox restriction, an unreachable Gotenberg — which the
                    // person exporting should see, not a server error page.
                    $this->notifyExportFailed(
                        'The PDF could not be generated',
                        $exception->getMessage().' Check the PDF driver set in config/report-designer.php.',
                    );

                    return null;
                }

                return $this->download($content, $template->key, $renderer);
            });
    }

    /**
     * @return array<int, mixed>
     */
    private function exportParameterInputs(): array
    {
        $key = $this->getRecord()->schema['data']['source'] ?? null;
        $registry = app(DataSourceRegistry::class);

        return is_string($key) && $registry->has($key)
            ? ReportTemplateResource::parameterInputs($registry->get($key))
            : [];
    }

    private function download(string $content, string $key, Renderer $renderer): StreamedResponse
    {
        return response()->streamDownload(
            function () use ($content): void {
                echo $content;
            },
            sprintf('%s-%s.%s', $key, now()->format('Y-m-d'), $renderer->extension()),
            ['Content-Type' => $renderer->mimeType()],
        );
    }

    private function notifyExportFailed(string $title, string $reason): void
    {
        Notification::make()
            ->danger()
            ->title($title)
            ->body($reason)
            ->persistent()
            ->send();
    }

    private function refuseToSave(string $reason): never
    {
        Notification::make()
            ->danger()
            ->title('The report cannot be saved')
            ->body($reason)
            ->persistent()
            ->send();

        $this->halt();

        throw new \LogicException('Unreachable: halt() always throws.');
    }

    private function notifyImportFailed(string $reason): void
    {
        Notification::make()
            ->danger()
            ->title('Import failed')
            ->body($reason)
            ->send();
    }
}
