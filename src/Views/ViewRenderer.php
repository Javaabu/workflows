<?php

namespace Javaabu\Workflows\Views;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Contracts\View\View;
use Inertia\Inertia;
use RuntimeException;

/**
 * Renders an admin page with the stack chosen in `config('workflows.views')`
 */
class ViewRenderer
{
    public const string BLADE = 'blade';

    public const string INERTIA = 'inertia';

    /**
     * @param  string  $page  e.g. `workflows.index`, rendered as the Blade view `workflows::workflows.index`
     *                        or the Inertia page `workflows::Workflows/Index`
     * @param  array<string, mixed>  $data
     */
    public function render(string $page, array $data = []): View|Responsable
    {
        if ($this->isInertia()) {
            return $this->renderInertia($page, $data);
        }

        return view('workflows::' . $page, $data);
    }

    public function stack(): string
    {
        return config('workflows.views', self::BLADE);
    }

    public function isInertia(): bool
    {
        return $this->stack() === self::INERTIA;
    }

    /**
     * The Inertia page name for a page: `workflows.index` → `workflows::Workflows/Index`
     */
    public function inertiaPage(string $page): string
    {
        $segments = array_map(fn (string $segment) => str($segment)->studly()->toString(), explode('.', $page));

        return 'workflows::' . implode('/', $segments);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function renderInertia(string $page, array $data): Responsable
    {
        if (! class_exists(Inertia::class)) {
            throw new RuntimeException('Install inertiajs/inertia-laravel to use the inertia views, or set workflows.views to blade.');
        }

        return Inertia::render($this->inertiaPage($page), $data);
    }
}
