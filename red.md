# Panduan Mencoba Merpatools di Hosting Server STB

Dokumen ini menjelaskan langkah demi langkah untuk menjalankan web app Merpatools di server STB Armbian/CasaOS. Jalur yang direkomendasikan adalah Docker Compose karena lebih mudah dipindah, lebih rapi, dan cocok untuk self-hosting.

## 1. Gambaran Singkat

Merpatools saat ini adalah aplikasi:

- PHP native
- MySQL/MariaDB
- HTML, CSS, JavaScript vanilla
- Leaflet map
- API ETS/RFID
- Upload foto ke folder `uploads`

Target hasil akhir:

- App bisa dibuka dari browser melalui IP STB atau domain lokal
- Database berjalan di MariaDB/MySQL
- Folder upload tetap aman dan persistent
- Endpoint ETS bisa dipanggil dari ESP32/perangkat RFID

Contoh alamat setelah berhasil:

```text
http://IP-STB:8088
```

Contoh:

```text
http://192.168.1.20:8088
```

## 2. Persiapan di STB

Masuk ke server STB lewat SSH:

```bash
ssh user@IP-STB
```

Contoh:

```bash
ssh root@192.168.1.20
```

Update sistem:

```bash
sudo apt update
sudo apt upgrade -y
```

Install paket dasar:

```bash
sudo apt install -y git curl unzip nano
```

Cek arsitektur STB:

```bash
uname -m
```

Jika hasilnya `aarch64` atau `arm64`, gunakan image Docker yang mendukung ARM64.

## 3. Install Docker dan Docker Compose

Jika memakai CasaOS, biasanya Docker sudah tersedia. Cek dulu:

```bash
docker --version
docker compose version
```

Jika belum ada Docker:

```bash
curl -fsSL https://get.docker.com | sh
sudo usermod -aG docker $USER
```

Logout lalu login lagi agar group Docker aktif.

Cek ulang:

```bash
docker --version
docker compose version
```

## 4. Upload Project ke STB

Buat folder project:

```bash
mkdir -p /opt/merpatools
```

Masuk folder:

```bash
cd /opt/merpatools
```

Upload semua isi project ini ke folder tersebut. Struktur minimal harus seperti ini:

```text
/opt/merpatools
├── api
├── assets
├── dashboard
├── uploads
├── config.php
├── db.sql
├── index.php
├── update.md
└── red.md
```

Jika upload dari komputer Windows memakai `scp`, contoh:

```powershell
scp -r C:\Users\hnzxz\Documents\MYCODE\merpatools-host\* root@192.168.1.20:/opt/merpatools/
```

Jika memakai WinSCP, cukup drag semua file project ke `/opt/merpatools`.

## 5. Buat File Docker Compose

Di STB, masuk ke folder project:

```bash
cd /opt/merpatools
```

Buat file `docker-compose.yml`:

```bash
nano docker-compose.yml
```

Isi dengan konfigurasi berikut:

```yaml
services:
  app:
    image: php:8.2-apache
    container_name: merpatools_app
    restart: unless-stopped
    ports:
      - "8088:80"
    environment:
      MERPATOOLS_DB_HOST: db
      MERPATOOLS_DB_NAME: merpatools
      MERPATOOLS_DB_USER: merpatools
      MERPATOOLS_DB_PASS: merpatools_password_ganti
      MERPATOOLS_GOOGLE_CLIENT_ID: ""
    volumes:
      - ./:/var/www/html
      - ./uploads:/var/www/html/uploads
    depends_on:
      - db
    command: >
      bash -lc "docker-php-ext-install pdo pdo_mysql mysqli gd
      && a2enmod rewrite
      && chown -R www-data:www-data /var/www/html/uploads
      && apache2-foreground"

  db:
    image: mariadb:10.11
    container_name: merpatools_db
    restart: unless-stopped
    environment:
      MYSQL_DATABASE: merpatools
      MYSQL_USER: merpatools
      MYSQL_PASSWORD: merpatools_password_ganti
      MYSQL_ROOT_PASSWORD: root_password_ganti
      TZ: Asia/Jakarta
    volumes:
      - db_data:/var/lib/mysql
    ports:
      - "3307:3306"

volumes:
  db_data:
```

Simpan file:

- Tekan `CTRL + O`
- Tekan `Enter`
- Tekan `CTRL + X`

Catatan penting:

- Ganti `merpatools_password_ganti` dengan password yang kuat.
- Ganti `root_password_ganti` dengan password root database yang kuat.
- Port app adalah `8088`.
- Port database host adalah `3307`, supaya tidak bentrok dengan database lain.

## 6. Jalankan Container

Masuk folder project:

```bash
cd /opt/merpatools
```

Jalankan:

```bash
docker compose up -d
```

Cek status:

```bash
docker compose ps
```

Harus terlihat service `app` dan `db` dalam kondisi running.

Cek log app:

```bash
docker logs -f merpatools_app
```

Cek log database:

```bash
docker logs -f merpatools_db
```

Keluar dari log:

```bash
CTRL + C
```

## 7. Buka Web App

Dari browser di laptop/HP yang satu jaringan dengan STB, buka:

```text
http://IP-STB:8088
```

Contoh:

```text
http://192.168.1.20:8088
```

Jika halaman terbuka, berarti app sudah jalan.

## 8. Setup Database Otomatis

App ini menjalankan fungsi `install_database()` dari `config.php`.

Saat halaman pertama kali dibuka, app akan:

- Membuat database jika belum ada
- Membuat tabel `users`
- Membuat tabel `burung`
- Membuat tabel `latihan`
- Membuat tabel `detail_latihan`
- Membuat tabel komunitas seperti `clubs`, `club_members`, `posts`, `comments`
- Membuat tabel ETS seperti `devices`, `device_logs`, `ets_checkins`
- Membuat tabel `audit_logs`

Jika browser menampilkan error database, cek:

```bash
docker logs merpatools_app
docker logs merpatools_db
```

Pastikan environment di `docker-compose.yml` sama:

```text
MERPATOOLS_DB_HOST=db
MERPATOOLS_DB_NAME=merpatools
MERPATOOLS_DB_USER=merpatools
MERPATOOLS_DB_PASS=merpatools_password_ganti
```

## 9. Buat Akun Pertama

Buka:

```text
http://IP-STB:8088/index.php?page=register
```

Isi:

- Nama kandang/club
- Email
- Username
- Password

Setelah daftar, lengkapi profil kandang:

- Nama pemilik
- Latitude kandang
- Longitude kandang

Gunakan tombol lokasi atau klik titik di peta.

## 10. Tambah Data Merpati

Masuk ke menu:

```text
Merpati
```

Klik `Tambah`.

Isi data:

- Nomor ring
- RFID Tag jika ada
- Nama burung
- Warna
- Jenis kelamin
- Tanggal lahir
- Bloodline
- Induk jantan
- Induk betina
- Berat gram
- Status
- Catatan
- Foto

Klik simpan.

## 11. Generate API Key ETS

Masuk ke menu:

```text
Kandang
```

Pada bagian `API Key ETS RFID`, klik:

```text
Generate
```

Simpan API key ini untuk perangkat ESP32/RFID.

Contoh API key:

```text
3d7f...panjang...a92c
```

Jangan bagikan API key ke orang lain.

## 12. Buat Sesi Latihan

Masuk ke:

```text
Latihan
```

Isi:

- Nama sesi
- Nama titik lepas
- Tanggal dan jam lepas
- Latitude titik lepas
- Longitude titik lepas
- Centang `Publik di Global Live Race` jika ingin tampil di halaman publik
- Pilih merpati

Klik:

```text
Mulai Latihan
```

Setelah itu app akan masuk ke halaman live race.

## 13. Tes Check-in Manual

Di halaman live race, klik tombol:

```text
Tandai Tiba
```

Jika berhasil:

- Status burung menjadi sampai
- MPM dihitung otomatis
- Koefisien dihitung otomatis
- Ranking berubah otomatis

## 14. Tes Check-in ETS/RFID

Endpoint lama:

```text
POST http://IP-STB:8088/api/ets-checkin.php
```

Endpoint baru versi API:

```text
POST http://IP-STB:8088/api/v1/ets/checkin.php
```

Body JSON:

```json
{
  "api_key": "ISI_API_KEY_KANDANG",
  "rfid_tag": "RFID_TAG_BURUNG"
}
```

Contoh memakai `curl` dari laptop atau STB:

```bash
curl -X POST http://IP-STB:8088/api/v1/ets/checkin.php \
  -H "Content-Type: application/json" \
  -d '{"api_key":"ISI_API_KEY_KANDANG","rfid_tag":"RFID_TAG_BURUNG"}'
```

Jika berhasil, response kira-kira:

```json
{
  "ok": true,
  "message": "Check-in RFID berhasil.",
  "ring": "ID2026-001",
  "arrival": "2026-05-15 09:30:10",
  "speed_mpm": 812.45,
  "koefisien": 8.12,
  "poin": 100
}
```

Jika gagal karena RFID belum terdaftar:

```json
{
  "ok": false,
  "message": "RFID tag belum terdaftar di kandang ini."
}
```

## 15. Contoh Kode ESP32 Sederhana

Contoh request HTTP dari ESP32:

```cpp
#include <WiFi.h>
#include <HTTPClient.h>

const char* ssid = "NAMA_WIFI";
const char* password = "PASSWORD_WIFI";

const char* endpoint = "http://IP-STB:8088/api/v1/ets/checkin.php";
const char* apiKey = "ISI_API_KEY_KANDANG";

void setup() {
  Serial.begin(115200);
  WiFi.begin(ssid, password);

  while (WiFi.status() != WL_CONNECTED) {
    delay(500);
    Serial.print(".");
  }

  Serial.println("WiFi connected");
}

void loop() {
  String rfidTag = "RFID_TAG_BURUNG";

  if (WiFi.status() == WL_CONNECTED) {
    HTTPClient http;
    http.begin(endpoint);
    http.addHeader("Content-Type", "application/json");

    String body = "{\"api_key\":\"" + String(apiKey) + "\",\"rfid_tag\":\"" + rfidTag + "\"}";
    int code = http.POST(body);

    Serial.println(code);
    Serial.println(http.getString());

    http.end();
  }

  delay(10000);
}
```

Ganti:

- `NAMA_WIFI`
- `PASSWORD_WIFI`
- `IP-STB`
- `ISI_API_KEY_KANDANG`
- `RFID_TAG_BURUNG`

## 16. Tes Halaman Publik

Buka:

```text
http://IP-STB:8088/index.php?page=home
```

Yang perlu dicek:

- Statistik global muncul
- Live race publik muncul jika ada sesi aktif dan dicentang publik
- Leaderboard global muncul jika sudah ada burung finish
- Section startup platform muncul
- Roadmap produk muncul

## 17. Tes API Live Race Publik

Endpoint lama:

```text
http://IP-STB:8088/api/get-global-live.php
```

Endpoint baru:

```text
http://IP-STB:8088/api/v1/races/live.php
```

Tes dengan curl:

```bash
curl http://IP-STB:8088/api/v1/races/live.php
```

Response sukses:

```json
{
  "ok": true,
  "data": []
}
```

Jika ada sesi live publik, array `data` akan berisi sesi race.

## 18. Akses dari Luar Jaringan

Untuk akses dari internet, ada beberapa opsi.

### Opsi A: Cloudflare Tunnel

Cocok jika tidak ingin membuka port router.

Install Cloudflared sesuai arsitektur STB, lalu arahkan tunnel ke:

```text
http://localhost:8088
```

### Opsi B: Nginx Proxy Manager di CasaOS

Jika memakai Nginx Proxy Manager:

- Domain: `merpatools.domainkamu.com`
- Forward hostname: IP STB
- Forward port: `8088`
- SSL: aktifkan Let's Encrypt

### Opsi C: Port Forward Router

Forward port router:

```text
Public 80  -> STB 8088
Public 443 -> Nginx Proxy Manager 443
```

Gunakan HTTPS jika app dibuka dari internet.

## 19. Backup Database

Backup manual:

```bash
docker exec merpatools_db mariadb-dump -u merpatools -p merpatools > /opt/merpatools/backup-merpatools.sql
```

Masukkan password:

```text
merpatools_password_ganti
```

Restore:

```bash
docker exec -i merpatools_db mariadb -u merpatools -p merpatools < /opt/merpatools/backup-merpatools.sql
```

Backup folder upload:

```bash
tar -czvf /opt/merpatools/uploads-backup.tar.gz /opt/merpatools/uploads
```

## 20. Update Project di STB

Jika ada file baru dari komputer:

1. Upload file baru ke `/opt/merpatools`
2. Restart container:

```bash
cd /opt/merpatools
docker compose restart app
```

Jika mengubah `docker-compose.yml`:

```bash
docker compose down
docker compose up -d
```

## 21. Troubleshooting

### Halaman tidak bisa dibuka

Cek container:

```bash
docker compose ps
```

Cek port:

```bash
ss -tulpn | grep 8088
```

Cek log:

```bash
docker logs merpatools_app
```

### Error database

Cek database running:

```bash
docker logs merpatools_db
```

Cek environment database di `docker-compose.yml`.

Restart:

```bash
docker compose restart db app
```

### Upload foto gagal

Pastikan folder upload writable:

```bash
sudo chmod -R 775 /opt/merpatools/uploads
sudo chown -R 33:33 /opt/merpatools/uploads
```

User `33:33` adalah `www-data` di container Apache PHP.

### Map tidak muncul

Pastikan browser punya internet karena tile map memakai OpenStreetMap:

```text
https://tile.openstreetmap.org
```

Jika STB offline tapi browser online, map tetap bisa muncul karena tile diambil dari browser.

### ETS check-in gagal

Cek:

- API key sudah dibuat di menu Kandang
- RFID tag sudah diisi di data merpati
- Ada sesi latihan aktif
- Burung yang discan ikut dipilih dalam sesi latihan
- Jam perangkat tidak lebih awal dari jam lepas

Tes cepat:

```bash
curl -X POST http://IP-STB:8088/api/v1/ets/checkin.php \
  -H "Content-Type: application/json" \
  -d '{"api_key":"ISI_API_KEY","rfid_tag":"ISI_RFID"}'
```

## 22. Checklist Uji Coba

Gunakan checklist ini setelah deploy:

- [ ] App terbuka di `http://IP-STB:8088`
- [ ] Bisa register user baru
- [ ] Bisa login
- [ ] Bisa isi profil kandang
- [ ] Bisa generate API key ETS
- [ ] Bisa tambah merpati
- [ ] Bisa upload foto
- [ ] Bisa isi RFID tag
- [ ] Bisa buat latihan
- [ ] Bisa pilih titik lepas di map
- [ ] Bisa check-in manual
- [ ] Bisa check-in via API ETS
- [ ] Halaman live race berubah setelah check-in
- [ ] Halaman publik menampilkan live race publik
- [ ] Leaderboard global muncul setelah ada finish
- [ ] Backup database berhasil dibuat

## 23. Rekomendasi Produksi

Untuk penggunaan komunitas yang lebih serius:

- Pakai domain sendiri
- Aktifkan HTTPS
- Jangan pakai password database default
- Backup database harian
- Backup folder `uploads`
- Batasi akses database dari luar
- Gunakan Cloudflare Tunnel atau Nginx Proxy Manager
- Monitor container dengan Uptime Kuma
- Simpan API key ETS dengan aman

## 24. Ringkasan Endpoint Penting

Web app:

```text
http://IP-STB:8088
```

Register:

```text
http://IP-STB:8088/index.php?page=register
```

Login:

```text
http://IP-STB:8088/index.php?page=login
```

Public leaderboard:

```text
http://IP-STB:8088/index.php?page=home
```

ETS check-in:

```text
POST http://IP-STB:8088/api/v1/ets/checkin.php
```

Global live race:

```text
GET http://IP-STB:8088/api/v1/races/live.php
```

## 25. Catatan Penting

File `config.php` sudah mendukung environment variable:

```text
MERPATOOLS_DB_HOST
MERPATOOLS_DB_NAME
MERPATOOLS_DB_USER
MERPATOOLS_DB_PASS
MERPATOOLS_GOOGLE_CLIENT_ID
```

Artinya app bisa dijalankan di STB, VPS, CasaOS, Coolify, Railway, atau hosting lain tanpa mengubah credential langsung di source code.

Untuk percobaan pertama di STB, cukup gunakan Docker Compose di atas.
