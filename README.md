# Filament AI Document Generator

Sistem penghasil dokumen otomatis berbasis AI (Gemini 2.5 Flash) yang terintegrasi dengan Filament PHP. Proyek ini mendemonstrasikan bagaimana AI dapat digunakan untuk mendesain dan mengisi dokumen `.docx` dan `.xlsx` secara dinamis berdasarkan template yang ada.

## Fitur Utama

- **Analisis Template Cerdas**: Sistem membaca struktur file Word (.docx) atau Excel (.xlsx) dan meminta AI untuk mengisinya sesuai konteks.
- **Dukungan File Sumber (Context-Aware)**: Bisa membaca file referensi (PDF, DOCX, XLSX, TXT) yang diupload user untuk akurasi data yang lebih tinggi.
- **Generasi Tabel Otomatis**: Mendukung pengisian tabel dinamis di dalam dokumen Word.
- **Integrasi Gemini 2.5 Flash**: Menggunakan model terbaru Google Gemini untuk pemrosesan teks yang cepat dan cerdas.
- **Status Real-time**: Pelacakan proses generasi dari `Pending` hingga `Completed`.

## Stack Teknologi

- **Framework**: Laravel 11 & Filament v3
- **AI Engine**: Google Gemini API (`gemini-2.5-flash`)
- **Document Processing**:
  - [PHPWord](https://github.com/PHPOffice/PHPWord) untuk manipulasi DOCX.
  - [PhpSpreadsheet](https://github.com/PHPOffice/PhpSpreadsheet) untuk manipulasi XLSX.
  - [Smalot PDFParser](https://github.com/smalot/pdf-parser) untuk ekstraksi teks PDF.
- **Database**: PostgreSQL

## Instalasi

1. Clone repository
2. Install dependensi:
   ```bash
   composer install
   npm install && npm run build
   ```
3. Set environment variable di `.env`:
   ```env
   GEMINI_API_KEY=your_api_key_here
   GEMINI_MODEL=gemini-2.5-flash
   ```
4. Jalankan migrasi:
   ```bash
   php artisan migrate
   ```
5. Simpan link storage:
   ```bash
   php artisan storage:link
   ```

## Cara Penggunaan

1. **Kelola Template**: Di menu `Document Templates`, upload file `.docx` atau `.xlsx` yang ingin dijadikan pola.
2. **Generate Dokumen**:
   - Buka menu `Generated Documents`.
   - Klik `New Generated Document`.
   - Pilih template.
   - Masukkan instruksi di bidang `Prompt`.
   - (Opsional) Upload file sumber seperti PDF atau Excel lama sebagai referensi data bagi AI.
3. **Hasil**: Tunggu proses selesai dan download file hasil yang muncul di kolom `Result File`.

## Dokumentasi Proyek

Untuk memahami lebih dalam tentang sistem ini, silakan merujuk pada dokumen berikut:

| Dokumen | Deskripsi | Target Pembaca |
| :--- | :--- | :--- |
| **[Product Requirements Document](docs/prd-ai-generator.md)** | Penjelasan detail kebutuhan bisnis, model data, dan alur sistem secara menyeluruh. | Manajer Proyek, Analis Sistem |
| **[Panduan Penggunaan Template](docs/template-guide.md)** | Instruksi langkah demi langkah membuat template Word dan Excel yang kompatibel dengan sistem. | Pengguna Akhir (Admin/Staf) |
| **[Panduan Teknis](docs/technical-guide.md)** | Dokumentasi implementasi kode, alur logika internal, dan dependensi sistem. | Pengembang (Developer) |

---

> *"Perfection is not a destination, but a continuous journey. This project is a living exploration of AI's potential in document automation constantly evolving, improving, and adapting to new possibilities."*

Copyright 2026 AI Document Generator Project
