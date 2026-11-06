import { resolve } from "node:path"
import { defineConfig } from "vite"

/** `pnpm examples` serves these pages; `pnpm examples:build` builds them (CI checks they build). */
export default defineConfig({
  root: __dirname,
  resolve: {
    alias: {
      "@javaabu/workflows": resolve(__dirname, "../resources/js"),
      "@javaabu/stencil": resolve(__dirname, "../vendor/javaabu/stencil/resources/js"),
      "@javaabu/form-builder": resolve(__dirname, "../vendor/javaabu/form-builder/resources/js"),
      // The pages call Inertia's router; outside an Inertia app the example logs instead
      "@inertiajs/react": resolve(__dirname, "inertia-stub.ts"),
    },
  },
  server: { fs: { allow: [resolve(__dirname, "..")] } },
  build: {
    outDir: resolve(__dirname, "dist"),
    emptyOutDir: true,
    chunkSizeWarningLimit: 2000,
    rollupOptions: {
      input: {
        blade: resolve(__dirname, "index.html"),
        react: resolve(__dirname, "react.html"),
      },
      onwarn(warning, warn) {
        if (warning.code === "MODULE_LEVEL_DIRECTIVE") return
        warn(warning)
      },
    },
  },
})
