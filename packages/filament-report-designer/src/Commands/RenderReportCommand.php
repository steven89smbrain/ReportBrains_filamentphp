<?php

declare(strict_types=1);

namespace ReportBrains\ReportDesigner\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use ReportBrains\ReportDesigner\Exceptions\TemplateNotFound;
use ReportBrains\ReportDesigner\Output\OutputFormats;
use ReportBrains\ReportDesigner\Output\PendingReport;
use ReportBrains\ReportDesigner\Output\ReportManager;
use ReportBrains\ReportDesigner\TemplateRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Throwable;

#[AsCommand(name: 'report:render', description: 'Render a report template to a file')]
class RenderReportCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'report:render
        {template : A stored template key, a file template key, or a path to a .json template}
        {--format= : pdf, html, markdown, csv or xlsx (default: from --output, otherwise pdf)}
        {--output= : Where to store the file (default: a dated file named after the template, in reports/)}
        {--disk= : The filesystem disk to store it on (default: the default disk)}
        {--param=* : A parameter as name=value; repeat for more}
        {--as= : The ID of the user to run as, so data source scopes apply}
        {--queue : Render on the queue instead of now}';

    public function handle(ReportManager $reports, OutputFormats $formats, TemplateRepository $templates): int
    {
        try {
            $report = $this->resolve($reports, $templates, (string) $this->argument('template'));
        } catch (TemplateNotFound $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $report = $report->with($this->parameters());
        $format = $this->option('format') ?: $formats->forExtension(pathinfo((string) $this->option('output'), PATHINFO_EXTENSION)) ?: 'pdf';

        if (! $formats->has($format)) {
            $this->components->error("Unknown format [{$format}]. Available: ".implode(', ', $formats->names()).'.');

            return self::FAILURE;
        }

        $extension = $formats->renderer($format)->extension();
        $path = $this->option('output') ?: sprintf('reports/%s-%s.%s', $report->document()['key'] ?? 'report', now()->format('Y-m-d-His'), $extension);
        $disk = $this->option('disk') ?: null;

        if (! $this->actAs()) {
            return self::FAILURE;
        }

        if ($this->option('queue')) {
            $report->queue($path, $disk, $format, Auth::user());
            $this->components->info("Queued [{$path}] on the ".($disk ?? 'default').' disk.');

            return self::SUCCESS;
        }

        try {
            $report->save($path, $disk, $format);
        } catch (Throwable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->components->info(sprintf(
            'Rendered [%s] (%s) on the %s disk.',
            $path,
            $this->humanSize(Storage::disk($disk)->size($path)),
            $disk ?? 'default',
        ));

        return self::SUCCESS;
    }

    /**
     * Stored templates win over file templates with the same key.
     */
    private function resolve(ReportManager $reports, TemplateRepository $templates, string $template): PendingReport
    {
        if (str_ends_with($template, '.json')) {
            return $reports->file($template);
        }

        return $templates->findOrNull($template) !== null
            ? $reports->template($template)
            : $reports->file($template);
    }

    /**
     * @return array<string, string>
     */
    private function parameters(): array
    {
        $parameters = [];

        foreach ((array) $this->option('param') as $pair) {
            [$name, $value] = array_pad(explode('=', (string) $pair, 2), 2, '');
            $parameters[trim($name)] = $value;
        }

        return $parameters;
    }

    private function actAs(): bool
    {
        $id = $this->option('as');

        if ($id === null || $id === '') {
            return true;
        }

        $guard = Auth::guard();
        $user = method_exists($guard, 'getProvider') ? $guard->getProvider()->retrieveById($id) : null;

        if ($user === null) {
            $this->components->error("No user with ID [{$id}].");

            return false;
        }

        $guard->setUser($user);

        return true;
    }

    private function humanSize(int $bytes): string
    {
        return $bytes >= 1048576
            ? number_format($bytes / 1048576, 1).' MB'
            : number_format($bytes / 1024, 1).' KB';
    }
}
