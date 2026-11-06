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
        // A plain script, so Blade apps using the prebuilt file aren't asked for React's build packages
        $this->assertSame(AssetTypes::TS, Manifest::find(Assets::BUILDER)->type);

        $package = Manifest::package(Assets::PACKAGE);
        $this->assertSame('js', $package->jsPath());
        $this->assertFileExists(dirname(__DIR__, 2) . '/resources/js/index.ts');

        $dist = json_decode(file_get_contents(dirname(__DIR__, 2) . '/dist/.vite/manifest.json'), true);
        $this->assertArrayHasKey('resources/js/blade/builder.ts', $dist);
        $this->assertArrayHasKey('resources/css/workflows.css', $dist);
        $this->assertArrayNotHasKey('css', $dist['resources/js/blade/builder.ts'], 'The Blade entry loads workflows::styles; it bundles no CSS of its own');
    }

    #[Test]
    public function it_scopes_the_react_packages_to_the_pages(): void
    {
        $package = Manifest::package(Assets::PACKAGE);

        $assets = [...$package->npmDependencies(NpmScopes::ASSETS), ...$package->npmPeerDependencies(NpmScopes::ASSETS)];
        $pages = [...$package->npmDependencies(NpmScopes::PAGES), ...$package->npmPeerDependencies(NpmScopes::PAGES)];

        $this->assertArrayNotHasKey('@javaabu/forms', $assets);
        $this->assertArrayNotHasKey('@javaabu/onu', $assets);
        $this->assertArrayNotHasKey('@inertiajs/react', $assets);
        $this->assertArrayNotHasKey('react', $assets);
        $this->assertArrayNotHasKey('@vitejs/plugin-react', $assets);
        $this->assertArrayHasKey('@javaabu/forms', $pages);
        $this->assertArrayHasKey('react', $pages);
        $this->assertArrayHasKey('react-dom', $pages);
        $this->assertArrayHasKey('@vitejs/plugin-react', $pages);
        $this->assertArrayHasKey('@javaabu/onu', $pages);
        $this->assertArrayHasKey('@inertiajs/react', $pages);
    }

    #[Test]
    public function it_asks_blade_apps_nothing_for_the_prebuilt_blade_entry(): void
    {
        $names = $this->checked(['assets']);

        $this->assertNotContains('@javaabu/forms', $names);
        $this->assertNotContains('@javaabu/onu', $names);
        $this->assertNotContains('@inertiajs/react', $names);
        $this->assertNotContains('react', $names);
        $this->assertNotContains('react-dom', $names);
        $this->assertNotContains('@vitejs/plugin-react', $names);
    }

    #[Test]
    public function it_asks_apps_with_pages_or_their_own_build_of_the_entry_for_react(): void
    {
        $names = $this->checked(['pages']);

        $this->assertContains('@javaabu/forms', $names);
        $this->assertContains('@javaabu/onu', $names);
        $this->assertContains('@inertiajs/react', $names);
        $this->assertContains('react', $names);
        $this->assertContains('react-dom', $names);
        $this->assertContains('@vitejs/plugin-react', $names);
    }

    /**
     * The npm packages manifest:check lists for this package
     *
     * @param  array<int, string>  $scopes
     * @return array<int, string>
     */
    protected function checked(array $scopes): array
    {
        Artisan::call('manifest:check', ['--scope' => $scopes, '--json' => true]);
        $rows = json_decode(Artisan::output(), true) ?? [];

        $ours = array_filter($rows, fn (array $row) => in_array(Assets::PACKAGE, $row['needed_by'], true));

        return array_values(array_column($ours, 'name'));
    }
}
