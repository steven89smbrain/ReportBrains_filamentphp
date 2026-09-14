<?php

declare(strict_types=1);

namespace ReportBrains\ReportDesigner\Jobs;

use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use ReportBrains\ReportDesigner\Events\ReportRendered;
use ReportBrains\ReportDesigner\Output\OutputFormats;
use ReportBrains\ReportDesigner\ReportRunner;
use RuntimeException;

/**
 * Renders a report and stores it, outside the request.
 *
 * Data source scopes usually depend on who is signed in, and nobody is signed
 * in on a queue worker. The job therefore runs as the user who asked for the
 * report, and refuses to run at all when that user no longer exists, rather
 * than reading data with no scope applied.
 */
class RenderReport implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $document
     * @param  array<string, mixed>  $parameters
     */
    public function __construct(
        public readonly array $document,
        public readonly array $parameters,
        public readonly string $format,
        public readonly string $path,
        public readonly ?string $disk = null,
        public readonly int|string|null $userId = null,
        public readonly ?string $guard = null,
    ) {}

    public function handle(ReportRunner $runner, OutputFormats $formats, AuthFactory $auth): void
    {
        if ($this->userId !== null) {
            $this->actAsRequester($auth);
        }

        $content = $runner->render($this->document, $formats->renderer($this->format), $this->parameters);

        Storage::disk($this->disk)->put($this->path, $content);

        ReportRendered::dispatch(
            $this->document['key'] ?? null,
            $this->format,
            $this->path,
            $this->disk,
            $this->userId,
        );
    }

    private function actAsRequester(AuthFactory $auth): void
    {
        $guard = $auth->guard($this->guard);
        $user = method_exists($guard, 'getProvider') ? $guard->getProvider()->retrieveById($this->userId) : null;

        if ($user === null) {
            throw new RuntimeException("Cannot render the report: the user [{$this->userId}] who requested it no longer exists.");
        }

        $guard->setUser($user);
    }
}
