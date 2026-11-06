import { defineConfig } from "vitest/config"

// @javaabu/stencil and @javaabu/form-builder are the React code in vendor/ (composer install first),
// as javaabu/manifest's Vite plugin resolves it in apps
export default defineConfig({
  resolve: {
    alias: {
      "@javaabu/workflows": new URL("./resources/js", import.meta.url).pathname,
      "@javaabu/stencil": new URL("./vendor/javaabu/stencil/resources/js", import.meta.url).pathname,
      "@javaabu/form-builder": new URL("./vendor/javaabu/form-builder/resources/js", import.meta.url).pathname,
    },
  },
  test: {
    environment: "jsdom",
    include: ["tests/js/**/*.test.{ts,tsx}"],
    setupFiles: ["tests/js/setup.ts"],
    coverage: {
      provider: "v8",
      include: ["resources/js/**/*.{ts,tsx}"],
      reporter: ["text", "clover", "lcov"],
      reportsDirectory: "coverage",
    },
  },
})
