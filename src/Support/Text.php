<?php

namespace Javaabu\Workflows\Support;

/**
 * Text that's a string, or a map of locale => string when it's translated
 */
class Text
{
    /**
     * The text in the locale (the current one by default), falling back to the
     * default locale, then any text there is
     *
     * @param  string|array<string, string>|null  $text
     */
    public static function resolve(string|array|null $text, ?string $locale = null): ?string
    {
        if ($text === null) {
            return null;
        }

        if (is_string($text)) {
            return $text === '' ? null : $text;
        }

        if (! Translations::isLocaleMap($text)) {
            return null;
        }

        $value = Translations::pick($text, $locale ?? app()->getLocale());

        return $value === null ? null : (string) $value;
    }

    /**
     * The text in every locale, for a builder input per locale
     *
     * @param  string|array<string, string>|null  $text
     * @param  array<int, string>  $locales
     * @return array<string, string|null>
     */
    public static function locales(string|array|null $text, array $locales): array
    {
        $values = [];

        foreach ($locales as $index => $locale) {
            if (is_array($text)) {
                $values[$locale] = isset($text[$locale]) && $text[$locale] !== '' ? (string) $text[$locale] : null;

                continue;
            }

            $values[$locale] = $index === 0 ? $text : null;
        }

        return $values;
    }

    /**
     * Back to a string when only one locale has text and it's the default one
     *
     * @param  array<string, string|null>|string|null  $values
     * @return string|array<string, string>|null
     */
    public static function compact(array|string|null $values): string|array|null
    {
        if (! is_array($values)) {
            return $values === '' ? null : $values;
        }

        $values = array_filter($values, fn ($value) => $value !== null && $value !== '');

        if ($values === []) {
            return null;
        }

        if (count($values) === 1 && array_key_first($values) === Translations::defaultLocale()) {
            return (string) reset($values);
        }

        return array_map('strval', $values);
    }
}
