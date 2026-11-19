import { defineConfig } from "vite";

// The admin script is committed in dist/: the Packagist mirror has no build step. CI
// builds it again and fails when the result differs from the committed files.
export default defineConfig({
  build: {
    outDir: "dist",
    emptyOutDir: true,
    minify: true,
    lib: {
      entry: "assets/admin.js",
      formats: ["es"],
      fileName: () => "admin.js",
      cssFileName: "admin",
    },
    rolldownOptions: {
      // The standalone geocoder never loads mapbox-gl.
      external: ["mapbox-gl"],
      output: { codeSplitting: false },
    },
  },
});
