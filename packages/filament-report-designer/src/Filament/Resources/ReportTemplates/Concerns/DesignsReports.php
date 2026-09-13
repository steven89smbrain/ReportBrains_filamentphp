<?php

declare(strict_types=1);

namespace ReportBrains\ReportDesigner\Filament\Resources\ReportTemplates\Concerns;

use Filament\Actions\Action;
use Filament\Forms\Components\CodeEditor;
use Filament\Forms\Components\CodeEditor\Enums\Language;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use JsonException;
use ReportBrains\ReportDesigner\DataSources\ReportQueryFactory;
use ReportBrains\ReportDesigner\Designer\DocumentFormMapper;
use ReportBrains\ReportDesigner\Exceptions\InvalidReportSchema;
use ReportBrains\ReportDesigner\Exceptions\UnknownDataSource;
use ReportBrains\ReportDesigner\Exceptions\UnknownField;
use ReportBrains\ReportDesigner\ReportDesignerPlugin;
use ReportBrains\ReportDesigner\Schema\ReportSchema;

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
     * @param  array<string, mixed>  $preserve  Document keys the designer does not edit.
     * @return array<string, mixed> Attributes for the model.
     */
    protected function prepareTemplateAttributes(array $data, array $preserve = []): array
    {
        $identity = [
            'key' => $data['key'] ?? $this->record?->key ?? null,
            'title' => $data['title'] ?? null,
        ];

        $document = app(DocumentFormMapper::class)->toDocument($data, $identity, $preserve);

        try {
            app(ReportSchema::class)->validate($document);
            app(ReportQueryFactory::class)->validateBindings($document);
        } catch (InvalidReportSchema $exception) {
            $this->refuseToSave($exception->summary());
        } catch (UnknownDataSource|UnknownField $exception) {
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
