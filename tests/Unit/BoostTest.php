<?php

namespace Javaabu\Workflows\Tests\Unit;

use Javaabu\Workflows\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class BoostTest extends TestCase
{
    #[Test]
    public function it_ships_the_boost_guideline_and_skill(): void
    {
        $root = dirname(__DIR__, 2) . '/resources/boost';

        $this->assertFileExists("{$root}/guidelines/core.blade.php");

        $skill = file_get_contents("{$root}/skills/javaabu-workflows/SKILL.md");

        $this->assertMatchesRegularExpression('/^---\nname: javaabu-workflows\ndescription: .+\n/', $skill);

        preg_match_all('/\]\((references\/[^)]+)\)/', $skill, $matches);

        foreach ($matches[1] as $reference) {
            $this->assertFileExists("{$root}/skills/javaabu-workflows/{$reference}");
        }

        $this->assertNotEmpty($matches[1]);
    }

    #[Test]
    public function it_compiles_the_guideline(): void
    {
        $html = view()->file(dirname(__DIR__, 2) . '/resources/boost/guidelines/core.blade.php')->render();

        $this->assertStringContainsString('Workflows::define(\'key\')', $html);
        $this->assertStringContainsString('javaabu-workflows', $html);
    }
}
