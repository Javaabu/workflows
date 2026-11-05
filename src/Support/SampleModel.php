<?php

namespace Javaabu\Workflows\Support;

use Illuminate\Database\Eloquent\Model;

/**
 * A stand-in subject or starter for the tester, made from sample attributes.
 * It's never saved.
 */
class SampleModel extends Model
{
    protected $guarded = [];

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function from(array $attributes): static
    {
        return (new static)->forceFill($attributes);
    }

    public function save(array $options = []): bool
    {
        return true;
    }
}
