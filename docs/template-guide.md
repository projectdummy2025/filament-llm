# Panduan Membuat Template Dokumen

## Format Placeholder untuk Template DOCX

Agar sistem AI Document Generator dapat mengisi konten ke dalam template, Anda **WAJIB** menggunakan placeholder dengan format:

```
${nama_variabel}
```

### Contoh Placeholder yang Valid:
- `${judul}` - untuk judul dokumen
- `${ringkasan}` - untuk ringkasan/abstrak
- `${isi}` - untuk isi utama dokumen
- `${nama}` - untuk nama
- `${tanggal}` - untuk tanggal
- `${nomor_surat}` - untuk nomor surat
- `${nama_perusahaan}` - untuk nama perusahaan

---

## Tipe Placeholder

### 1. Placeholder Biasa (Teks Statis)
Untuk teks yang tidak berulang seperti judul, deskripsi, tanggal, dll.

**Format:** `${nama_variabel}`

**Contoh di Word:**
```
Judul: ${judul}
Tanggal: ${tanggal}
Deskripsi: ${deskripsi}
```

### 2. Placeholder Tabel (Data Berulang)
Untuk data yang berbentuk tabel dengan banyak baris.

**Format:** `${nama_kolom#1}` (perhatikan `#1` di akhir)

**Contoh di Word (buat tabel):**
| Nama | Nilai | Keterangan |
|------|-------|------------|
| ${nama#1} | ${nilai#1} | ${keterangan#1} |

**Penjelasan:**
- Baris pertama tabel adalah header (teks statis)
- Baris kedua berisi placeholder dengan akhiran `#1`
- Sistem akan otomatis menduplikasi baris ini sesuai jumlah data dari AI

---

## Cara Membuat Template di Microsoft Word

### Template Teks Biasa:

1. Buka Microsoft Word
2. Buat dokumen dengan layout yang diinginkan
3. Di tempat yang ingin diisi oleh AI, ketik placeholder seperti `${judul}`, `${isi}`, dll.
4. Simpan sebagai file `.docx`
5. Upload file tersebut sebagai template

### Template dengan Tabel:

1. Buka Microsoft Word
2. Insert → Table
3. Baris pertama: ketik header kolom (Nama, Nilai, dst.)
4. Baris kedua: ketik placeholder dengan format `${nama_kolom#1}`
5. Simpan sebagai file `.docx`

**Contoh Template dengan Tabel:**
```
                    ${judul}
                    
Deskripsi:
${deskripsi}

Daftar Nilai:
┌──────────┬───────┬─────────────┐
│   Nama   │ Nilai │ Keterangan  │
├──────────┼───────┼─────────────┤
│${nama#1} │${nilai#1}│${keterangan#1}│
└──────────┴───────┴─────────────┘

Kesimpulan: ${kesimpulan}
```

---

## Contoh Output AI untuk Template dengan Tabel

Ketika template memiliki tabel, AI akan menghasilkan output dengan format campuran:

```
judul=Laporan Nilai Siswa
deskripsi=Berikut adalah daftar nilai siswa semester 1
TABLE_START
nama	nilai	keterangan
Budi Santoso	85	Lulus
Ani Wijaya	92	Lulus dengan predikat baik
Candra Pratama	78	Lulus
TABLE_END
kesimpulan=Rata-rata nilai siswa adalah 85
```

---

## PENTING: Kesalahan Umum

### ❌ Template Tanpa Placeholder
Jika template Anda tidak memiliki placeholder `${...}`, maka:
- AI akan menghasilkan konten
- **TAPI** konten tersebut tidak akan dimasukkan ke dokumen
- File output akan sama persis dengan template asli!

### ❌ Placeholder Terpisah
Kadang Microsoft Word memecah teks menjadi beberapa "run" sehingga placeholder tidak terdeteksi:

**Salah** (di XML internal): `${` `judul` `}` (terpisah)
**Benar**: `${judul}` (satu kesatuan)

**Solusi**: Ketik placeholder dalam satu ketikan tanpa jeda. Atau copy-paste dari teks biasa.

### ❌ Lupa `#1` untuk Placeholder Tabel
Untuk placeholder dalam tabel, **WAJIB** tambahkan `#1` di akhir:
- **Salah**: `${nama}` dalam tabel
- **Benar**: `${nama#1}` dalam tabel

### ✅ Tips Membuat Placeholder yang Benar:
1. Ketik placeholder secara langsung tanpa jeda
2. Jangan format sebagian teks dalam placeholder (misal bold hanya pada kata tertentu)
3. Gunakan nama variabel sederhana tanpa spasi (gunakan underscore: `nama_lengkap`)
4. Untuk tabel, selalu gunakan format `${nama#1}`
5. Test template dengan menjalankan generate untuk memastikan placeholder terdeteksi

---

## Template Contoh yang Tersedia

### 1. Template Teks Biasa
`storage/app/public/templates/contoh-template-dengan-placeholder.docx`

Placeholder:
- `${judul}`
- `${ringkasan}`
- `${isi}`

### 2. Template dengan Tabel
`storage/app/public/templates/contoh-template-dengan-tabel.docx`

Placeholder:
- `${judul}` (teks biasa)
- `${deskripsi}` (teks biasa)
- `${nama#1}`, `${nilai#1}`, `${keterangan#1}` (tabel)
- `${kesimpulan}` (teks biasa)

---

## Verifikasi Template

Untuk memverifikasi apakah template Anda memiliki placeholder yang benar, lihat log aplikasi setelah proses generate:

```
[INFO] Template placeholders found: judul, deskripsi, nama#1, nilai#1, keterangan#1, kesimpulan
```

Jika muncul:
```
[ERROR] CRITICAL: Template has NO placeholders!
```

Maka template Anda perlu diperbaiki dengan menambahkan placeholder.
