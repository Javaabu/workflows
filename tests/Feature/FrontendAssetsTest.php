<?php

namespace Javaabu\Workflows\Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Javaabu\Manifest\Enums\AssetTypes;
use Javaabu\Manifest\Enums\NpmScopes;
use Javaabu\Manifest\Facades\Manifest;
use Javaabu\Workflows\Assets\Assets;
use Javaabu\Workflows\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class FrontendAssetsTest extends TestCase
{
    #[Test]
    public function it_registers_the_styles_the_blade_entry_and_the_pages(): void
    {
        $this->assertTrue(Manifest::has(Assets::STYLES));
        $this->assertTrue(Manifest::has(Assets::BUILDER));
        $this->assertSame(AssetTypes::REACT, Manifest::find(Assets::BUILDER)->type);

        $package = Manifest::package(Assets::PACKAGE);
        $this->assertSame('js', $package->jsPath());
        $this->assertFileExists(dirname(__DIR__, 2) . '/resources/js/index.ts');
        $this->assertFileExists(dirname(__DIR__, 2) . '/dist/.vite/manifest.json');
    }

    #[Test]
    public function only_the_pages_need_the_react_form_libraries(): void
    {
        $package = Manifest::package(Assets::PACKAGE);

        $assets = [...$package->npmDependencies(NpmScopes::ASSETS), ...$package->npmPeerDependencies(NpmScopes::ASSETS)];
        $pages = [...$package->npmDependencies(NpmScopes::PAGES), ...$package->npmPeerDependencies(NpmScopes::PAGES)];

        $this->assertArrayNotHasKey('@javaabu/forms', $assets);
        $this->assertArrayNotHasKey('@javaabu/onu', $assets);
        $this->assertArrayNotHasKey('@inertiajs/react', $assets);
        $this->assertArrayHasKey('@javaabu/forms', $pages);
        $this->assertArrayHasKey('@javaabu/onu', $pages);
        $this->assertArrayHasKey('@inertiajs/react', $pages);
    }

    #[Test]
    public function manifest_check_asks_blade_apps_only_for_what_the_blade_entry_needs(): void
    {
        Artisan::call('manifest:check', ['--scope' => ['assets'], '--json' => true]);
        $rows = json_decode(Artisan::output(), true) ?? [];

        $this->assertNotSame([], $rows);

        $ours = array_values(array_filter($rows, fn (array $row) => in_array(Assets::PACKAGE, $row['needed_by'], true)));
        $names = array_column($ours, 'name');

        $this->assertNotContains('@javaabu/forms', $names);
        $this->assertNotContains('@javaabu/onu', $names);
        $this->assertNotContains('@inertiajs/react', $names);
        $this->assertContains('react', $names);
    }
}
