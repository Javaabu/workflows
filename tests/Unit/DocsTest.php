<?php

namespace Javaabu\Workflows\Tests\Unit;

use Illuminate\Support\Str;
use Javaabu\Workflows\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class DocsTest extends TestCase
{
    protected function root(): string
    {
        return dirname(__DIR__, 2);
    }

    /**
     * @return array<int, string>
     */
    protected function pages(): array
    {
        $pages = [];

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->root() . '/docs')) as $file) {
            if ($file->getExtension() === 'md') {
                $pages[] = Str::after($file->getPathname(), $this->root() . '/');
            }
        }

        sort($pages);

        return $pages;
    }

    #[Test]
    public function it_lists_every_docs_page_in_llms_txt(): void
    {
        $llms = file_get_contents($this->root() . '/llms.txt');

        preg_match_all('/\]\((docs\/[^)]+\.md)\)/', $llms, $matches);

        $listed = $matches[1];
        sort($listed);

        $this->assertEquals($this->pages(), $listed);
    }

    #[Test]
    public function it_only_links_to_pages_that_exist(): void
    {
        foreach ($this->pages() as $page) {
            preg_match_all('/\]\((\.{1,2}\/[^)#]+)(#[^)]*)?\)/', file_get_contents($this->root() . "/{$page}"), $matches);

            foreach ($matches[1] as $link) {
                $target = realpath(dirname($this->root() . "/{$page}") . '/' . $link);
                $target = $target ?: realpath(dirname($this->root() . "/{$page}") . '/' . $link . '.md');

                $this->assertNotFalse($target, "{$page} links to {$link}, which doesn't exist.");
            }
        }
    }

    #[Test]
    public function it_has_front_matter_on_every_page(): void
    {
        foreach ($this->pages() as $page) {
            $this->assertMatchesRegularExpression('/^---\ntitle: .+\n/', file_get_contents($this->root() . "/{$page}"), "{$page} has no title.");
        }
    }
}
