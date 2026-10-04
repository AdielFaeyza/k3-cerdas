# K3 Cerdas — CCTV + YOLOv8 + Laravel + React

Sistem pengawasan K3 berbasis computer vision dengan poin disiplin (demerit) real-time.

```
CCTV ──RTSP──▶ edge/ (Python, YOLOv8)
                 │  POST /api/v1/violations (multipart + X-Edge-Token)
                 ▼
             server/ (Laravel 11 + MySQL)
                 │  broadcast via Laravel Reverb (WebSocket)
                 ▼
             frontend/ (React + Vite) — dasbor HR & HSE
```

## Struktur

| Folder | Isi |
|---|---|
| `.vscode/` | Ekstensi yang disarankan, debug edge (F5), tasks "Jalankan Semua" |
| `edge/` | Deteksi APD, pembacaan QR, sesi pelanggaran, zona bahaya (homography), klien API + unit test |
| `backend/` | File Laravel (migrasi, model, controller, event, command) yang ditimpakan ke proyek Laravel baru |
| `frontend/` | Dasbor: LiveToastManager, ViolationFeed, LeaderboardTable, Spatial Heatmap |
| `scripts/setup-backend.sh` | Membuat `server/` (Laravel 11) dan memasang file `backend/` |

## Prasyarat
PHP 8.2+, Composer, Node 18+, Python 3.10+, Docker (untuk MySQL).

## Menjalankan

```bash
# 1. Database
docker compose up -d mysql

# 2. Backend
./scripts/setup-backend.sh
cd server && php artisan k3:user hse@perusahaan.co.id "Tim HSE" --role=hse   # catat token
php artisan serve            # terminal 1
php artisan reverb:start     # terminal 2

# 3. Frontend
cd frontend && cp .env.example .env   # isi VITE_API_TOKEN & VITE_REVERB_APP_KEY dari server/.env
npm install && npm run dev            # http://localhost:5173

# 4. Edge
cd edge && python -m venv .venv && source .venv/bin/activate   # Windows: .venv\Scripts\activate
pip install -r requirements.txt
python -m unittest discover -s tests
# taruh model di edge/models/k3-ppe-yolov8.pt dan video uji di edge/samples/uji.mp4
python main.py --test-video samples/uji.mp4 --show
```

Di VS Code: **Terminal → Run Task → Jalankan Semua**, lalu **F5 → "Edge: Uji dengan video file"**.

Uji API tanpa kamera:
```bash
curl -X POST http://localhost:8000/api/v1/violations -H "X-Edge-Token: ganti-dengan-token-rahasia" -H "Accept: application/json" \
  -F event_uuid=$(uuidgen) -F camera_code=CAM-ZONE-A -F type_code=NO_HELMET -F worker_code=ID-049 \
  -F detected_at=$(date -u +%FT%TZ) -F snapshot=@foto.jpg
```

## Model YOLOv8
Model COCO bawaan tidak punya kelas helm/rompi. Latih model kustom dari dataset APD (mis. "Hard Hat Workers" / "PPE Detection" di Roboflow Universe):
```bash
yolo detect train data=ppe.yaml model=yolov8s.pt epochs=100 imgsz=640
```
Sesuaikan nama kelas di `edge/config.yaml → model.classes`.

## Keputusan desain (perbaikan dari dokumen awal)

| Masalah | Solusi di kode |
|---|---|
| QR di helm hilang saat pelanggaran "tanpa helm" | QR utama di rompi (punggung & dada); ID di-cache per track ID |
| Jeda 10 detik memotong poin berulang | `SessionTracker`: 1 pelanggaran berkelanjutan = 1 laporan |
| False positive sesaat | Pelanggaran harus konsisten ≥ 1,5 detik |
| Pekerja tidak teridentifikasi | Status `unidentified`, ditetapkan manual oleh supervisor |
| Duplikat lintas kamera / retry | `event_uuid` unik + jendela dedupe 60 detik |
| `ON DELETE CASCADE` menghapus bukti | Soft delete + `restrictOnDelete` |
| Skor bulanan tidak tersimpan | Tabel `monthly_scores`, `php artisan k3:close-month` (terjadwal tgl 1) |
| Radius bahaya dalam piksel | Homography → meter di bidang lantai |
| Auto-shutdown dari CV | Hanya peringatan; shutdown wajib lewat interlock bersertifikat |
| File tidak ikut rollback | File disimpan dulu, dihapus jika transaksi gagal |
| Klaim "tak terbantahkan" | Alur review: konfirmasi / tolak (poin dikembalikan) / banding |
| Foto bukti publik (UU PDP) | Disk private + signed URL 30 menit, channel WebSocket private per role |
| Skor negatif | Skor minimum 0; poin yang benar-benar dipotong yang dicatat |

Ambang: skor < 70 → panggilan training (email); < 50 untuk kontrak/harian/magang → **rekomendasi** tidak diperpanjang untuk ditinjau HR (bukan keputusan otomatis).

## Catatan produksi
- `VITE_API_TOKEN` di frontend hanya untuk prototype; produksi gunakan login Sanctum SPA.
- Jalankan scheduler: `* * * * * php artisan schedule:run` dan Reverb di belakang supervisor/systemd.
- Tetapkan kebijakan retensi foto bukti dan persetujuan pekerja sesuai UU No. 27/2022 (PDP).
