import { resolve } from "node:path";
import { defineConfig } from "vite";
import dts from "vite-plugin-dts";

export default defineConfig({
  build: {
    lib: {
      entry: resolve(import.meta.dirname, "src/index.ts"),
      formats: ["es"],
      fileName: "index",
    },
    rollupOptions: {
      external: ["node:fs", "node:path", "node:fs/promises", "vite", "fast-glob"],
    },
    minify: false,
    sourcemap: true,
  },
  plugins: [
    dts({
      bundleTypes: true,
    }),
  ],
});
