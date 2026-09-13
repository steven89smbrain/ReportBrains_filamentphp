<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * A minimal report document that passes schema validation.
 *
 * Tests override only the part they are about, so a failure points at the
 * change rather than at unrelated fixture noise.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function validReportDocument(array $overrides = []): array
{
    return array_replace_recursive([
        'schema_version' => 1,
        'key' => 'user-directory',
        'title' => 'User Directory',
        // Matches the "users" source registered in AppServiceProvider, so
        // documents built here pass the registry check as well as the schema.
        'data' => [
            'source' => 'users',
            'sort' => [['field' => 'created_at', 'dir' => 'desc']],
        ],
        'bands' => [
            'document_header' => [
                ['type' => 'heading', 'level' => 1, 'content' => 'User Directory'],
            ],
            'detail' => [
                [
                    'type' => 'table',
                    'columns' => [
                        ['field' => 'name', 'label' => 'Name'],
                        ['field' => 'email', 'label' => 'Email address'],
                    ],
                ],
            ],
        ],
    ], $overrides);
}
