# Panduan Teknis Pengembangan

Dokumen ini menjelaskan alur internal sistem AI Document Generator untuk pengembang.

## Alur Generasi Konten

Sistem menggunakan class `DocumentGeneratorService` untuk menangani semua logika pembuatan dokumen.

### 1. Ekstraksi Dokumen Sumber
Method `extractSourceFileContent($filePath)` akan mendeteksi tipe file:
- **PDF**: Menggunakan class `Parser` dari library `smalot/pdfparser`.
- **Word**: Menggunakan `WordIOFactory::load()` untuk membaca paragraf dan tabel.
- **Excel**: Menggunakan `SpreadsheetIOFactory::load()` untuk membaca semua sheet dan mengonversinya ke format teks TSV agar mudah dipahami AI.

### 2. Analisis Struktur Template (DOCX)
Method `analyzeDocxTemplate($filePath)` melakukan iterasi pada setiap elemen dokumen:
- Mengidentifikasi apakah elemen tersebut adalah `TextRun` (paragraf) atau `Table`.
- Menyimpan index dan konten asli sebagai konteks struktur.
- Hasil analisis dikirim ke AI agar AI tahu berapa banyak "BAGIAN" yang harus dihasilkan.

### 3. Prompt Engineering
Sistem membangun prompt yang sangat ketat:
- **System Instruction**: Meminta AI menjadi generator dokumen tanpa penjelasan/markdown.
- **Structure Context**: Memberitahu AI struktur template yang ditemukan.
- **Source Context**: (Jika ada) Memberikan data mentah yang diekstrak dari file sumber.
- **User Prompt**: Instruksi spesifik dari user melalui Filament UI.

### 4. Parsing dan Rekonstruksi
Setelah AI memberikan response:
- Role `parseAiResponse` memilah teks berdasarkan prefix `BAGIAN n:` dan `TABEL n:`.
- Method `createDocxFromStructure`:
  - Membuat `PhpWord` instance baru.
  - Meniru elemen dari template asli (Font, Bold, Alignment).
  - Memasukkan konten dari AI ke dalam elemen baru tersebut.

## Perintah Berguna

### Membersihkan Cache
Jika Anda melakukan perubahan pada config atau routes:
```bash
php artisan config:clear
php artisan cache:clear
```

### Menjalankan Queue
Sistem ini mendukung proses background (jika diaktifkan di PRD):
```bash
php artisan queue:work
```

### Monitoring Log
Semua aktivitas AI dicatat di log:
```bash
tail -f storage/logs/laravel.log
```

## Dependensi Penting
- `google/gax`: Digunakan oleh Gemini Client.
- `phpoffice/phpword`: Manipulasi Docx.
- `phpoffice/phpspreadsheet`: Manipulasi Xlsx.
- `smalot/pdfparser`: Ekstraksi PDF.
