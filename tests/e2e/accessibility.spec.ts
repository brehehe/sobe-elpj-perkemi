import AxeBuilder from '@axe-core/playwright';
import { expect, test } from '@playwright/test';

const wcagTags = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'];

test('login has no automated WCAG A/AA violations', async ({ page }) => {
    await page.goto('/login');
    const results = await new AxeBuilder({ page }).withTags(wcagTags).analyze();

    expect(results.violations).toEqual([]);
});

test('authenticated dashboard has no automated WCAG A/AA violations', async ({ page }) => {
    await page.goto('/login');
    await page.getByLabel('Email organisasi').fill('finance@perkemi.test');
    await page.getByLabel('Kata sandi', { exact: true }).fill('password');
    await page.getByRole('button', { name: 'Masuk', exact: true }).click();
    await expect(page).toHaveURL(/\/dashboard$/);

    const results = await new AxeBuilder({ page }).withTags(wcagTags).analyze();
    expect(results.violations).toEqual([]);
});

test('global search dialog has no automated WCAG A/AA violations', async ({ page }) => {
    await page.goto('/login');
    await page.getByLabel('Email organisasi').fill('auditor@perkemi.test');
    await page.getByLabel('Kata sandi', { exact: true }).fill('password');
    await page.getByRole('button', { name: 'Masuk', exact: true }).click();
    await expect(page).toHaveURL(/\/dashboard$/);
    await page.keyboard.press(process.platform === 'darwin' ? 'Meta+k' : 'Control+k');
    const dialog = page.getByRole('dialog', { name: 'Pencarian global' });
    await dialog.getByRole('combobox', { name: 'Cari seluruh ruang kerja' }).fill('KGT-2026-035');
    await expect(dialog.getByRole('option').filter({ hasText: 'KGT-2026-035' }).first()).toBeVisible();

    const results = await new AxeBuilder({ page })
        .include('dialog')
        .withTags(wcagTags)
        .analyze();
    expect(results.violations).toEqual([]);
});

test('new operational workspaces have no automated WCAG A/AA violations', async ({ page }) => {
    test.setTimeout(60_000);
    await page.goto('/login');
    await page.getByLabel('Email organisasi').fill('admin@perkemi.test');
    await page.getByLabel('Kata sandi', { exact: true }).fill('password');
    await page.getByRole('button', { name: 'Masuk', exact: true }).click();
    await expect(page).toHaveURL(/\/dashboard$/);

    for (const path of ['/planning', '/budgets', '/finance', '/workspace/evidence', '/action-center', '/portfolio', '/activity-intelligence', '/next-activity', '/lpjs', '/audit', '/intelligence', '/organization/hierarchy', '/integrations', '/administration', '/administration/users', '/administration/access', '/administration/units', '/administration/taxonomies', '/administration/kpis', '/administration/governance', '/administration/backup-health', '/about', '/missing-operational-page']) {
        await page.goto(path);
        await expect(page).toHaveTitle(/.+/);
        const results = await new AxeBuilder({ page }).withTags(wcagTags).analyze();
        expect(results.violations, `Accessibility violations on ${path}`).toEqual([]);
    }
});

test('activity execution workspace has no automated WCAG A/AA violations', async ({ page }) => {
    await page.goto('/login');
    await page.getByLabel('Email organisasi').fill('pic@perkemi.test');
    await page.getByLabel('Kata sandi', { exact: true }).fill('password');
    await page.getByRole('button', { name: 'Masuk', exact: true }).click();
    await expect(page).toHaveURL(/\/dashboard$/);
    await page.goto('/activities');
    await page.getByRole('link', { name: 'Buka KGT-2026-041' }).click();

    const results = await new AxeBuilder({ page }).withTags(wcagTags).analyze();
    expect(results.violations).toEqual([]);
});

test('LPJ composer has no automated WCAG A/AA violations', async ({ page }) => {
    await page.goto('/login');
    await page.getByLabel('Email organisasi').fill('pic@perkemi.test');
    await page.getByLabel('Kata sandi', { exact: true }).fill('password');
    await page.getByRole('button', { name: 'Masuk', exact: true }).click();
    await expect(page).toHaveURL(/\/dashboard$/);
    await page.goto('/lpjs');
    await page.getByRole('link', { name: 'Buka LPJ KGT-2026-035' }).click();
    await expect(page.locator('#lpj-composer')).toBeVisible();

    const results = await new AxeBuilder({ page })
        .include('#lpj-composer')
        .withTags(wcagTags)
        .analyze();
    expect(results.violations).toEqual([]);
});

test('public Verified Pack reference has no automated WCAG A/AA violations', async ({ page }) => {
    await page.goto('/login');
    await page.getByLabel('Email organisasi').fill('pb@perkemi.test');
    await page.getByLabel('Kata sandi', { exact: true }).fill('password');
    await page.getByRole('button', { name: 'Masuk', exact: true }).click();
    await expect(page).toHaveURL(/\/dashboard$/);
    await page.goto('/lpjs');
    await page.getByRole('link', { name: 'Buka LPJ KGT-2026-029' }).click();
    const verificationUrl = await page.getByRole('link', { name: /Validasi publik/ }).getAttribute('href');
    expect(verificationUrl).toBeTruthy();
    await page.goto(verificationUrl!);

    const results = await new AxeBuilder({ page }).withTags(wcagTags).analyze();
    expect(results.violations).toEqual([]);
});
