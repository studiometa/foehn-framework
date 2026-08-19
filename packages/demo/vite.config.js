import { defineConfig } from 'vite';
import tailwindcss from '@tailwindcss/vite';
import foehn from '@studiometa/foehn-vite-plugin';

export default defineConfig({
  plugins: [
    foehn({
      input: ['theme/assets/js/app.js', 'theme/assets/css/app.css'],
      reload: ['theme/templates/**/*.twig', 'theme/app/**/*.php'],
    }),
    tailwindcss(),
  ],
});
