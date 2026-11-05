<?php

namespace Javaabu\Workflows\Tests\Unit;

use Javaabu\Workflows\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class ServiceProviderTest extends TestCase
{
    #[Test]
    public function it_merges_the_default_config(): void
    {
        $this->assertSame('blade', config('workflows.views'));
    }
}
