# Panduan Membuat Template Dokumen (Generasi Dinamis)

Sistem AI Document Generator menggunakan pendekatan **Dynamic Rebuilding**. Sistem akan membaca struktur template Anda (paragraf, gaya teks, dan tabel) dan meminta AI untuk mendesain konten baru yang mengikuti gaya tersebut.

---

## Template Word (.docx)

Anda tidak perlu lagi menggunakan placeholder khusus seperti `${nama}`. Sistem bekerja dengan cara:

1. **Gunakan Teks Contoh**: Buat dokumen Word dengan teks contoh yang menunjukkan struktur yang Anda inginkan.
2. **AI Membaca Struktur**: Jika template memiliki 3 paragraf dan 1 tabel, AI akan diberikan instruksi untuk mengisi "BAGIAN 1", "BAGIAN 2", "BAGIAN 3", dan "TABEL 1".
3. **Gaya Teks (Styling)**: Sistem akan mencoba mempertahankan gaya teks (Bold, Italic, Font Size) dari template asli.

### Tips Membuat Template DOCX:
- **Tabel**: Buat tabel dengan baris header yang jelas. AI akan mengisi baris data di bawahnya secara otomatis.
- **Urutan**: AI mengisi dokumen dari atas ke bawah. Pastikan urutan teks contoh Anda logis.
- **Deskripsi dalam Template**: Anda bisa menulis instruksi kecil di dalam template, misalnya: `[Tuliskan ringkasan eksekutif di sini]`. AI akan mengganti teks tersebut dengan konten yang dihasilkan.

---

## Template Excel (.xlsx)

Untuk Excel, sistem fokus pada pengisian data tabel:

1. **Header di Baris 1**: Pastikan baris pertama berisi nama-nama kolom (misal: Nama, Alamat, No. Telp).
2. **Generasi Data**: AI akan mengisi baris ke-2 dan seterusnya berdasarkan instruksi Anda.
3. **Dukungan Formula**: Disarankan untuk menambahkan formula atau formatting pada kolom jika diperlukan setelah file didownload, karena AI hanya menghasilkan data mentah (text/numbers).

---

## Menggunakan File Sumber (Source File)

Sistem memiliki fitur unggulan di mana Anda bisa mengupload file sumber (PDF/Sreapsheet/Word) sebagai **referensi**.

**Skenario Contoh:**
- **Template**: Template Laporan Mingguan (.docx).
- **Source File**: File Excel berisi log aktivitas harian.
- **Prompt**: "Buatkan laporan mingguan berdasarkan log aktivitas ini."
- **Hasil**: AI akan membaca log dari Excel, meringkasnya, dan memasukkannya ke dalam struktur Template Word.

---

## Hal yang Perlu Diperhatikan
- **Ukuran File**: Hindari template yang terlalu besar (> 10MB) karena dapat memperlambat proses analisis struktur.
- **Tabel Kompleks**: Untuk hasil terbaik, gunakan tabel sederhana. Hindari *merged cells* yang terlalu rumit di dalam template.
