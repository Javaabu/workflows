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

The Blade pages need no JavaScript build: the builder's Blade entry (`workflows::builder`) is prebuilt in `dist/` with React bundled in, so `php artisan manifest:check` asks a Blade app for nothing from this package.

manifest's Vite plugin adds every registered asset to the app's build, so a Blade app that uses it builds the entry too, and needs `react` and `react-dom` for that. Either install them, or leave the entry out of your build and let the prebuilt file load:

```js
// vite.config.js
const assets = manifest({ input: false })

export default defineConfig({
  plugins: [
    laravel({ input: ["resources/js/app.js", ...assets.inputs().filter((input) => !input.includes("workflows/resources/js/blade/"))] }),
    assets,
  ],
})
```

The Inertia React pages (and anything you import from `@javaabu/workflows`) need:

- React 19 and `@inertiajs/react`
- [@javaabu/forms](https://github.com/Javaabu/react-forms)
- [@javaabu/onu](https://www.npmjs.com/package/@javaabu/onu)
- `@vitejs/plugin-react`
- manifest's Vite plugin, which resolves `@javaabu/workflows`, `@javaabu/form-builder` and `@javaabu/stencil`

These are declared for manifest's `pages` scope, so a Blade-only app isn't asked for them. Run `php artisan manifest:check` to see anything that's missing (`--scope=pages` lists them in an app without an Inertia adapter, such as a Blade app that builds the Blade entry itself).

The pages import the package's styles (`resources/css/workflows.css`), and so does `@javaabu/workflows`, so Vite bundles them: there's nothing to add to your app's CSS.
