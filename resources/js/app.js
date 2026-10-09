const modules = {
    'activity-intel': {
        group: 'Command Center', title: 'Analisis Jenis Kegiatan', status: '24 data sebanding', action: 'Ekspor analisis',
        description: 'Bandingkan kegiatan dalam taxonomy dan faktor normalisasi yang setara.',
        metrics: [['Outcome index', '84', 'naik 5 poin'], ['Median peserta', '148', '24 LPJ verified'], ['Median SLA', '7 hari', 'turun 3 hari']],
        requirements: [['Kelompok comparable', 'Filter jenis, level, durasi, format, dan unit sebelum membandingkan.'], ['Normalisasi', 'Bandingkan biaya per peserta/hari dan outcome dengan formula terversi.'], ['Drill-down sumber', 'Setiap angka dapat dibuka sampai LPJ dan Activity-ID sumber.'], ['Kualitas data', 'Hanya record verified yang memenuhi kelengkapan minimum.']],
        flow: ['Eligibility analytics', 'Analitik lintas kegiatan baru layak setelah data verified mencukupi.', ['Pilih taxonomy dan comparable factors.', 'Validasi kualitas serta periode sumber.', 'Tampilkan asumsi, normalisasi, dan keterbatasan.']],
        table: [['Kegiatan', 'Peserta', 'Outcome', 'Budget', 'Evidence', 'LPJ SLA'], [['Penataran 2026-B', '146', '89', '97%', '95%', '5 hari'], ['Penataran 2026-A', '152', '86', '96%', '93%', '7 hari'], ['Penataran 2025-B', '138', '82', '94%', '90%', '9 hari']]],
    },
    recommendation: {
        group: 'Command Center', title: 'Rekomendasi per Kegiatan', status: 'Review manusia', action: 'Buat paket rekomendasi',
        description: 'Skenario kegiatan berikutnya dari kelompok LPJ verified yang benar-benar sebanding.',
        metrics: [['Comparable LPJ', '24', 'verified records'], ['Skenario', '3', 'efisiensi, skala, kualitas'], ['Confidence', '82%', 'kualitas data baik']],
        requirements: [['Sumber & sitasi', 'Cantumkan LPJ, periode, faktor comparable, dan lineage sumber.'], ['Pilihan skenario', 'Sediakan efisiensi, scale-up, dan quality focus dengan trade-off.'], ['Human-in-the-loop', 'Reviewer menerima, mengubah, atau menolak dengan alasan.'], ['Konversi terkontrol', 'Hanya rekomendasi yang diterima menjadi draft e-Planning.']],
        flow: ['RecommendationReviewed', 'AI tidak dapat mengesahkan LPJ atau membuat planning aktif tanpa keputusan manusia.', ['Lakukan quality dan eligibility check.', 'Buat rekomendasi dengan confidence dan limitations.', 'Simpan review manusia beserta alasan override.']],
        table: [['Skenario', 'Peserta', 'Durasi', 'Fokus', 'Risiko', 'Keputusan'], [['Efisiensi', '140–160', '3 hari', 'Kontrol biaya', 'Rendah', 'Direkomendasikan'], ['Scale up', '180–220', '3 hari', 'Kapasitas', 'Sedang', 'Perlu review'], ['Quality focus', '100–130', '3–4 hari', 'Outcome', 'Rendah', 'Alternatif']]],
    },
    'action-center': {
        group: 'Command Center', title: 'Smart Action Center', status: '8 tugas aktif', action: 'Atur tampilan',
        description: 'Satu antrean kerja berdasarkan role, lingkup unit, prioritas, dan SLA.',
        metrics: [['Jatuh tempo hari ini', '3', '2 prioritas tinggi'], ['Terlambat', '1', 'perlu eskalasi'], ['Selesai minggu ini', '26', '92% sesuai SLA']],
        requirements: [['Task berbasis event', 'Task dibuat oleh workflow, bukan baris statis di dashboard.'], ['SLA dan eskalasi', 'Setiap task memiliki due time, completion condition, dan escalation path.'], ['Role dan unit scope', 'Penerima hanya melihat objek dalam kewenangannya.'], ['Deep link', 'Setiap tugas membuka object dan versi sumber yang tepat.']],
        flow: ['Task workflow', 'Tugas selesai hanya setelah completion condition pada backend terpenuhi.', ['Tetapkan role atau user penerima.', 'Pantau due time dan SLA status.', 'Catat penyelesaian sebagai audit event.']],
        table: [['Prioritas', 'Objek', 'Tindakan', 'Unit', 'Tenggat', 'Status'], [['Tinggi', 'LPJ-2026-0017', 'Verifikasi keuangan', 'Pengprov Jatim', 'Hari ini', 'Risiko SLA'], ['Tinggi', 'FND-2026-0019', 'Bukti CAPA', 'Pengkot Surabaya', 'Besok', 'Menunggu'], ['Normal', 'PLN-2026-0049', 'Review planning', 'PB PERKEMI', '10 Okt', 'Belum dimulai']]],
    },
    portfolio: {
        group: 'Command Center', title: 'Portfolio LPJ Nasional', status: '128 kegiatan', action: 'Ekspor portfolio',
        description: 'Drill-down PB → Pengprov → Pengkot/Pengkab → Dojo → kegiatan → transaksi dan evidence.',
        metrics: [['LPJ diajukan', '94', 'periode aktif'], ['Verified', '76', '81% dari submitted'], ['Open findings', '17', '2 material']],
        requirements: [['Hierarki organisasi', 'Filter dan agregasi mengikuti lingkup otorisasi pengguna.'], ['Status & versi', 'Tampilkan status aktual dan versi LPJ yang diproses.'], ['Drill-down', 'Telusuri budget, actual, verification, finding, dan evidence.'], ['URL state', 'Filter, sorting, pagination, dan deep link dipertahankan.']],
        flow: ['Portfolio read model', 'Ringkasan berasal dari backend dan selalu dapat ditelusuri ke record sumber.', ['Terapkan policy per unit.', 'Gunakan pagination dan filter server-side.', 'Bedakan data stale, loading, empty, dan error.']],
        table: [['LPJ-ID', 'Kegiatan', 'Unit', 'Anggaran', 'Verifikasi', 'Status'], [['LPJ-2026-0017', 'Penataran', 'Pengprov Jatim', 'Rp125 jt', '3/4', 'Review'], ['LPJ-2026-0018', 'Kejuaraan', 'Pengkot A', 'Rp80 jt', '4/4', 'Verified'], ['LPJ-2026-0019', 'Pembinaan', 'Pengprov Bali', 'Rp96 jt', '2/4', 'Verification']]],
    },
    mandate: {
        group: 'Input Kegiatan', title: 'Mandat & Dasar Kegiatan', status: 'Registered', action: 'Registrasi mandat',
        description: 'Registrasikan authority reference sebelum kegiatan masuk ke pipeline pertanggungjawaban.',
        metrics: [['Mandat aktif', '41', 'tahun kerja 2026'], ['Draft', '6', 'menunggu kelengkapan'], ['Ditolak', '2', 'dapat diperbaiki']],
        requirements: [['Data dasar lengkap', 'Jenis, nomor, tanggal, unit, PIC, nama, periode, dan authority reference.'], ['Dokumen aman', 'Validasi tipe, ukuran, malware, checksum, dan klasifikasi akses.'], ['Cegah duplikasi', 'Periksa nomor dokumen, unit, periode, dan referensi sumber.'], ['Workspace planning', 'Mandate-ID registered membuat planning workspace dan audit event.']],
        flow: ['Gate registrasi mandat', 'Mandat tetap DRAFT bila data, dokumen, hak pengguna, atau validasi sumber belum terpenuhi.', ['Validasi field, format, duplikasi, unit, dan PIC.', 'Jalankan pemeriksaan keamanan berkas.', 'Terbitkan MandateRegistered setelah kontrol lulus.']],
        table: [['Mandate-ID', 'Dasar', 'Kegiatan', 'Unit', 'Tanggal', 'Status'], [['MND-2026-0041', 'Surat Keputusan', 'Penataran Pelatih Nasional', 'PB PERKEMI', '03 Okt 2026', 'Registered'], ['MND-2026-0040', 'Program Kerja', 'Pembinaan Atlet', 'Pengprov Jatim', '01 Okt 2026', 'Draft'], ['MND-2026-0039', 'Surat Tugas', 'Raker Wilayah', 'Pengprov Bali', '29 Sep 2026', 'Registered']]],
    },
    planning: {
        group: 'Input Kegiatan', title: 'e-Planning', status: 'Review planning', action: 'Buat planning',
        description: 'Kelola tujuan, output, outcome, KPI, jadwal, risiko, dan sumber daya secara berversi.',
        metrics: [['Planning aktif', '37', 'tahun kerja 2026'], ['Perlu review', '5', '2 mendekati SLA'], ['Approved', '28', 'versi berlaku']],
        requirements: [['Mandat registered', 'Planning hanya dibuat dari Mandate-ID yang valid.'], ['Output dan outcome', 'Pisahkan output langsung, outcome, target peserta, dan KPI.'], ['Risiko & sumber daya', 'Catat mitigasi, jadwal, SDM, dan kebutuhan operasional.'], ['Versioning', 'Planning approved tidak ditimpa; revisi membuat versi baru.']],
        flow: ['PlanSubmitted → PlanApproved', 'Reviewer dapat approve, return, atau reject sesuai kewenangan.', ['Pilih mandate yang registered.', 'Lengkapi tujuan, KPI, jadwal, risiko, dan target.', 'Ajukan versi untuk review dan simpan keputusan.']],
        table: [['Plan-ID', 'Mandate-ID', 'Jenis', 'Target', 'Versi', 'Status'], [['PLN-2026-0041', 'MND-2026-0041', 'Penataran', '150 peserta', '1.2', 'Approved'], ['PLN-2026-0049', 'MND-2026-0047', 'Kejuaraan', '320 peserta', '1.0', 'Review'], ['PLN-2026-0038', 'MND-2026-0039', 'Raker', '85 peserta', '1.1', 'Returned']]],
    },
    budget: {
        group: 'Input Kegiatan', title: 'e-Budget / RAB', status: 'Versi 1.2 berlaku', action: 'Tambah baris RAB',
        description: 'Susun budget line berversi yang dapat ditautkan ke transaksi aktual.',
        metrics: [['Total anggaran', 'Rp125 jt', 'versi berlaku'], ['Baris RAB', '18', '6 akun biaya'], ['Batas kewenangan', 'Sesuai', 'authority matrix']],
        requirements: [['Baris terstruktur', 'Akun, uraian, kuantitas, unit cost, subtotal, periode, dan sumber dana.'], ['Perhitungan otomatis', 'Subtotal dan total menggunakan numeric/decimal, bukan float.'], ['Authority threshold', 'Nilai dan jenis biaya menentukan jalur approval.'], ['Versi berlaku', 'RAB approved read-only; revisi membuat versi baru.']],
        flow: ['BudgetApproved', 'Activity hanya dapat dibuat dari planning dan budget yang disetujui.', ['Validasi akun, qty, unit cost, periode, dan batas.', 'Review serta approve versi RAB.', 'Tautkan budget line ke transaksi sumber.']],
        table: [['Akun', 'Uraian', 'Qty', 'Unit cost', 'Subtotal', 'Status'], [['Konsumsi', 'Paket peserta', '150', 'Rp75.000', 'Rp11,25 jt', 'Sesuai'], ['Akomodasi', 'Penginapan', '72', 'Rp550.000', 'Rp39,60 jt', 'Sesuai'], ['Perlengkapan', 'Modul & ATK', '150', 'Rp95.000', 'Rp14,25 jt', 'Sesuai']]],
    },
    activity: {
        group: 'Input Kegiatan', title: 'Pelaksanaan Kegiatan', status: 'In progress', action: 'Perbarui pelaksanaan',
        description: 'Activity-ID menjadi penghubung peserta, transaksi, evidence, evaluasi, dan LPJ.',
        metrics: [['Progress agenda', '75%', '9 dari 12 sesi'], ['Peserta hadir', '146', '97% dari target'], ['Issue terbuka', '1', 'nonmaterial']],
        requirements: [['Dari plan approved', 'Kegiatan dibuat dari planning dan budget versi berlaku.'], ['Catatan pelaksanaan', 'Agenda, lokasi, progres, panitia, insiden, dan perubahan.'], ['Evidence real-time', 'Bukti operasional dikumpulkan selama kegiatan.'], ['Gate completion', 'Tanggal, agenda, attendance, issue, dan evidence minimum harus lolos.']],
        flow: ['ActivityStarted → ActivityCompleted', 'Penyelesaian diblokir bila agenda, kehadiran, issue, atau evidence belum memenuhi aturan.', ['Aktifkan Activity-ID saat kegiatan dimulai.', 'Catat perubahan dan event pelaksanaan.', 'Jalankan completion check sebelum COMPLETED.']],
        table: [['Activity-ID', 'Kegiatan', 'Tanggal', 'Lokasi', 'Progress', 'Status'], [['PBK-2026-DIKTAR-00017', 'Penataran Pelatih', '10–12 Okt', 'Surabaya', '75%', 'In progress'], ['PBK-2026-EVENT-00016', 'Kejuaraan Wilayah', '02–04 Okt', 'Malang', '100%', 'Completed'], ['PBK-2026-RAPAT-00012', 'Raker Nasional', '28 Sep', 'Jakarta', '100%', 'Completed']]],
    },
    participants: {
        group: 'Input Kegiatan', title: 'Peserta & SDM', status: 'Sinkron 10:18', action: 'Sinkronkan peserta',
        description: 'Sinkronkan peserta dan panitia dari sistem sumber tanpa input ulang atau duplikasi.',
        metrics: [['Peserta valid', '146', '100% termapping'], ['Panitia', '18', 'role assigned'], ['Exception', '0', 'sinkron terakhir']],
        requirements: [['Sistem sumber', 'Prioritaskan SIMPERKEMI, e-Event, e-Training, atau sumber resmi.'], ['Idempotency', 'Sinkronisasi ulang tidak menggandakan participant atau attendance.'], ['Identity mapping', 'Validasi NIK/ID, unit, role, dan kemungkinan duplikasi.'], ['Attendance', 'Simpan kehadiran per sesi serta peran peserta/panitia.']],
        flow: ['ParticipantSynced', 'Record gagal mapping masuk integration exception, bukan dipaksakan aktif.', ['Ambil record dari sistem sumber.', 'Mapping identity, unit, dan role.', 'Link ke Activity-ID setelah validasi.']],
        table: [['NIK / ID', 'Nama', 'Peran', 'Unit', 'Kehadiran', 'Status'], [['••••011', 'Peserta A', 'Peserta', 'Dojo A', '100%', 'Valid'], ['EMP-014', 'Panitia B', 'Finance', 'Pengprov Jatim', '—', 'Assigned'], ['••••287', 'Peserta C', 'Peserta', 'Dojo C', '92%', 'Valid']]],
    },
    transactions: {
        group: 'Input Kegiatan', title: 'Transaksi & Realisasi', status: '3 exception', action: 'Tautkan transaksi',
        description: 'Hubungkan referensi transaksi ledger ke Activity-ID, Budget-Line-ID, dan evidence.',
        metrics: [['Transaksi', '84', 'dari ledger sumber'], ['Matched', '81', '96% rekonsiliasi'], ['Exception', 'Rp4,25 jt', '3 transaksi']],
        requirements: [['Bukan shadow ledger', 'Simpan reference, matching, dan snapshot; transaksi tetap di sumber.'], ['Stable reference', 'Import memakai reference ID stabil dan idempotency key.'], ['Matching', 'Nilai, tanggal, pihak, bank ref, budget line, dan evidence.'], ['Exception owner', 'Selisih memiliki kategori, severity, owner, due, dan resolution path.']],
        flow: ['TransactionImported → Reconciliation', 'Exception harus selesai atau memperoleh approved exception sebelum gate final.', ['Import ledger secara idempotent.', 'Cocokkan transaksi dengan budget line.', 'Buat task untuk selisih atau data tidak lengkap.']],
        table: [['Transaction-ID', 'Tanggal', 'Budget line', 'Pihak', 'Nilai', 'Status'], [['TRX-2026-00881', '10 Okt', 'Konsumsi', 'Vendor A', 'Rp11,25 jt', 'Matched'], ['TRX-2026-00882', '10 Okt', 'Akomodasi', 'Hotel B', 'Rp38,90 jt', 'Matched'], ['TRX-2026-00890', '11 Okt', 'Transport', 'Vendor C', 'Rp4,25 jt', 'Exception']]],
    },
    evidence: {
        group: 'Input Kegiatan', title: 'Evidence Center', status: '92% lengkap', action: 'Unggah evidence',
        description: 'Kelola bukti berversi dengan source linkage, checksum, scan, klasifikasi, dan review.',
        metrics: [['Evidence terdaftar', '118', 'aktivitas aktif'], ['Verified', '104', '88% diterima'], ['Perlu klasifikasi', '2', 'scan lulus']],
        requirements: [['Metadata lengkap', 'Evidence-ID, tipe, relasi, uploader, timestamp, checksum, dan klasifikasi.'], ['Private storage', 'Akses file mengikuti policy dan setiap download dicatat.'], ['Scan & quarantine', 'Berkas gagal tetap di karantina dan tidak aktif.'], ['Versioning', 'Evidence pengganti membuat versi/supersede; histori tidak dihapus.']],
        flow: ['EvidenceRegistered', 'Evidence aktif setelah pemeriksaan tipe, ukuran, malware, checksum, dan hak akses lulus.', ['Unggah ke area karantina.', 'Scan serta validasi metadata.', 'Link ke object dan kirim untuk review.']],
        table: [['Evidence-ID', 'Tipe', 'Relasi', 'Versi', 'Scan', 'Review'], [['EV-2026-0102', 'Invoice', 'TRX-00881', '1', 'Lulus', 'Verified'], ['EV-2026-0103', 'Foto sesi', 'ACT-00017', '2', 'Lulus', 'Linked'], ['EV-2026-0104', 'Daftar hadir', 'ATT-00341', '1', 'Lulus', 'Verified']]],
    },
    evaluation: {
        group: 'Input Kegiatan', title: 'Evaluasi & Outcome', status: 'Siap finalisasi', action: 'Finalisasi evaluasi',
        description: 'Nilai target, aktual, KPI, outcome, lessons learned, dan bukti substantif.',
        metrics: [['Output tercapai', '96%', 'sesuai target'], ['Completion', '142', 'dari 146 peserta'], ['Satisfaction', '4,6/5', '138 respons']],
        requirements: [['Target dari planning', 'Baseline, target, satuan, formula, periode, dan actual konsisten.'], ['Evidence KPI', 'Setiap hasil KPI ditelusuri ke evidence dan definisi formula.'], ['Konteks manusia', 'Simpan lessons learned, praktik berhasil, dan area perbaikan.'], ['Review substantif', 'Verifier dapat menerima, klarifikasi, atau membuat finding.']],
        flow: ['EvaluationFinalized', 'Evaluasi final menjadi input LPJ Composer dan analytics; formula harus berversi.', ['Muat target planning versi berlaku.', 'Isi actual, outcome, lessons learned, dan evidence.', 'Finalisasi setelah seluruh KPI tervalidasi.']],
        table: [['KPI', 'Baseline', 'Target', 'Aktual', 'Satuan', 'Status'], [['Completion', '88', '95', '97', '%', 'Tercapai'], ['Kompetensi', '68', '80', '84', 'Indeks', 'Tercapai'], ['Satisfaction', '4,1', '4,5', '4,6', 'Skala 5', 'Tercapai']]],
    },
    composer: {
        group: 'Pertanggungjawaban', title: 'LPJ Composer', status: '94% lengkap', action: 'Buat draft LPJ',
        description: 'Gabungkan source records otomatis dan minta narasi hanya untuk konteks yang memerlukan manusia.',
        metrics: [['Kelengkapan', '94%', '2 warning'], ['Bagian siap', '6/8', 'finance & evidence'], ['Versi draft', '1.2', 'belum diajukan']],
        requirements: [['Auto-compose', 'Gabungkan mandat, planning, RAB, pelaksanaan, peserta, transaksi, evidence, dan evaluasi.'], ['Source reference', 'Setiap bagian penting menunjukkan source record dan versi.'], ['Completeness rules', 'Indikator dihitung dari aturan nyata, bukan hard-coded.'], ['Preview & template', 'Template berdasarkan jenis kegiatan dan ekspor draft bila berizin.']],
        flow: ['Gate LPJ draft', 'Draft belum dapat diajukan saat blocker system check atau data wajib belum lengkap.', ['Muat source record serta versi berlaku.', 'Tampilkan missing data dan warning.', 'Kunci draft saat membuat submission snapshot.']],
        table: [['Bagian', 'Sumber', 'Kelengkapan', 'Versi', 'Diperbarui', 'Status'], [['Identitas & mandat', 'MND-0041', '100%', '1.0', '03 Okt', 'Siap'], ['Pelaksanaan', 'ACT-00017', '98%', '1.1', '12 Okt', 'Siap'], ['Keuangan', '84 transaksi', '94%', '1.2', '10:12', 'Warning'], ['Evidence', '118 file', '92%', '1.2', '10:21', 'Warning']]],
    },
    submission: {
        group: 'Pertanggungjawaban', title: 'Submit & Version Snapshot', status: '2 warning', action: 'Ajukan LPJ v1.2',
        description: 'Jalankan pre-check dan buat snapshot immutable untuk setiap pengajuan LPJ.',
        metrics: [['Pre-check', '12/14', '2 warning, 0 blocker'], ['Snapshot', 'v1.2', 'belum dibuat'], ['Evidence manifest', '118', 'checksum siap']],
        requirements: [['Pre-submission check', 'Validasi mandate, plan, budget, completion, evidence, transaksi, dan evaluasi.'], ['Immutable snapshot', 'Simpan data, file version, manifest, reconciliation, hash, rule version, dan waktu.'], ['Versioning', 'Return membuat draft versi berikutnya; snapshot lama tetap read-only.'], ['Concurrency control', 'Submit memakai distributed lock dan idempotency key.']],
        flow: ['LPJSubmitted', 'Status SUBMITTED hanya terjadi setelah snapshot berhasil dibuat dan integritas tersimpan.', ['Blokir bila system check menemukan blocker.', 'Proses warning lewat exception approval bila perlu.', 'Buat snapshot lalu kirim ke verification queue.']],
        table: [['Control', 'Hasil', 'Detail', 'Rule', 'Owner', 'Tindakan'], [['Required fields', 'Pass', 'Lengkap', 'SUB-3.2', 'System', '—'], ['Budget vs actual', 'Pass', 'Balanced', 'FIN-2.1', 'Finance', '—'], ['Evidence', 'Warning', '2 file pendukung', 'EVD-4.0', 'Activity Owner', 'Lengkapi'], ['Reconciliation', 'Pass', '96% sesuai kebijakan', 'REC-2.4', 'Finance', '—']]],
    },
    verification: {
        group: 'Pertanggungjawaban', title: 'Digital Verification', status: '3 dari 4 selesai', action: 'Lanjutkan verifikasi',
        description: 'Periksa snapshot melalui system, administratif, keuangan, dan substantif/compliance.',
        metrics: [['System', 'Pass', 'otomatis'], ['Administratif', 'Verified', 'VER-0186'], ['Keuangan', 'In review', 'jatuh tempo 16:00']],
        requirements: [['Checklist domain', 'Setiap item mencatat hasil, evidence, catatan, reviewer, dan waktu.'], ['Keputusan eksplisit', 'Verified, finding, returned, atau clarification required.'], ['Version-bound', 'Verifier memeriksa snapshot dan LPJ version yang sama.'], ['Segregation of duties', 'Pembuat tidak memverifikasi atau menyetujui record sendiri.']],
        flow: ['VerificationCompleted', 'Approval tersedia setelah semua domain wajib lulus dan finding material ditutup.', ['Jalankan system verification.', 'Selesaikan domain admin, finance, dan substantive.', 'Buat clarification, finding, atau return bila gagal.']],
        table: [['Domain', 'Verifier', 'Checklist', 'Evidence', 'Keputusan', 'Status'], [['System', 'Service', '14/14', 'Snapshot', 'Verified', 'Selesai'], ['Administratif', 'R. Lestari', '18/18', '12 file', 'Verified', 'Selesai'], ['Keuangan', 'D. Wijaya', '21/24', '42 file', '—', 'In review'], ['Substantif', 'Belum ditugaskan', '0/16', '—', '—', 'Queued']]],
    },
    disposition: {
        group: 'Pertanggungjawaban', title: 'Disposisi Digital', status: '4 terbuka', action: 'Buat disposisi',
        description: 'Kirim instruksi terstruktur yang dapat dilacak sampai respons dan penyelesaian.',
        metrics: [['Terbuka', '4', '1 prioritas tinggi'], ['Direspons', '7', 'periode ini'], ['Tepat waktu', '91%', '30 hari']],
        requirements: [['Object reference', 'Tautkan disposisi ke LPJ, finding, evidence, atau object sumber.'], ['Instruksi jelas', 'Dari, kepada, prioritas, tenggat, instruksi, dan completion condition.'], ['Tracking', 'Catat acknowledgement, respons, evidence, completion, dan eskalasi.'], ['Audit', 'Semua perubahan status menghasilkan event append-only.']],
        flow: ['Disposition task', 'Disposisi selesai hanya jika completion condition terpenuhi, bukan sekadar ada respons.', ['Pilih object dan penerima berwenang.', 'Tetapkan prioritas, due date, dan instruksi.', 'Verifikasi respons sebelum menutup task.']],
        table: [['Disposisi', 'Object', 'Dari', 'Kepada', 'Prioritas', 'Status'], [['DSP-2026-0042', 'LPJ-0017', 'Verifier', 'Finance PIC', 'Tinggi', 'Open'], ['DSP-2026-0039', 'EV-0098', 'Admin Verifier', 'Activity Owner', 'Normal', 'Acknowledged'], ['DSP-2026-0036', 'FND-0017', 'Approver', 'Pengprov', 'Normal', 'Closed']]],
    },
    findings: {
        group: 'Pertanggungjawaban', title: 'Findings & CAPA', status: '17 terbuka', action: 'Buat finding',
        description: 'Kelola ketidaksesuaian, corrective action, bukti, dan verifikasi efektivitas.',
        metrics: [['Open findings', '17', '2 material'], ['CAPA in progress', '9', '3 mendekati due'], ['Closure rate', '88%', '90 hari']],
        requirements: [['Finding lengkap', 'Criteria, condition, cause, risk, recommendation, owner, due, dan severity.'], ['State machine CAPA', 'Open → acknowledged → in progress → evidence submitted → closed/reopened.'], ['Evidence & effectiveness', 'Penutupan membutuhkan bukti dan penilaian efektivitas.'], ['Independent closure', 'Owner tidak menutup finding sendiri tanpa authority eksplisit.']],
        flow: ['FindingRaised → FindingClosed', 'Finding material memblokir approval sampai CAPA diverifikasi pihak berwenang.', ['Dokumentasikan kondisi, sebab, risiko, dan action.', 'Assign owner serta due date.', 'Verifier menilai bukti dan efektivitas sebelum closure.']],
        table: [['Finding-ID', 'Domain', 'Severity', 'Kondisi', 'Owner', 'Status'], [['FND-2026-0019', 'Finance', 'Medium', 'Evidence belum lengkap', 'Finance PIC', 'Evidence submitted'], ['FND-2026-0018', 'Substantive', 'High', 'KPI tanpa bukti', 'Activity Owner', 'In progress'], ['FND-2026-0016', 'Administratif', 'Low', 'Format dokumen', 'Sekretariat', 'Closed']]],
    },
    approval: {
        group: 'Pertanggungjawaban', title: 'Approval e-LPJ', status: 'Menunggu gate', action: 'Buka decision pack',
        description: 'Tinjau decision pack, verification, finance, findings, exception, dan audit sebelum keputusan.',
        metrics: [['Siap approval', '6', 'seluruh gate lulus'], ['Clarification', '2', 'menunggu respons'], ['Rata-rata keputusan', '1,8 hari', 'periode aktif']],
        requirements: [['Decision pack', 'Identitas, versi, budget/actual, verification, finding/CAPA, exception, dan source.'], ['Authority matrix', 'Approver berwenang untuk unit, jenis, dan nilai anggaran.'], ['Conflict of interest', 'Actor yang sama pada role terpisah diblokir.'], ['Version integrity', 'Decision pack identik dengan versi yang diperiksa.']],
        flow: ['LPJApproved', 'Approve, clarification, return, dan reject seluruhnya memerlukan catatan dan audit.', ['Validasi verification, finding, clarification, dan exception.', 'Periksa authority dan conflict of interest.', 'Simpan keputusan terikat pada LPJ version.']],
        table: [['LPJ-ID', 'Versi', 'Unit', 'Nilai', 'Verifikasi', 'Status'], [['LPJ-2026-0018', '1.1', 'Pengkot A', 'Rp78 jt', '4/4', 'Ready'], ['LPJ-2026-0021', '2.0', 'Pengprov Bali', 'Rp142 jt', '4/4', 'Ready'], ['LPJ-2026-0017', '1.2', 'Pengprov Jatim', 'Rp121 jt', '3/4', 'Blocked']]],
    },
    verified: {
        group: 'Pertanggungjawaban', title: 'PB Verified', status: '76 verified', action: 'Buat verified pack',
        description: 'Terbitkan status final setelah seluruh gate, authority, dan integritas paket terpenuhi.',
        metrics: [['Verified', '76', 'periode aktif'], ['Pack valid', '76', 'hash tersimpan'], ['Koreksi resmi', '1', 'versi lanjutan']],
        requirements: [['Final gate', 'Verification selesai, tidak ada material finding, reconciliation dan exception valid.'], ['Verified pack', 'Final LPJ, manifest, approval, CAPA, audit summary, hash, dan metadata.'], ['Verification reference', 'QR/reference menunjuk hasil verifikasi yang dapat divalidasi.'], ['Read-only', 'Record final tidak ditimpa; koreksi resmi membuat versi lanjutan.']],
        flow: ['LPJVerified', 'PB_VERIFIED diberikan backend setelah versi approved sama dengan versi terbit.', ['Validasi approval dan final gate.', 'Bangun paket serta simpan hash.', 'Terbitkan reference lalu kirim ke arsip.']],
        table: [['LPJ-ID', 'Versi', 'Unit', 'Approval-ID', 'Verified', 'Integritas'], [['LPJ-2026-0018', '1.1', 'Pengkot A', 'APR-0188', '03 Okt 2026', 'Valid'], ['LPJ-2026-0014', '1.0', 'Pengprov Jabar', 'APR-0181', '30 Sep 2026', 'Valid'], ['LPJ-2026-0011', '2.1', 'PB PERKEMI', 'APR-0175', '26 Sep 2026', 'Valid']]],
    },
    archive: {
        group: 'Pertanggungjawaban', title: 'Digital Archive', status: 'Integritas valid', action: 'Cari arsip',
        description: 'Simpan paket final, manifest, keputusan, CAPA, audit, dan retention metadata.',
        metrics: [['Paket arsip', '188', 'tahun 2026'], ['Integrity check', '100%', 'hash valid'], ['Retention review', '4', '30 hari ke depan']],
        requirements: [['Immutable package', 'Final pack dan manifest terkait version approved yang sama.'], ['Retention policy', 'Kebijakan retensi dan legal hold diterapkan berversi.'], ['Access audit', 'Akses baca dan unduh pengguna berwenang dicatat.'], ['Integrity verification', 'Hash paket dapat diperiksa ulang dan hasil terdokumentasi.']],
        flow: ['LPJArchived', 'Archive-ID dibuat setelah paket final tersimpan dan integritas tervalidasi.', ['Simpan verified pack di object storage privat.', 'Terapkan retention dan klasifikasi.', 'Jalankan integrity check serta audit akses.']],
        table: [['Archive-ID', 'LPJ-ID', 'Versi', 'Unit', 'Retention', 'Integritas'], [['ARC-2026-0188', 'LPJ-2026-0018', '1.1', 'Pengkot A', '7 tahun', 'Valid'], ['ARC-2026-0181', 'LPJ-2026-0014', '1.0', 'Pengprov Jabar', '7 tahun', 'Valid'], ['ARC-2026-0175', 'LPJ-2026-0011', '2.1', 'PB PERKEMI', 'Permanen', 'Valid']]],
    },
    finance: {
        group: 'Analytics & Governance', title: 'Finance & Rekonsiliasi', status: '96% reconciled', action: 'Tinjau exception',
        description: 'Pantau budget, actual, matching, exception, dan materiality tanpa membuat shadow ledger.',
        metrics: [['Anggaran', 'Rp125 jt', 'versi berlaku'], ['Realisasi', 'Rp121 jt', '84 transaksi'], ['Exception', 'Rp4,25 jt', '3 record']],
        requirements: [['Ledger sebagai sumber', 'Transaksi dikoreksi di sistem keuangan sumber, bukan e-LPJ.'], ['Reconciliation rule', 'Cocokkan nominal, tanggal, pihak, bank ref, budget line, dan evidence.'], ['Materiality', 'Threshold dan exception authority berasal dari konfigurasi berversi.'], ['Operational view', 'Tampilkan owner, due, retry, hasil sync, dan resolution path.']],
        flow: ['Reconciliation exception', 'Penyelesaian berupa mapping, koreksi sumber, klarifikasi, atau approved exception.', ['Buat exception lengkap dengan severity.', 'Assign ke Finance PIC dan tetapkan due.', 'Sinkron ulang serta periksa hasil sebelum final gate.']],
        table: [['Transaksi', 'Budget line', 'Ledger', 'LPJ snapshot', 'Selisih', 'Status'], [['TRX-00881', 'Konsumsi', 'Rp11,25 jt', 'Rp11,25 jt', 'Rp0', 'Matched'], ['TRX-00882', 'Akomodasi', 'Rp38,90 jt', 'Rp38,90 jt', 'Rp0', 'Matched'], ['TRX-00890', 'Transport', 'Rp4,25 jt', '—', 'Rp4,25 jt', 'Exception']]],
    },
    intelligence: {
        group: 'Analytics & Governance', title: 'LPJ Intelligence', status: 'Human review required', action: 'Buat analytical brief',
        description: 'Analisis grounded untuk completeness, finance, outcome, process, finding, dan evidence.',
        metrics: [['Sumber eligible', '76 LPJ', 'verified only'], ['Insight aktif', '12', '4 prioritas tinggi'], ['Review rate', '100%', 'human-in-the-loop']],
        requirements: [['Grounded output', 'Setiap claim memiliki source LPJ, periode, dan citation.'], ['Structured result', 'Output tervalidasi dengan schema, model/rule version, confidence, dan limitation.'], ['Guardrails', 'AI tidak approve, mengubah transaksi, menutup finding, atau memindahkan dana.'], ['Audit & biaya', 'Simpan ai run, fingerprint, output, reviewer, usage, rate limit, dan error.']],
        flow: ['AI analysis workflow', 'Aplikasi inti tetap bekerja saat AI tidak tersedia; output selalu direview manusia.', ['Pilih data eligible sesuai policy.', 'Generate output terstruktur dan bersitasi.', 'Reviewer menerima, mengubah, atau menolak dengan alasan.']],
        table: [['Dimensi', 'Signal', 'Evidence', 'Confidence', 'Interpretasi', 'Review'], [['Budget efficiency', 'Positif', 'Budget/actual', 'Tinggi', 'Realisasi di bawah rencana', 'Diterima'], ['Outcome', 'Kuat', 'KPI evaluation', 'Tinggi', 'KPI utama tercapai', 'Diterima'], ['Process', 'Bottleneck', 'Workflow events', 'Sedang', 'Finance verification lambat', 'Perlu review']]],
    },
    'next-activity': {
        group: 'Analytics & Governance', title: 'Analisis Kegiatan Berikutnya', status: '3 skenario', action: 'Buat opsi kegiatan',
        description: 'Ubah organizational learning dari LPJ verified menjadi opsi draft planning.',
        metrics: [['Verified source', '24', 'kelompok Penataran'], ['Skenario', '3', 'trade-off terlihat'], ['Human decision', 'Pending', 'belum ke planning']],
        requirements: [['Quality check', 'Berhenti dengan insufficient data bila sumber belum cukup.'], ['Scenario planning', 'Tawarkan tujuan, kapasitas, kontrol, KPI, mitigasi, dan risiko.'], ['Human decision', 'Accept as draft, modify, atau reject dengan alasan.'], ['Traceability', 'Draft planning menyimpan recommendation dan source LPJ.']],
        flow: ['Verified LPJ → Draft e-Planning', 'Rekomendasi yang belum direview tidak dapat membuat planning aktif.', ['Validasi eligibility dan comparable group.', 'Buat skenario lengkap dengan limitations.', 'Konversi review manusia menjadi draft planning.']],
        table: [['Skenario', 'Tujuan', 'Peserta', 'Kontrol utama', 'Risiko', 'Status'], [['A · Efisiensi', 'Pertahankan output', '150', 'Daily evidence checkpoint', 'Rendah', 'Recommended'], ['B · Scale up', 'Perbesar kapasitas', '200', 'Tambah verifier', 'Sedang', 'Alternative'], ['C · Quality', 'Perdalam competency', '120', 'Longitudinal outcome', 'Rendah', 'Alternative']]],
    },
    hierarchy: {
        group: 'Analytics & Governance', title: 'Hierarki PB–Dojo', status: '34 Pengprov', action: 'Kelola unit',
        description: 'Atur scope organisasi, ownership data, dan kewenangan dari Dojo sampai PB PERKEMI.',
        metrics: [['Pengprov', '34', 'aktif'], ['Pengkot/Pengkab', '286', 'terdaftar'], ['Dojo', '1.842', 'scope operasional']],
        requirements: [['Hierarchy path', 'PB → Pengprov → Pengkot/Pengkab → Dojo dengan parent-child valid.'], ['Unit scope', 'Policy membatasi record dan action sesuai unit pengguna.'], ['Delegation', 'Delegasi menyimpan periode, capability, pemberi, penerima, dan audit.'], ['Aggregate view', 'Oversight melihat agregat tanpa membuka data sensitif di luar izin.']],
        flow: ['Organization scope', 'Role tidak menggantikan object-level policy dan unit scope.', ['Validasi parent serta kode unik unit.', 'Terapkan permission dan policy per object.', 'Audit perubahan hierarchy serta delegation.']],
        table: [['Unit', 'Tingkat', 'Parent', 'PIC', 'Kegiatan', 'Status'], [['PB PERKEMI', 'Nasional', '—', 'Sekjen', '22', 'Aktif'], ['Pengprov Jawa Timur', 'Provinsi', 'PB PERKEMI', 'Ketua Pengprov', '14', 'Aktif'], ['Pengkot Surabaya', 'Kota', 'Pengprov Jawa Timur', 'Ketua Pengkot', '7', 'Aktif']]],
    },
    integration: {
        group: 'Analytics & Governance', title: 'Integration Hub', status: '1 partial failed', action: 'Jalankan health check',
        description: 'Pantau API, event, batch, mapping, idempotency, retry, dan contract version sistem sumber.',
        metrics: [['Integrasi aktif', '8', 'sumber resmi'], ['Sync sukses', '99,2%', '24 jam'], ['Backlog', '14', '3 perlu mapping']],
        requirements: [['Health aktual', 'Connected hanya tampil dari health check, bukan status hard-coded.'], ['Operational metadata', 'Last success/attempt, latency, backlog, hasil, version, dan owner.'], ['Idempotency & mapping', 'Retry tidak membuat duplikasi dan mismatch menjadi task.'], ['Failure handling', 'Partial failed, retry, dead-letter, dan exception queue terlihat.']],
        flow: ['Integration run', 'Record sukses tidak diproses ulang; kegagalan permanen masuk exception queue.', ['Validasi schema dan authorization.', 'Mapping identity/reference lalu idempotency check.', 'Catat audit, retry, dan dead-letter bila gagal.']],
        table: [['Sistem', 'Kontrak', 'Last success', 'Latency', 'Backlog', 'Status'], [['SIMPERKEMI', 'v2.4', '10:18', '420 ms', '0', 'Healthy'], ['e-Finance/Bank', 'v3.1', '10:12', '1,2 s', '3', 'Degraded'], ['e-Event', 'v1.9', '09:58', '680 ms', '11', 'Healthy'], ['e-Surat', 'v2.0', 'Kemarin', '—', '0', 'Partial failed']]],
    },
    audit: {
        group: 'Analytics & Governance', title: 'Audit & Compliance', status: 'Append-only', action: 'Ekspor audit',
        description: 'Telusuri siapa, apa, kapan, channel, nilai lama/baru, authority, alasan, dan evidence.',
        metrics: [['Event hari ini', '1.284', 'seluruh scope'], ['Access events', '342', 'file & record'], ['Anomali', '2', 'perlu review']],
        requirements: [['Append-only', 'Koreksi dibuat sebagai event baru; histori tidak dihapus.'], ['Context lengkap', 'Actor/service, action, time, channel, correlation, object/version, reason, source.'], ['Material diff', 'Tampilkan nilai lama dan baru untuk perubahan material.'], ['Permission-aware', 'Auditor read-only dan hanya melihat scope yang diizinkan.']],
        flow: ['Audit event contract', 'Setiap transisi dan percobaan aksi sensitif membentuk audit event.', ['Rekam actor, authority, object, version, dan correlation ID.', 'Simpan before/after yang relevan dan alasan.', 'Sediakan filter, export async, dan retention policy.']],
        table: [['Waktu', 'Actor', 'Action', 'Object', 'Version', 'Result'], [['15:02:11', 'R. Lestari', 'FindingClosed', 'FND-0016', '1', 'Success'], ['14:31:08', 'Finance PIC', 'CAPAEvidenceSubmitted', 'FND-0019', '2', 'Success'], ['11:16:42', 'D. Wijaya', 'FindingRaised', 'LPJ-0017', '1.2', 'Success'], ['10:18:03', 'Integration Service', 'ParticipantSynced', 'ACT-00017', '1', 'Success']]],
    },
};

const shell = document.querySelector('[data-app-shell]');
const dashboard = document.querySelector('[data-page="dashboard"]');
const modulePage = document.querySelector('[data-page="module"]');
const navItems = [...document.querySelectorAll('[data-module]')];
const registrationDialog = document.querySelector('[data-registration-dialog]');
const registrationForm = document.querySelector('[data-registration-form]');
const searchDialog = document.querySelector('[data-search-dialog]');
const searchInput = document.querySelector('[data-search-input]');
const searchResults = document.querySelector('[data-search-results]');
const notificationToggle = document.querySelector('[data-notifications-toggle]');
const notificationPanel = document.querySelector('[data-notifications-panel]');
const toast = document.querySelector('[data-toast]');
let toastTimer;
let selectedSearch = 0;

const escapeHtml = (value) => String(value).replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;').replaceAll('"', '&quot;').replaceAll("'", '&#039;');

function showToast(title, message) {
    window.clearTimeout(toastTimer);
    toast.querySelector('[data-toast-title]').textContent = title;
    toast.querySelector('[data-toast-message]').textContent = message;
    toast.hidden = false;
    toastTimer = window.setTimeout(() => { toast.hidden = true; }, 4200);
}

function closeSidebar() {
    shell.classList.remove('is-mobile-open');
    document.querySelector('[data-sidebar-open]')?.setAttribute('aria-expanded', 'false');
}

function renderModule(key) {
    if (key === 'dashboard') {
        dashboard.hidden = false;
        modulePage.hidden = true;
        document.title = 'Dashboard Nasional · Smart e-LPJ';
    } else {
        const item = modules[key];
        if (!item) return;
        dashboard.hidden = true;
        modulePage.hidden = false;
        modulePage.querySelector('[data-module-group]').textContent = item.group;
        modulePage.querySelector('[data-module-breadcrumb]').textContent = item.title;
        modulePage.querySelector('[data-module-title]').textContent = item.title;
        modulePage.querySelector('[data-module-description]').textContent = item.description;
        modulePage.querySelector('[data-module-status] span').textContent = item.status;
        modulePage.querySelector('[data-module-action] span').textContent = item.action;
        modulePage.querySelector('[data-workspace-title]').textContent = `${item.title} · workspace aktif`;
        modulePage.querySelector('[data-workspace-subtitle]').textContent = `Contoh data pada lingkup nasional untuk ${item.title.toLowerCase()}.`;
        modulePage.querySelector('[data-table-caption]').textContent = `Daftar contoh data ${item.title}`;
        modulePage.querySelector('[data-module-flow-title]').textContent = item.flow[0];
        modulePage.querySelector('[data-module-flow-description]').textContent = item.flow[1];
        modulePage.querySelector('[data-module-metrics]').innerHTML = item.metrics.map(([label, value, detail]) => `<article class="module-stat"><span>${escapeHtml(label)}</span><strong>${escapeHtml(value)}</strong><small>${escapeHtml(detail)}</small></article>`).join('');
        modulePage.querySelector('[data-module-requirements]').innerHTML = item.requirements.map(([title, description]) => `<div class="requirement"><svg aria-hidden="true"><use href="#i-check"/></svg><div><b>${escapeHtml(title)}</b><p>${escapeHtml(description)}</p></div></div>`).join('');
        modulePage.querySelector('[data-module-controls]').innerHTML = item.flow[2].map((control) => `<li>${escapeHtml(control)}</li>`).join('');
        modulePage.querySelector('[data-module-table-head]').innerHTML = item.table[0].map((heading) => `<th scope="col">${escapeHtml(heading)}</th>`).join('');
        modulePage.querySelector('[data-module-table-body]').innerHTML = item.table[1].map((row) => `<tr>${row.map((cell, index) => `<td>${index === 0 ? `<button class="table-link" type="button">${escapeHtml(cell)}</button>` : escapeHtml(cell)}</td>`).join('')}</tr>`).join('');
        document.title = `${item.title} · Smart e-LPJ`;
    }

    navItems.forEach((nav) => {
        const active = nav.dataset.module === key;
        nav.classList.toggle('is-active', active);
        nav.setAttribute('aria-current', active ? 'page' : 'false');
    });
    window.history.replaceState({}, '', key === 'dashboard' ? window.location.pathname : `#${key}`);
    closeSidebar();
    document.querySelector('#main-content').focus({ preventScroll: true });
    window.scrollTo({ top: 0, behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' });
}

navItems.forEach((item) => item.addEventListener('click', () => renderModule(item.dataset.module)));
document.querySelectorAll('[data-open-module]').forEach((item) => item.addEventListener('click', () => {
    renderModule(item.dataset.openModule);
    if (notificationPanel) notificationPanel.hidden = true;
    notificationToggle?.setAttribute('aria-expanded', 'false');
}));

document.querySelector('[data-sidebar-open]')?.addEventListener('click', (event) => {
    shell.classList.add('is-mobile-open');
    event.currentTarget.setAttribute('aria-expanded', 'true');
});
document.querySelector('[data-sidebar-close]')?.addEventListener('click', closeSidebar);
document.querySelector('[data-sidebar-backdrop]')?.addEventListener('click', closeSidebar);
document.querySelector('[data-sidebar-collapse]')?.addEventListener('click', (event) => {
    const collapsed = shell.classList.toggle('is-collapsed');
    event.currentTarget.setAttribute('aria-expanded', String(!collapsed));
    event.currentTarget.setAttribute('aria-label', collapsed ? 'Lebarkan navigasi' : 'Ciutkan navigasi');
});

notificationToggle?.addEventListener('click', () => {
    const open = notificationPanel.hidden;
    notificationPanel.hidden = !open;
    notificationToggle.setAttribute('aria-expanded', String(open));
});
document.addEventListener('click', (event) => {
    if (!notificationPanel?.hidden && !notificationPanel.contains(event.target) && !notificationToggle.contains(event.target)) {
        notificationPanel.hidden = true;
        notificationToggle.setAttribute('aria-expanded', 'false');
    }
});

document.querySelectorAll('[data-registration-open]').forEach((button) => button.addEventListener('click', () => {
    registrationDialog.showModal();
    registrationDialog.querySelector('select')?.focus();
}));
document.querySelectorAll('[data-registration-close]').forEach((button) => button.addEventListener('click', () => registrationDialog.close()));
registrationForm?.addEventListener('submit', (event) => {
    event.preventDefault();
    const fields = [...registrationForm.querySelectorAll('[required]')];
    let firstInvalid;
    fields.forEach((field) => {
        const invalid = !field.value;
        field.setAttribute('aria-invalid', String(invalid));
        const error = registrationForm.querySelector(`[data-error-for="${field.name}"]`);
        if (error) error.textContent = invalid ? 'Field ini wajib dilengkapi.' : '';
        if (invalid && !firstInvalid) firstInvalid = field;
    });
    if (firstInvalid) {
        firstInvalid.focus();
        showToast('Data belum lengkap', 'Lengkapi field yang ditandai sebelum melanjutkan.');
        return;
    }
    const activityName = registrationForm.elements.activity_name.value;
    registrationDialog.close();
    registrationForm.reset();
    fields.forEach((field) => field.removeAttribute('aria-invalid'));
    showToast('Simulasi registrasi berhasil', `${activityName} siap masuk validasi mandat. Data tidak disimpan ke server.`);
    renderModule('mandate');
});

function searchItems(query = '') {
    const menu = navItems.map((nav) => ({ key: nav.dataset.module, label: nav.dataset.moduleLabel, group: nav.closest('.nav-group')?.querySelector('p')?.textContent || 'Modul', description: nav.dataset.module === 'dashboard' ? 'Ringkasan nasional dan antrean prioritas' : modules[nav.dataset.module]?.description }));
    const records = [
        { key: 'verification', label: 'LPJ-2026-0017', group: 'Record demonstrasi', description: 'Penataran · verifikasi keuangan' },
        { key: 'activity', label: 'PBK-2026-DIKTAR-00017', group: 'Record demonstrasi', description: 'Activity-ID · Penataran Pelatih' },
        { key: 'findings', label: 'FND-2026-0019', group: 'Record demonstrasi', description: 'Finding finance · evidence submitted' },
        { key: 'transactions', label: 'TRX-2026-00881', group: 'Record demonstrasi', description: 'Transaksi konsumsi · matched' },
    ];
    return [...menu, ...records].filter((item) => `${item.label} ${item.group} ${item.description}`.toLowerCase().includes(query.toLowerCase())).slice(0, 9);
}

function renderSearch(query = '') {
    const items = searchItems(query);
    selectedSearch = Math.min(selectedSearch, Math.max(items.length - 1, 0));
    if (!items.length) {
        searchResults.innerHTML = '<div class="empty-search"><b>Tidak ada hasil</b><br>Periksa istilah atau gunakan ID lengkap.</div>';
        return;
    }
    searchResults.innerHTML = items.map((item, index) => `<button class="search-result ${index === selectedSearch ? 'selected' : ''}" type="button" data-search-key="${escapeHtml(item.key)}"><i><svg><use href="#i-search"/></svg></i><span><b>${escapeHtml(item.label)}</b><small>${escapeHtml(item.group)} · ${escapeHtml(item.description || '')}</small></span><svg><use href="#i-chevron"/></svg></button>`).join('');
    searchResults.querySelectorAll('[data-search-key]').forEach((button) => button.addEventListener('click', () => {
        searchDialog.close();
        renderModule(button.dataset.searchKey);
    }));
}

function openSearch() {
    selectedSearch = 0;
    searchInput.value = '';
    renderSearch();
    searchDialog.showModal();
    searchInput.focus();
}

document.querySelector('[data-search-open]')?.addEventListener('click', openSearch);
document.querySelector('[data-search-close]')?.addEventListener('click', () => searchDialog.close());
searchInput?.addEventListener('input', () => { selectedSearch = 0; renderSearch(searchInput.value); });
searchInput?.addEventListener('keydown', (event) => {
    const items = searchItems(searchInput.value);
    if (!items.length) return;
    if (event.key === 'ArrowDown') { event.preventDefault(); selectedSearch = (selectedSearch + 1) % items.length; renderSearch(searchInput.value); }
    if (event.key === 'ArrowUp') { event.preventDefault(); selectedSearch = (selectedSearch - 1 + items.length) % items.length; renderSearch(searchInput.value); }
    if (event.key === 'Enter') { event.preventDefault(); searchDialog.close(); renderModule(items[selectedSearch].key); }
});
document.addEventListener('keydown', (event) => {
    const tag = event.target.tagName?.toLowerCase();
    if (event.key === '/' && !['input', 'textarea', 'select'].includes(tag) && !searchDialog.open) { event.preventDefault(); openSearch(); }
});

document.querySelector('[data-filter-toggle]')?.addEventListener('click', (event) => {
    const active = event.currentTarget.getAttribute('aria-pressed') === 'true';
    event.currentTarget.setAttribute('aria-pressed', String(!active));
    event.currentTarget.textContent = active ? 'Prioritas tinggi' : 'Semua prioritas';
    document.querySelectorAll('[data-action-table] tr').forEach((row) => { row.hidden = !active && row.dataset.priority !== 'high'; });
});
document.querySelectorAll('[data-period]').forEach((button) => button.addEventListener('click', () => {
    document.querySelectorAll('[data-period]').forEach((item) => item.classList.toggle('active', item === button));
    showToast('Periode diperbarui', `Portfolio menampilkan ringkasan ${button.dataset.period.toLowerCase()} (data demonstrasi).`);
}));
document.querySelector('[data-export-dashboard]')?.addEventListener('click', () => showToast('Ringkasan disiapkan', 'Pada produksi, ekspor berjalan sebagai job dengan audit dan notifikasi.'));
document.querySelector('[data-scope-action]')?.addEventListener('click', () => showToast('Pemilih lingkup', 'Lingkup mengikuti unit dan kewenangan pengguna aktif.'));
document.querySelector('[data-lineage-action]')?.addEventListener('click', () => showToast('Rantai lineage', 'Mandate → Plan/Budget → Activity → LPJ → Snapshot → Verification → Approval → Archive.'));
document.querySelector('[data-module-secondary]')?.addEventListener('click', () => renderModule('audit'));
document.querySelector('[data-module-action]')?.addEventListener('click', () => showToast('Aksi modul siap', 'Simulasi UI: mutasi produksi harus melalui command backend, policy, validasi, dan audit.'));
document.querySelector('[data-toast-close]')?.addEventListener('click', () => { window.clearTimeout(toastTimer); toast.hidden = true; });

const initialModule = window.location.hash.slice(1);
renderModule(initialModule && modules[initialModule] ? initialModule : 'dashboard');
