# Feature Specification: Freemium Paywall, Zero-Knowledge Nominal Encryption, and Production Dockerization

**Feature Branch**: `002-freemium-privacy-docker`

**Created**: 2026-09-09

**Status**: Draft

**Input**: User description: "saya mau menambahkan versi berbayar nya, misalnya hanya gratis maksimal 100 percakapan, habis itu harus berlangganan, terus sangat perhatikan keamanan dan performa nya, saya mau chatbot ini siap publish dan punya docker agar gampang publish nya, terus saya mau untuk nominalnya hanya user yang bisa tau jadi enkripsi satu arah bahkan developer pun gak bisa liat nominal uang dari si user nya"

## Clarifications

### Session 2026-09-09
- Q: Bagaimana mekanisme penanganan jika pengguna lupa 6-digit PIN privasi enkripsi mereka? → A: Pengguna diberikan 16-karakter Recovery Code saat pertama kali membuat PIN; jika lupa PIN, pengguna dapat memasukkan Recovery Code tersebut via chat untuk mereset PIN tanpa kehilangan data historis.
- Q: Berapa nominal harga dan durasi masa aktif paket langganan berbayar (Midtrans) yang ingin diberlakukan untuk akun Premium? → A: Rp 25.000 per 30 hari aktif.

### Session 2026-09-10
- Q: Jika seorang pengguna mengirimkan satu pesan WhatsApp yang berisi beberapa transaksi sekaligus (misalnya `keluar 50000 bensin, 20000 parkir`), apakah sistem menghitung ini sebagai 1 pengurangan kuota atau 2? → A: 1 pesan WhatsApp = 1 pengurangan kuota, terlepas dari berapa transaksi yang di-parse dari pesan tersebut.
- Q: Bagaimana alur onboarding pengguna baru yang belum pernah mengatur PIN enkripsi — apakah bot otomatis meminta PIN saat transaksi pertama, atau pengguna harus eksplisit menjalankan perintah setup PIN? → A: Bot secara otomatis memicu alur setup PIN (dan menghasilkan Recovery Code) saat pengguna pertama kali mengirimkan pesan transaksi finansial.
- Q: Jika pengguna mengubah PIN enkripsi mereka (via reset menggunakan Recovery Code), apa yang terjadi pada seluruh data transaksi lama yang sudah terenkripsi dengan kunci turunan PIN sebelumnya? → A: Sistem melakukan re-enkripsi seluruh data historis dengan kunci turunan PIN baru secara otomatis setelah PIN berhasil direset, sehingga hanya 1 kunci aktif per pengguna.
- Q: Jika webhook pembayaran Midtrans tiba saat pengguna masih memiliki sisa masa aktif langganan yang belum berakhir, apakah masa aktif yang baru ditambahkan ke atas sisa waktu (*extend/stack*) atau direset menjadi 30 hari dari tanggal pembayaran? → A: Masa aktif 30 hari baru ditambahkan di atas sisa waktu yang ada (`new_expires_at = current_expires_at + 30 hari`).

---

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Freemium Usage Quota & Subscription Paywall (Priority: P1)

Sebagai pengguna WhatsApp Bot baru, saya ingin dapat mencoba mencatat transaksi keuangan secara gratis hingga batas kuota (100 percakapan/transaksi), dan ketika kuota habis saya menerima peringatan berlangganan yang jelas dengan opsi peningkatan akun (*upgrade*) agar saya dapat terus menggunakan layanan bot secara penuh.

**Why this priority**: Menghadirkan model bisnis freemium berkelanjutan yang memungkinkan monetisasi bot, sekaligus melindungi kapasitas server dari eksploitasi penggunaan gratis tak terbatas.

**Independent Test**: Dapat diuji secara independen dengan menyimulasikan pengguna yang mengirim pesan transaksi dari percakapan ke-1 hingga ke-100 (berhasil diproses), dan pada percakapan ke-101 sistem menolak pemrosesan transaksi baru serta mengembalikan pesan tagihan langganan.

**Acceptance Scenarios**:
1. **Given** pengguna baru memiliki kuota aktif (misal sisa 5 percakapan), **When** pengguna mencatat transaksi `keluar 25000 makan`, **Then** transaksi berhasil dicatat dan sisa kuota berkurang 1.
2. **Given** pengguna telah menggunakan 100 percakapan gratis (kuota habis), **When** pengguna mengirim pesan transaksi baru, **Then** bot menolak pencatatan dan mengirim balasan ramah bahwa kuota gratis telah habis beserta petunjuk/link aktivasi langganan.
3. **Given** pengguna telah melakukan langganan (status akun `PREMIUM`/`ACTIVE_SUBSCRIBER`), **When** pengguna mengirim transaksi melampaui 100 kali, **Then** bot tetap memproses transaksi secara tanpa batas (*unlimited*).
4. **Given** pengguna dengan kuota habis, **When** pengguna mengetik perintah non-transaksi dasar (seperti `saldo`, `bantuan`, atau `status langganan`), **Then** bot tetap melayani pertanyaan tersebut tanpa memblokir akses ke informasi data historis milik pengguna.

---

### User Story 2 - Zero-Knowledge Financial Privacy & Nominal Encryption (Priority: P2)

Sebagai pengguna yang sadar privasi, saya ingin seluruh nominal transaksi dan saldo saya tersimpan dalam keadaan terenkripsi di database sehingga pihak developer, pengelola basis data, maupun pihak ketiga yang memiliki akses fisik ke server tidak dapat membaca nominal uang saya secara telanjang (*plaintext*).

**Why this priority**: Memenuhi standar privasi perbankan/finansial tingkat tinggi (*zero-knowledge architecture*) dan melindungi integritas kerahasiaan kekayaan pengguna dari kebocoran data (*data breach*).

**Independent Test**: Dapat diuji dengan mencatat transaksi `masuk 1000000 gaji`, lalu menginspeksi baris data di database mentah. Kolom nominal transaksi dan saldo harus tersimpan dalam bentuk *ciphertext* terenkripsi (bukan angka polos `1000000`), dan saat pengguna meminta `saldo` via akun WhatsApp terotentikasi, nilai asli berhasil didekripsi dan ditampilkan secara akurat.

**Acceptance Scenarios**:
1. **Given** pengguna baru pertama kali mengirimkan pesan transaksi finansial (belum memiliki PIN aktif), **When** sistem mendeteksi tidak ada PIN terdaftar untuk JID pengguna tersebut, **Then** bot menunda pencatatan transaksi, memulai alur setup PIN secara otomatis, menghasilkan 16-karakter Recovery Code, dan menampilkan Recovery Code tersebut kepada pengguna dengan instruksi penyimpanan yang jelas.
2. **Given** pengguna mencatat transaksi `keluar 50000 bensin`, **When** data disimpan ke basis data, **Then** kolom nominal amount dan balance terenkripsi secara kriptografis sehingga nilai angka aslinya tidak dapat dibaca oleh kueri langsung administrator basis data.
3. **Given** data tersimpan dalam bentuk terenkripsi, **When** pengguna yang sah mengirim pesan `saldo` atau `rekap`, **Then** sistem mendekripsi data transaksi hanya untuk konteks pemrosesan pengguna tersebut dan menyajikan laporan angka yang benar.
4. **Given** pengembang atau penyusup membuka tabel transaksi di server penyimpanan, **When** mereka melihat rekaman keuangan, **Then** tidak ditemukan angka nominal uang dalam bentuk teks biasa.

---

### User Story 3 - Production Readiness & High-Performance Security Hardening (Priority: P3)

Sebagai pengelola sistem (SRE / Sysadmin), saya ingin bot memiliki sistem keamanan yang tangguh (rate limiting, anti-spam WhatsApp, sanitasi data ketat, dan proteksi DoS) serta performa tinggi yang responsif agar bot siap melayani ribuan pengguna tanpa risiko akun diblokir oleh pihak WhatsApp atau server kehabisan memori.

**Why this priority**: Menjaga stabilitas operasional, mematuhi prinsip anti-ban WhatsApp, dan memastikan waktu respon interaksi tetap di bawah batas nyaman pengguna (< 1.5 detik).

**Independent Test**: Dapat diuji dengan mengirimkan tembakan pesan cepat berturut-turut (misal 20 pesan per detik dari satu nomor). Sistem harus menerapkan mekanisme pelambatan (*rate limit*) dan antrean keluar (*throttling*), serta membuang pesan spam tanpa menyebabkan aplikasi backend lumpuh atau melanggar kebijakan frekuensi pesan WhatsApp.

**Acceptance Scenarios**:
1. **Given** lonjakan pesan masuk serentak dari puluhan pengguna, **When** gateway menerima pesan-pesan tersebut, **Then** proses penerusan pesan ke backend berjalan asinkron dan latensi pengakuan pesan awal tetap di bawah 1.5 detik.
2. **Given** satu nomor mengirimkan pesan berulang secara masif (indikasi spam/bot flooding), **When** batas ambang laju terlewati, **Then** sistem secara aman menahan respon dan memberikan peringatan jeda waktu tanpa memutus koneksi gateway.
3. **Given** seluruh proses transaksi finansial, **When** transaksi dieksekusi, **Then** data diproses dengan jaminan integritas ACID untuk mencegah anomali mutasi ganda.

---

### User Story 4 - Multi-Container Docker Deployment & Zero-Configuration Publish (Priority: P4)

Sebagai pengembang perangkat lunak, saya ingin seluruh ekosistem aplikasi (Backend Laravel API, WhatsApp Gateway Baileys, Database/Storage, dan Webhook Bridge) terangkum dalam konfigurasi Docker dan Docker Compose siap pakai (*production-ready*) agar aplikasi dapat dipublikasikan ke server cloud (VPS/PaaS) hanya dengan satu perintah.

**Why this priority**: Memudahkan orkestrasi layanan, menjamin konsistensi lingkungan antara pengembangan lokal dan server produksi, serta mempermudah pencadangan data sesi WhatsApp dan database.

**Independent Test**: Dapat diuji dengan menjalankan `docker compose up -d` pada mesin bersih (*clean environment*). Seluruh kontainer (backend, gateway, dan penyimpanan) harus menyala secara otomatis dengan status *healthy*, volume terpasang dengan benar, dan bot siap menerima instruksi pairing.

**Acceptance Scenarios**:
1. **Given** server produksi dengan Docker dan Docker Compose terpasang, **When** perintah penyebaran dijalankan, **Then** seluruh layanan (Laravel backend dan Node.js Baileys gateway) beroperasi otomatis tanpa memerlukan instalasi manual PHP atau Node.js pada host.
2. **Given** kontainer dijalankan ulang atau diperbarui (*restart/recreate*), **When** kontainer aktif kembali, **Then** sesi autentikasi WhatsApp (`auth_info_baileys`) dan basis data tidak hilang berkat pengelolaan *named volume / persistent volume*.
3. **Given** beban kontainer mengalami kendala, **When** mekanisme *healthcheck* mendeteksi kegagalan, **Then** orchestrator otomatis memicu pemulihan layanan secara terisolasi.

---

### Edge Cases

- **Pengguna Mencapai Batas Kuota di Tengah Transaksi**: Pesan finansial ke-100 berhasil disimpan (termasuk seluruh entri transaksi yang di-parse dari pesan tersebut) dan dikonfirmasi dengan catatan peringatan bahwa kuota telah habis. Pesan finansial ke-101 dan seterusnya akan ditahan sampai langganan diaktifkan.
- **Pesan Multi-Transaksi dan Debit Kuota**: Jika satu pesan berisi beberapa item transaksi (misalnya `keluar 50000 bensin, 20000 parkir`), seluruh entri dalam pesan tersebut diproses sekaligus atau tidak sama sekali (atomik), dan hanya 1 unit kuota yang didebit untuk keseluruhan pesan tersebut.
- **Re-Enkripsi Massal Gagal di Tengah Proses**: Jika proses re-enkripsi data historis terganggu (misal koneksi putus), sistem HARUS menyelesaikan proses dalam sebuah transaksi database atomik — seluruh data berhasil di-re-enkripsi atau tidak ada yang berubah sama sekali; PIN reset dinyatakan gagal dan pengguna diminta mencoba ulang.
- **Pembayaran Masuk saat Langganan Masih Aktif (Stacking)**: Jika webhook Midtrans terverifikasi diterima saat pengguna masih dalam masa aktif, sistem HARUS menghitung `new_expires_at = current_expires_at + 30 hari` (bukan dari tanggal bayar); konfirmasi aktivasi menampilkan tanggal kedaluwarsa baru yang telah diperpanjang.
- **Enkripsi Kriptografis vs Operasi Matematika Ledger**: Jika nominal dienkripsi satu arah (*hashing*), sistem tidak dapat menjumlahkan saldo secara matematis. Oleh karena itu, sistem harus menggunakan enkripsi simetris dengan kunci turunan privat (*user-derived key*) atau skema enkripsi homomorfik / zero-knowledge payload agar saldo tetap dapat dihitung dengan presisi tanpa mengekspos nominal asli ke publik.
- **Koneksi WhatsApp Putus saat Kontainer Berjalan**: Layanan gateway di dalam Docker harus memiliki logika rekoneksi otomatis (*auto-retry*) dengan *backoff* eksponensial tanpa mematikan seluruh kontainer backend.
- **Pesan dengan Format Nominal Sangat Besar atau Negatif**: Sistem menolak input angka abnormal atau negatif dengan pesan kesalahan validasi sebelum data masuk ke tahap enkripsi dan basis data.
- **Pengguna Lupa PIN Privasi**: Pengguna dapat memulihkan akses buku kas dan mereset PIN menggunakan 16-karakter Recovery Code rahasia tanpa memerlukan bantuan pihak administrator atau developer.

---

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: Sistem HARUS melacak jumlah **pesan finansial** yang dikirimkan oleh setiap pengguna berdasarkan WhatsApp ID (`user_jid`); unit kuota adalah per pesan WhatsApp, bukan per entri transaksi yang di-parse.
- **FR-002**: Sistem HARUS memberlakukan batas kuota gratis maksimal 100 **pesan finansial** untuk setiap akun baru; setiap pesan yang berhasil mencatat satu atau lebih transaksi dihitung sebagai 1 unit kuota.
- **FR-003**: Sistem HARUS memblokir pencatatan transaksi baru setelah kuota gratis 100 habis dan mengirimkan pesan notifikasi langganan berisi instruksi aktivasi.
- **FR-004**: Sistem HARUS menyediakan status keanggotaan pengguna (`FREE` atau `PREMIUM`) beserta tanggal kedaluwarsa langganan.
- **FR-005**: Pengguna HARUS dapat memeriksa status kuota dan masa aktif langganan mereka kapan saja dengan perintah `status langganan` atau `kuota`.
- **FR-006**: Sistem HARUS menyediakan mekanisme aktivasi langganan berbayar via integrasi Payment Gateway Midtrans (Snap link / QRIS) dan otomatis mengaktifkan status PREMIUM pengguna seketika setelah menerima notifikasi webhook pembayaran yang valid. Jika pengguna masih memiliki sisa masa aktif, periode 30 hari baru HARUS ditambahkan di atas sisa waktu tersebut (`new_expires_at = current_expires_at + 30 hari`).
- **FR-007**: Sistem HARUS mengenkripsi seluruh nominal finansial (pemasukan, pengeluaran, dan saldo) pada saat penyimpanan istirahat (*at-rest*) di basis data.
- **FR-008**: Sistem HARUS menerapkan arsitektur proteksi privasi nominal Zero-Knowledge berbasis User-Keyed Encryption (AES-256-GCM), di mana kunci enkripsi/dekripsi diturunkan dari PIN rahasia milik pengguna sehingga nominal hanya tersimpan sebagai ciphertext acak di database dan pihak pengembang maupun administrator server tidak dapat melihat nominal asli pengguna.
- **FR-009**: Sistem HARUS memastikan bahwa pengembang atau administrator basis data yang membaca tabel mentah tidak dapat melihat angka nominal transaksi dalam bentuk teks biasa (*plaintext*).
- **FR-010**: Sistem HARUS mendekripsi nominal secara aman di memori saat menyajikan laporan ringkasan (`saldo`, `rekap`, `export excel`) kepada pengguna WhatsApp yang terotentikasi dengan kunci PIN yang valid.
- **FR-011**: Gateway WhatsApp HARUS menerapkan pembatas laju pesan keluar (*outbound throttling queue*) untuk mencegah pemblokiran nomor (*anti-ban*) oleh penyedia WhatsApp.
- **FR-012**: Backend HARUS memproses pesan masuk dengan pengecekan idempoten berdasarkan ID pesan unik guna mencegah pemrosesan transaksi ganda.
- **FR-013**: Seluruh komponen aplikasi (Backend Laravel, WhatsApp Baileys Sidecar, dan dependensi pendukung) HARUS dikemas ke dalam berkas `Dockerfile` dan `docker-compose.yml` multi-layanan yang terisolasi.
- **FR-014**: Konfigurasi Docker HARUS menyediakan volume persisten terpisah untuk data sesi WhatsApp (`auth_info_baileys`), basis data, dan berkas ekspor spreadsheet.
- **FR-015**: Konfigurasi produksi HARUS menyertakan skrip pemeriksaan kesehatan (*health check endpoints*) untuk memantau status aktif backend dan gateway socket.
- **FR-016**: Sistem HARUS memberlakukan konsumsi kuota 100 gratis HANYA untuk pesan yang berhasil mencatat transaksi finansial (pemasukan dan pengeluaran); satu pesan yang berhasil di-parse menjadi beberapa entri transaksi tetap hanya memotong 1 unit kuota. Kueri pertanyaan data seperti `saldo`, `rekap`, `kategori`, dan `bantuan` tidak memotong kuota pengguna.
- **FR-017**: Sistem HARUS menghasilkan 16-karakter Recovery Code rahasia satu kali saat pengguna mengatur PIN awal, dan HARUS menyediakan alur pemulihan `reset pin <recovery_code> <pin_baru>` jika pengguna lupa PIN.
- **FR-018**: Sistem HARUS mematok harga paket langganan bulanan sebesar Rp 25.000 dengan masa aktif 30 hari kalender sejak notifikasi pembayaran Midtrans terverifikasi.
- **FR-019**: Sistem HARUS secara otomatis memicu alur setup PIN saat pengguna pertama kali mengirimkan pesan transaksi finansial (tanpa memerlukan perintah eksplisit dari pengguna); transaksi pertama tersebut ditangguhkan hingga pengguna menyelesaikan setup PIN dan Recovery Code diterima.
- **FR-020**: Saat pengguna berhasil mereset PIN menggunakan Recovery Code, sistem HARUS secara otomatis dan atomik me-re-enkripsi seluruh data transaksi historis pengguna tersebut dengan kunci turunan PIN baru, sehingga hanya satu kunci enkripsi aktif per pengguna yang berlaku di setiap waktu.

---

### Key Entities

- **User**: Pengguna bot yang diidentifikasi oleh WhatsApp JID unik, memiliki atribut profil, saldo terenkripsi, status tingkatan akun (`FREE`/`PREMIUM`), kuota pesan finansial, dan status setup PIN (`PENDING_PIN_SETUP` / `PIN_ACTIVE`).
- **Subscription**: Entitas yang merekam riwayat langganan pengguna, mencakup tanggal mulai, tanggal kedaluwarsa, tipe paket, dan status pembayaran.
- **UsageLog**: Pencatat frekuensi penggunaan pesan/transaksi pengguna untuk menghitung konsumsi kuota secara akurat dan mencegah eksploitasi.
- **EncryptedTransaction**: Entitas catatan keuangan di mana atribut nominal (`amount`) dan saldo berjalan (`balance_after`) disimpan dalam bentuk *ciphertext* terenkripsi.

---

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: 100% pengguna baru dapat menggunakan bot hingga mencapai tepat 100 percakapan/transaksi gratis sebelum diwajibkan berlangganan.
- **SC-002**: 100% data nominal transaksi dan saldo pada tabel basis data tersimpan dalam bentuk terenkripsi kriptografis dan tidak terbaca teks polos oleh administrator server.
- **SC-003**: Waktu respon pengakuan pesan (*acknowledgment latency*) dari pengguna mengirim chat hingga bot merespon rata-rata di bawah 1.5 detik pada beban penggunaan normal.
- **SC-004**: Seluruh stack aplikasi dapat dijalankan dari keadaan awal (*fresh clone*) di lingkungan baru menggunakan satu perintah Docker Compose dalam waktu kurang dari 3 menit.
- **SC-005**: Tidak terjadi kehilangan sesi WhatsApp (*session persistence*) maupun data keuangan saat kontainer Docker di-restart atau di-deploy ulang.
- **SC-006**: Tingkat keberhasilan pemrosesan transaksi tanpa anomali duplikasi (*zero duplicate processing*) mencapai 99.99% dengan perlindungan idempotensi.
- **SC-007**: 100% aktivasi langganan sukses memperpanjang masa aktif akun; jika akun masih aktif saat pembayaran terverifikasi, `expires_at` baru = `expires_at` lama + 30 hari; jika sudah kedaluwarsa, `expires_at` baru = tanggal verifikasi webhook + 30 hari.
- **SC-008**: Proses re-enkripsi data historis saat PIN direset harus bersifat atomik penuh: 100% berhasil atau 0% berubah; tidak boleh ada rekaman yang tersimpan dalam keadaan setengah terenkripsi (*partial re-encryption*) setelah operasi selesai.

---

## Assumptions

- Pengguna mengakses bot secara mandiri melalui aplikasi resmi WhatsApp di smartphone atau WhatsApp Web.
- Server target produksi memiliki dukungan untuk menjalankan Docker Engine (versi 24+) dan Docker Compose (versi 2+).
- Pengguna bersedia menyimpan 16-karakter Recovery Code yang dihasilkan secara otomatis oleh sistem saat PIN pertama kali dibuat melalui alur onboarding yang dipicu bot.
- Koneksi internet pada server penyedia stabil untuk menjaga koneksi WebSocket Baileys ke server WhatsApp.
