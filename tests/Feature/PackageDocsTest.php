<?php

declare(strict_types=1);

/**
 * The documentation ships twice: `Documentation/` is the source, and a copy
 * inside the package reaches buyers, because only the package directory is
 * split into the release repository.
 *
 * Two copies drift apart silently, so this test fails as soon as they differ.
 * `composer docs:sync` refreshes the copy.
 */
it('ships the documentation inside the package, matching the source', function () {
    $source = base_path('Documentation');
    $shipped = base_path('packages/filament-report-designer/docs');

    expect(is_dir($shipped))->toBeTrue('The package has no docs/ directory. Run: composer docs:sync');

    $this->assertSame(
        contentsOf($source),
        contentsOf($shipped),
        'The documentation shipped with the package is out of date. Run: composer docs:sync',
    );
});

it('keeps internal planning notes out of the package', function () {
    expect(glob(base_path('packages/filament-report-designer/docs/*keputusan*')))->toBe([])
        ->and(glob(base_path('packages/filament-report-designer/docs/*roadmap*')))->toBe([]);
});
