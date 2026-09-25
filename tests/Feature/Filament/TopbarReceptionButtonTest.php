<?php

declare(strict_types=1);

use App\Models\User;
use Filament\Facades\Filament;
use Filament\View\PanelsRenderHook;

test('authenticated user sees reception button in filament top bar', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(Filament::getUrl())
        ->assertOk()
        ->assertSee(route('reception.index'))
        ->assertSee('الاستقبال');
});

test('filament panel registers reception button in topbar render hook', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::bootCurrentPanel();

    $html = \Filament\Support\Facades\FilamentView::renderHook(PanelsRenderHook::GLOBAL_SEARCH_BEFORE)->toHtml();

    expect($html)
        ->toContain(route('reception.index'))
        ->toContain('الاستقبال');
});

test('reception route is accessible to authenticated users', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('reception.index'))
        ->assertOk();
});
