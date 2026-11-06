import { defineConfig } from "vite"

/**
 * Builds the prebuilt assets in dist/ for apps that don't build javaabu/workflows
 * with their own Vite. javaabu/manifest finds them through dist/.vite/manifest.json,
 * keyed by these entry paths. dist/ is committed; CI fails when it's out of date.
 *
 * The Blade entry bundles React and the builder, so Blade apps need no React of
 * their own. The Inertia pages aren't prebuilt: apps build them from resources/js.
 */
export const entries = ["resources/js/blade/builder.ts", "resources/css/workflows.css"]

export default defineConfig({
  // Relative URLs, as javaabu/manifest serves dist/ from a sub-path.
  base: "./",
  publicDir: false,
  define: {
    "process.env.NODE_ENV": JSON.stringify("production"),
  },
  build: {
    outDir: "dist",
    emptyOutDir: true,
    manifest: true,
    target: "es2020",
    sourcemap: false,
    reportCompressedSize: false,
    chunkSizeWarningLimit: 800,
    rollupOptions: {
      input: entries,
      onwarn(warning, warn) {
        // Onu's "use client" directives mean nothing in a browser bundle.
        if (warning.code === "MODULE_LEVEL_DIRECTIVE") return
        warn(warning)
      },
    },
  },
})
