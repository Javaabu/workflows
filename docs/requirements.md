---
title: Requirements
sidebar_position: 1.1
---

This package requires:

- PHP 8.4 or higher
- Laravel 12 or 13
- [javaabu/form-builder](https://github.com/Javaabu/form-builder): the forms steps collect data with (installed with the package)
- [javaabu/stencil](https://github.com/Javaabu/stencil): the settings form and the step forms (installed with the package)
- [javaabu/forms](https://github.com/Javaabu/forms) v2: the Blade pages (installed with the package)
- [javaabu/permissions](https://github.com/Javaabu/permissions) v3: permissions and the policies (installed with the package)
- [javaabu/manifest](https://github.com/Javaabu/manifest): ships the builder, the styles and the React pages to your app (installed with the package)

Optional:

- [inertiajs/inertia-laravel](https://inertiajs.com) v2, for the Inertia React pages (`workflows.views` = `inertia`)
- [javaabu/notifications](https://github.com/Javaabu/notifications), so the notify action sends its notifications (every channel, the bell, preferences). Without it, the action sends a plain Laravel mail and database notification. See [Notifications](advanced-usage/notifications.md).
- [javaabu/translatable](https://github.com/Javaabu/translatable) v3, for workflow names, step names and labels in every language. See [Translatable workflows](advanced-usage/translatable.md).
- [laravel/ai](https://github.com/laravel/ai) 1.3+, for the assistant that designs workflows from a description. See [AI assistant](advanced-usage/ai-assistant.md).

# Frontend

The Blade pages need no JavaScript build: the builder's Blade entry (`workflows::builder`) is prebuilt in `dist/` with React bundled in. If your app builds it with its own Vite, it needs `react`, `react-dom` and `@vitejs/plugin-react`, as for any React asset.

The Inertia React pages (and anything you import from `@javaabu/workflows`) need:

- React 19 and `@inertiajs/react`
- [@javaabu/forms](https://github.com/Javaabu/react-forms)
- [@javaabu/onu](https://www.npmjs.com/package/@javaabu/onu)
- manifest's Vite plugin, which resolves `@javaabu/workflows`, `@javaabu/form-builder` and `@javaabu/stencil`

These are declared for manifest's `pages` scope, so a Blade-only app isn't asked for them. Run `php artisan manifest:check` to see anything that's missing.
