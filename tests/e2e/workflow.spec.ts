import { expect, test } from '@playwright/test';

test('owner can inspect a real LPJ workflow', async ({ page }) => {
    await page.goto('/login');
    await page.getByLabel('Email organisasi').fill('pic@perkemi.test');
    await page.getByLabel('Kata sandi', { exact: true }).fill('password');
    await page.getByRole('button', { name: 'Masuk', exact: true }).click();
    await expect(page).toHaveURL(/\/dashboard$/);
    await expect(page.getByRole('heading', { level: 1 })).toContainText('Akuntabilitas kegiatan');

    if ((page.viewportSize()?.width ?? 0) < 1024) {
        await page.getByRole('button', { name: 'Buka menu' }).click();
    }
    await page.getByRole('link', { name: 'e-LPJ', exact: true }).click();
    await expect(page.getByRole('heading', { level: 1, name: 'e-LPJ' })).toBeVisible();

    await page.getByRole('link', { name: 'Buka LPJ KGT-2026-035' }).click();
    await expect(page.getByRole('heading', { level: 1 })).toContainText('Kejuaraan Antar Dojo');
    await expect(page.getByRole('heading', { name: 'LPJ Composer' })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'A. Identitas' })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'H. Audit Pack' })).toBeVisible();
    await page.getByRole('button', { name: 'Preview draft' }).click();
    await expect(page.getByRole('heading', { name: 'Preview Laporan Pertanggungjawaban' })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Verifikasi digital' })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'System check' })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Temuan & tindakan korektif' })).toBeVisible();
    await page.getByText('Pemeriksaan pendukung AI', { exact: true }).click();
    await expect(page.getByText('Hasil AI bersifat advisory, dapat keliru, dan tidak menggantikan verifikator.')).toBeVisible();
});

test('owner can filter the LPJ register and export an authorized draft', async ({ page }) => {
    await page.goto('/login');
    await page.getByLabel('Email organisasi').fill('pic@perkemi.test');
    await page.getByLabel('Kata sandi', { exact: true }).fill('password');
    await page.getByRole('button', { name: 'Masuk', exact: true }).click();
    await expect(page).toHaveURL(/\/dashboard$/);
    await page.goto('/lpjs');

    await expect(page.getByRole('heading', { name: 'Ringkasan SOBE Jawa Barat' })).toBeVisible();
    await page.getByLabel('Cari LPJ').fill('KGT-2026-041');
    await page.getByLabel('Status').selectOption('DRAFT');
    await page.getByRole('button', { name: 'Terapkan filter' }).click();

    await expect.poll(() => new URL(page.url()).searchParams.get('q')).toBe('KGT-2026-041');
    await expect.poll(() => new URL(page.url()).searchParams.get('status')).toBe('DRAFT');
    await expect(page.getByText('1 filter aktif')).not.toBeVisible();
    await expect(page.getByText('2 filter aktif')).toBeVisible();
    await expect(page.getByRole('link', { name: 'Buka LPJ KGT-2026-041' })).toBeVisible();
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth)).toBe(true);

    await page.getByRole('link', { name: 'Buka LPJ KGT-2026-041' }).click();
    const downloadPromise = page.waitForEvent('download');
    await page.getByRole('link', { name: 'Ekspor draf' }).click();
    const download = await downloadPromise;
    expect(download.suggestedFilename()).toBe('draft-lpj-kgt-2026-041-r0.html');
});

test('finance verifier sees only the active mandatory gate', async ({ page }) => {
    await page.goto('/login');
    await page.getByLabel('Email organisasi').fill('verifier.finance@perkemi.test');
    await page.getByLabel('Kata sandi', { exact: true }).fill('password');
    await page.getByRole('button', { name: 'Masuk', exact: true }).click();
    await expect(page).toHaveURL(/\/dashboard$/);
    await page.goto('/lpjs');
    await page.getByRole('link', { name: 'Buka LPJ KGT-2026-038' }).click();

    await expect(page.getByRole('heading', { name: 'Verifikasi digital' })).toBeVisible();
    await expect(page.getByText('Gate berikutnya:')).toContainText('Keuangan');
    await page.getByText('Verifikasi Keuangan', { exact: true }).click();
    await expect(page.getByLabel('Keputusan', { exact: true })).toHaveValue('VERIFIED');
    await expect(page.getByRole('group', { name: 'Checklist domain' })).toBeVisible();
    await expect(page.getByRole('group', { name: 'Evidence yang diperiksa' })).toBeVisible();
});

test('global search finds scoped records and supports keyboard navigation', async ({ page }) => {
    await page.goto('/login');
    await page.getByLabel('Email organisasi').fill('auditor@perkemi.test');
    await page.getByLabel('Kata sandi', { exact: true }).fill('password');
    await page.getByRole('button', { name: 'Masuk', exact: true }).click();
    await expect(page).toHaveURL(/\/dashboard$/);
    await page.keyboard.press(process.platform === 'darwin' ? 'Meta+k' : 'Control+k');

    const dialog = page.getByRole('dialog', { name: 'Pencarian global' });
    await expect(dialog).toBeVisible();
    const search = dialog.getByRole('combobox', { name: 'Cari seluruh ruang kerja' });
    await expect(search).toBeFocused();
    await search.fill('KGT-2026-035');
    await expect(dialog.getByRole('option').filter({ hasText: 'KGT-2026-035' }).first()).toBeVisible();
    await page.keyboard.press('Enter');
    await expect(page).toHaveURL(/\/activities\/[0-9a-f-]+$/);
    await expect(page.getByRole('heading', { level: 1 })).toContainText('Kejuaraan Antar Dojo');

    await page.keyboard.press(process.platform === 'darwin' ? 'Meta+k' : 'Control+k');
    await expect(dialog).toBeVisible();
    await page.keyboard.press('Escape');
    await expect(dialog).toBeHidden();
});

test('owner can follow planning, RAB, finance, and action center workspaces', async ({ page }) => {
    await page.goto('/login');
    await page.getByLabel('Email organisasi').fill('pic@perkemi.test');
    await page.getByLabel('Kata sandi', { exact: true }).fill('password');
    await page.getByRole('button', { name: 'Masuk', exact: true }).click();
    await expect(page).toHaveURL(/\/dashboard$/);

    for (const [path, heading] of [
        ['/planning', 'Planning kegiatan'],
        ['/budgets', 'Rencana anggaran biaya'],
        ['/finance', 'Keuangan dan rekonsiliasi'],
        ['/action-center', 'Action Center'],
    ] as const) {
        await page.goto(path);
        await expect(page.getByRole('heading', { level: 1, name: heading })).toBeVisible();
    }

    await page.goto('/planning');
    await page.getByText('Buat dan ajukan planning', { exact: true }).click();
    await expect(page.getByRole('group', { name: 'Indikator kinerja' })).toBeVisible();
    await expect(page.getByRole('group', { name: 'Jadwal pelaksanaan' })).toBeVisible();
    await expect(page.getByRole('group', { name: 'Sumber daya' })).toBeVisible();
    await expect(page.getByRole('group', { name: 'Risiko & mitigasi' })).toBeVisible();
});

test('action center exposes scoped workflow tasks with SLA and real source actions', async ({ page }) => {
    await page.goto('/login');
    await page.getByLabel('Email organisasi').fill('pic@perkemi.test');
    await page.getByLabel('Kata sandi', { exact: true }).fill('password');
    await page.getByRole('button', { name: 'Masuk', exact: true }).click();
    await expect(page).toHaveURL(/\/dashboard$/);
    await page.goto('/action-center');

    await expect(page.getByRole('heading', { level: 1, name: 'Action Center' })).toBeVisible();
    await expect(page.getByRole('navigation', { name: 'Filter status cepat' })).toBeVisible();
    await page.getByLabel('Cari tugas atau Activity-ID').fill('pelatihan administrasi');
    await page.getByRole('button', { name: 'Terapkan' }).click();

    await expect.poll(() => new URL(page.url()).searchParams.get('q')).toBe('pelatihan administrasi');
    await expect(page.locator('h3:visible', { hasText: /^Mulai pelaksanaan pelatihan administrasi$/ })).toBeVisible();
    await expect(page.locator('span:visible', { hasText: /^Dalam SLA$/ })).toBeVisible();
    await expect(page.getByRole('link', { name: 'Buka kegiatan' })).toBeVisible();

    if ((page.viewportSize()?.width ?? 0) >= 1024) {
        await page.getByText('Syarat selesai & eskalasi', { exact: true }).click();
    }
    await expect(page.locator('p:visible', { hasText: /Kegiatan berstatus dalam pelaksanaan\.$/ })).toBeVisible();
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth)).toBe(true);
});

test('administrator can manage governed access and inspect operational health', async ({ page }, testInfo) => {
    await page.goto('/login');
    await page.getByLabel('Email organisasi').fill('admin@perkemi.test');
    await page.getByLabel('Kata sandi', { exact: true }).fill('password');
    await page.getByRole('button', { name: 'Masuk', exact: true }).click();
    await expect(page).toHaveURL(/\/dashboard$/);

    await page.goto('/administration');
    await expect(page.getByRole('heading', { level: 1, name: 'Administrasi' })).toBeVisible();
    await expect(page.getByText('Pengguna aktif', { exact: true })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Kontrak kebijakan' })).toBeVisible();

    await page.goto('/administration/users');
    await expect(page.getByRole('heading', { level: 1, name: 'Pengguna' })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Register pengguna' })).toBeVisible();
    await page.locator('summary').filter({ hasText: 'Undang pengguna' }).click();
    await page.getByLabel('Nama lengkap').fill(`Pengguna Uji ${testInfo.project.name}`);
    await page.getByLabel('Email organisasi').fill(`pengguna.${testInfo.project.name}@perkemi.test`);
    await page.getByLabel('Role awal').selectOption('Owner/PIC');
    await page.getByRole('button', { name: 'Buat & kirim undangan' }).click();
    await expect(page.getByRole('heading', { name: `Pengguna Uji ${testInfo.project.name}` })).toBeVisible();

    await page.goto('/administration/access');
    await expect(page.getByRole('heading', { level: 1, name: 'Role & Permission' })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Perubahan matriks tersedia' })).toBeVisible();
    const ownerRole = page.locator('details').filter({ has: page.getByText('Owner/PIC', { exact: true }) });
    await ownerRole.locator('summary').click();
    await expect(ownerRole.getByRole('group', { name: 'Mandat' })).toBeVisible();
    await expect(ownerRole.getByText('admin.manage-roles', { exact: true })).toBeVisible();

    await page.goto('/administration/units');
    await expect(page.getByRole('heading', { level: 1, name: 'Unit organisasi' })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Struktur unit dalam kewenangan' })).toBeVisible();

    const testSuffix = testInfo.project.name.toUpperCase();
    const taxonomyName = `Lokakarya E2E ${testInfo.project.name}`;
    await page.goto('/administration/taxonomies');
    await expect(page.getByRole('heading', { level: 1, name: 'Taksonomi kegiatan' })).toBeVisible();
    await page.getByText('Tambah taxonomy kegiatan', { exact: true }).click();
    await page.getByLabel('Kode taxonomy').fill(`E2E-${testSuffix}`);
    await page.getByLabel('Nama yang ditampilkan').fill(taxonomyName);
    await page.getByLabel('Keterangan').fill('Taxonomy verifikasi browser otomatis.');
    await page.getByLabel('Faktor pembanding').fill('tema\ndurasi\njumlah peserta');
    await page.getByRole('button', { name: 'Tambah taxonomy' }).click();
    await expect(page.getByRole('heading', { name: taxonomyName })).toBeVisible();

    const kpiName = `Kehadiran E2E ${testInfo.project.name}`;
    await page.goto('/administration/kpis');
    await expect(page.getByRole('heading', { level: 1, name: 'Definisi KPI' })).toBeVisible();
    await page.locator('summary').filter({ hasText: 'Tambah definisi KPI' }).click();
    await page.getByLabel('Kode KPI').fill(`KPI-E2E-${testSuffix}`);
    await page.getByLabel('Nama indikator').fill(kpiName);
    await page.getByLabel('Cakupan kegiatan').selectOption({ label: taxonomyName });
    await page.getByLabel('Definisi operasional').fill('Persentase kehadiran dibanding peserta terdaftar.');
    await page.getByRole('button', { name: 'Tambah definisi KPI' }).click();
    await expect(page.getByRole('heading', { name: kpiName })).toBeVisible();

    await page.goto('/planning');
    await page.getByText('Buat dan ajukan planning', { exact: true }).click();
    await page.getByLabel('Jenis kegiatan').selectOption({ label: `${taxonomyName} (E2E-${testSuffix})` });
    await expect(page.getByLabel('Gunakan definisi KPI').getByRole('option', { name: new RegExp(kpiName) })).toBeAttached();

    await page.goto('/administration/governance');
    await expect(page.getByRole('heading', { level: 1, name: 'Kebijakan dan pengaturan' })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Checklist verifikasi' })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Aturan workflow' })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Template LPJ' })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Kebijakan retensi' })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'AI & penggunaan' })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Pengaturan sistem' })).toBeVisible();
    await expect(page.getByText('API key dan project ID tidak pernah ditampilkan di halaman ini.')).toBeVisible();
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth)).toBe(true);

    await page.goto('/administration');
    await page.getByRole('link', { name: /^Backup Health/ }).first().click();
    await expect(page).toHaveURL(/\/administration\/backup-health$/);
    await expect(page.getByRole('heading', { level: 1, name: 'Backup Health' })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Kesiapan pemulihan' })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Restore drill staging' })).toBeVisible();

    await page.goto('/about');
    await expect(page.getByRole('heading', { level: 1, name: 'Informasi sistem' })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Status layanan' })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Ringkasan backup khusus admin' })).toBeVisible();
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth)).toBe(true);

    if ((page.viewportSize()?.width ?? 0) < 1024) {
        await page.setViewportSize({ width: 812, height: 375 });
        await page.goto('/administration/users');
        await expect(page.getByRole('heading', { level: 1, name: 'Pengguna' })).toBeVisible();
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth)).toBe(true);
    }
});

test('owner can compare verified activities and inspect next-activity scenarios', async ({ page }, testInfo) => {
    await page.goto('/login');
    await page.getByLabel('Email organisasi').fill('pic@perkemi.test');
    await page.getByLabel('Kata sandi', { exact: true }).fill('password');
    await page.getByRole('button', { name: 'Masuk', exact: true }).click();
    await expect(page).toHaveURL(/\/dashboard$/);
    await page.goto('/activity-intelligence');

    await expect(page.getByRole('heading', { level: 1, name: 'Analisis Kegiatan Sama & Sejenis' })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Cohort layak dianalisis' })).toBeVisible();
    await expect(page.getByText('3 LPJ PB_VERIFIED/ARCHIVED', { exact: true })).toBeVisible();
    await expect(page.getByRole('heading', { name: /Comparative matrix/ })).toBeVisible();
    await expect(page.getByRole('link', { name: 'KGT-2026-029', exact: true })).toBeVisible();

    await page.goto('/next-activity?type=UJIAN&period=all');
    await expect(page.getByRole('heading', { level: 1, name: 'Analisis Kegiatan Berikutnya' })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Pilihan skenario berbasis baseline terverifikasi' })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Skenario A — Efisiensi' })).toBeVisible();
    await expect(page.getByText('AI tidak mengesahkan LPJ atau planning', { exact: true })).toBeVisible();
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth)).toBe(true);
    await page.getByRole('button', { name: 'Proses AI & review manusia' }).click();
    await expect(page).toHaveURL(/\/intelligence\?type=NEXT_ACTIVITY/);
    const cohortReview = page.getByRole('article').filter({ hasText: 'NEXT ACTIVITY' }).first();
    await expect(cohortReview).toBeVisible();
    await expect(cohortReview.getByText('Comparable Cohort', { exact: true })).toBeVisible();
    await expect(cohortReview.getByText('REVIEW REQUIRED', { exact: true })).toBeVisible({ timeout: 20_000 });
    await expect(cohortReview.getByText('PENDING', { exact: true }).first()).toBeVisible();

    if (testInfo.project.name === 'mobile') {
        await page.setViewportSize({ width: 812, height: 375 });
        await page.goto('/activity-intelligence?type=UJIAN&period=all');
        await expect(page.getByRole('heading', { level: 1, name: 'Analisis Kegiatan Sama & Sejenis' })).toBeVisible();
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth)).toBe(true);
    }
});

test('finance PIC sees operational integration signals instead of static connected labels', async ({ page }) => {
    await page.goto('/login');
    await page.getByLabel('Email organisasi').fill('finance@perkemi.test');
    await page.getByLabel('Kata sandi', { exact: true }).fill('password');
    await page.getByRole('button', { name: 'Masuk', exact: true }).click();
    await expect(page).toHaveURL(/\/dashboard$/);
    await page.goto('/integrations');

    await expect(page.getByRole('heading', { level: 1, name: 'Integration Hub' })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Kesehatan provider' })).toBeVisible();
    const financeProvider = page.getByRole('article').filter({ has: page.getByRole('heading', { name: 'e-Finance / Bank' }) });
    await expect(financeProvider.getByText('OPERATIONAL', { exact: true })).toBeVisible();
    await expect(financeProvider.getByText('finance-transaction-v1', { exact: true })).toBeVisible();
    await expect(page.getByText('Connected', { exact: true })).toHaveCount(0);
    await expect(page.getByRole('heading', { name: 'Riwayat run' })).toBeVisible();
});

test('substantive verifier can inspect grounded intelligence before human review', async ({ page }) => {
    await page.goto('/login');
    await page.getByLabel('Email organisasi').fill('verifier.substantive@perkemi.test');
    await page.getByLabel('Kata sandi', { exact: true }).fill('password');
    await page.getByRole('button', { name: 'Masuk', exact: true }).click();
    await expect(page).toHaveURL(/\/dashboard$/);
    await page.goto('/intelligence');

    await expect(page.getByRole('heading', { level: 1, name: 'LPJ Intelligence Center' })).toBeVisible();
    await expect(page.getByText('Fallback rule engine aktif')).toBeVisible();
    await page.getByLabel('Jenis analisis').selectOption('RISK_REVIEW');
    await page.getByRole('button', { name: 'Terapkan filter' }).click();
    await expect(page).toHaveURL(/type=RISK_REVIEW/);

    const review = page.getByRole('article').filter({ hasText: 'KGT-2026-035' });
    await expect(review).toBeVisible();
    await expect(review.getByText('Rule-based', { exact: false })).toBeVisible();
    await expect(review.getByRole('heading', { name: 'Trust & provenance' })).toBeVisible();
    await expect(review.getByText('Batasan', { exact: true })).toBeVisible();
    await expect(review.getByLabel('Keputusan')).toBeVisible();
});

test('owner receives a realtime completion update for a queued AI review', async ({ page }) => {
    await page.goto('/login');
    await page.getByLabel('Email organisasi').fill('pic@perkemi.test');
    await page.getByLabel('Kata sandi', { exact: true }).fill('password');
    await page.getByRole('button', { name: 'Masuk', exact: true }).click();
    await expect(page).toHaveURL(/\/dashboard$/);
    await page.goto('/intelligence');

    await page.getByText('Jalankan pemeriksaan pendukung', { exact: true }).click();
    await page.getByLabel('Jenis pemeriksaan').selectOption('NARRATIVE');
    await page.getByRole('button', { name: 'Jalankan', exact: true }).click();

    await expect(page.getByText('Pemeriksaan pendukung masuk antrean.', { exact: false })).toBeVisible();
    await expect(page.getByText('Pemeriksaan NARRATIVE siap direview manusia.', { exact: true })).toBeVisible({ timeout: 20_000 });

    const queuedReview = page.getByRole('article').filter({ hasText: 'NARRATIVE' }).first();
    await expect(queuedReview.getByText('REVIEW REQUIRED', { exact: true })).toBeVisible();
    await expect(queuedReview.getByText('Rule-based', { exact: false })).toBeVisible();
});

test('owner can turn an accepted recommendation into a traceable planning draft', async ({ page }) => {
    await page.goto('/login');
    await page.getByLabel('Email organisasi').fill('pic@perkemi.test');
    await page.getByLabel('Kata sandi', { exact: true }).fill('password');
    await page.getByRole('button', { name: 'Masuk', exact: true }).click();
    await expect(page).toHaveURL(/\/dashboard$/);
    await page.goto('/intelligence?type=PRECHECK');

    const review = page.getByRole('article').filter({ hasText: 'KGT-2026-029' });
    await expect(review).toBeVisible();
    await expect(review.getByText('ACCEPTED', { exact: true })).toBeVisible();

    const createDraft = review.getByRole('button', { name: 'Buat draft planning' });
    if (await createDraft.count()) {
        await createDraft.click();
    } else {
        await review.getByRole('link', { name: 'Buka draft' }).click();
    }

    await expect(page).toHaveURL(/\/planning\?focus=/);
    await expect(page.getByRole('heading', { level: 1, name: 'Planning kegiatan' })).toBeVisible();
    const draft = page.getByRole('article').filter({ hasText: 'Sumber intelligence direview' });
    await expect(draft).toBeVisible();
    await expect(draft.getByText('DRAFT', { exact: true })).toBeVisible();
    await expect(draft.getByText('Lengkapi draft sebelum diajukan')).toBeVisible();
    await expect(draft.getByText('Lineage:', { exact: false })).toBeVisible();
});

test('approver can inspect a pending reconciliation exception decision', async ({ page }) => {
    await page.goto('/login');
    await page.getByLabel('Email organisasi').fill('approver@perkemi.test');
    await page.getByLabel('Kata sandi', { exact: true }).fill('password');
    await page.getByRole('button', { name: 'Masuk', exact: true }).click();
    await expect(page).toHaveURL(/\/dashboard$/);
    await page.goto('/finance');

    await expect(page.getByText('PENDING APPROVAL', { exact: true })).toBeVisible();
    await expect(page.getByText('Permintaan pengecualian', { exact: true })).toBeVisible();
    await expect(page.getByLabel('Keputusan', { exact: true })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Catat keputusan' })).toBeVisible();
});

test('PB verifier can inspect the immutable approval decision pack', async ({ page }) => {
    await page.goto('/login');
    await page.getByLabel('Email organisasi').fill('pb@perkemi.test');
    await page.getByLabel('Kata sandi', { exact: true }).fill('password');
    await page.getByRole('button', { name: 'Masuk', exact: true }).click();
    await expect(page).toHaveURL(/\/dashboard$/);
    await page.goto('/lpjs');
    await page.getByRole('link', { name: 'Buka LPJ KGT-2026-029' }).click();

    await expect(page.getByRole('heading', { name: /KGT-2026-029/ })).toBeVisible();
    await expect(page.getByText('APPROVAL DECISION PACK', { exact: false })).toBeVisible();
    await expect(page.getByText('lpj-approval-authority-v1', { exact: true })).toBeVisible();
    await expect(page.getByText('Semua verifikasi wajib', { exact: true })).toBeVisible();
    await expect(page.getByText('DIRECT:PB', { exact: false })).toBeVisible();
    await expect(page.getByText('Arsip final tersedia', { exact: true })).toBeVisible();
});

test('published Verified Pack opens a public integrity record', async ({ page }) => {
    await page.goto('/login');
    await page.getByLabel('Email organisasi').fill('pb@perkemi.test');
    await page.getByLabel('Kata sandi', { exact: true }).fill('password');
    await page.getByRole('button', { name: 'Masuk', exact: true }).click();
    await expect(page).toHaveURL(/\/dashboard$/);
    await page.goto('/lpjs');
    await page.getByRole('link', { name: 'Buka LPJ KGT-2026-029' }).click();

    await expect(page.getByRole('heading', { name: 'Verified Pack diterbitkan' })).toBeVisible();
    const verificationUrl = await page.getByRole('link', { name: /Validasi publik/ }).getAttribute('href');
    expect(verificationUrl).toBeTruthy();
    await page.goto(verificationUrl!);

    await expect(page.getByRole('heading', { level: 1, name: 'Dokumen terverifikasi' })).toBeVisible();
    await expect(page.getByText('KGT-2026-029 · Ujian Kenaikan Tingkat Regional')).toBeVisible();
    await expect(page.getByText('Checksum paket cocok')).toBeVisible();
    await expect(page.getByText('Lineage ID konsisten')).toBeVisible();
    await expect(page.getByText('PUBLIC VERIFICATION', { exact: true })).toBeVisible();
});

test('auditor can inspect the organization hierarchy', async ({ page }) => {
    await page.goto('/login');
    await page.getByLabel('Email organisasi').fill('auditor@perkemi.test');
    await page.getByLabel('Kata sandi', { exact: true }).fill('password');
    await page.getByRole('button', { name: 'Masuk', exact: true }).click();
    await expect(page).toHaveURL(/\/dashboard$/);
    await page.goto('/organization/hierarchy');

    await expect(page.getByRole('heading', { level: 1, name: 'Hierarki organisasi' })).toBeVisible();
    await expect(page.locator('#main-content').getByText('PB PERKEMI', { exact: true })).toBeVisible();
});

test('auditor can investigate and export a scoped audit trail', async ({ page }) => {
    await page.goto('/login');
    await page.getByLabel('Email organisasi').fill('auditor@perkemi.test');
    await page.getByLabel('Kata sandi', { exact: true }).fill('password');
    await page.getByRole('button', { name: 'Masuk', exact: true }).click();
    await expect(page).toHaveURL(/\/dashboard$/);
    await page.goto('/audit');

    await expect(page.getByRole('heading', { level: 1, name: 'Audit & Compliance' })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Timeline kepatuhan' })).toBeVisible();
    await expect(page.getByText('Tidak dapat diedit atau dihapus')).toBeVisible();

    await page.getByLabel('Domain').selectOption('lpj');
    await page.getByLabel('Jenis event').selectOption('lpj.submitted');
    await page.getByRole('button', { name: 'Terapkan filter' }).click();

    await expect(page).toHaveURL(/domain=lpj/);
    await expect(page).toHaveURL(/event=lpj\.submitted/);
    await expect(page.getByText('lpj.submitted', { exact: true }).first()).toBeVisible();
    await expect(page.getByText('Event ·', { exact: false }).first()).toBeVisible();
    await expect(page.getByText('Correlation ·', { exact: false }).first()).toBeVisible();
    await expect(page.getByRole('link', { name: 'Ekspor CSV' })).toHaveAttribute('href', /audit\/export\?.*domain=lpj.*event=lpj\.submitted/);
    await page.getByText('Lihat metadata tersanitasi').first().click();
    await expect(page.locator('pre').first()).toBeVisible();
});

test('PB verifier can drill down and filter the national LPJ portfolio', async ({ page }) => {
    await page.goto('/login');
    await page.getByLabel('Email organisasi').fill('pb@perkemi.test');
    await page.getByLabel('Kata sandi', { exact: true }).fill('password');
    await page.getByRole('button', { name: 'Masuk', exact: true }).click();
    await expect(page).toHaveURL(/\/dashboard$/);
    await page.goto('/portfolio');

    await expect(page.getByRole('heading', { level: 1, name: 'Portfolio LPJ Nasional' })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Turunan organisasi' })).toBeVisible();
    await page.getByRole('link').filter({ hasText: 'PB PERKEMI' }).click();
    await expect(page.getByRole('heading', { level: 1, name: 'Portfolio LPJ · PB PERKEMI' })).toBeVisible();
    await page.getByRole('link').filter({ hasText: 'SOBE Jawa Barat' }).click();
    await expect(page.getByRole('heading', { level: 1, name: 'Portfolio LPJ · SOBE Jawa Barat' })).toBeVisible();

    await page.getByLabel('Cari LPJ atau kegiatan').fill('KGT-2026-029');
    await page.getByLabel('Status LPJ').selectOption('ARCHIVED');
    await page.getByRole('button', { name: 'Terapkan' }).click();
    await expect(page.getByRole('link', { name: 'KGT-2026-029', exact: true })).toBeVisible();
    await expect(page.getByText('KGT-2026-035', { exact: true })).toHaveCount(0);
});

test('owner can inspect the activity execution workspace', async ({ page }) => {
    await page.goto('/login');
    await page.getByLabel('Email organisasi').fill('pic@perkemi.test');
    await page.getByLabel('Kata sandi', { exact: true }).fill('password');
    await page.getByRole('button', { name: 'Masuk', exact: true }).click();
    await expect(page).toHaveURL(/\/dashboard$/);
    await page.goto('/activities');

    await page.getByRole('link', { name: 'Buka KGT-2026-041' }).click();
    await expect(page.getByRole('heading', { name: 'Kontrol pelaksanaan' })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Gate penyelesaian' })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Agenda pelaksanaan' })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Buku lapangan' })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Evaluasi, output & outcome' })).toBeVisible();
    const evaluationRecord = page.locator('section[aria-labelledby="activity-evaluation-title"] article').filter({ hasText: 'Evaluasi v1' });
    await expect(evaluationRecord.getByText('CAPAIAN TERTIMBANG', { exact: true })).toBeVisible();
    await expect(evaluationRecord.getByText('HIGHER IS BETTER', { exact: false })).toBeVisible();
    await expect(evaluationRecord.getByText(/(kpi-achievement-v1|legacy-manual-v0)/)).toBeVisible();
    await expect(page.getByText(/Kebijakan 2026-10-activity-v1/)).toBeVisible();
});

test('owner can inspect evidence chain of custody', async ({ page }) => {
    await page.goto('/login');
    await page.getByLabel('Email organisasi').fill('pic@perkemi.test');
    await page.getByLabel('Kata sandi', { exact: true }).fill('password');
    await page.getByRole('button', { name: 'Masuk', exact: true }).click();
    await expect(page).toHaveURL(/\/dashboard$/);
    await page.goto('/workspace/evidence');

    await expect(page.getByRole('heading', { level: 1, name: 'Evidence Center' })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Alur bukti yang dapat diaudit' })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Register evidence' })).toBeVisible();
    await page.getByLabel('Status lifecycle').selectOption('LINKED');
    await expect(page.getByRole('status')).toContainText('bukti ditampilkan');
});

test('application shell communicates offline and restored connection states', async ({ context, page }) => {
    await page.goto('/login');
    await page.getByLabel('Email organisasi').fill('pic@perkemi.test');
    await page.getByLabel('Kata sandi', { exact: true }).fill('password');
    await page.getByRole('button', { name: 'Masuk', exact: true }).click();
    await expect(page).toHaveURL(/\/dashboard$/);

    await context.setOffline(true);
    const offlineStatus = page.getByRole('status').filter({ hasText: 'Koneksi terputus' });
    await expect(offlineStatus).toBeVisible();
    await expect(offlineStatus).toContainText('perubahan baru belum dapat dikirim');
    await expect(offlineStatus.getByRole('button', { name: 'Muat ulang data' })).toBeVisible();

    await context.setOffline(false);
    const restoredStatus = page.getByRole('status').filter({ hasText: 'Koneksi pulih' });
    await expect(restoredStatus).toBeVisible();
    await expect(restoredStatus).toContainText('mengambil data terbaru');
});

test('permission denial provides recovery actions and a traceable request id', async ({ page }) => {
    await page.goto('/login');
    await page.getByLabel('Email organisasi').fill('pic@perkemi.test');
    await page.getByLabel('Kata sandi', { exact: true }).fill('password');
    await page.getByRole('button', { name: 'Masuk', exact: true }).click();
    await expect(page).toHaveURL(/\/dashboard$/);

    const response = await page.goto('/administration');

    expect(response?.status()).toBe(403);
    await expect(page.getByRole('heading', { level: 1, name: 'Akses dibatasi' })).toBeVisible();
    await expect(page.getByText('Request ID', { exact: true })).toBeVisible();
    await expect(page.getByRole('link', { name: 'Kembali ke dashboard' })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Halaman sebelumnya' })).toBeVisible();
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth)).toBe(true);
});
