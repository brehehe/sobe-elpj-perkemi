# API v1

Base path: `/api/v1`. Semua endpoint memakai Sanctum bearer token atau sesi first-party, JSON, throttling, permission, dan Policy unit/object.

## Endpoint

| Method | Path | Fungsi |
| --- | --- | --- |
| GET/POST | `/activities` | Daftar atau buat kegiatan |
| GET/PATCH/DELETE | `/activities/{activity}` | Baca/perbarui/hapus bila policy mengizinkan |
| POST | `/activities/{activity}/evidence` | Unggah evidence privat |
| GET | `/lpjs` | Daftar LPJ sesuai unit |
| GET | `/lpjs/{lpj}` | Decision pack LPJ |
| POST | `/lpjs/{lpj}/submissions` | Pre-check dan snapshot immutable |
| POST | `/lpjs/{lpj}/verifications` | Keputusan satu tahap verifikasi |
| POST | `/lpjs/{lpj}/findings` | Buat temuan |
| POST | `/findings/{finding}/corrective-actions` | Ajukan CAPA |
| POST | `/findings/{finding}/corrective-actions/{correctiveAction}/review` | Terima/tolak CAPA |
| POST | `/lpjs/{lpj}/approvals` | Keputusan approver/PB |
| POST | `/lpjs/{lpj}/archives` | Hasilkan verified pack immutable |
| POST | `/integrations/finance/transactions` | Sinkron transaksi sumber idempoten |
| POST | `/ai-reviews` | Pemeriksaan advisory berbasis sumber |

## Idempotensi finance

`idempotency_key` wajib stabil per request logis. Pengulangan key dengan payload identik mengembalikan transaksi yang sama; key yang sama dengan payload berbeda menghasilkan HTTP `409`.

```json
{
  "provider": "SIMKEU",
  "idempotency_key": "event-2026-000123",
  "activity_id": "019a...",
  "external_id": "ledger-88421",
  "reference_number": "BANK-9988",
  "description": "Pembayaran tempat kegiatan",
  "transaction_date": "2026-10-08",
  "amount": 2500000,
  "currency": "IDR"
}
```

## Verifikasi dan approval

Verifikasi menerima `stage` (`ADMINISTRATION`, `FINANCE`, `SUBSTANTIVE`), `status` (`PASSED`, `CLARIFICATION`, `FAILED`), serta catatan. Approval menerima `level` (`APPROVER`, `PB`) dan `decision` (`APPROVED`, `RETURNED`). Backend menolak state yang tidak valid dan conflict of interest walaupun client mengirim request langsung.

## Error contract

- `401`: tidak terautentikasi
- `403`: permission/policy menolak
- `404`: objek tidak ditemukan atau tersembunyi oleh scope unit
- `409`: konflik idempotensi
- `422`: validasi atau invariant workflow gagal; body berisi `message` dan `errors`
- `429`: rate limit

Secret, OpenAI key, path storage privat, password, dan token tidak pernah diserialisasi oleh API Resource.
