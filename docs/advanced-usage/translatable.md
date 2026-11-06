---
title: Translatable workflows
sidebar_position: 9
---

With [javaabu/translatable](https://github.com/Javaabu/translatable) installed and more than one language active:

- workflows use `Models\Translatable\Workflow`: the name and description are kept in every language (its morph class is still `Models\Workflow`);
- the settings page and the builder have an input per language for names, descriptions, labels and text action settings, with Thaana and right to left for Dhivehi;
- step names, transition labels and notification text are locale maps in the definition, read in the current language, falling back to the default language, then any text there is.

```php
Workflows::define('leave-request')
    ->name(['en' => 'Leave request', 'dv' => 'ޗުއްޓީގެ އެދުން'])
    ->steps([Step::task('review')->name(['en' => 'Manager review', 'dv' => 'މެނޭޖަރުގެ ރިވިއު']), ...]);

Workflows::text($step->getName());     // in the current locale
$workflow->text('name');               // ['en' => …, 'dv' => …]
```

Without the package, or with `translatable.enabled` false, names are plain strings, locale maps in definitions still work, and the builder shows one input for the app's language. `Support\Translations::$enabled = false` turns it off for this package only. Everything goes through `Support\Translations`, which checks the package is installed first.
