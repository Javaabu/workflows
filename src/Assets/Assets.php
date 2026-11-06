<?php

namespace Javaabu\Workflows\Assets;

use Javaabu\Manifest\Package;

/**
 * The package's frontend assets, registered with javaabu/manifest
 */
class Assets
{
    public const string PACKAGE = 'workflows';

    /** Styles for the builder canvas, the tester and the history (logical properties only) */
    public const string STYLES = 'workflows::styles';

    /**
     * The Blade entry: mounts the React builder on [data-workflow-builder] and
     * the read-only diagram on [data-workflow-graph]. Apps load the prebuilt
     * file in dist/, which bundles React, so it's a plain script() (a .ts file
     * without JSX), not a react() asset: Blade apps aren't asked for React's
     * build packages. Apps that build it with their own Vite need the pages'
     * react, react-dom and @vitejs/plugin-react (`manifest:check --scope=pages`).
     */
    public const string BUILDER = 'workflows::builder';

    public static function register(Package $package): void
    {
        $package
            ->js('js')
            ->pages()
            ->dist('../dist')
            ->style('styles', 'css/workflows.css')
            ->script('builder', 'js/blade/builder.ts', ['styles'])
            // Only the Inertia pages, code apps import as @javaabu/workflows, and
            // apps building the Blade entry themselves use these
            ->npm(
                [
                    '@javaabu/forms' => 'github:Javaabu/react-forms#next-gen',
                    '@javaabu/onu' => '^0.2.1',
                    '@vitejs/plugin-react' => '*',
                ],
                peer_dependencies: [
                    'react' => '^19.0',
                    'react-dom' => '^19.0',
                    '@inertiajs/react' => '^2.0 || ^3.0',
                ],
                for: 'pages',
            );
    }
}
