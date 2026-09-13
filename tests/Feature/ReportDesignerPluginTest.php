<?php

declare(strict_types=1);

use App\Models\User;
use Filament\Facades\Filament;
use ReportBrains\ReportDesigner\Filament\Resources\ReportTemplates\ReportTemplateResource;
use ReportBrains\ReportDesigner\ReportDesignerPlugin;

it('registers the plugin on the admin panel', function () {
    $plugin = Filament::getPanel('admin')->getPlugin('report-designer');

    expect($plugin)->toBeInstanceOf(ReportDesignerPlugin::class);
});

it('discovers the resources shipped by the package', function () {
    expect(Filament::getPanel('admin')->getResources())->toContain(ReportTemplateResource::class);
});

it('renders the designer for an authenticated user', function () {
    $this->actingAs(User::factory()->create())
        ->get(ReportTemplateResource::getUrl('index', panel: 'admin'))
        ->assertSuccessful();
});

it('keeps the designer behind authentication', function () {
    $this->get(ReportTemplateResource::getUrl('index', panel: 'admin'))
        ->assertRedirect(route('filament.admin.auth.login'));
});

it('enables the JSON editor by default and lets a panel switch it off', function () {
    expect(ReportDesignerPlugin::jsonEditorEnabled())->toBeTrue();

    Filament::getPanel('admin')->getPlugin('report-designer')->jsonEditor(false);

    expect(ReportDesignerPlugin::jsonEditorEnabled())->toBeFalse();
});
