# Product Requirements Document — FactoryOS
**Version:** 1.0  
**Status:** Draft  
**Last Updated:** 2026-07-06

---

## 1. Problem Statement

Pabrik menengah Indonesia (50–500 karyawan) menjalankan operasi produksi harian
dengan tiga alat utama: **Excel, WhatsApp, dan intuisi kepala produksi.**

Akibatnya:

**Penjadwalan produksi dilakukan secara manual.**
PPIC membuat jadwal di Excel tanpa mempertimbangkan kapasitas mesin secara sistematis.
Ketika ada WO baru masuk atau ada mesin breakdown, jadwal harus dibuat ulang dari nol.
Tidak ada cara objektif untuk membandingkan apakah urutan A lebih baik dari urutan B.

**OEE tidak diukur atau diukur tidak konsisten.**
Sebagian pabrik mencatat downtime di buku atau kertas shift, lalu direkap manual di
Excel pada akhir bulan. Ketika rekap selesai, kesempatan untuk perbaikan sudah lewat.
Pareto downtime — yang seharusnya jadi prioritas perbaikan — tidak pernah dihitung.

**Keputusan pembelian material berbasis feeling.**
Tidak ada formula yang menghitung kapan harus order dan berapa banyak. Akibatnya:
pabrik kelebihan stok di satu material, kehabisan di material lain, dan sering terjadi
produksi berhenti karena material tidak tersedia saat dibutuhkan.

**Tidak ada satu sumber kebenaran.**
Data jadwal ada di Excel satu orang. Data produksi ada di buku shift operator.
Data stok ada di sistem terpisah atau Excel lain. Ketiga data ini tidak pernah
terhubung, sehingga laporan manajemen selalu terlambat dan sering tidak akurat.

---

## 2. Goals

### Goal Bisnis
- Mengurangi Work Order terlambat minimal 30% dalam 3 bulan pemakaian
- Meningkatkan OEE rata-rata pabrik minimal 10 poin persentase dalam 6 bulan
- Menghilangkan kejadian stockout material yang menyebabkan produksi berhenti
- Mengurangi waktu pembuatan laporan produksi mingguan dari ~4 jam menjadi <15 menit

### Goal Produk
- Menyediakan satu platform terpadu yang menghubungkan jadwal, produksi, dan inventory
- Mengotomasi kalkulasi yang sebelumnya manual (OEE, EOQ, MRP) dengan akurasi tinggi
- Memberikan rekomendasi berbasis algoritma yang bisa diaudit dan dipertanggungjawabkan
- Bisa dioperasikan oleh operator lantai produksi tanpa pelatihan teknis yang panjang

### Bukan Goal (Out of Scope v1)
- Bukan ERP lengkap — tidak ada modul akuntansi, HR, atau CRM
- Bukan pengganti MES (Manufacturing Execution System) skala enterprise
- Tidak ada integrasi langsung ke mesin (IoT sensor) pada v1
- Tidak ada modul penjualan atau hubungan ke customer

---

## 3. Target Users

### Primary Users

**Production Manager / Kepala Produksi**
Bertanggung jawab atas output dan on-time delivery. Keputusan sehari-hari mencakup
alokasi mesin dan prioritas WO. Pain point utama: tidak punya data objektif untuk
menjelaskan keputusan ke manajemen. Ekspektasi dari FactoryOS: dashboard yang
langsung menunjukkan kondisi lantai produksi, perbandingan algoritma penjadwalan,
dan alert dini jika ada WO yang berisiko terlambat.

**PPIC (Production Planning & Inventory Control)**
Bertanggung jawab atas perencanaan produksi dan ketersediaan material. Pain point:
harus rekonsiliasi data dari banyak sumber sebelum bisa ambil keputusan, dan sering
kali terlambat menyadari material hampir habis. Ekspektasi: MRP grid yang jelas,
reorder alert otomatis, dan EOQ yang sudah terhitung tanpa perlu buka kalkulator.

**Operator Lantai Produksi**
Mengoperasikan mesin dan mencatat hasil produksi per shift. Pain point: form
pencatatan yang rumit atau harus kembali ke kantor untuk input data. Ekspektasi:
form input yang bisa diakses dari tablet di lantai produksi, sederhana, dan
tidak membutuhkan pemahaman teknis mendalam.

### Secondary Users

**Manajer Umum / Direktur Operasional**
Tidak terlibat operasional harian. Butuh ringkasan eksekutif untuk meeting dan
pengambilan keputusan strategis. Ekspektasi: dashboard KPI dan laporan PDF yang
bisa dicetak tanpa perlu masuk ke detail sistem.

---

## 4. User Stories

### Engine 1 — Job Shop Scheduler

**US-01**
Sebagai PPIC, saya ingin membuat Work Order baru dengan informasi produk, qty,
dan due date, agar tim produksi tahu apa yang harus dikerjakan dan kapan selesainya.

**US-02**
Sebagai PPIC, saya ingin menjalankan algoritma penjadwalan otomatis (SPT/EDD/CR/FIFO)
dan melihat perbandingan hasilnya dalam satu tabel, agar saya bisa memilih jadwal
yang paling meminimalkan keterlambatan secara objektif.

**US-03**
Sebagai Production Manager, saya ingin melihat Gantt Chart interaktif dari jadwal
yang dipilih, agar saya bisa dengan cepat melihat alokasi mesin dan identifikasi
WO yang berisiko terlambat.

**US-04**
Sebagai Production Manager, saya ingin bisa hover ke sebuah bar di Gantt Chart
dan melihat detail operasi (WO, produk, durasi, status), agar saya tidak perlu
membuka tabel terpisah untuk informasi detail.

**US-05**
Sebagai PPIC, saya ingin mengekspor jadwal terpilih sebagai PDF, agar bisa dibagikan
ke kepala shift tanpa perlu akses ke sistem.

### Engine 2 — OEE & Downtime

**US-06**
Sebagai Operator, saya ingin mengisi form log produksi harian (planned time, downtime,
output, good output) dalam satu halaman yang sederhana, agar pencatatan tidak memakan
waktu lebih dari 5 menit per shift.

**US-07**
Sebagai Operator, saya ingin menambahkan detail downtime (kategori, alasan, durasi)
langsung di form yang sama, agar data downtime tercatat akurat tanpa form terpisah.

**US-08**
Sebagai Production Manager, saya ingin melihat OEE setiap mesin terupdate secara
otomatis setelah operator submit log, agar saya bisa memantau kondisi lantai produksi
tanpa menunggu rekap harian.

**US-09**
Sebagai Production Manager, saya ingin melihat Pareto Chart downtime untuk rentang
tanggal tertentu, agar saya tahu kategori mana yang menyumbang 80% masalah dan
harus diprioritaskan untuk perbaikan.

**US-10**
Sebagai Manajer Umum, saya ingin mengekspor laporan OEE bulanan sebagai Excel,
agar bisa dipresentasikan dalam meeting review bulanan.

### Engine 3 — Inventory Optimizer

**US-11**
Sebagai PPIC, saya ingin memasukkan parameter material (annual demand, ordering cost,
holding cost, lead time), lalu sistem otomatis hitung EOQ, Safety Stock, dan ROP,
agar saya punya angka objektif sebagai dasar keputusan pembelian.

**US-12**
Sebagai PPIC, saya ingin menerima alert otomatis ketika stok material mendekati atau
di bawah Reorder Point, agar saya tidak pernah kehabisan material karena lupa monitor.

**US-13**
Sebagai PPIC, saya ingin menjalankan MRP setelah jadwal produksi dibuat dan melihat
grid kebutuhan material per periode, agar saya tahu material apa yang harus dipesan
dan kapan harus keluar PO-nya.

**US-14**
Sebagai PPIC, saya ingin mengekspor MRP grid sebagai Excel, agar bisa digunakan
sebagai dasar pembuatan Purchase Order di sistem procurement yang sudah ada.

**US-15**
Sebagai Production Manager, saya ingin melihat dashboard yang merangkum kondisi
ketiga engine (jadwal aktif, OEE hari ini, alert stok), agar saya punya satu titik
pantau tanpa harus membuka banyak halaman.

---

## 5. Functional Requirements

### FR-01 — Manajemen Master Data
- Sistem dapat membuat, membaca, mengubah, dan menonaktifkan Work Center
- Sistem dapat membuat, membaca, mengubah, dan menghapus Product dan Material
- Sistem dapat mendefinisikan BOM per produk: daftar material beserta qty per unit produk
- Sistem dapat mendefinisikan Routing per produk: urutan operasi dan mesin yang digunakan
- Sistem memvalidasi kelengkapan BOM dan Routing sebelum Work Order bisa dibuat

### FR-02 — Work Order Management
- Sistem dapat membuat Work Order dengan atribut: produk, qty, due date, priority, release date
- Sistem otomatis men-generate `wo_operations` dari routing produk saat WO dibuat
- Sistem menampilkan dan melacak status WO: draft → scheduled → in_progress → done / late
- Sistem mencegah penghapusan WO yang sudah berstatus in_progress atau done

### FR-03 — Job Shop Scheduling
- Sistem dapat menjalankan empat algoritma dispatching: SPT, EDD, CR, FIFO
- Sistem dapat menjalankan keempat algoritma sekaligus dan menampilkan perbandingan metrik
- Metrik yang dihitung per algoritma: Makespan, Total Tardiness, Late WO Count, Mean Flow Time
- Setiap run algoritma menghasilkan record Schedule baru yang immutable
- Sistem menampilkan Gantt Chart interaktif dari schedule yang dipilih
- Gantt Chart menampilkan due date line merah dan highlight bar operasi yang akan terlambat
- Sistem dapat mengekspor jadwal terpilih sebagai PDF

### FR-04 — Log Produksi & OEE
- Operator dapat mengisi log produksi per shift per mesin per hari
- Log mencakup: planned minutes, downtime minutes, actual output, good output, ideal cycle time
- Operator dapat menambah satu atau lebih downtime events per log (kategori, alasan, durasi)
- Sistem otomatis menghitung OEE (Availability × Performance × Quality) saat log disimpan
- Performance di-cap pada 1.0 dan tidak bisa melebihi 100%
- Log yang sudah divalidasi tidak bisa diedit; koreksi via adjustment entry baru
- Dashboard OEE menampilkan nilai terkini per mesin secara real-time via WebSocket

### FR-05 — Pareto & Trend Analysis
- Sistem menghitung Pareto downtime untuk rentang tanggal dan filter mesin tertentu
- Pareto menampilkan: kategori, total menit, persentase, persentase kumulatif
- Sistem menampilkan trend OEE harian per mesin dalam bentuk chart garis
- Sistem membandingkan OEE aktual dengan benchmark world class (85%)

### FR-06 — Inventory & EOQ
- Sistem mencatat stok on-hand dan on-order per material
- Setiap pergerakan stok dicatat sebagai transaction immutable (masuk, keluar, adjust)
- PPIC dapat mengisi parameter EOQ per material dan sistem otomatis menghitung EOQ, Safety Stock, dan ROP
- Sistem menampilkan visual perbandingan stok on-hand vs safety stock vs ROP

### FR-07 — MRP
- Sistem dapat menjalankan MRP berdasarkan schedule yang dipilih
- MRP melakukan BOM explosion dan menghitung kebutuhan material per periode
- Output MRP: Gross Requirement, Projected On-Hand, Net Requirement, Planned Order Release
- Planned Order Release mempertimbangkan lead time supplier (backward scheduling)
- Qty Planned Order Release dibulatkan ke atas ke kelipatan EOQ

### FR-08 — Reorder Alert
- Sistem memeriksa stok vs ROP setiap hari pukul 06:00 secara otomatis
- Jika stok ≤ ROP, sistem membuat reorder alert dengan status open
- PPIC dapat mengubah status alert: open → acknowledged → ordered
- Alert tertutup otomatis saat inventory transaction type=in masuk untuk material tersebut

### FR-09 — Export
- Sistem mengekspor laporan jadwal produksi sebagai PDF
- Sistem mengekspor laporan OEE harian/bulanan sebagai Excel
- Sistem mengekspor MRP grid sebagai Excel multi-sheet (ringkasan, grid, planned orders)
- Export diproses secara background (queued job), file tersedia untuk download 7 hari

### FR-10 — Dashboard
- Dashboard menampilkan KPI ringkasan lintas tiga engine:
  Engine 1: jumlah WO aktif, WO terlambat, makespan jadwal aktif
  Engine 2: OEE rata-rata hari ini, mesin dengan OEE terendah
  Engine 3: jumlah reorder alert open, material dengan stok kritis

### FR-11 — Autentikasi & Otorisasi
- Semua halaman kecuali login membutuhkan autentikasi
- Production Log hanya bisa diedit oleh pembuatnya atau admin, dan hanya sebelum divalidasi
- Schedule tidak bisa dihapus kecuali oleh admin

---

## 6. Non-Functional Requirements

### NFR-01 — Akurasi Kalkulasi
Semua kalkulasi kritis (OEE, EOQ, Safety Stock, ROP, MRP netting) menggunakan PHP bcmath
dengan presisi minimum 6 desimal. Input yang sama selalu menghasilkan output yang sama.

### NFR-02 — Performa
- Halaman utama load dalam < 2 detik pada koneksi LAN internal pabrik
- Algoritma scheduling untuk 50 WO dan 20 mesin selesai dalam < 10 detik
- MRP explosion untuk 50 material dan 30 hari periode selesai dalam < 15 detik
- Export PDF/Excel diproses di background; user tidak perlu menunggu di halaman yang sama

### NFR-03 — Ketersediaan
- Sistem berjalan on-premise tanpa dependency ke layanan cloud berbayar
- Queue worker dan scheduler dikelola via Supervisor untuk auto-restart jika crash

### NFR-04 — Kemudahan Penggunaan
- Operator lantai produksi dapat mengisi log produksi dalam < 5 menit setelah pelatihan 1 sesi
- Seluruh label dan pesan error UI menggunakan Bahasa Indonesia
- Form input memiliki validasi real-time dengan pesan error yang jelas dan actionable

### NFR-05 — Keamanan Data
- Semua route dilindungi autentikasi Laravel
- Immutability log dan schedule di-enforce di level Policy, bukan hanya UI
- Tidak ada data yang dikirim ke server eksternal

### NFR-06 — Maintainability
- Arsitektur Thin Controller + Service Layer memudahkan penambahan fitur
- Setiap Service memiliki unit test yang meng-cover semua kalkulasi kritis
- Setiap formula engineering memiliki referensi ke file docs/ yang relevan

### NFR-07 — Skalabilitas (dalam batas on-premise)
- Database menggunakan NUMERIC type dan indexing yang tepat untuk query analitik
- Queue worker dapat ditambah jumlahnya via Supervisor jika beban meningkat
- Arsitektur mendukung penambahan engine baru tanpa mengubah engine yang sudah ada

---

## 7. Scope

### Dalam Scope (v1)

| Modul | Fitur |
|---|---|
| Master Data | Work Center, Product, Material, BOM, Routing |
| Work Order | CRUD, status tracking, generate operations dari routing |
| Scheduling | SPT/EDD/CR/FIFO, perbandingan metrik, Gantt Chart interaktif, export PDF |
| Produksi | Log harian per shift per mesin, downtime events, validasi supervisor |
| OEE | Kalkulasi otomatis, dashboard real-time, trend chart, Pareto, world class benchmark |
| Inventory | Stok on-hand, transaksi immutable, parameter EOQ, kalkulasi EOQ/SS/ROP, ROP gauge |
| MRP | BOM explosion, grid kebutuhan per periode, planned order release |
| Alert | Reorder alert otomatis harian, status tracking open/acknowledged/ordered |
| Export | PDF jadwal, PDF OEE, Excel MRP, Excel OEE trend |
| Dashboard | KPI ringkasan lintas 3 engine dalam satu halaman |
| Auth | Login, register, session management, policy per resource |

### Luar Scope — Kandidat v2

| Fitur | Alasan Ditunda |
|---|---|
| Integrasi IoT / sensor mesin | Butuh infrastruktur hardware tambahan |
| Modul akuntansi / biaya produksi | Scope ERP, bukan fokus platform ini |
| Manajemen Purchase Order | Bisa dihandle manual dari output MRP grid |
| Notifikasi email / WhatsApp | Butuh integrasi eksternal; in-app alert sudah cukup untuk v1 |
| Multi-pabrik / multi-site | Kompleksitas data isolation; single-tenant dulu |
| Mobile app native | Web responsive sudah cukup untuk tablet lantai produksi |
| AI / LLM recommendation | Tidak ada paid API; algoritma deterministik sudah memadai |
| Kapasitas dinamis / nonlinear scheduling | Ditunda ke v2 untuk kasus routing yang lebih kompleks |

### Batasan Teknis yang Ditetapkan
- Single tenant — satu instalasi untuk satu pabrik
- On-premise — tidak ada cloud deployment pada v1
- Browser modern (Chrome, Firefox, Edge versi terkini)
- Resolusi minimum: 1280×720 untuk desktop, 768px untuk tablet operator