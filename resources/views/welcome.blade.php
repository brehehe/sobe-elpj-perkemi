<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Smart e-LPJ SOBE PB PERKEMI — ruang kerja pertanggungjawaban kegiatan terintegrasi.">
    <title>Smart e-LPJ · SOBE PB PERKEMI</title>
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <a href="#main-content" class="skip-link">Lewati ke konten utama</a>

    <svg class="svg-sprite" aria-hidden="true">
        <defs>
            <symbol id="i-grid" viewBox="0 0 24 24"><path d="M4 4h6v7H4V4Zm10 0h6v4h-6V4ZM4 15h6v5H4v-5Zm10-3h6v8h-6v-8Z"/></symbol>
            <symbol id="i-chart" viewBox="0 0 24 24"><path d="M4 19V9m5 10V5m6 14v-7m5 7H2"/></symbol>
            <symbol id="i-bolt" viewBox="0 0 24 24"><path d="m13 2-9 12h7l-1 8 9-12h-7l1-8Z"/></symbol>
            <symbol id="i-folder" viewBox="0 0 24 24"><path d="M3 6h6l2 2h10v11H3V6Z"/></symbol>
            <symbol id="i-file" viewBox="0 0 24 24"><path d="M6 2h8l4 4v16H6V2Zm8 0v5h5M9 12h6m-6 4h6"/></symbol>
            <symbol id="i-plan" viewBox="0 0 24 24"><path d="M5 4h14v16H5V4Zm4 0V2m6 2V2M8 9h8m-8 4h5m-5 4h7"/></symbol>
            <symbol id="i-wallet" viewBox="0 0 24 24"><path d="M3 6h16v14H3V6Zm0 3h18v7h-6a3 3 0 0 1 0-6h6M6 6V4h11v2"/></symbol>
            <symbol id="i-activity" viewBox="0 0 24 24"><path d="M4 4h16v16H4V4Zm3 10 3-3 3 3 4-5"/></symbol>
            <symbol id="i-users" viewBox="0 0 24 24"><path d="M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm7-1a3 3 0 1 0 0-6m-7 10c-4 0-6 2-6 5h12c0-3-2-5-6-5Zm7-1c3 0 5 2 5 5h-4"/></symbol>
            <symbol id="i-receipt" viewBox="0 0 24 24"><path d="M6 3h12v18l-3-2-3 2-3-2-3 2V3Zm3 5h6m-6 4h6m-6 4h4"/></symbol>
            <symbol id="i-paperclip" viewBox="0 0 24 24"><path d="m8 12 6-6a4 4 0 1 1 6 6l-8 8a6 6 0 0 1-9-9l8-8"/></symbol>
            <symbol id="i-check" viewBox="0 0 24 24"><path d="m5 12 4 4L19 6"/></symbol>
            <symbol id="i-compose" viewBox="0 0 24 24"><path d="M4 4h11v16H4V4Zm4 4h3m-3 4h3m-3 4h5m4-8 3-3 2 2-7 7-3 1 1-3 4-4Z"/></symbol>
            <symbol id="i-upload" viewBox="0 0 24 24"><path d="M12 16V3m-5 5 5-5 5 5M4 14v6h16v-6"/></symbol>
            <symbol id="i-shield" viewBox="0 0 24 24"><path d="M12 2 20 5v6c0 5-3.5 9-8 11-4.5-2-8-6-8-11V5l8-3Zm-4 9 3 3 5-6"/></symbol>
            <symbol id="i-message" viewBox="0 0 24 24"><path d="M4 4h16v13H9l-5 4V4Zm4 5h8m-8 4h5"/></symbol>
            <symbol id="i-alert" viewBox="0 0 24 24"><path d="M12 3 2 21h20L12 3Zm0 6v5m0 3v1"/></symbol>
            <symbol id="i-archive" viewBox="0 0 24 24"><path d="M3 5h18v4H3V5Zm2 4h14v12H5V9Zm4 4h6"/></symbol>
            <symbol id="i-bank" viewBox="0 0 24 24"><path d="M3 20h18M5 9h14M4 6l8-4 8 4v3H4V6Zm2 3v8m4-8v8m4-8v8m4-8v8"/></symbol>
            <symbol id="i-hierarchy" viewBox="0 0 24 24"><path d="M10 3h4v4h-4V3ZM3 17h4v4H3v-4Zm7 0h4v4h-4v-4Zm7 0h4v4h-4v-4ZM12 7v5M5 17v-5h14v5m-7-5v5"/></symbol>
            <symbol id="i-plug" viewBox="0 0 24 24"><path d="m8 12 8-8m-3 1 6 6m-9 0 3 3-5 5H4v-4l6-6m-5 10 2 2"/></symbol>
            <symbol id="i-history" viewBox="0 0 24 24"><path d="M4 12a8 8 0 1 0 2-5.3L4 9m0-5v5h5m3-2v6l4 2"/></symbol>
            <symbol id="i-search" viewBox="0 0 24 24"><circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 5 5"/></symbol>
            <symbol id="i-menu" viewBox="0 0 24 24"><path d="M4 7h16M4 12h16M4 17h16"/></symbol>
            <symbol id="i-bell" viewBox="0 0 24 24"><path d="M5 17h14l-2-3V9a5 5 0 0 0-10 0v5l-2 3Zm5 3h4"/></symbol>
            <symbol id="i-chevron" viewBox="0 0 24 24"><path d="m9 6 6 6-6 6"/></symbol>
            <symbol id="i-plus" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></symbol>
            <symbol id="i-x" viewBox="0 0 24 24"><path d="m6 6 12 12M18 6 6 18"/></symbol>
            <symbol id="i-download" viewBox="0 0 24 24"><path d="M12 3v13m-5-5 5 5 5-5M4 20h16"/></symbol>
            <symbol id="i-arrow" viewBox="0 0 24 24"><path d="M5 12h14m-5-5 5 5-5 5"/></symbol>
            <symbol id="i-lock" viewBox="0 0 24 24"><path d="M6 10h12v11H6V10Zm3 0V7a3 3 0 0 1 6 0v3"/></symbol>
        </defs>
    </svg>

    @php
        $navigation = [
            'Command Center' => [
                ['dashboard', 'Dashboard Nasional', 'grid'], ['activity-intel', 'Analisis Jenis Kegiatan', 'chart'],
                ['recommendation', 'Rekomendasi per Kegiatan', 'chart'], ['action-center', 'Smart Action Center', 'bolt', 8],
                ['portfolio', 'Portfolio LPJ', 'folder'],
            ],
            'Input Kegiatan' => [
                ['mandate', 'Mandat & Dasar Kegiatan', 'file'], ['planning', 'e-Planning', 'plan'],
                ['budget', 'e-Budget / RAB', 'wallet'], ['activity', 'Pelaksanaan Kegiatan', 'activity'],
                ['participants', 'Peserta & SDM', 'users'], ['transactions', 'Transaksi & Realisasi', 'receipt'],
                ['evidence', 'Evidence Center', 'paperclip'], ['evaluation', 'Evaluasi & Outcome', 'check'],
            ],
            'Pertanggungjawaban' => [
                ['composer', 'LPJ Composer', 'compose'], ['submission', 'Submit & Snapshot', 'upload'],
                ['verification', 'Digital Verification', 'shield', 3], ['disposition', 'Disposisi Digital', 'message'],
                ['findings', 'Findings & CAPA', 'alert', 2], ['approval', 'Approval e-LPJ', 'shield'],
                ['verified', 'PB Verified', 'check'], ['archive', 'Digital Archive', 'archive'],
            ],
            'Analytics & Governance' => [
                ['finance', 'Finance & Rekonsiliasi', 'bank'], ['intelligence', 'LPJ Intelligence', 'chart'],
                ['next-activity', 'Analisis Kegiatan Berikutnya', 'chart'], ['hierarchy', 'Hierarki PB–Dojo', 'hierarchy'],
                ['integration', 'Integration Hub', 'plug'], ['audit', 'Audit & Compliance', 'history'],
            ],
        ];
    @endphp

    <div class="app-shell" data-app-shell>
        <div class="sidebar-backdrop" data-sidebar-backdrop></div>
        <aside class="sidebar" id="primary-sidebar" aria-label="Navigasi utama">
            <div class="brand">
                <div class="brand-mark" aria-hidden="true">P</div>
                <div class="sidebar-copy"><strong>Smart e-LPJ</strong><span>SOBE PB PERKEMI</span></div>
                <button class="icon-button sidebar-close" type="button" data-sidebar-close aria-label="Tutup navigasi"><svg><use href="#i-x"/></svg></button>
            </div>
            <div class="scope-card sidebar-copy"><i></i><div><b>Lingkup nasional</b><span>PB PERKEMI · SOBE</span></div></div>
            <nav class="sidebar-nav">
                @foreach($navigation as $group => $items)
                    <div class="nav-group">
                        <p class="sidebar-copy">{{ $group }}</p>
                        @foreach($items as $item)
                            <button class="nav-item {{ $item[0] === 'dashboard' ? 'is-active' : '' }}" type="button" data-module="{{ $item[0] }}" data-module-label="{{ $item[1] }}" aria-current="{{ $item[0] === 'dashboard' ? 'page' : 'false' }}" title="{{ $item[1] }}">
                                <svg aria-hidden="true"><use href="#i-{{ $item[2] }}"/></svg>
                                <span class="sidebar-copy">{{ $item[1] }}</span>
                                @isset($item[3])<em class="sidebar-copy">{{ $item[3] }}</em>@endisset
                            </button>
                        @endforeach
                    </div>
                @endforeach
            </nav>
            <div class="sidebar-footer">
                <div class="sidebar-copy"><b>Versi antarmuka 0.1</b><span>Mode demonstrasi</span></div>
                <button class="icon-button collapse-button" type="button" data-sidebar-collapse aria-label="Ciutkan navigasi" aria-expanded="true"><svg><use href="#i-chevron"/></svg></button>
            </div>
        </aside>

        <div class="app-body">
            <header class="topbar">
                <button class="icon-button menu-button" type="button" data-sidebar-open aria-label="Buka navigasi" aria-controls="primary-sidebar" aria-expanded="false"><svg><use href="#i-menu"/></svg></button>
                <div class="topbar-title"><b>Pusat Pertanggungjawaban Nasional</b><span>Tahun kerja 2026</span></div>
                <button class="search-trigger" type="button" data-search-open aria-haspopup="dialog"><svg><use href="#i-search"/></svg><span>Cari Activity-ID, LPJ, transaksi, atau modul…</span><kbd>/</kbd></button>
                <div class="topbar-actions">
                    <span class="demo-badge"><i></i>Data demonstrasi</span>
                    <div class="notification-wrap">
                        <button class="icon-button" type="button" data-notifications-toggle aria-label="Buka 3 notifikasi" aria-expanded="false"><svg><use href="#i-bell"/></svg><sup>3</sup></button>
                        <div class="notifications" data-notifications-panel hidden>
                            <div class="notifications-head"><b>Notifikasi</b><span>3 hal memerlukan perhatian</span></div>
                            <button data-open-module="verification"><i class="warning"></i><span><b>Verifikasi keuangan</b><small>LPJ-2026-0017 jatuh tempo hari ini</small></span></button>
                            <button data-open-module="findings"><i class="danger"></i><span><b>Bukti CAPA belum lengkap</b><small>FND-2026-0019 · tersisa 1 hari</small></span></button>
                            <button data-open-module="integration"><i class="info"></i><span><b>Sinkronisasi peserta selesai</b><small>146 record berhasil</small></span></button>
                        </div>
                    </div>
                    <button class="user-button" type="button" aria-label="Buka menu akun"><i>NA</i><span><b>Nadia Ardi</b><small>National Oversight</small></span><svg><use href="#i-chevron"/></svg></button>
                </div>
            </header>

            <main class="main-content" id="main-content" tabindex="-1">
                <section data-page="dashboard" aria-labelledby="dashboard-title">
                    <div class="page-heading">
                        <div>
                            <nav class="breadcrumb" aria-label="Breadcrumb"><span>Command Center</span><svg><use href="#i-chevron"/></svg><b>Dashboard Nasional</b></nav>
                            <div class="title-row"><h1 id="dashboard-title">Dashboard Nasional</h1><span class="status info"><i></i>Pembaruan 10:24 WIB</span></div>
                            <p>Pantau alur pertanggungjawaban, SLA, risiko, dan tindakan prioritas dalam satu ruang kerja.</p>
                        </div>
                        <div class="page-actions">
                            <button class="button secondary" type="button" data-export-dashboard><svg><use href="#i-download"/></svg>Unduh ringkasan</button>
                            <button class="button primary" type="button" data-registration-open><svg><use href="#i-plus"/></svg>Registrasi kegiatan</button>
                        </div>
                    </div>

                    <div class="scope-strip"><span><svg><use href="#i-hierarchy"/></svg></span><div><b>Lingkup data: PB PERKEMI dan 34 Pengprov</b><p>Angka di halaman ini merupakan data demonstrasi prototipe, bukan rekam operasional.</p></div><button type="button" data-scope-action>Ubah lingkup</button></div>

                    <div class="metrics" aria-label="Ringkasan kinerja">
                        <article class="blue"><header><span>Kegiatan aktif</span><svg><use href="#i-activity"/></svg></header><strong>128</strong><p><b>+12</b> dari periode sebelumnya</p></article>
                        <article class="green"><header><span>LPJ terverifikasi</span><svg><use href="#i-shield"/></svg></header><strong>76<small>/94</small></strong><p>81% dari LPJ yang diajukan</p></article>
                        <article class="orange"><header><span>Risiko SLA</span><svg><use href="#i-history"/></svg></header><strong>9</strong><p><b>3 jatuh tempo</b> hari ini</p></article>
                        <article class="red"><header><span>Temuan terbuka</span><svg><use href="#i-alert"/></svg></header><strong>17</strong><p>2 material · 15 nonmaterial</p></article>
                    </div>

                    <div class="dashboard-grid">
                        <section class="panel pipeline-panel" aria-labelledby="pipeline-title">
                            <header class="section-heading"><div><h2 id="pipeline-title">Pipeline pertanggungjawaban</h2><p>Satu rantai data dari mandat sampai arsip tanpa kehilangan versi dan sumber.</p></div><button class="text-link" type="button" data-open-module="portfolio">Lihat portfolio <svg><use href="#i-arrow"/></svg></button></header>
                            <div class="pipeline" role="list" aria-label="Tahapan pertanggungjawaban">
                                <button class="step complete" data-open-module="mandate" role="listitem"><i><svg><use href="#i-check"/></svg></i><b>Mandat</b><small>MND-0041</small></button><span class="line complete"></span>
                                <button class="step complete" data-open-module="planning" role="listitem"><i><svg><use href="#i-check"/></svg></i><b>Planning & RAB</b><small>Disetujui</small></button><span class="line complete"></span>
                                <button class="step complete" data-open-module="activity" role="listitem"><i><svg><use href="#i-check"/></svg></i><b>Pelaksanaan</b><small>Selesai</small></button><span class="line complete"></span>
                                <button class="step complete" data-open-module="submission" role="listitem"><i><svg><use href="#i-lock"/></svg></i><b>Snapshot</b><small>Versi 1.2</small></button><span class="line current"></span>
                                <button class="step current" data-open-module="verification" role="listitem" aria-current="step"><i>3/4</i><b>Verifikasi</b><small>Keuangan</small></button><span class="line"></span>
                                <button class="step" data-open-module="approval" role="listitem"><i>6</i><b>Approval</b><small>Menunggu gate</small></button><span class="line"></span>
                                <button class="step" data-open-module="archive" role="listitem"><i>7</i><b>Arsip</b><small>Belum tersedia</small></button>
                            </div>
                            <div class="lineage"><div><span>Activity-ID aktif</span><b>PBK-2026-DIKTAR-00017</b></div><svg><use href="#i-arrow"/></svg><div><span>LPJ-ID / versi</span><b>LPJ-2026-0017 · v1.2</b></div><svg><use href="#i-arrow"/></svg><div><span>Snapshot</span><b>SNP-2026-0188</b></div></div>
                        </section>

                        <aside class="panel attention" aria-labelledby="attention-title">
                            <header class="section-heading compact"><div><h2 id="attention-title">Perlu perhatian</h2><p>Menurut materialitas dan SLA.</p></div><strong class="count">8</strong></header>
                            <div class="attention-list">
                                <button data-open-module="verification"><i class="danger"></i><span><b>Verifikasi keuangan tertahan</b><small>LPJ-2026-0017 · hari ini 16:00</small></span><svg><use href="#i-chevron"/></svg></button>
                                <button data-open-module="findings"><i class="warning"></i><span><b>CAPA menunggu bukti efektivitas</b><small>FND-2026-0019 · tersisa 1 hari</small></span><svg><use href="#i-chevron"/></svg></button>
                                <button data-open-module="transactions"><i class="warning"></i><span><b>3 transaksi belum cocok</b><small>Exception Rp4.250.000</small></span><svg><use href="#i-chevron"/></svg></button>
                                <button data-open-module="evidence"><i class="info"></i><span><b>2 evidence perlu klasifikasi</b><small>Scan keamanan telah lulus</small></span><svg><use href="#i-chevron"/></svg></button>
                            </div>
                            <button class="button quiet full" type="button" data-open-module="action-center">Buka semua tugas</button>
                        </aside>
                    </div>

                    <div class="dashboard-grid lower">
                        <section class="panel" aria-labelledby="queue-title">
                            <header class="section-heading"><div><h2 id="queue-title">Antrean kerja prioritas</h2><p>Tugas aktual mengikuti role, unit, kewenangan, dan tenggat.</p></div><button class="filter-button" type="button" data-filter-toggle aria-pressed="false">Prioritas tinggi</button></header>
                            <div class="table-shell"><table><caption>Daftar antrean kerja prioritas</caption><thead><tr><th>Objek</th><th>Tindakan</th><th>Unit</th><th>Tenggat</th><th>Status</th><th><span class="sr-only">Aksi</span></th></tr></thead><tbody data-action-table>
                                <tr data-priority="high"><td><button class="table-link" data-open-module="verification">LPJ-2026-0017</button><small>Versi 1.2</small></td><td>Verifikasi keuangan</td><td>Pengprov Jatim</td><td class="due">Hari ini, 16:00</td><td><span class="status warning"><i></i>Dalam review</span></td><td><button class="row-action" data-open-module="verification" aria-label="Buka LPJ-2026-0017"><svg><use href="#i-chevron"/></svg></button></td></tr>
                                <tr data-priority="high"><td><button class="table-link" data-open-module="findings">FND-2026-0019</button><small>Severity medium</small></td><td>Periksa bukti CAPA</td><td>Pengkot Surabaya</td><td>Besok, 12:00</td><td><span class="status info"><i></i>Bukti dikirim</span></td><td><button class="row-action" data-open-module="findings" aria-label="Buka FND-2026-0019"><svg><use href="#i-chevron"/></svg></button></td></tr>
                                <tr data-priority="normal"><td><button class="table-link" data-open-module="planning">PLN-2026-0049</button><small>Versi 1.0</small></td><td>Review planning</td><td>PB PERKEMI</td><td>10 Okt, 09:00</td><td><span class="status neutral"><i></i>Belum dimulai</span></td><td><button class="row-action" data-open-module="planning" aria-label="Buka PLN-2026-0049"><svg><use href="#i-chevron"/></svg></button></td></tr>
                                <tr data-priority="normal"><td><button class="table-link" data-open-module="integration">INT-2026-0314</button><small>e-Finance</small></td><td>Perbaiki mapping</td><td>Pengprov Bali</td><td>11 Okt, 14:00</td><td><span class="status danger"><i></i>Gagal parsial</span></td><td><button class="row-action" data-open-module="integration" aria-label="Buka INT-2026-0314"><svg><use href="#i-chevron"/></svg></button></td></tr>
                            </tbody></table></div>
                        </section>
                        <aside class="panel insight" aria-labelledby="insight-title">
                            <span class="insight-label"><svg><use href="#i-chart"/></svg>Ringkasan analitis</span><h2 id="insight-title">Pola yang perlu ditinjau</h2>
                            <p class="insight-lead">Waktu penyelesaian LPJ turun dari 10 menjadi 7 hari, tetapi rekonsiliasi keuangan masih menjadi bottleneck utama.</p>
                            <div class="insight-facts"><div><b>9</b><span>LPJ berisiko SLA</span></div><div><b>3</b><span>pola temuan berulang</span></div></div>
                            <div class="source-note"><svg><use href="#i-lock"/></svg><p><b>Sumber:</b> 24 LPJ verified periode 2025–2026. Rekomendasi memerlukan review manusia dan tidak mengubah status LPJ.</p></div>
                            <button class="text-link" type="button" data-open-module="intelligence">Lihat analisis & keterbatasan <svg><use href="#i-arrow"/></svg></button>
                        </aside>
                    </div>

                    <div class="triple-grid">
                        <section class="panel" aria-labelledby="portfolio-health-title">
                            <header class="section-heading"><div><h2 id="portfolio-health-title">Kesehatan portfolio</h2><p>Outcome index menurut jenis kegiatan.</p></div><div class="segment"><button class="active" data-period="Triwulan">Triwulan</button><button data-period="Tahunan">Tahunan</button></div></header>
                            <div class="bar-chart" role="img" aria-label="Kejuaraan 91, Pembinaan 87, Penataran 84, Raker 78, Sosialisasi 73">
                                <div><span>Kejuaraan</span><i><b style="width:91%"></b></i><em>91</em></div><div><span>Pembinaan</span><i><b style="width:87%"></b></i><em>87</em></div><div><span>Penataran</span><i><b style="width:84%"></b></i><em>84</em></div><div><span>Raker/Rapat</span><i><b style="width:78%"></b></i><em>78</em></div><div><span>Sosialisasi</span><i><b class="warn" style="width:73%"></b></i><em>73</em></div>
                            </div><small class="chart-note">Data demonstratif. Produksi memakai formula terversi dan LPJ verified.</small>
                        </section>
                        <section class="panel" aria-labelledby="finance-title"><header class="section-heading"><div><h2 id="finance-title">Finance & rekonsiliasi</h2><p>Ledger resmi tetap menjadi sumber.</p></div><span class="status success"><i></i>96% cocok</span></header><div class="money"><div><span>Anggaran</span><b>Rp125,0 jt</b></div><div><span>Realisasi</span><b>Rp121,0 jt</b></div><div><span>Selisih</span><b>Rp4,0 jt</b></div></div><div class="progress"><i></i></div><div class="progress-meta"><span>81 transaksi cocok</span><span>3 exception</span></div><button class="button secondary full" data-open-module="finance">Buka rekonsiliasi</button></section>
                        <section class="panel" aria-labelledby="activity-feed-title"><header class="section-heading compact"><div><h2 id="activity-feed-title">Aktivitas terbaru</h2><p>Audit event pada lingkup aktif.</p></div></header><ol class="activity-feed"><li><i class="success"><svg><use href="#i-check"/></svg></i><span><b>Evidence EV-0102 terdaftar</b><small>10:21 · Activity Owner · scan lulus</small></span></li><li><i class="info"><svg><use href="#i-receipt"/></svg></i><span><b>TRX-00881 dibuka untuk review</b><small>11:04 · Finance Verifier</small></span></li><li><i class="warning"><svg><use href="#i-alert"/></svg></i><span><b>Finding FND-0019 dibuat</b><small>11:16 · Verifier Keuangan</small></span></li><li><i class="success"><svg><use href="#i-shield"/></svg></i><span><b>Bukti CAPA diterima</b><small>14:31 · System event</small></span></li></ol><button class="text-link" data-open-module="audit">Buka audit trail <svg><use href="#i-arrow"/></svg></button></section>
                    </div>
                </section>

                <section data-page="module" aria-labelledby="module-title" hidden>
                    <nav class="breadcrumb" aria-label="Breadcrumb"><span data-module-group>Modul</span><svg><use href="#i-chevron"/></svg><b data-module-breadcrumb>Detail</b></nav>
                    <div class="page-heading module-heading"><div><div class="title-row"><h1 id="module-title" data-module-title>Detail modul</h1><span class="status info" data-module-status><i></i><span>Aktif</span></span></div><p data-module-description></p></div><div class="page-actions"><button class="button secondary" data-module-secondary><svg><use href="#i-history"/></svg>Lihat riwayat</button><button class="button primary" data-module-action><svg><use href="#i-plus"/></svg><span>Tambah data</span></button></div></div>
                    <div class="module-metrics" data-module-metrics></div>
                    <div class="module-layout">
                        <div class="module-main">
                            <section class="panel"><header class="section-heading"><div><h2 data-workspace-title>Workspace aktif</h2><p data-workspace-subtitle>Data utama pada lingkup yang dipilih.</p></div><span class="demo-label">Contoh tampilan</span></header><div class="table-shell"><table><caption data-table-caption>Data modul</caption><thead><tr data-module-table-head></tr></thead><tbody data-module-table-body></tbody></table></div></section>
                            <section class="panel"><header class="section-heading compact"><div><h2>Fitur dan kebutuhan utama</h2><p>Ditafsirkan dari prototipe dan diselaraskan dengan gate pada flow.</p></div></header><div class="requirements" data-module-requirements></div></section>
                        </div>
                        <aside class="module-aside">
                            <section class="panel flow-rule"><span>Aturan alur</span><h2 data-module-flow-title>Gate aktif</h2><p data-module-flow-description></p><ol data-module-controls></ol></section>
                            <section class="panel lineage-panel"><header class="section-heading compact"><div><h2>Data lineage</h2><p>Referensi pembentuk konteks record.</p></div></header><ol><li><span>Mandate-ID</span><b>MND-2026-0041</b></li><li><span>Plan / Budget</span><b>PLN-0041 · BDG-0041</b></li><li><span>Activity-ID</span><b>PBK-2026-DIKTAR-00017</b></li><li><span>LPJ / Version</span><b>LPJ-0017 · v1.2</b></li></ol><button class="button quiet full" data-lineage-action>Tampilkan seluruh rantai</button></section>
                        </aside>
                    </div>
                </section>
            </main>
        </div>
    </div>

    <dialog class="app-dialog" data-registration-dialog aria-labelledby="registration-title">
        <form data-registration-form novalidate>
            <header><div><span>Langkah 1 dari 4 · Mandat</span><h2 id="registration-title">Registrasi kegiatan</h2><p>Lengkapi dasar kegiatan untuk membuat Mandate-ID dan workspace planning.</p></div><button class="icon-button" type="button" data-registration-close aria-label="Tutup dialog"><svg><use href="#i-x"/></svg></button></header>
            <div class="dialog-body"><div class="form-notice"><svg><use href="#i-lock"/></svg><p>Simulasi antarmuka. Data tidak dikirim atau disimpan ke server.</p></div><div class="form-grid">
                <label>Jenis dasar <b>*</b><select name="mandate_type" required><option value="">Pilih jenis dasar</option><option>Surat Keputusan</option><option>Surat Tugas</option><option>Program Kerja</option></select><small>Dokumen yang memberi kewenangan kegiatan.</small><em data-error-for="mandate_type"></em></label>
                <label>Nomor dokumen <b>*</b><input name="document_number" placeholder="041/PB/X/2026" required><em data-error-for="document_number"></em></label>
                <label>Tanggal dokumen <b>*</b><input name="document_date" type="date" required><em data-error-for="document_date"></em></label>
                <label>Unit pemilik <b>*</b><select name="owner_unit" required><option value="">Pilih unit</option><option>PB PERKEMI</option><option>Pengprov Jawa Timur</option><option>Pengkot Surabaya</option></select><em data-error-for="owner_unit"></em></label>
                <label class="full-field">Nama kegiatan <b>*</b><input name="activity_name" placeholder="Nama kegiatan sesuai mandat" required><em data-error-for="activity_name"></em></label>
                <label>Penanggung jawab <b>*</b><input name="activity_pic" placeholder="Cari nama atau NIK" required><em data-error-for="activity_pic"></em></label>
                <label>Periode kegiatan <b>*</b><input name="activity_period" type="month" required><em data-error-for="activity_period"></em></label>
                <label class="full-field">Dokumen mandat <b>*</b><input name="mandate_file" type="file" accept=".pdf,.jpg,.jpeg,.png" required><small>PDF, JPG, atau PNG · maksimum 10 MB · akan diperiksa keamanannya.</small><em data-error-for="mandate_file"></em></label>
            </div></div>
            <footer><p><i></i>Draft lokal belum tersimpan</p><div><button class="button secondary" type="button" data-registration-close>Batal</button><button class="button primary" type="submit">Validasi & registrasikan</button></div></footer>
        </form>
    </dialog>

    <dialog class="search-dialog" data-search-dialog aria-labelledby="search-title"><div><header><svg><use href="#i-search"/></svg><label class="sr-only" id="search-title" for="command-search">Pencarian global</label><input id="command-search" type="search" placeholder="Cari modul, Activity-ID, LPJ-ID…" data-search-input autocomplete="off"><button type="button" data-search-close>Esc</button></header><main data-search-results aria-live="polite"></main><footer><span><kbd>↑</kbd><kbd>↓</kbd> navigasi</span><span><kbd>Enter</kbd> buka</span><span>Data record adalah demonstrasi</span></footer></div></dialog>

    <div class="toast" role="status" aria-live="polite" data-toast hidden><i><svg><use href="#i-check"/></svg></i><div><b data-toast-title>Berhasil</b><p data-toast-message>Tindakan selesai.</p></div><button type="button" data-toast-close aria-label="Tutup notifikasi"><svg><use href="#i-x"/></svg></button></div>
</body>
</html>
