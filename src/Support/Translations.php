<?php

namespace Javaabu\Workflows\Support;

use Illuminate\Database\Eloquent\Model;
use Javaabu\Translatable\Contracts\Translatable as TranslatableContract;
use Javaabu\Translatable\Translatable;

/**
 * Optional javaabu/translatable support, in one place: every call checks the
 * package is installed first.
 */
class Translations
{
    /** Set to false to turn translatable text off even when the package is installed */
    public static bool $enabled = true;

    /**
     * Whether javaabu/translatable is installed, enabled and has active languages
     */
    public static function enabled(): bool
    {
        return static::$enabled
            && class_exists(Translatable::class)
            && Translatable::enabled();
    }

    /**
     * The locale values are read in
     */
    public static function currentLocale(): string
    {
        return static::enabled()
            ? Translatable::currentLocale()
            : app()->getLocale();
    }

    /**
     * The locale a translatable value falls back to
     */
    public static function defaultLocale(): string
    {
        return static::enabled()
            ? Translatable::defaultLocale()
            : (string) config('app.fallback_locale', config('app.locale', 'en'));
    }

    /**
     * The codes of the active locales, the default first
     *
     * @return array<int, string>
     */
    public static function locales(): array
    {
        if (! static::enabled()) {
            return array_values(array_unique([static::defaultLocale(), app()->getLocale()]));
        }

        return Translatable::locales()->map(fn ($locale) => $locale->code)->values()->all();
    }

    /**
     * Whether a stored value is a map of locale => value
     */
    public static function isLocaleMap(mixed $value): bool
    {
        if (! is_array($value) || $value === [] || array_is_list($value)) {
            return false;
        }

        foreach (array_keys($value) as $key) {
            if (! is_string($key) || ! preg_match('/^[a-z]{2,3}([_-][A-Za-z0-9]+)?$/', $key)) {
                return false;
            }
        }

        return true;
    }

    /**
     * The value of a locale map in the locale, falling back to the default
     * locale, then the first value that isn't empty
     *
     * @param  array<string, mixed>  $values
     */
    public static function pick(array $values, ?string $locale = null): mixed
    {
        $locale ??= static::currentLocale();

        if (static::filled($values[$locale] ?? null)) {
            return $values[$locale];
        }

        $default = static::defaultLocale();

        if (static::filled($values[$default] ?? null)) {
            return $values[$default];
        }

        foreach ($values as $value) {
            if (static::filled($value)) {
                return $value;
            }
        }

        return null;
    }

    protected static function filled(mixed $value): bool
    {
        return $value !== null && $value !== '';
    }

    /**
     * Save text given as locale maps on a model's translatable attributes. The
     * default locale's text is already on the model; without javaabu/translatable
     * (or a translatable model) this does nothing.
     *
     * @param  array<string, string|array<string, string>|null>  $attributes
     */
    public static function saveModelText(Model $model, array $attributes): void
    {
        if (! static::enabled() || ! $model instanceof TranslatableContract) {
            return;
        }

        $default = static::defaultLocale();
        $changed = false;

        foreach ($attributes as $attribute => $text) {
            if (! is_array($text) || ! static::isLocaleMap($text) || ! $model->isTranslatable($attribute)) {
                continue;
            }

            foreach ($text as $locale => $value) {
                if ($locale === $default || $value === null || $value === '') {
                    continue;
                }

                $model->addTranslation($locale, $attribute, (string) $value);
                $changed = true;
            }
        }

        if ($changed) {
            $model->save();
        }
    }

    /**
     * A locale's name in its own language, for labels of per-locale inputs
     */
    public static function localeName(string $code): string
    {
        if (static::enabled()) {
            $locale = Translatable::locale($code);

            if ($locale) {
                return $locale->native_name ?: $locale->name;
            }
        }

        return match ($code) {
            'en' => 'English',
            'dv' => 'ދިވެހި',
            'ar' => 'العربية',
            default => $code,
        };
    }

    /**
     * Whether a locale is written right to left
     */
    public static function isRtl(string $code): bool
    {
        if (static::enabled()) {
            $locale = Translatable::locale($code);

            if ($locale) {
                return $locale->isRtl();
            }
        }

        return in_array($code, ['dv', 'ar', 'fa', 'he', 'ur'], true);
    }
}
