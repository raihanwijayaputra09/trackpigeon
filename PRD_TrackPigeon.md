PRD — TrackPigeon

Platform Lomba & Latihan Merpati Pos Berbasis ETS Mandiri

---

1. Ringkasan Produk

TrackPigeon adalah aplikasi web sederhana untuk digitalisasi lomba dan latihan merpati pos berbasis ETS buatan sendiri. Aplikasi ini dibuat untuk menjadi alternatif yang lebih murah, transparan, dan mudah dihosting secara mandiri menggunakan STB Armbian + Docker + Cloudflare Tunnel.

Berbeda dari sistem lomba yang masih memiliki input manual, TrackPigeon dirancang tanpa pencatatan manual untuk hasil lomba. Semua data waktu kedatangan merpati harus berasal dari perangkat ETS berbasis mikrokontroler, RFID, GPS, dan RTC.

Selain untuk lomba bersama klub, TrackPigeon juga memiliki fitur latihan mandiri, sehingga penghobi dapat mencatat performa burung sendiri tanpa harus mengikuti event klub.

---

2. Visi Produk

Menjadi platform sederhana, murah, dan transparan untuk komunitas merpati pos, yang menggabungkan:

· Web app lomba merpati
· Perangkat ETS mandiri murah
· Pencatatan otomatis berbasis RFID
· Leaderboard real-time
· Latihan mandiri
· Riwayat performa burung
· Hosting mandiri di server rumahan/STB

---

3. Tujuan Utama

TrackPigeon dibuat untuk menyelesaikan beberapa masalah:

· Harga ETS komersial mahal
    TrackPigeon ingin menyediakan sistem ETS alternatif menggunakan komponen yang lebih murah seperti Wemos Lolin S2 Mini, RFID RDM6300, GPS, dan RTC.
· Pencatatan lomba harus transparan
    Hasil lomba tidak boleh diinput manual oleh panitia agar lebih adil dan mengurangi kecurangan.
· Komunitas kecil butuh sistem sederhana
    Banyak klub kecil belum mampu membeli sistem mahal. TrackPigeon bisa dijalankan di STB Armbian dengan Cloudflare Tunnel.
· Penghobi butuh fitur latihan mandiri
    Tidak semua aktivitas merpati adalah lomba klub. Latihan pribadi juga perlu dicatat agar performa burung bisa dianalisis.
· Data burung sering tidak terdokumentasi
    TrackPigeon menyimpan data burung, ring, pemilik, event, hasil, latihan, dan riwayat performa dalam satu sistem.

---

4. Nama Produk

TrackPigeon

Tagline
"Affordable ETS & Smart Race Tracking for Pigeon Communities."

Alternatif tagline Bahasa Indonesia:
"Lomba dan latihan merpati lebih akurat, otomatis, dan transparan."

---

5. Target Pengguna

5.1 Penghobi Merpati Pos

Pengguna individu yang memiliki burung merpati dan ingin:

· Mendaftarkan burung
· Melihat riwayat performa
· Melakukan latihan mandiri
· Melihat statistik burung
· Ikut lomba klub

5.2 Admin Klub

Pengurus klub yang ingin:

· Membuat event lomba
· Mengelola anggota
· Mengelola peserta lomba
· Melihat hasil otomatis
· Mengumumkan leaderboard

5.3 Panitia Lomba

Pihak yang menjalankan event lomba:

· Menyiapkan jadwal lomba
· Menentukan titik lepas
· Menghubungkan perangkat ETS
· Memantau data masuk
· Memverifikasi status perangkat

5.4 Teknisi ETS

Orang yang mengelola perangkat:

· Mendaftarkan device ETS
· Melihat status koneksi
· Mengecek baterai/sinyal jika tersedia
· Mengecek GPS dan RTC
· Memastikan data terkirim ke server

5.5 Viewer Publik

Orang umum yang ingin melihat:

· Event lomba
· Hasil lomba
· Ranking
· Profil klub
· Statistik publik

---

6. Perbedaan TrackPigeon dengan POMSI Live

Aspek POMSI Live TrackPigeon
Fokus utama Platform lomba POMSI Platform lomba + latihan mandiri
Perangkat ETS Sistem ETS resmi/komersial ETS buatan sendiri yang lebih murah
Input hasil Ada sistem berbasis event TrackPigeon dirancang tanpa input hasil manual
Latihan mandiri Tidak menjadi fokus utama Menjadi fitur utama
Hosting Server pusat Bisa self-hosting di STB Armbian
Target awal Organisasi/klub besar Komunitas kecil, klub lokal, penghobi mandiri
Transparansi Bergantung sistem resmi Audit log, device token, timestamp, GPS, RTC
Biaya awal Relatif mahal Lebih murah dengan komponen umum
Skalabilitas Nasional/organisasi Mulai kecil, bisa berkembang

---

7. Prinsip Produk

TrackPigeon harus mengikuti prinsip berikut:

· ETS-first
    Data hasil lomba hanya valid jika berasal dari perangkat ETS.
· No manual race result input
    Tidak ada fitur input waktu kedatangan manual untuk lomba resmi.
· Affordable hardware
    Perangkat harus bisa dibuat dengan komponen murah dan mudah dicari.
· Self-host friendly
    Aplikasi harus bisa berjalan di STB Armbian dengan resource terbatas.
· Mobile-first
    Tampilan harus nyaman digunakan dari HP.
· Transparan dan bisa diaudit
    Semua data penting harus memiliki log.
· Bisa offline sementara
    Perangkat ETS boleh menyimpan data sementara jika internet mati, lalu sinkron saat online.

---

8. Platform dan Deployment

8.1 Target Hosting Awal

TrackPigeon dirancang untuk dijalankan di:

· STB Armbian
· Docker
· PHP Apache/Nginx
· MariaDB/MySQL
· Cloudflare Tunnel
· Domain pribadi

8.2 Stack MVP yang Direkomendasikan

Karena kamu ingin sederhana dan cocok untuk STB:

Backend

· PHP 8.2
· MySQL/MariaDB
· PDO prepared statement
· REST API sederhana
· Session login atau JWT

Frontend

· HTML
· CSS
· JavaScript
· Bootstrap atau Tailwind
· Chart.js untuk grafik
· DataTables untuk tabel hasil
· PWA ringan agar bisa dibuka seperti aplikasi HP

Database

· MariaDB
· Volume Docker diarahkan ke SSD eksternal STB

Realtime

· Untuk MVP: AJAX polling setiap 3–5 detik
· Untuk versi lanjutan: WebSocket, MQTT bridge, Node.js realtime service

Tunnel

· Cloudflare Tunnel
· Domain contoh: trackpigeon.domainkamu.com

---

9. Arsitektur Sistem

9.1 Arsitektur Sederhana

```
[Ring RFID Burung]
         |
         v
[RFID Reader RDM6300]
         |
         v
[Wemos Lolin S2 Mini]
         |
         |    +--> [RTC DS3231]
         |    +--> [GPS GY-NEO6MV2]
         |
         +------------------> [Wi-Fi]
                                  |
                                  v
                        [Cloudflare Tunnel]
                                  |
                                  v
                    [TrackPigeon Server di STB]
                                  |
                                  v
                        [MariaDB Database]
                                  |
                                  v
                    [Dashboard Web / HP User]
```

---

10. Komponen ETS

10.1 Mikrokontroler

Wemos Lolin S2 Mini

Fungsi:

· Pusat kendali perangkat
· Membaca data RFID
· Membaca data GPS
· Membaca data RTC
· Mengirim data ke server
· Mengatur LED dan buzzer
· Menyimpan data sementara saat offline

10.2 RFID Reader

RDM6300

Fungsi:

· Membaca ring chip merpati
· Mengambil ID unik ring
· Mengirim ID ke mikrokontroler

Target teknis:

· Jarak baca efektif: ±2,5 cm
· Waktu respon target: 130–140 ms
· Harus menolak pembacaan ganda dalam waktu tertentu

10.3 GPS

GY-NEO6MV2

Fungsi:

· Mengambil koordinat lokasi perangkat
· Memastikan alat berada di lokasi kandang/event
· Menyimpan lokasi saat scan terjadi

Target teknis:

· Deviasi lokasi: ±1–2 meter dalam kondisi ideal
· Jika GPS belum fix, sistem memberi peringatan

10.4 RTC

DS3231

Fungsi:

· Menyimpan waktu akurat
· Menjadi sumber waktu lokal saat internet mati
· Membantu mode semi-offline

Target teknis:

· Waktu tetap stabil walau Wi-Fi mati
· Sinkronisasi waktu dapat dilakukan dari server saat online

10.5 Output Perangkat

· LED hijau: scan sukses
· LED merah: scan gagal
· Buzzer pendek: data terbaca
· Buzzer panjang: error/koneksi gagal
· OLED display opsional untuk menampilkan status

---

11. Mode Kerja Perangkat ETS

11.1 Mode Online

Alur:

1. RFID membaca ring burung.
2. Wemos mengambil waktu dari RTC.
3. Wemos mengambil lokasi GPS.
4. Data dikirim ke server melalui Wi-Fi.
5. Server memvalidasi data.
6. Server menyimpan hasil.
7. Leaderboard diperbarui.
8. LED/buzzer memberi tanda sukses.

11.2 Mode Semi-Offline

Digunakan saat Wi-Fi/internet mati.

Alur:

1. RFID membaca ring burung.
2. Data scan disimpan lokal di perangkat.
3. Data menyimpan: RFID tag, waktu RTC, koordinat GPS terakhir, Device ID, Event ID, Signature/checksum.
4. Saat internet kembali, data dikirim ke server.
5. Server menandai data sebagai synced from offline mode.
6. Dashboard menampilkan status bahwa data berasal dari mode semi-offline.

11.3 Larangan Sistem

Untuk lomba resmi:

· Tidak boleh input waktu finish manual.
· Tidak boleh edit waktu finish.
· Tidak boleh hapus hasil scan.
· Tidak boleh mengganti device saat event berjalan tanpa log.
· Tidak boleh menerima data dari device tidak terdaftar.

---

12. Modul Aplikasi

12.1 Landing Page Publik

Halaman utama berisi:

· Nama aplikasi TrackPigeon
· Penjelasan singkat
· Event lomba aktif
· Hasil lomba terbaru
· Leaderboard publik
· Statistik komunitas
· Tombol login/register
· Link dokumentasi perangkat ETS

Konten utama:

TrackPigeon adalah platform lomba dan latihan merpati pos berbasis ETS mandiri. Sistem ini membantu penghobi dan klub mencatat waktu kedatangan merpati secara otomatis menggunakan RFID, GPS, RTC, dan koneksi Wi-Fi.

12.2 Autentikasi User

Fitur:

· Register
· Login
· Logout
· Lupa password
· Verifikasi email opsional
· Ubah password
· Manajemen sesi

Data user:

· Nama lengkap
· Username
· Email
· Nomor WhatsApp
· Password hash
· Role
· Klub
· Status akun

Role:

· Super Admin
· Admin Klub
· Panitia
· Teknisi ETS
· Member/Penghobi
· Viewer

12.3 Manajemen Klub

Fitur:

· Buat klub
· Edit profil klub
· Upload logo klub
· Tambah anggota
· Hapus anggota
· Lihat statistik klub
· Lihat daftar burung milik anggota

Data klub:

· Nama klub
· Kota/kabupaten
· Provinsi
· Alamat
· Kontak
· Email
· Logo
· Deskripsi
· Status aktif

12.4 Manajemen Burung

Fitur:

· Tambah burung
· Edit data burung
· Upload foto burung
· Hubungkan burung dengan RFID ring
· Riwayat lomba
· Riwayat latihan
· Statistik performa

Data burung:

· Nama burung
· Nomor ring visual
· RFID tag ID
· Pemilik
· Klub
· Warna
· Jenis kelamin
· Tahun lahir
· Foto
· Status: aktif, pensiun, hilang, dijual
· Catatan

Aturan penting:

· Satu RFID tag hanya boleh terdaftar pada satu burung aktif.
· Jika burung pindah pemilik, harus ada log perubahan.
· Riwayat lomba tidak boleh hilang saat burung pindah pemilik.

12.5 Manajemen Perangkat ETS

Fitur:

· Registrasi device ETS
· Pairing device dengan klub/user
· Generate device token
· Lihat status online/offline
· Lihat lokasi terakhir
· Lihat firmware version
· Lihat event aktif yang sedang digunakan
· Reset token
· Nonaktifkan device

Data device:

· Device ID
· Nama device
· Tipe device
· Pemilik device
· Klub
· Token rahasia
· Firmware version
· IP terakhir
· Lokasi GPS terakhir
· Status online/offline
· Last heartbeat
· Dibuat pada

Status device:

· Online
· Offline
· Siap lomba
· Sedang digunakan
· Error GPS
· Error RTC
· Error RFID
· Belum diverifikasi

12.6 Event Lomba

Fitur:

· Buat event lomba
· Tentukan lokasi start
· Tentukan lokasi finish/kandang
· Tentukan tanggal dan jam release
· Tentukan kategori lomba
· Tentukan jarak lomba
· Buka/tutup pendaftaran
· Hubungkan device ETS
· Monitor leaderboard

Data event:

· Nama event
· Klub penyelenggara
· Tanggal event
· Waktu release
· Lokasi release
· Koordinat release
· Lokasi finish
· Koordinat finish
· Jarak resmi
· Kategori
· Status event
· Deskripsi aturan

Status event:

· Draft
· Registration Open
· Registration Closed
· Device Check
· Running
· Finished
· Published
· Archived

12.7 Pendaftaran Lomba

Fitur:

· Member memilih event
· Member memilih burung
· Sistem validasi RFID burung
· Admin menyetujui peserta
· Sistem membuat nomor peserta
· Sistem menyiapkan data ring ke event

Data peserta lomba:

· Event ID
· User ID
· Bird ID
· RFID tag
· Klub
· Nomor peserta
· Status pembayaran jika ada
· Status validasi

Status peserta:

· Pending
· Approved
· Rejected
· Checked-in
· DNS / Did Not Start
· Finished
· Disqualified

12.8 Sistem Hasil Lomba Full ETS

Ini adalah fitur inti TrackPigeon.

Data hasil lomba hanya boleh masuk dari API ETS.

Ketika device mengirim data, server menerima:

· Device ID
· Event ID
· RFID tag
· Timestamp RTC
· Timestamp server
· Latitude
· Longitude
· Signal/status GPS
· Firmware version
· Scan nonce
· Signature/token
· Mode online/offline

Server melakukan validasi:

1. Device terdaftar.
2. Token valid.
3. Event sedang berjalan.
4. RFID terdaftar sebagai peserta event.
5. Burung belum pernah finish sebelumnya.
6. Timestamp masuk akal.
7. Lokasi sesuai toleransi.
8. Data tidak duplikat.
9. Checksum/signature valid.

Jika valid:

· Hasil disimpan.
· Ranking dihitung.
· Leaderboard diperbarui.
· Log audit dibuat.

Jika tidak valid:

· Data disimpan sebagai rejected scan.
· Alasan penolakan dicatat.
· Admin bisa melihat, tetapi tidak bisa menjadikannya hasil resmi secara manual.

12.9 Leaderboard Real-Time

Leaderboard menampilkan:

· Ranking
· Nama burung
· Pemilik
· Klub
· Nomor ring
· Waktu finish
· Durasi terbang
· Jarak
· Kecepatan
· Status validasi
· Mode data: online/offline sync

Rumus dasar:

```
Durasi = Waktu Finish - Waktu Release
Kecepatan = Jarak / Durasi
```

Contoh satuan:

· Meter per menit
· Kilometer per jam

Filter leaderboard:

· Semua peserta
· Per klub
· Per kategori
· Per jarak
· Hanya finish
· Belum finish

12.10 Latihan Mandiri

Fitur ini membedakan TrackPigeon dari platform lomba biasa.

Latihan mandiri digunakan oleh penghobi tanpa event klub.

Fitur:

· Buat sesi latihan
· Pilih burung yang ikut latihan
· Tentukan titik lepas
· Tentukan kandang finish
· Catat release time
· Scan finish otomatis via ETS
· Lihat hasil latihan
· Lihat grafik perkembangan

Data latihan:

· Nama latihan
· User ID
· Tanggal
· Lokasi release
· Koordinat release
· Lokasi kandang
· Koordinat kandang
· Jarak latihan
· Cuaca opsional
· Catatan
· Device ETS
· Daftar burung
· Hasil scan

Status latihan:

· Draft
· Running
· Finished
· Archived

Perbedaan dengan lomba:

Lomba Latihan Mandiri
Dibuat admin klub/panitia Dibuat user sendiri
Banyak peserta Satu pemilik atau grup kecil
Hasil publik Bisa privat
Ranking kompetitif Fokus analisis performa
Tidak bisa manual Tetap berbasis ETS
Aturan ketat Lebih fleksibel

12.11 Statistik Burung

Setiap burung memiliki halaman performa.

Data yang ditampilkan:

· Total lomba
· Total latihan
· Total finish
· Persentase finish
· Kecepatan rata-rata
· Kecepatan terbaik
· Jarak terbaik
· Ranking terbaik
· Grafik performa per waktu
· Grafik performa per jarak

Insight sederhana:

· Burung cocok jarak pendek
· Burung stabil di jarak menengah
· Performa menurun
· Performa meningkat
· Sering telat pulang
· Belum cukup data

12.12 Audit Log

Semua aktivitas penting harus dicatat.

Contoh log:

· User login
· Admin membuat event
· Admin mengubah event
· Device dihubungkan ke event
· Device mengirim scan
· Scan diterima
· Scan ditolak
· Token device diganti
· Burung diganti pemilik
· Event dipublish

Data audit:

· User ID
· Role
· Aksi
· Modul
· Data lama
· Data baru
· IP address
· User agent
· Waktu

---

13. Alur Utama Pengguna

13.1 Alur Member Mendaftarkan Burung

1. User login.
2. Masuk menu "Burung Saya".
3. Klik tambah burung.
4. Isi data burung.
5. Masukkan nomor ring visual.
6. Scan RFID menggunakan device atau input RFID saat registrasi awal.
7. Sistem memvalidasi RFID belum dipakai.
8. Burung berhasil disimpan.

13.2 Alur Admin Membuat Lomba

1. Admin login.
2. Masuk menu "Event Lomba".
3. Klik "Buat Event".
4. Isi nama event, tanggal, lokasi, jarak, kategori.
5. Tentukan waktu release.
6. Hubungkan device ETS.
7. Buka pendaftaran.
8. Peserta mendaftar.
9. Admin validasi peserta.
10. Event masuk status Device Check.
11. Event dimulai.
12. Device menerima scan finish.
13. Leaderboard update otomatis.
14. Event selesai.
15. Hasil dipublish.

13.3 Alur ETS Mengirim Hasil

1. Burung sampai ke kandang.
2. RFID reader membaca ring.
3. Wemos mengambil waktu RTC.
4. Wemos mengambil koordinat GPS.
5. Wemos mengirim data ke API TrackPigeon.
6. Server validasi token dan event.
7. Server cocokkan RFID dengan peserta.
8. Server menyimpan hasil.
9. Leaderboard update.
10. Device menerima response sukses.
11. LED hijau dan buzzer berbunyi.

13.4 Alur Latihan Mandiri

1. User login.
2. Masuk menu "Latihan Mandiri".
3. Klik "Buat Latihan".
4. Pilih burung.
5. Tentukan lokasi lepas.
6. Tentukan waktu release.
7. Pilih device ETS.
8. Mulai latihan.
9. Burung pulang dan discan ETS.
10. Sistem mencatat waktu otomatis.
11. User melihat hasil dan grafik.

---

14. API ETS

14.1 Endpoint Heartbeat Device

```
POST /api/device/heartbeat
```

Fungsi:

· Mengecek device online
· Mengirim status hardware
· Mengirim GPS terakhir
· Mengirim firmware version

Payload contoh:

```json
{
  "device_id": "TP-ETS-001",
  "token": "secret_device_token",
  "firmware": "1.0.0",
  "gps_status": "fix",
  "rtc_status": "ok",
  "rfid_status": "ok",
  "lat": -7.123456,
  "lng": 107.123456,
  "battery": 87
}
```

Response:

```json
{
  "success": true,
  "server_time": "2026-06-02 14:30:00",
  "message": "Device online"
}
```

14.2 Endpoint Submit Scan

```
POST /api/ets/scan
```

Payload contoh:

```json
{
  "device_id": "TP-ETS-001",
  "event_id": 12,
  "mode": "race",
  "rfid_tag": "982000123456789",
  "rtc_time": "2026-06-02 14:35:21",
  "lat": -7.123456,
  "lng": 107.123456,
  "gps_fix": true,
  "nonce": "abc123xyz",
  "signature": "generated_signature"
}
```

Response sukses:

```json
{
  "success": true,
  "status": "accepted",
  "message": "Scan accepted",
  "bird_name": "Garuda Muda",
  "rank": 1
}
```

Response gagal:

```json
{
  "success": false,
  "status": "rejected",
  "reason": "RFID not registered in this event"
}
```

14.3 Endpoint Sync Offline Data

```
POST /api/ets/sync
```

Fungsi:

· Mengirim banyak data scan yang tersimpan saat offline.

Payload contoh:

```json
{
  "device_id": "TP-ETS-001",
  "event_id": 12,
  "token": "secret_device_token",
  "offline_data": [
    {
      "rfid_tag": "982000123456789",
      "rtc_time": "2026-06-02 14:35:21",
      "lat": -7.123456,
      "lng": 107.123456,
      "nonce": "offline001",
      "signature": "signature1"
    }
  ]
}
```

---

15. Struktur Database Awal

15.1 users

```
users
- id
- name
- username
- email
- phone
- password_hash
- role
- club_id
- status
- created_at
- updated_at
```

15.2 clubs

```
clubs
- id
- name
- city
- province
- address
- phone
- email
- logo
- description
- status
- created_at
- updated_at
```

15.3 birds

```
birds
- id
- owner_id
- club_id
- name
- ring_number
- rfid_tag
- color
- gender
- birth_year
- photo
- status
- notes
- created_at
- updated_at
```

15.4 devices

```
devices
- id
- device_id
- name
- owner_id
- club_id
- token_hash
- firmware_version
- status
- last_lat
- last_lng
- last_heartbeat
- created_at
- updated_at
```

15.5 race_events

```
race_events
- id
- club_id
- name
- event_date
- release_time
- release_lat
- release_lng
- finish_lat
- finish_lng
- distance_meters
- category
- status
- description
- created_by
- created_at
- updated_at
```

15.6 race_participants

```
race_participants
- id
- event_id
- user_id
- bird_id
- rfid_tag
- participant_number
- status
- created_at
- updated_at
```

15.7 race_results

```
race_results
- id
- event_id
- participant_id
- bird_id
- device_id
- rfid_tag
- release_time
- finish_time_rtc
- finish_time_server
- duration_seconds
- distance_meters
- speed_mpm
- speed_kmh
- rank_position
- lat
- lng
- mode
- validation_status
- created_at
```

15.8 training_sessions

```
training_sessions
- id
- user_id
- name
- training_date
- release_time
- release_lat
- release_lng
- finish_lat
- finish_lng
- distance_meters
- weather
- notes
- device_id
- status
- visibility
- created_at
- updated_at
```

15.9 training_participants

```
training_participants
- id
- training_id
- bird_id
- rfid_tag
- status
- created_at
```

15.10 training_results

```
training_results
- id
- training_id
- bird_id
- device_id
- rfid_tag
- finish_time_rtc
- finish_time_server
- duration_seconds
- distance_meters
- speed_mpm
- speed_kmh
- lat
- lng
- validation_status
- created_at
```

15.11 device_scan_logs

```
device_scan_logs
- id
- device_id
- event_id
- training_id
- mode
- rfid_tag
- rtc_time
- server_time
- lat
- lng
- gps_fix
- nonce
- signature
- status
- reject_reason
- raw_payload
- created_at
```

15.12 audit_logs

```
audit_logs
- id
- user_id
- action
- module
- old_data
- new_data
- ip_address
- user_agent
- created_at
```

---

16. Keamanan Sistem

Karena aplikasi ini berhubungan dengan hasil lomba, keamanan sangat penting.

16.1 Keamanan Web

Wajib:

· Password di-hash dengan password_hash()
· Query database menggunakan PDO prepared statement
· Validasi input
· Proteksi XSS
· Proteksi CSRF untuk form admin
· Session aman
· Rate limit login
· Role-based access control
· Upload file dibatasi
· Error detail tidak ditampilkan ke publik

16.2 Keamanan API Device

Wajib:

· Setiap device punya token unik
· Token disimpan dalam bentuk hash di server
· API hanya menerima device terdaftar
· Nonce untuk mencegah replay attack
· Signature/checksum untuk validasi payload
· Log semua scan, termasuk yang ditolak
· HTTPS melalui Cloudflare Tunnel

16.3 Keamanan Data Lomba

Wajib:

· Hasil lomba tidak bisa diedit manual
· Scan pertama yang valid menjadi hasil resmi
· Scan duplikat dicatat sebagai duplicate
· Semua perubahan event dicatat di audit log
· Event yang sudah publish tidak bisa diubah sembarangan

---

17. Fitur Anti-Cheat

TrackPigeon harus punya sistem anti-cheat sederhana:

1. Device token — Hanya perangkat terdaftar yang bisa kirim data.
2. RFID validation — RFID harus cocok dengan burung yang didaftarkan.
3. Event validation — Scan hanya diterima saat event sedang berjalan.
4. Duplicate prevention — Satu burung hanya bisa finish satu kali.
5. GPS validation — Lokasi scan harus berada di radius yang masuk akal dari kandang finish.
6. RTC + server timestamp — Sistem menyimpan waktu dari device dan waktu server.
7. Offline sync marking — Data offline tetap diterima, tetapi diberi label khusus.
8. Audit log — Semua aktivitas penting tercatat.
9. No manual finish input — Panitia tidak bisa menginput hasil finish lomba secara manual.

---

18. Dashboard

18.1 Dashboard Super Admin

Menampilkan:

· Total klub
· Total user
· Total burung
· Total event
· Total device
· Device online/offline
· Event aktif
· Scan terbaru
· Log error

18.2 Dashboard Admin Klub

Menampilkan:

· Anggota klub
· Burung klub
· Event klub
· Hasil lomba
· Device klub
· Statistik klub

18.3 Dashboard Member

Menampilkan:

· Burung saya
· Latihan terbaru
· Lomba yang diikuti
· Hasil terbaik
· Grafik performa
· Device saya jika punya

18.4 Dashboard Teknisi

Menampilkan:

· Status device
· Last heartbeat
· GPS status
· RTC status
· RFID status
· Firmware version
· Scan logs
· Error logs

---

19. Halaman Utama Aplikasi

Minimal halaman yang harus ada:

Publik

· Beranda
· Event Lomba
· Hasil Lomba
· Leaderboard
· Klub
· Tentang TrackPigeon
· Login
· Register

Member

· Dashboard
· Burung Saya
· Latihan Mandiri
· Riwayat Lomba
· Statistik
· Profil Saya

Admin Klub

· Dashboard Klub
· Anggota
· Burung
· Event Lomba
· Peserta Lomba
· Leaderboard
· Device ETS
· Audit Log

Teknisi

· Dashboard Device
· Pairing Device
· Heartbeat
· Scan Logs
· Error Logs

Super Admin

· Semua User
· Semua Klub
· Semua Event
· Semua Device
· Global Logs
· Pengaturan Sistem

---

20. MVP TrackPigeon

Untuk startup sederhana, jangan langsung membuat semua fitur. Buat bertahap.

MVP 1 — Web App Dasar

Fitur wajib:

· Landing page
· Login/register
· Role user
· Data klub
· Data burung
· Data device ETS
· Event lomba
· Pendaftaran lomba
· API scan ETS
· Leaderboard otomatis
· Latihan mandiri
· Riwayat hasil burung
· Audit log dasar

Target:

· Bisa dipakai demo ke komunitas.
· Bisa jalan di STB Armbian.
· Bisa diakses online via Cloudflare Tunnel.
· Bisa menerima data dari Wemos.

MVP 2 — Stabilitas & Transparansi

Tambahan:

· Device heartbeat
· Status online/offline
· Offline sync
· GPS validation
· Duplicate scan prevention
· Export hasil PDF/Excel
· QR profil burung
· Grafik performa

MVP 3 — Komunitas & Monetisasi

Tambahan:

· Profil klub publik
· Galeri event
· Komentar event
· Sertifikat digital
· Paket premium klub
· Marketplace sederhana
· Sistem pembayaran

---

21. Fitur yang Tidak Masuk MVP Awal

Agar proyek tidak terlalu berat, fitur berikut ditunda:

· Mobile app native Android
· Payment gateway otomatis
· Marketplace penuh
· AI analisis performa
· Live tracking burung secara GPS
· Chat internal
· Multi-region server
· Firmware OTA update
· Sistem ranking nasional kompleks

---

22. Kebutuhan Non-Fungsional

22.1 Performa

Target untuk STB Armbian:

· Bisa melayani 10–50 user aktif awal
· Bisa menerima scan ETS secara cepat
· Halaman leaderboard ringan
· Database tidak terlalu berat
· Gambar dikompres

22.2 Kompatibilitas

Aplikasi harus berjalan di:

· Chrome Android
· Chrome Desktop
· Edge
· Firefox
· WebView mobile

22.3 Responsivitas

Prioritas tampilan:

1. HP
2. Tablet
3. Desktop

22.4 Ketersediaan

Karena hosting di rumah:

· Harus ada backup database
· Harus ada export data
· Harus ada restart otomatis Docker
· Cloudflare Tunnel auto-start

22.5 Backup

Minimal:

· Backup database harian
· Backup folder upload
· Export hasil lomba per event
· Simpan backup ke SSD eksternal

---

23. Deployment di STB Armbian

23.1 Container yang Dibutuhkan

Minimal:

· trackpigeon-web — PHP 8.2 Apache
· trackpigeon-db — MariaDB
· trackpigeon-tunnel — Cloudflared

Opsional:

· trackpigeon-phpmyadmin
· trackpigeon-redis
· trackpigeon-mqtt

23.2 Struktur Folder

Contoh:

```
/opt/trackpigeon/
├── app/
│   ├── public/
│   ├── api/
│   ├── admin/
│   ├── member/
│   ├── includes/
│   └── uploads/
├── database/
├── docker-compose.yml
└── backups/
```

23.3 Domain

Contoh:

```
https://trackpigeon.domainkamu.com
```

Subdomain lain opsional:

```
https://api.trackpigeon.domainkamu.com
https://admin.trackpigeon.domainkamu.com
```

Untuk MVP, cukup satu domain saja.

---

24. Contoh Struktur Menu

```
TrackPigeon
├── Beranda
├── Event
│   ├── Event Aktif
│   ├── Hasil Event
│   └── Leaderboard
├── Latihan
│   ├── Buat Latihan
│   ├── Riwayat Latihan
│   └── Statistik Latihan
├── Burung
│   ├── Burung Saya
│   ├── Tambah Burung
│   └── Statistik Burung
├── Klub
│   ├── Profil Klub
│   ├── Anggota
│   └── Burung Klub
├── Device ETS
│   ├── Device Saya
│   ├── Pairing Device
│   ├── Scan Logs
│   └── Status Device
└── Akun
    ├── Profil
    └── Logout
```

---

25. User Story

Member

· Sebagai member, saya ingin mendaftarkan burung saya agar bisa mengikuti lomba dan latihan.
· Sebagai member, saya ingin melihat riwayat latihan agar tahu perkembangan performa burung.
· Sebagai member, saya ingin melihat hasil lomba secara real-time agar tidak menunggu rekap manual.

Admin Klub

· Sebagai admin klub, saya ingin membuat event lomba agar anggota bisa mendaftar.
· Sebagai admin klub, saya ingin melihat leaderboard otomatis agar hasil lomba lebih transparan.
· Sebagai admin klub, saya ingin menghubungkan device ETS ke event agar hasil masuk otomatis.

Teknisi

· Sebagai teknisi, saya ingin melihat status device agar tahu apakah alat siap digunakan.
· Sebagai teknisi, saya ingin melihat scan log agar bisa debug jika RFID gagal terbaca.

Viewer

· Sebagai viewer, saya ingin melihat hasil lomba publik agar bisa mengikuti perkembangan event.

---

26. Acceptance Criteria

Event Lomba — Sistem dianggap berhasil jika:

· Admin bisa membuat event.
· Peserta bisa mendaftarkan burung.
· Device bisa dikaitkan ke event.
· Event bisa dimulai.
· Hasil hanya masuk dari API ETS.
· Leaderboard otomatis berubah saat scan diterima.

Device ETS — Sistem dianggap berhasil jika:

· Device bisa heartbeat ke server.
· Device bisa mengirim RFID tag.
· Server bisa menerima scan.
· Server bisa menolak scan tidak valid.
· Device mendapat response sukses/gagal.
· Scan log tersimpan.

Latihan Mandiri — Sistem dianggap berhasil jika:

· User bisa membuat sesi latihan.
· User bisa memilih burung.
· Device bisa mengirim hasil latihan.
· Hasil latihan tersimpan.
· Statistik burung diperbarui.

Anti Manual — Sistem dianggap berhasil jika:

· Tidak ada tombol input hasil lomba manual.
· Admin tidak bisa mengubah waktu finish.
· Semua data scan memiliki log device.
· Hasil lomba selalu terhubung ke device ETS.

---

27. Risiko Produk

27.1 Risiko Teknis

Risiko Solusi
Wi-Fi mati saat lomba Mode semi-offline
GPS belum fix Tampilkan status error dan jangan mulai event
RFID gagal baca LED/buzzer error dan scan ulang
RTC tidak sinkron Sinkronisasi waktu dari server sebelum event
STB mati Backup dan UPS kecil
Cloudflare Tunnel disconnect Auto restart service/container
Database corrupt Backup harian

27.2 Risiko Komunitas

Risiko Solusi
Pengguna belum percaya sistem Tampilkan audit log dan data transparan
Klub belum punya alat Mulai dari latihan mandiri dan demo
Alat dianggap ribet Buat pairing device sederhana
Hasil offline diperdebatkan Beri label khusus dan tampilkan timestamp server saat sync

---

28. Roadmap Pengembangan

Tahap 1 — Prototype
Target:

· Web login
· Data burung
· Data device
· API scan
· Simulasi scan RFID
· Leaderboard sederhana

Tahap 2 — MVP Lokal
Target:

· Jalan di STB Armbian
· MariaDB aktif
· Cloudflare Tunnel aktif
· Device Wemos bisa kirim data
· Latihan mandiri berjalan
· Event lomba sederhana berjalan

Tahap 3 — Demo Komunitas
Target:

· Dipakai oleh 1 klub kecil
· 1–2 device ETS
· 1 event uji coba
· 5–20 burung
· Leaderboard publik

Tahap 4 — Validasi Startup
Target:

· Beberapa user aktif
· Feedback komunitas
· Perbaikan UI/UX
· Sistem paket harga
· Dokumentasi perangkat

Tahap 5 — Versi Komersial
Target:

· Multi klub
· Paket langganan
· Sertifikat digital
· Statistik lanjutan
· Marketplace sederhana
· Device ETS siap jual/rakit

---

29. Model Bisnis Sederhana

Untuk awal, jangan langsung terlalu mahal.

Opsi Monetisasi

1. Gratis untuk latihan mandiri terbatas
   · Maksimal 5 burung
   · Maksimal 10 sesi latihan per bulan
2. Paket Member Premium
   · Burung tak terbatas
   · Statistik lengkap
   · Export hasil
   · QR profil burung
3. Paket Klub
   · Buat event lomba
   · Leaderboard publik
   · Multi admin
   · Export hasil
4. Jual/Rakit ETS
   · Paket alat ETS murah
   · Jasa rakit
   · Jasa setting
   · Jasa hosting
5. Jasa Setup Server
   · Setup TrackPigeon di STB
   · Setup Cloudflare Tunnel
   · Setup domain

---

30. Deskripsi Promosi TrackPigeon

TrackPigeon adalah platform lomba dan latihan merpati pos berbasis ETS mandiri yang dirancang untuk komunitas kecil, klub lokal, dan penghobi individu. Dengan dukungan RFID, GPS, RTC, dan mikrokontroler Wemos Lolin S2 Mini, TrackPigeon mencatat waktu kedatangan merpati secara otomatis tanpa input manual.

Aplikasi ini menyediakan fitur manajemen burung, event lomba, leaderboard real-time, latihan mandiri, riwayat performa, statistik burung, audit log, dan integrasi perangkat ETS murah. TrackPigeon dapat dihosting secara mandiri menggunakan STB Armbian dan diakses online melalui Cloudflare Tunnel, sehingga menjadi solusi digitalisasi lomba merpati yang lebih terjangkau, transparan, dan fleksibel.

---

31. Prioritas Pengerjaan Paling Realistis

Untuk kamu sekarang, urutan paling masuk akal:

1. Buat web app MVP dulu
   · Login
   · Data burung
   · Data device
   · Event
   · Latihan
   · API scan
   · Leaderboard
2. Buat simulasi ETS dulu
   · Kirim data RFID palsu dari Postman/cURL
   · Pastikan server menerima data
3. Baru sambungkan Wemos
   · Wemos kirim HTTP POST ke API
4. Tambah RFID RDM6300
   · Baca tag
   · Kirim tag ke server
5. Tambah RTC
   · Kirim waktu device
6. Tambah GPS
   · Kirim koordinat
7. Tambah mode semi-offline
   · Simpan data lokal
   · Sync saat online

---

32. Definisi MVP Final TrackPigeon

MVP TrackPigeon dinyatakan berhasil jika:

1. ✅ Web berjalan di STB Armbian.
2. ✅ Web bisa diakses dari internet lewat Cloudflare Tunnel.
3. ✅ User bisa login/register.
4. ✅ User bisa mendaftarkan burung dan RFID.
5. ✅ Admin bisa membuat event lomba.
6. ✅ User bisa membuat latihan mandiri.
7. ✅ Device ETS bisa mengirim data scan ke server.
8. ✅ Hasil lomba tidak bisa diinput manual.
9. ✅ Leaderboard otomatis muncul dari data ETS.
10. ✅ Riwayat performa burung tersimpan.
11. ✅ Sistem memiliki audit log dasar.
12. ✅ Data dapat dibackup.

---

33. Update Produk v1.1 - RBAC, Approval, Klasemen, dan ETS-Only Race

33.1 Prinsip Akses Baru

TrackPigeon harus memisahkan area admin dan area member secara tegas.

- Section Lomba admin, Klub admin, dan Sponsor hanya bisa diakses oleh Admin Klub yang sudah approved atau Super Admin.
- Member/Penghobi tidak boleh mengakses halaman manajemen lomba, manajemen klub, atau sponsor admin.
- Member tetap bisa melihat lomba tersedia melalui halaman partisipasi member, bukan halaman admin lomba.
- Member tetap bisa melihat klub tersedia melalui halaman gabung klub, bukan halaman admin klub.
- Super Admin memiliki akses penuh untuk user, klub, lomba, ETS, sponsor, audit log, dan approval.

33.2 Approval Admin Klub dan Pembuatan Klub

Pendaftaran sebagai Admin Klub tidak boleh langsung aktif.

Alur register Admin Klub:

1. User memilih opsi Request Admin Klub saat registrasi.
2. Sistem membuat akun sebagai member aktif.
3. Sistem membuat klub awal dengan status pending dan inactive.
4. Sistem membuat relasi club_members sebagai admin pending.
5. Super Admin atau pembuat platform menerima notifikasi approval.
6. Jika disetujui, user berubah menjadi club_admin, klub menjadi approved dan active.
7. Jika ditolak, user tetap member dan klub tetap inactive/rejected.

Aturan pembuatan klub:

- Klub baru dari Admin Klub existing tetap harus pending sampai disetujui Super Admin.
- Super Admin boleh membuat klub langsung approved.
- Klub pending/rejected tidak boleh tampil di direktori publik, daftar lomba, API public races, atau klasemen publik.
- Jika database lama belum memiliki Super Admin, user pertama dianggap sebagai pembuat/platform owner untuk mencegah approval macet.

33.3 Hak Member/Penghobi

Member hanya memiliki akses ke fitur berikut:

- Daftar akun dan login.
- Lengkapi profil kandang.
- Join klub tersedia.
- Kelola burung sendiri.
- Pasang RFID tag ke burung.
- Daftar lomba atau latihan bersama dari klub yang sudah diikuti dan approved.
- Daftarkan burung ke lomba untuk proses approval/basketing admin klub.
- Latihan mandiri.
- Lihat klasemen burung sendiri.
- Lihat klasemen klub tempat member approved.
- Lihat klasemen global seluruh klub/komunitas yang dipublikasikan.
- Lihat notifikasi pendaftaran, basketing, release, dan clocking.

Member tidak boleh:

- Membuat lomba dari section admin.
- Membuat/aktifkan klub tanpa approval Super Admin.
- Mengelola sponsor.
- Mengubah status peserta lomba.
- Melakukan manual clocking untuk lomba resmi.

33.4 Lomba Resmi ETS-Only

Lomba resmi TrackPigeon wajib ETS-only.

Aturan:

- Tidak ada tombol manual clocking untuk lomba resmi.
- Endpoint manual clocking untuk lomba resmi harus menolak request.
- Hasil resmi hanya berasal dari ETS check-in yang valid.
- Burung wajib terdaftar pada lomba.
- Burung wajib sudah dibasketing oleh admin klub.
- Lomba wajib sudah released.
- RFID tag wajib cocok dengan burung dan user/device.
- Clocking pertama yang valid menjadi hasil resmi.
- Duplicate clocking dicatat tetapi tidak mengubah hasil.

Manual check-in masih boleh dipertimbangkan hanya untuk latihan mandiri non-resmi jika dibutuhkan sebagai fitur lokal, tetapi tidak boleh memengaruhi hasil lomba resmi.

33.5 Alur Member Mengikuti Lomba Klub

1. Member login.
2. Member join klub tersedia.
3. Admin klub approve membership.
4. Member membuka halaman Ikut Lomba.
5. Member memilih lomba dari klub yang sudah approved.
6. Member memilih burung aktif miliknya.
7. Sistem menyimpan pendaftaran dengan status pending atau approved sesuai aturan event.
8. Admin klub melakukan approval dan/atau basketing.
9. Member menerima notifikasi bahwa burung sudah terdaftar/masuk proses basketing.
10. Setelah basketing berhasil, member menerima notifikasi basketing.
11. Setelah lomba released, ETS mencatat kedatangan.
12. Member dan admin klub menerima notifikasi clocking ETS.
13. Klasemen lomba otomatis diperbarui.

33.6 Klasemen Publik dan Member

Landing page publik tidak boleh langsung memuat Global Leaderboard sebagai konten utama.

Aturan halaman:

- Landing page publik fokus pada hero, live race aktif, statistik, produk ETS, dan informasi platform.
- Klasemen publik ditempatkan pada halaman khusus Klasemen.
- Halaman Klasemen publik menampilkan:
  - Global leaderboard koefisien dari sesi publik.
  - Klasemen lomba resmi lintas klub approved.
  - Filter kandang dan warna.
- Member memiliki halaman Klasemen yang menampilkan:
  - Ranking burung milik sendiri.
  - Klasemen lomba dari klub tempat member sudah approved.
- Data klub pending/rejected tidak boleh muncul di klasemen publik.

33.7 Notifikasi Wajib

Sistem harus membuat notifikasi untuk kejadian berikut:

- Request admin klub baru masuk ke Super Admin.
- Request klub baru masuk ke Super Admin.
- Approval/rejection admin klub.
- Request join klub masuk ke admin klub.
- Status membership klub berubah.
- Lomba baru dibuka oleh admin klub.
- Member mendaftarkan burung ke lomba.
- Admin menerima pendaftaran burung lomba.
- Burung masuk/terverifikasi basketing.
- Lomba released.
- ETS mencatat clocking lomba.
- ETS mencatat check-in latihan mandiri.

33.8 Acceptance Criteria Tambahan v1.1

- Member yang membuka page admin Lomba diarahkan ke halaman Ikut Lomba.
- Member yang membuka page admin Klub diarahkan ke halaman Gabung Klub.
- Member yang membuka Sponsor admin diarahkan keluar dari section sponsor.
- Register sebagai Admin Klub menghasilkan akun member + klub pending, bukan role admin langsung.
- Super Admin bisa approve/reject request admin klub dari dashboard Super Admin.
- Klub pending tidak muncul di direktori klub member.
- Klub pending tidak bisa dipakai membuat/menampilkan lomba publik.
- Member hanya bisa mendaftarkan burung ke lomba dari klub yang membership-nya approved.
- Tombol manual clocking lomba resmi tidak tampil.
- Endpoint manual clocking lomba resmi menolak request.
- Clocking ETS membuat notifikasi ke member dan admin klub.
- Landing page publik tidak menampilkan Global Leaderboard langsung.
- Halaman Klasemen publik tersedia dan menampilkan leaderboard global serta hasil resmi lintas klub approved.

---

Dokumen ini adalah Product Requirements Document (PRD) untuk TrackPigeon.
Versi: 1.1
Status: Draft Final
