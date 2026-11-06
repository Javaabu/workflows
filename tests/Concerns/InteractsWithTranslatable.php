<?php

namespace Javaabu\Workflows\Tests\Concerns;

use Illuminate\Support\Facades\Schema;
use Javaabu\Translatable\Facades\Languages;
use Javaabu\Translatable\LanguageRegistrar;
use Javaabu\Translatable\Models\Language;
use Javaabu\Translatable\Support\LanguagePresets;
use ReflectionClass;

/**
 * javaabu/translatable's InteractsWithLanguages, without using its trait, so
 * the test classes load when the package isn't installed (and are skipped)
 */
trait InteractsWithTranslatable
{
    /**
     * @param  array<int, string>  $codes
     */
    protected function setUpLanguages(array $codes = ['en', 'dv']): void
    {
        if (! Schema::hasTable('languages')) {
            $package = dirname((new ReflectionClass(Language::class))->getFileName(), 3);

            $migration = require "{$package}/database/migrations/create_languages_table.php";

            $migration->up();
        }

        foreach ($codes as $code) {
            Language::query()->firstOrCreate(['code' => $code], LanguagePresets::get($code));
        }

        app(LanguageRegistrar::class)->forgetCachedLanguages();

        Languages::setCurrentLocale($codes[0]);
    }

    protected function withoutTranslatable(callable $callback): mixed
    {
        $enabled = config('translatable.enabled');

        config(['translatable.enabled' => false]);

        try {
            return $callback();
        } finally {
            config(['translatable.enabled' => $enabled]);
        }
    }
}
