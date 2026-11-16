# File Manager (SLiMS plugin)

Manajemen terpusat untuk file lampiran bibliografi: tabel `files` dan relasinya di `biblio_attachment`.

Menu: **Master File → LAMPIRAN FILE → File Manager**

## Fitur
- **Daftar file**
  - Pencarian (judul, nama file, deskripsi).
  - Filter: jenis (file/URL), status (terlampir/tidak terlampir), MIME, dan folder.
  - Sort dan paginasi.
  - Badge *File hilang*, *Tidak terlampir*, dan *Password*.
- **Detail file**: metadata, ukuran, uploader, dan daftar bibliografi yang memakai file.
  - Pratinjau dan unduh lewat stream milik plugin (privilege `master_file` r).
- **Ubah metadata**: judul, deskripsi, password file (PDF), dan URL untuk lampiran URL.
- **Unggah file baru** ke folder repository yang dipilih, atau tambah lampiran **URL**.
- **Ganti file fisik** tanpa mengubah `file_id`, sehingga relasi ke bibliografi tetap.
- **Kelola relasi**:
  - Cari dan lampirkan ke bibliografi.
  - Lepaskan dari bibliografi.
  - Atur penempatan (link/popup/embed), akses (publik/privat), dan batas tipe anggota.
- **Hapus**
  - Jika file masih terlampir: peringatan + daftar bibliografi, lalu konfirmasi pelepasan.
  - Opsi ikut menghapus file fisik. Opsi ini otomatis dinonaktifkan bila file dipakai data lain.
- **Pembersihan**
  - *File hilang*: baris yang file fisiknya tidak ada.
  - *Tidak terlampir*: baris yatim.
  - *File tidak terdaftar*: file di `repository/` yang tidak dirujuk DB.
  - Hapus massal (maks. 100 per aksi).
- Semua aksi tulis dicatat ke **System Log** (`writeLog`, submodul `FileManager`).

## Instalasi
1. Folder ini berada di `plugins/file_manager`.
2. Aktifkan di **System → Plugins**.
3. Untuk user non-super-admin:
   - Beri privilege modul **Master File**: Read untuk melihat, Write untuk mengubah.
   - Centang menu *File Manager* di **System → User Group**.

## Keamanan
| Lapisan | Implementasi |
|---|---|
| Akses | `INDEX_AUTH`, `do_checkIP('smc'/'smc-masterfile')`, `session.inc.php` + `session_check.inc.php`, `havePrivilege('master_file','r'/'w')`, cek izin menu plugin untuk user non-admin |
| CSRF | Token 256-bit per sesi (`random_bytes`), dibandingkan dengan `hash_equals`, wajib di setiap POST. GET tidak pernah mengubah data |
| SQL injection | PDO prepared statement. Kolom/arah sort di-whitelist. `LIMIT/OFFSET` di-cast int. Wildcard `LIKE` di-escape |
| XSS | Semua output di-escape (`View::e`). JS hanya memakai `textContent` untuk data. JSON memakai flag `JSON_HEX_*` |
| Path traversal | Path dibangun dari `REPOBS` lalu diverifikasi `realpath()` tetap di dalam repository. `..`, null byte, path absolut, symlink keluar root, dan file `index.html/.htaccess` ditolak |
| Upload | Ekstensi whitelist `allowed_file_att` (case-insensitive) + denylist keras (php, phtml, phar, html, svg, js, dll.), termasuk ekstensi ganda. MIME dicek dari isi file (`finfo`). Batas ukuran `max_upload`. Nama acak `random_bytes`. Gambar JPEG/PNG di-encode ulang dengan GD (buang EXIF/payload). Validasi dilakukan sebelum file masuk repository |
| Unduhan | `Content-Type` dari whitelist, `X-Content-Type-Options: nosniff`, CSP `sandbox` (kecuali PDF), inline hanya untuk tipe aman |
| URL | Hanya http/https. Kredensial di URL, localhost, dan IP privat/reserved ditolak |
| Deserialisasi | `access_limit` dibaca dengan `unserialize(..., ['allowed_classes' => false])` |
| Mass assignment | Hanya kolom whitelist yang bisa diubah |
| Kebocoran | Password file tidak pernah ditampilkan. Error DB tidak dikirim ke browser (hanya `error_log`) |

## Struktur
```
file_manager.plugin.php   registrasi menu
index.php                 entry: cek keamanan, lalu Controller
src/                      Controller, FileRepository (PDO), Disk, Uploader, Csrf, View, Icons, Lang
views/                    template PHP
assets/fm.css, fm.js      UI gaya shadcn/ui (token zinc, di-scope ke .fm-root), vanilla JS
lang/id_ID.php            terjemahan Indonesia
```

Ikon: subset [Lucide](https://lucide.dev) (ISC), dibundel lokal tanpa CDN.
