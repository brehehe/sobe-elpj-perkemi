<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Draf LPJ {{ $document['activity']['code'] }}</title>
    <style>
        :root { color-scheme: light; font-family: Arial, Helvetica, sans-serif; color: #172033; background: #f3f6fa; }
        * { box-sizing: border-box; }
        body { margin: 0; padding: 24px; font-size: 12px; line-height: 1.55; }
        main { width: min(100%, 920px); margin: 0 auto; background: #fff; border: 1px solid #dbe2ea; border-radius: 12px; overflow: hidden; }
        header { padding: 28px 32px 24px; border-bottom: 4px solid #155eef; }
        h1, h2, h3, p { margin-top: 0; }
        h1 { margin-bottom: 7px; font-size: 24px; line-height: 1.2; }
        h2 { margin-bottom: 12px; font-size: 15px; }
        h3 { margin-bottom: 6px; font-size: 12px; }
        section { padding: 22px 32px; border-bottom: 1px solid #e6ebf1; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 9px 10px; border: 1px solid #dbe2ea; text-align: left; vertical-align: top; overflow-wrap: anywhere; }
        th { width: 25%; background: #f6f8fb; font-size: 10px; letter-spacing: .04em; text-transform: uppercase; }
        .draft-mark { display: inline-block; margin-bottom: 14px; padding: 5px 9px; border: 1px solid #f0b429; border-radius: 999px; color: #8b5500; background: #fff8e6; font-weight: 700; letter-spacing: .04em; }
        .muted { color: #56647a; }
        .meta { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin-top: 20px; }
        .metric { padding: 12px; border: 1px solid #dbe2ea; border-radius: 8px; }
        .metric span { display: block; color: #66758c; font-size: 9px; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; }
        .metric strong { display: block; margin-top: 4px; font-size: 14px; }
        .narrative { margin-top: 14px; padding: 14px; border-left: 3px solid #9dbcf7; background: #f8faff; white-space: pre-wrap; }
        .module { display: grid; grid-template-columns: 1.2fr .7fr 2fr; gap: 10px; padding: 10px 0; border-top: 1px solid #e6ebf1; }
        .state { font-weight: 700; color: #176b42; }
        .state.blocked { color: #a33b26; }
        .break { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 9px; overflow-wrap: anywhere; }
        footer { padding: 18px 32px 24px; color: #56647a; font-size: 10px; }
        @media (max-width: 680px) { body { padding: 0; } main { border: 0; border-radius: 0; } header, section, footer { padding-left: 18px; padding-right: 18px; } .meta { grid-template-columns: 1fr 1fr; } .module { grid-template-columns: 1fr; } }
        @media print { @page { size: A4; margin: 14mm; } body { padding: 0; background: #fff; } main { width: 100%; border: 0; border-radius: 0; } section, table, .module, .narrative { break-inside: avoid; } }
    </style>
</head>
<body>
<main>
    <header>
        <span class="draft-mark">DRAF · BUKAN DOKUMEN FINAL</span>
        <h1>Laporan Pertanggungjawaban</h1>
        <p><strong>{{ $document['activity']['code'] }}</strong> · {{ $document['activity']['title'] }}</p>
        <p class="muted">{{ $document['unit']['code'] }} · {{ $document['unit']['name'] }}</p>
        <div class="meta">
            <div class="metric"><span>Revisi draf</span><strong>r{{ $document['lpj']['draft_revision'] }}</strong></div>
            <div class="metric"><span>Kelengkapan</span><strong>{{ $document['composer']['percentage'] }}%</strong></div>
            <div class="metric"><span>Status</span><strong>{{ $document['lpj']['status_label'] }}</strong></div>
            <div class="metric"><span>Dibuat</span><strong>{{ $document['generated_at']->timezone('Asia/Jakarta')->format('d M Y H:i') }} WIB</strong></div>
        </div>
    </header>

    <section>
        <h2>Identitas dan sumber resmi</h2>
        <table>
            <tbody>
                <tr><th>Activity-ID</th><td>{{ $document['activity']['code'] }}</td></tr>
                <tr><th>Pemilik LPJ</th><td>{{ $document['owner']['name'] }} · {{ $document['owner']['email'] }}</td></tr>
                <tr><th>Periode</th><td>{{ $document['activity']['starts_at']?->timezone('Asia/Jakarta')->format('d M Y H:i') ?? '—' }} – {{ $document['activity']['ends_at']?->timezone('Asia/Jakarta')->format('d M Y H:i') ?? '—' }}</td></tr>
                <tr><th>Lokasi</th><td>{{ $document['activity']['location'] ?: '—' }}</td></tr>
                <tr><th>Mandat</th><td>{{ $document['mandate']['number'] ?? 'Belum tersedia' }}{{ !empty($document['mandate']['title']) ? ' · '.$document['mandate']['title'] : '' }}</td></tr>
                <tr><th>Planning</th><td>{{ $document['plan']['code'] ?? 'Belum tersedia' }}{{ isset($document['plan']['current_version']) ? ' · v'.$document['plan']['current_version'] : '' }}</td></tr>
                <tr><th>RAB</th><td>{{ $document['budget']['code'] ?? 'Belum tersedia' }}{{ isset($document['budget']['current_version']) ? ' · v'.$document['budget']['current_version'] : '' }}</td></tr>
                <tr><th>Template</th><td>{{ $document['composer']['template']['label'] }} · {{ $document['composer']['template']['policy_version'] }}</td></tr>
                <tr><th>Pre-check</th><td>{{ $document['composer']['policy_version'] }}</td></tr>
            </tbody>
        </table>
    </section>

    <section>
        <h2>Ringkasan keuangan</h2>
        <div class="meta">
            <div class="metric"><span>Pagu</span><strong>Rp {{ number_format((float) $document['financial']['budget_ceiling'], 0, ',', '.') }}</strong></div>
            <div class="metric"><span>Realisasi</span><strong>Rp {{ number_format((float) $document['financial']['actual_amount'], 0, ',', '.') }}</strong></div>
            <div class="metric"><span>Selisih</span><strong>Rp {{ number_format((float) $document['financial']['variance'], 0, ',', '.') }}</strong></div>
            <div class="metric"><span>Rekonsiliasi</span><strong>{{ $document['financial']['reconciled_count'] }}/{{ $document['financial']['transaction_count'] }}</strong></div>
        </div>
    </section>

    <section>
        <h2>Narasi LPJ</h2>
        @foreach ($document['narrative_labels'] as $field => $label)
            <article class="narrative">
                <h3>{{ $label }}</h3>
                <div>{{ filled($document['narratives'][$field]) ? $document['narratives'][$field] : 'Belum dilengkapi.' }}</div>
            </article>
        @endforeach
    </section>

    <section>
        <h2>Kesiapan composer</h2>
        <p class="muted">Dokumen ini dapat diekspor sebelum lengkap. Item yang belum siap tetap ditampilkan sebagai kontrol tindak lanjut.</p>
        @foreach ($document['composer']['modules'] as $module)
            <div class="module">
                <div><strong>{{ $module['label'] }}</strong><br><span class="muted">{{ $module['description'] }}</span></div>
                <div class="state {{ $module['ready'] ? '' : 'blocked' }}">{{ $module['ready'] ? 'Siap' : 'Belum siap' }} · {{ $module['percentage'] }}%</div>
                <div><strong>Sumber:</strong> {{ $module['source_reference'] }} ({{ $module['source_count'] }})
                    @if (!empty($module['missing']))<br><strong>Kurang:</strong> {{ implode(' ', $module['missing']) }}@endif
                </div>
            </div>
        @endforeach
    </section>

    <section>
        <h2>Referensi bukti aktif</h2>
        <p class="muted">{{ $document['sources']['agenda_count'] }} agenda · {{ $document['sources']['execution_record_count'] }} catatan pelaksanaan · {{ $document['sources']['participant_count'] }} peserta · {{ $document['sources']['evaluation_count'] }} evaluasi.</p>
        @if (count($document['sources']['evidence']) > 0)
            <table>
                <thead><tr><th>Judul / tipe</th><th>Versi</th><th>Review</th><th>Checksum SHA-256</th></tr></thead>
                <tbody>
                    @foreach ($document['sources']['evidence'] as $evidence)
                        <tr><td>{{ $evidence['title'] }}<br><span class="muted">{{ $evidence['type'] }}</span></td><td>v{{ $evidence['version'] }}</td><td>{{ $evidence['review_status'] }}</td><td class="break">{{ $evidence['sha256'] }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <p>Belum ada bukti aktif.</p>
        @endif
    </section>

    <footer>
        Dokumen draf ini dibuat dari data sumber e-LPJ pada {{ $document['generated_at']->toIso8601String() }} dan dicatat pada audit trail. Dokumen ini tidak menggantikan snapshot immutable, Verified Pack, atau arsip final.
    </footer>
</main>
</body>
</html>
