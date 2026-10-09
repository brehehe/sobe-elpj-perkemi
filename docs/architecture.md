# Arsitektur Smart e-LPJ

## Boundary dan sumber kebenaran

Aplikasi memakai modular monolith. Laravel menangani autentikasi, policy, workflow, persistence, queue, integrasi, dan `/api/v1`; React + Inertia menangani ruang kerja internal. PostgreSQL adalah source of truth, sedangkan Redis hanya dipakai untuk cache, session, queue, rate limiting, dan lock.

`Activity-ID` adalah jangkar seluruh data. Transaksi pada `financial_transactions` merupakan referensi/snapshot dari provider resmi dengan pasangan unik `(source, external_id)`, bukan ledger baru. Evidence dan verified pack disimpan pada disk privat.

## Aggregate utama

- `Unit → User, Mandate, Activity`
- `Mandate → Activity`
- `Activity → Participant, FinancialTransaction, Evidence, Evaluation, LPJ`
- `LPJ → LPJ Version → Verification, Finding/CAPA, Approval, Archive`
- `Activity → Smart Task, AI Review`
- `Integration Run` mencatat idempotency key, attempt, hasil, retry, dan dead-letter.
- `Audit Event` append-only mencatat aktor, objek, transisi, request metadata, dan payload aman.

## State machine LPJ

```text
DRAFT / READY_TO_SUBMIT
  → IN_VERIFICATION (snapshot immutable baru)
  → CLARIFICATION_REQUIRED | FINDING_OPEN | RETURNED
  → IN_VERIFICATION (setelah klarifikasi/CAPA) atau resubmit sebagai versi baru
  → READY_FOR_APPROVAL
  → APPROVED
  → PB_VERIFIED
  → ARCHIVED
```

Setiap submit menaikkan `current_version`; snapshot lama dan checksum tidak diubah. Tiga verifikasi (`ADMINISTRATION`, `FINANCE`, `SUBSTANTIVE`) harus `PASSED` dan tidak boleh ada finding material terbuka sebelum approval.

## Segregation of duties

| Capability | Role demo | Invariant backend |
| --- | --- | --- |
| Compose/submit | Owner/PIC, Finance PIC | Hanya unit yang sama; sumber wajib lengkap |
| Verify admin | Verifier Admin | Tidak boleh pengaju |
| Verify finance | Verifier Finance | Tidak boleh pengaju |
| Verify substantive | Verifier Substantive | Tidak boleh pengaju |
| Raise/review finding | Verifier | Pengaju CAPA tidak boleh mereview sendiri |
| Approval | Approver | Tidak boleh pengaju atau verifier |
| PB verification | PB Verifier | Tidak boleh pengaju, verifier, atau approver versi yang sama |
| Archive | User berizin | Hanya status `PB_VERIFIED` |

Role menentukan capability melalui Spatie Permission; Policy tetap memeriksa object dan scope unit. Super Admin merupakan satu-satunya bypass global.

## AI boundary

`OpenAiReviewer` menerima sumber yang dipilih server, memakai Responses API dengan strict JSON Schema, `store: false`, timeout, rate limit, dan idempotency header. Output disimpan sebagai rekomendasi dengan confidence, limitations, prompt hash, sumber, dan status human review. Adapter tidak memiliki jalur kode untuk mengubah transaksi atau status workflow. Tanpa API key atau ketika layanan gagal, alur inti tetap berjalan dengan rules engine lokal/non-blocking.

## Operasional

- Worker: `php artisan queue:work --tries=5 --backoff=30`
- Scheduler: backup DB 01:00, cleanup 02:00, monitor 07:00
- Health endpoint: `/up`
- Evidence/archive: disk privat; akses melalui controller ber-policy dan menghasilkan audit event
- Retry integrasi: exponential backoff job; kegagalan terakhir menjadi `DEAD_LETTER`
- Backup: ZIP diverifikasi setelah dibuat; restore wajib dilakukan pada lingkungan terisolasi.

Keputusan organisasi yang belum bisa dikodekan tanpa authority matrix nyata—batas nominal, delegasi, SSO, ledger, retensi, dan format QR/tanda tangan—tetap harus ditetapkan pemilik proses sebelum produksi.
