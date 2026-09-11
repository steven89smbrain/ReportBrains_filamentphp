<?php

declare(strict_types=1);

use App\Models\User;
use Filament\Facades\Filament;
use ReportBrains\ReportDesigner\Filament\Pages\Reports;
use ReportBrains\ReportDesigner\ReportDesignerPlugin;

it('registers the plugin on the admin panel', function () {
    $plugin = Filament::getPanel('admin')->getPlugin('report-designer');

    expect($plugin)->toBeInstanceOf(ReportDesignerPlugin::class);
});

it('discovers the pages shipped by the package', function () {
    expect(Filament::getPanel('admin')->getPages())->toContain(Reports::class);
});

it('renders the package page for an authenticated user', function () {
    $this->actingAs(User::factory()->create())
        ->get(Reports::getUrl(panel: 'admin'))
        ->assertSuccessful()
        ->assertSee('Report Designer');
});

it('keeps the package page behind authentication', function () {
    $this->get(Reports::getUrl(panel: 'admin'))
        ->assertRedirect(route('filament.admin.auth.login'));
});
