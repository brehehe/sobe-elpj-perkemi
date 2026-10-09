import { defineConfig, devices } from '@playwright/test';
import { e2eDatabasePath } from './tests/e2e/database';

export default defineConfig({
    testDir: './tests/e2e',
    globalTeardown: './tests/e2e/global-teardown.ts',
    fullyParallel: false,
    workers: 1,
    forbidOnly: Boolean(process.env.CI),
    retries: process.env.CI ? 2 : 0,
    reporter: 'line',
    use: {
        baseURL: 'http://127.0.0.1:8765',
        channel: 'chrome',
        trace: 'retain-on-failure',
        screenshot: 'only-on-failure',
    },
    projects: [
        { name: 'desktop', use: { ...devices['Desktop Chrome'], viewport: { width: 1440, height: 900 } } },
        { name: 'mobile', use: { ...devices['Desktop Chrome'], viewport: { width: 375, height: 812 } } },
    ],
    webServer: {
        command: 'node tests/e2e/start-server.mjs',
        env: {
            CACHE_STORE: 'array',
            DB_CONNECTION: 'sqlite',
            DB_DATABASE: e2eDatabasePath,
        },
        url: 'http://127.0.0.1:8765/login',
        reuseExistingServer: false,
        timeout: 30_000,
    },
});
