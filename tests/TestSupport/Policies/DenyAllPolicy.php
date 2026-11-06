<?php

namespace Javaabu\Workflows\Tests\TestSupport\Policies;

class DenyAllPolicy
{
    public function before(): bool
    {
        return false;
    }
}
