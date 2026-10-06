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
      // Every Node built-in: one missing from a list is bundled as a browser
      // stub and fails only at run time.
      external: [/^node:/, "vite", "fast-glob"],
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
