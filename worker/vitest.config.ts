import { defineConfig } from 'vitest/config';

// Testes ponta a ponta: sobem o Worker real com `wrangler dev` (workerd local) e
// conversam com ele por HTTP e WebSocket de verdade.
export default defineConfig({
  test: { globalSetup: './test/global-setup.ts', testTimeout: 20000, hookTimeout: 90000, include: ['test/**/*.test.ts'] },
});
