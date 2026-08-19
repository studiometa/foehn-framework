import { playwright } from '@vitest/browser-playwright';
import { defineConfig } from 'vitest/config';

export default defineConfig({
  test: {
    // The framework ships Node-only tests under vendor/; they run from the
    // monorepo root, not here, and would fail in the browser environment.
    exclude: ['**/node_modules/**', '**/vendor/**'],
    browser: {
      enabled: true,
      provider: playwright(),
      headless: true,
      instances: [{ browser: 'chromium' }],
    },
  },
});
