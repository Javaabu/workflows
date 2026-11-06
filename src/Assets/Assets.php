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
     * the read-only diagram on [data-workflow-graph]. React is bundled in.
     */
    public const string BUILDER = 'workflows::builder';

    public static function register(Package $package): void
    {
        $package
            ->js('js')
            ->pages()
            ->dist('../dist')
            ->style('styles', 'css/workflows.css')
            ->script('builder', 'js/blade/builder.tsx', ['styles'])
            ->npm([
                '@javaabu/forms' => 'github:Javaabu/react-forms#next-gen',
                '@javaabu/onu' => '^0.2.1',
            ], [
                'react' => '^19.0',
                'react-dom' => '^19.0',
                '@inertiajs/react' => '^2.0 || ^3.0',
            ]);
    }
}
