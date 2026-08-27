<?php

/* =========================================================
   IMPORT EXCEL - SKILL MONITORING
   GARUDAFOOD • TEKNIK

   SESUAI STRUKTUR TABEL PEKERJA:

   pekerja
   ├── id
   ├── no_reg
   ├── nama
   ├── departemen
   ├── keterangan
   └── status

   CATATAN:
   - TIDAK menggunakan id_jabatan
   - TIDAK menggunakan tabel jabatan
   - Nama pekerja dari header Excel dicocokkan
     dengan pekerja.nama
   - Jika pekerja belum ada, otomatis dibuat
   - Departemen / keterangan / status tidak ditimpa
     saat import nilai skill
========================================================= */


/* =========================================================
   1. KONEKSI
========================================================= */

require_once __DIR__ . '/../config/database.php';


/* =========================================================
   HELPER E()
========================================================= */

if (!function_exists('e')) {

    function e($v)
    {
        return htmlspecialchars(
            (string)$v,
            ENT_QUOTES,
            'UTF-8'
        );
    }
}


/* =========================================================
   2. PHPSPREADSHEET
========================================================= */

$autoload =
    __DIR__ . '/../vendor/autoload.php';


if (!file_exists($autoload)) {

    $page_title =
        'Import Data Excel';

    require __DIR__ . '/../partials/header.php';

    echo '

    <div class="alert alert-danger border-0 shadow-sm">

        <strong>
            <i class="bi bi-exclamation-triangle-fill me-1"></i>
            PhpSpreadsheet belum terpasang.
        </strong>

        <div class="mt-2">

            Jalankan:

            <code>
                composer require phpoffice/phpspreadsheet
            </code>

        </div>

    </div>

    ';

    require __DIR__ . '/../partials/footer.php';

    exit;
}


require_once $autoload;


use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;


/* =========================================================
   3. HELPER
========================================================= */

function clean_text($value)
{
    $value = trim((string)$value);

    $value = preg_replace(
        '/\s+/',
        ' ',
        $value
    );

    return $value;
}


function normalize_name($value)
{
    $value =
        clean_text($value);

    /*
     * Normalisasi tambahan
     */

    $value =
        preg_replace(
            '/\s+/',
            ' ',
            $value
        );

    return strtolower(
        trim($value)
    );
}


function normalize_skill($value)
{
    $value =
        clean_text($value);

    return strtolower(
        trim($value)
    );
}


/* =========================================================
   4. CARI PEKERJA BERDASARKAN NAMA
=========================================================

   PENTING:

   Import sekarang mengikuti struktur Data Pekerja.

   Tidak lagi:

       id_jabatan
       tabel jabatan

========================================================= */

function getPekerjaId(
    $conn,
    $nama
) {

    $nama =
        clean_text($nama);


    if ($nama === '') {

        return 0;
    }


    /*
     * Cari berdasarkan nama
     */

    $stmt =
        $conn->prepare("
            SELECT
                id
            FROM pekerja
            WHERE
                LOWER(
                    TRIM(nama)
                )
                =
                LOWER(
                    TRIM(?)
                )
            LIMIT 1
        ");


    if (!$stmt) {

        throw new Exception(
            'Gagal menyiapkan query pencarian pekerja.'
        );
    }


    $stmt->bind_param(
        's',
        $nama
    );


    $stmt->execute();


    $result =
        $stmt->get_result();


    if (
        $row =
        $result->fetch_assoc()
    ) {

        $id =
            (int)$row['id'];

        $stmt->close();

        return $id;
    }


    $stmt->close();


    /*
     * Jika belum ada:
     * buat pekerja baru.
     *
     * Data lainnya dibuat default.
     *
     * Departemen:
     * Belum Diisi
     *
     * Keterangan:
     * Diimport dari Excel
     *
     * Status:
     * Aktif
     */

    $departemen =
        'Belum Diisi';

    $keterangan =
        'Ditambahkan melalui import Excel';

    $status =
        'Aktif';


    $stmt =
        $conn->prepare("
            INSERT INTO pekerja
            (
                nama,
                departemen,
                keterangan,
                status
            )
            VALUES
            (?, ?, ?, ?)
        ");


    if (!$stmt) {

        throw new Exception(
            'Gagal menyiapkan query tambah pekerja.'
        );
    }


    $stmt->bind_param(
        'ssss',
        $nama,
        $departemen,
        $keterangan,
        $status
    );


    if (!$stmt->execute()) {

        $error =
            $stmt->error;

        $stmt->close();

        throw new Exception(
            'Gagal membuat pekerja baru: ' .
            $error
        );
    }


    $id =
        (int)$conn->insert_id;


    $stmt->close();


    return $id;
}


/* =========================================================
   5. CARI / BUAT SKILL
========================================================= */

function getSkillId(
    $conn,
    $namaSkill
) {

    $namaSkill =
        clean_text($namaSkill);


    if ($namaSkill === '') {

        return 0;
    }


    /*
     * Cari skill
     */

    $stmt =
        $conn->prepare("
            SELECT
                id
            FROM skill
            WHERE
                LOWER(
                    TRIM(nama_skill)
                )
                =
                LOWER(
                    TRIM(?)
                )
            LIMIT 1
        ");


    if (!$stmt) {

        throw new Exception(
            'Gagal menyiapkan query pencarian skill.'
        );
    }


    $stmt->bind_param(
        's',
        $namaSkill
    );


    $stmt->execute();


    $result =
        $stmt->get_result();


    if (
        $row =
        $result->fetch_assoc()
    ) {

        $id =
            (int)$row['id'];

        $stmt->close();

        return $id;
    }


    $stmt->close();


    /*
     * Skill baru
     */

    $status =
        'Aktif';


    $stmt =
        $conn->prepare("
            INSERT INTO skill
            (
                nama_skill,
                status
            )
            VALUES
            (?, ?)
        ");


    if (!$stmt) {

        throw new Exception(
            'Gagal menyiapkan query tambah skill.'
        );
    }


    $stmt->bind_param(
        'ss',
        $namaSkill,
        $status
    );


    if (!$stmt->execute()) {

        $error =
            $stmt->error;

        $stmt->close();

        throw new Exception(
            'Gagal membuat skill baru: ' .
            $error
        );
    }


    $id =
        (int)$conn->insert_id;


    $stmt->close();


    return $id;
}


/* =========================================================
   6. SIMPAN NILAI SKILL
========================================================= */

function saveNilai(
    $conn,
    $pekerjaId,
    $skillId,
    $nilai,
    $tahun
) {

    if (
        $pekerjaId <= 0 ||
        $skillId <= 0
    ) {

        return [
            'saved' => false,
            'updated' => false
        ];
    }


    if (
        $nilai === null ||
        $nilai === ''
    ) {

        return [
            'saved' => false,
            'updated' => false
        ];
    }


    /*
     * Normalisasi nilai
     */

    $nilai =
        str_replace(
            ',',
            '.',
            trim((string)$nilai)
        );


    /*
     * Bersihkan karakter non angka
     *
     * Contoh:
     * "3.5 " -> 3.5
     */

    if (
        !is_numeric($nilai)
    ) {

        return [
            'saved' => false,
            'updated' => false
        ];
    }


    $nilai =
        (float)$nilai;


    /*
     * Validasi nilai
     *
     * Skala:
     * 0 - 5
     */

    if ($nilai < 0) {

        $nilai = 0;
    }


    if ($nilai > 5) {

        $nilai = 5;
    }


    /*
     * Cek apakah sudah ada
     */

    $stmt =
        $conn->prepare("
            SELECT
                id
            FROM penilaian_skill
            WHERE
                id_pekerja = ?
                AND id_skill = ?
                AND tahun = ?
            LIMIT 1
        ");


    if (!$stmt) {

        throw new Exception(
            'Gagal mengecek nilai skill.'
        );
    }


    $stmt->bind_param(
        'iii',
        $pekerjaId,
        $skillId,
        $tahun
    );


    $stmt->execute();


    $result =
        $stmt->get_result();


    if (
        $row =
        $result->fetch_assoc()
    ) {

        $id =
            (int)$row['id'];

        $stmt->close();


        /*
         * UPDATE
         */

        $stmt =
            $conn->prepare("
                UPDATE penilaian_skill
                SET
                    nilai = ?
                WHERE
                    id = ?
            ");


        if (!$stmt) {

            throw new Exception(
                'Gagal menyiapkan update nilai.'
            );
        }


        $stmt->bind_param(
            'di',
            $nilai,
            $id
        );


        $ok =
            $stmt->execute();


        $stmt->close();


        return [
            'saved' => $ok,
            'updated' => true
        ];
    }


    $stmt->close();


    /*
     * INSERT
     */

    $stmt =
        $conn->prepare("
            INSERT INTO penilaian_skill
            (
                id_pekerja,
                id_skill,
                nilai,
                tahun
            )
            VALUES
            (?, ?, ?, ?)
        ");


    if (!$stmt) {

        throw new Exception(
            'Gagal menyiapkan insert nilai.'
        );
    }


    $stmt->bind_param(
        'iidi',
        $pekerjaId,
        $skillId,
        $nilai,
        $tahun
    );


    $ok =
        $stmt->execute();


    $stmt->close();


    return [
        'saved' => $ok,
        'updated' => false
    ];
}


/* =========================================================
   7. DETEKSI TAHUN
========================================================= */

function detectYear(
    $sheetName,
    $fileName = ''
) {

    /*
     * Tahun 4 digit
     *
     * Contoh:
     * 2025
     * 2026
     */

    if (
        preg_match(
            '/\b(20\d{2})\b/',
            $sheetName,
            $m
        )
    ) {

        return (int)$m[1];
    }


    if (
        preg_match(
            '/\b(20\d{2})\b/',
            $fileName,
            $m
        )
    ) {

        return (int)$m[1];
    }


    /*
     * Tahun 25 / 26
     */

    if (
        preg_match(
            '/(?:^|\D)(\d{2})(?:\D|$)/',
            $sheetName,
            $m
        )
    ) {

        $yy =
            (int)$m[1];


        if (
            $yy >= 0 &&
            $yy <= 99
        ) {

            return
                2000 + $yy;
        }
    }


    /*
     * Jika tidak terdeteksi:
     * gunakan tahun sekarang
     */

    return
        (int)date('Y');
}


/* =========================================================
   8. DETEKSI JENIS SHEET
========================================================= */

function detectJenisSheet(
    $sheetName
) {

    $name =
        strtolower(
            clean_text(
                $sheetName
            )
        );


    if (
        strpos(
            $name,
            'leader'
        ) !== false
    ) {

        return 'Leader';
    }


    if (
        strpos(
            $name,
            'supervisor'
        ) !== false
    ) {

        return 'Leader';
    }


    if (
        strpos(
            $name,
            'pelaksana'
        ) !== false
    ) {

        return 'Pelaksana';
    }


    return 'Skill';
}


/* =========================================================
   9. CARI BARIS HEADER
========================================================= */

function findHeaderRow(
    $sheet
) {

    $highestRow =
        $sheet->getHighestRow();


    $highestColumn =
        $sheet->getHighestColumn();


    $highestColumnIndex =
        Coordinate::columnIndexFromString(
            $highestColumn
        );


    /*
     * Cari maksimal 20 baris pertama
     */

    for (
        $row = 1;
        $row <= min(
            $highestRow,
            20
        );
        $row++
    ) {

        $values = [];


        for (
            $col = 1;
            $col <= $highestColumnIndex;
            $col++
        ) {

            $value =
                clean_text(
                    $sheet
                        ->getCellByColumnAndRow(
                            $col,
                            $row
                        )
                        ->getValue()
                );


            if (
                $value !== ''
            ) {

                $values[] =
                    strtolower($value);
            }
        }


        foreach (
            $values as $value
        ) {

            /*
             * Kemungkinan header skill
             */

            if (
                strpos(
                    $value,
                    'skill'
                ) !== false
            ) {

                return $row;
            }


            if (
                strpos(
                    $value,
                    'kompetensi'
                ) !== false
            ) {

                return $row;
            }


            if (
                strpos(
                    $value,
                    'nama skill'
                ) !== false
            ) {

                return $row;
            }
        }
    }


    /*
     * Default
     */

    return 1;
}


/* =========================================================
   10. DETEKSI KOLOM SKILL
========================================================= */

function findSkillColumn(
    $sheet,
    $headerRow
) {

    $highestColumn =
        $sheet->getHighestColumn();


    $highestColumnIndex =
        Coordinate::columnIndexFromString(
            $highestColumn
        );


    for (
        $col = 1;
        $col <= $highestColumnIndex;
        $col++
    ) {

        $header =
            clean_text(
                $sheet
                    ->getCellByColumnAndRow(
                        $col,
                        $headerRow
                    )
                    ->getValue()
            );


        $headerLower =
            strtolower(
                $header
            );


        if (
            strpos(
                $headerLower,
                'skill'
            ) !== false
        ) {

            return $col;
        }


        if (
            strpos(
                $headerLower,
                'kompetensi'
            ) !== false
        ) {

            return $col;
        }


        if (
            strpos(
                $headerLower,
                'nama skill'
            ) !== false
        ) {

            return $col;
        }
    }


    /*
     * Default kolom pertama
     */

    return 1;
}


/* =========================================================
   11. CEK APAKAH HEADER ADALAH NAMA PEKERJA
========================================================= */

function isWorkerHeader(
    $value
) {

    $value =
        clean_text($value);


    if (
        $value === ''
    ) {

        return false;
    }


    $lower =
        strtolower($value);


    /*
     * Header umum yang harus diabaikan
     */

    $ignore = [

        'nama',

        'nama pekerja',

        'pekerja',

        'karyawan',

        'employee',

        'nik',

        'no',

        'no.',

        'no reg',

        'no. reg',

        'noreg',

        'reg',

        'jabatan',

        'departemen',

        'department',

        'keterangan',

        'status',

        'skill',

        'kompetensi',

        'nama skill',

        'total',

        'jumlah',

        'rata-rata',

        'rata rata',

        'average',

        'avg',

        'target',

        'actual',

        'nilai'

    ];


    if (
        in_array(
            $lower,
            $ignore,
            true
        )
    ) {

        return false;
    }


    /*
     * Jangan anggap angka sebagai nama
     */

    if (
        is_numeric($value)
    ) {

        return false;
    }


    return true;
}


/* =========================================================
   12. AMBIL NAMA PEKERJA DARI HEADER
========================================================= */

function getWorkerHeaders(
    $sheet,
    $headerRow,
    $skillColumn
) {

    $workers = [];


    $highestColumn =
        $sheet->getHighestColumn();


    $highestColumnIndex =
        Coordinate::columnIndexFromString(
            $highestColumn
        );


    for (
        $col = 1;
        $col <= $highestColumnIndex;
        $col++
    ) {

        /*
         * Kolom skill dilewati
         */

        if (
            $col === $skillColumn
        ) {

            continue;
        }


        $header =
            clean_text(
                $sheet
                    ->getCellByColumnAndRow(
                        $col,
                        $headerRow
                    )
                    ->getValue()
            );


        if (
            !isWorkerHeader(
                $header
            )
        ) {

            continue;
        }


        $workers[$col] =
            $header;
    }


    return $workers;
}


/* =========================================================
   13. PROSES IMPORT
========================================================= */

$success =
    false;


$message =
    '';


$errors = [];


$summary = [

    'sheet' => 0,

    'pekerja' => 0,

    'pekerja_baru' => 0,

    'skill' => 0,

    'skill_baru' => 0,

    'nilai' => 0,

    'update' => 0,

    'kosong' => 0

];


if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset(
        $_FILES['excel_file']
    )
) {

    $file =
        $_FILES['excel_file'];


    /* =====================================================
       VALIDASI UPLOAD
    ====================================================== */

    if (
        $file['error'] !==
        UPLOAD_ERR_OK
    ) {

        $errors[] =
            'File gagal diupload.';
    }


    /* =====================================================
       VALIDASI SIZE
    ====================================================== */

    if (
        $file['size'] >
        20 * 1024 * 1024
    ) {

        $errors[] =
            'Ukuran file maksimal 20 MB.';
    }


    /* =====================================================
       VALIDASI EXTENSION
    ====================================================== */

    $extension =
        strtolower(
            pathinfo(
                $file['name'],
                PATHINFO_EXTENSION
            )
        );


    $allowed = [

        'xls',

        'xlsx',

        'csv'

    ];


    if (
        !in_array(
            $extension,
            $allowed,
            true
        )
    ) {

        $errors[] =
            'Format file harus XLS, XLSX, atau CSV.';
    }


    /* =====================================================
       PROSES
    ====================================================== */

    if (
        empty($errors)
    ) {

        try {

            /*
             * Load Excel
             */

            $spreadsheet =
                IOFactory::load(
                    $file['tmp_name']
                );


            /*
             * Transaction
             */

            $conn->begin_transaction();


            /*
             * Loop sheet
             */

            foreach (
                $spreadsheet->getWorksheetIterator()
                as $sheet
            ) {

                $summary['sheet']++;


                $sheetName =
                    $sheet->getTitle();


                /*
                 * Tahun
                 */

                $tahun =
                    detectYear(
                        $sheetName,
                        $file['name']
                    );


                /*
                 * Header
                 */

                $headerRow =
                    findHeaderRow(
                        $sheet
                    );


                /*
                 * Kolom skill
                 */

                $skillColumn =
                    findSkillColumn(
                        $sheet,
                        $headerRow
                    );


                /*
                 * Worker header
                 */

                $workerHeaders =
                    getWorkerHeaders(
                        $sheet,
                        $headerRow,
                        $skillColumn
                    );


                /*
                 * Jika tidak ada pekerja
                 */

                if (
                    empty(
                        $workerHeaders
                    )
                ) {

                    continue;
                }


                /*
                 * Mapping kolom:
                 *
                 * Excel column
                 * =>
                 * pekerja ID
                 */

                $workerMap = [];


                foreach (
                    $workerHeaders
                    as $col => $workerName
                ) {

                    /*
                     * Cari pekerja
                     */

                    $pekerjaId =
                        getPekerjaId(
                            $conn,
                            $workerName
                        );


                    if (
                        $pekerjaId <= 0
                    ) {

                        continue;
                    }


                    /*
                     * Cek apakah pekerja baru
                     *
                     * Jika id baru:
                     * tidak mudah diketahui setelah
                     * getPekerjaId.
                     *
                     * Karena itu cukup dihitung
                     * sebagai pekerja yang berhasil
                     * ditemukan/dibuat.
                     */

                    $workerMap[$col] =
                        $pekerjaId;


                    $summary['pekerja']++;
                }


                /*
                 * Baris terakhir
                 */

                $highestRow =
                    $sheet->getHighestRow();


                /*
                 * Loop skill
                 */

                for (
                    $row =
                        $headerRow + 1;

                    $row <= $highestRow;

                    $row++
                ) {

                    /*
                     * Nama skill
                     */

                    $skillName =
                        clean_text(
                            $sheet
                                ->getCellByColumnAndRow(
                                    $skillColumn,
                                    $row
                                )
                                ->getValue()
                        );


                    /*
                     * Skill kosong
                     */

                    if (
                        $skillName === ''
                    ) {

                        continue;
                    }


                    /*
                     * Normalisasi skill
                     */

                    $skillLower =
                        strtolower(
                            $skillName
                        );


                    /*
                     * Abaikan baris total
                     */

                    if (
                        strpos(
                            $skillLower,
                            'total'
                        ) !== false
                    ) {

                        continue;
                    }


                    if (
                        strpos(
                            $skillLower,
                            'rata-rata'
                        ) !== false
                    ) {

                        continue;
                    }


                    if (
                        strpos(
                            $skillLower,
                            'rata rata'
                        ) !== false
                    ) {

                        continue;
                    }


                    if (
                        strpos(
                            $skillLower,
                            'average'
                        ) !== false
                    ) {

                        continue;
                    }


                    /*
                     * Cari / buat skill
                     */

                    $skillId =
                        getSkillId(
                            $conn,
                            $skillName
                        );


                    if (
                        $skillId <= 0
                    ) {

                        continue;
                    }


                    $summary['skill']++;


                    /*
                     * Loop pekerja
                     */

                    foreach (
                        $workerMap
                        as $col => $pekerjaId
                    ) {

                        /*
                         * Ambil nilai
                         */

                        $cell =
                            $sheet
                                ->getCellByColumnAndRow(
                                    $col,
                                    $row
                                );


                        /*
                         * Gunakan calculated value
                         */

                        $nilai =
                            $cell
                                ->getCalculatedValue();


                        /*
                         * Jika kosong
                         */

                        if (
                            $nilai === null ||
                            trim(
                                (string)$nilai
                            ) === ''
                        ) {

                            $summary['kosong']++;

                            continue;
                        }


                        /*
                         * Simpan
                         */

                        $result =
                            saveNilai(
                                $conn,
                                $pekerjaId,
                                $skillId,
                                $nilai,
                                $tahun
                            );


                        if (
                            $result['saved']
                        ) {

                            $summary['nilai']++;


                            if (
                                $result['updated']
                            ) {

                                $summary['update']++;
                            }
                        }
                    }
                }
            }


            /*
             * Commit
             */

            $conn->commit();


            $success =
                true;


            $message =
                'Import data Excel berhasil.';


        } catch (
            Throwable $e
        ) {

            /*
             * Rollback
             */

            try {

                $conn->rollback();

            } catch (
                Throwable $ignore
            ) {
            }


            $errors[] =
                'Import gagal: ' .
                $e->getMessage();
        }
    }
}


/* =========================================================
   14. LOAD HEADER
========================================================= */

$page_title =
    'Import Data Excel';


require __DIR__ .
    '/../partials/header.php';

?>


<style>

/* =========================================================
   IMPORT PAGE
========================================================= */

.import-page {

    max-width:
        1120px;

}


/* =========================================================
   HERO
========================================================= */

.import-hero {

    position:
        relative;

    overflow:
        hidden;

    background:
        linear-gradient(
            135deg,
            #092f63,
            #123f7a
        );

    border-radius:
        18px;

    padding:
        28px;

    color:
        #ffffff;

    margin-bottom:
        20px;

    box-shadow:
        0 10px 30px
        rgba(9,47,99,.14);

}


.import-hero::after {

    content:
        "";

    position:
        absolute;

    width:
        190px;

    height:
        190px;

    border-radius:
        50%;

    right:
        -70px;

    top:
        -100px;

    background:
        rgba(255,255,255,.05);

}


.import-hero h2 {

    position:
        relative;

    z-index:
        2;

    font-size:
        23px;

    font-weight:
        750;

    margin:
        0 0 7px;

}


.import-hero p {

    position:
        relative;

    z-index:
        2;

    margin:
        0;

    color:
        rgba(255,255,255,.78);

    font-size:
        13px;

}


/* =========================================================
   CARD
========================================================= */

.import-card {

    background:
        #ffffff;

    border:
        1px solid
        #e7ebf1;

    border-radius:
        17px;

    padding:
        25px;

    box-shadow:
        0 5px 20px
        rgba(20,43,76,.045);

}


/* =========================================================
   UPLOAD
========================================================= */

.upload-box {

    border:
        2px dashed
        #cbd7e8;

    border-radius:
        15px;

    padding:
        38px 25px;

    text-align:
        center;

    background:
        #f8fbff;

    transition:
        .2s ease;

}


.upload-box:hover {

    border-color:
        #123f7a;

    background:
        #f1f6ff;

}


.upload-icon {

    width:
        60px;

    height:
        60px;

    margin:
        0 auto 14px;

    border-radius:
        15px;

    display:
        flex;

    align-items:
        center;

    justify-content:
        center;

    background:
        #eaf2ff;

    color:
        #123f7a;

    font-size:
        27px;

}


.upload-title {

    font-weight:
        700;

    color:
        #172033;

    margin-bottom:
        5px;

}


.upload-note {

    color:
        #7d8796;

    font-size:
        12px;

    margin-bottom:
        18px;

}


/* =========================================================
   RESULT
========================================================= */

.import-result {

    border-radius:
        14px;

    padding:
        18px;

    margin-bottom:
        20px;

}


.import-result.success {

    background:
        #e9f8f0;

    border:
        1px solid
        #c9ecd9;

    color:
        #176c43;

}


.import-result.error {

    background:
        #ffecee;

    border:
        1px solid
        #f5c9cf;

    color:
        #9b1c2b;

}


/* =========================================================
   SUMMARY
========================================================= */

.summary-grid {

    display:
        grid;

    grid-template-columns:
        repeat(5, 1fr);

    gap:
        10px;

    margin-top:
        15px;

}


.summary-item {

    background:
        #f8fafc;

    border:
        1px solid
        #e7ebf1;

    border-radius:
        12px;

    padding:
        13px;

}


.summary-item small {

    display:
        block;

    color:
        #7d8796;

    font-size:
        9px;

    font-weight:
        700;

    text-transform:
        uppercase;

}


.summary-item strong {

    display:
        block;

    margin-top:
        4px;

    font-size:
        20px;

    color:
        #092f63;

}


/* =========================================================
   INFO
========================================================= */

.info-box {

    margin-top:
        20px;

    padding:
        17px;

    background:
        #f8fafc;

    border:
        1px solid
        #e7ebf1;

    border-radius:
        13px;

}


.info-box-title {

    font-size:
        13px;

    font-weight:
        700;

    color:
        #172033;

    margin-bottom:
        9px;

}


.info-box ul {

    margin:
        0;

    padding-left:
        18px;

    color:
        #687386;

    font-size:
        12px;

    line-height:
        1.9;

}


.info-highlight {

    margin-top:
        14px;

    padding:
        12px 14px;

    border-radius:
        10px;

    background:
        #eaf2ff;

    color:
        #123f7a;

    font-size:
        11px;

    line-height:
        1.7;

}


/* =========================================================
   RESPONSIVE
========================================================= */

@media (
    max-width: 900px
) {

    .summary-grid {

        grid-template-columns:
            repeat(3, 1fr);

    }

}


@media (
    max-width: 600px
) {

    .summary-grid {

        grid-template-columns:
            repeat(2, 1fr);

    }


    .import-hero {

        padding:
            22px;

    }


    .import-card {

        padding:
            17px;

    }

}

</style>


<!-- =======================================================
     PAGE
======================================================= -->

<div class="import-page">


    <!-- =====================================================
         HERO
    ====================================================== -->

    <div class="import-hero">

        <h2>

            <i
                class="
                    bi
                    bi-file-earmark-spreadsheet-fill
                    me-2
                "
            ></i>

            Import Data Skill

        </h2>


        <p>

            Import nilai skill dari file Excel
            tanpa mengubah struktur Data Pekerja.

        </p>

    </div>


    <!-- =====================================================
         ERROR
    ====================================================== -->

    <?php if (
        !empty($errors)
    ): ?>

        <div class="import-result error">

            <strong>

                <i
                    class="
                        bi
                        bi-exclamation-triangle-fill
                        me-1
                    "
                ></i>

                Import gagal

            </strong>


            <ul
                class="mb-0 mt-2"
            >

                <?php foreach (
                    $errors
                    as $error
                ): ?>

                    <li>

                        <?= e($error) ?>

                    </li>

                <?php endforeach; ?>

            </ul>

        </div>

    <?php endif; ?>


    <!-- =====================================================
         SUCCESS
    ====================================================== -->

    <?php if (
        $success
    ): ?>

        <div class="import-result success">

            <strong>

                <i
                    class="
                        bi
                        bi-check-circle-fill
                        me-1
                    "
                ></i>

                Import berhasil

            </strong>


            <div class="mt-1">

                <?= e($message) ?>

            </div>


            <!-- SUMMARY -->

            <div class="summary-grid">


                <div class="summary-item">

                    <small>
                        Sheet
                    </small>

                    <strong>
                        <?= number_format(
                            $summary['sheet']
                        ) ?>
                    </strong>

                </div>


                <div class="summary-item">

                    <small>
                        Pekerja
                    </small>

                    <strong>
                        <?= number_format(
                            $summary['pekerja']
                        ) ?>
                    </strong>

                </div>


                <div class="summary-item">

                    <small>
                        Skill
                    </small>

                    <strong>
                        <?= number_format(
                            $summary['skill']
                        ) ?>
                    </strong>

                </div>


                <div class="summary-item">

                    <small>
                        Nilai
                    </small>

                    <strong>
                        <?= number_format(
                            $summary['nilai']
                        ) ?>
                    </strong>

                </div>


                <div class="summary-item">

                    <small>
                        Update
                    </small>

                    <strong>
                        <?= number_format(
                            $summary['update']
                        ) ?>
                    </strong>

                </div>


            </div>

        </div>

    <?php endif; ?>


    <!-- =====================================================
         UPLOAD CARD
    ====================================================== -->

    <div class="import-card">


        <form
            method="POST"
            enctype="multipart/form-data"
        >


            <div class="upload-box">


                <div class="upload-icon">

                    <i
                        class="
                            bi
                            bi-cloud-arrow-up-fill
                        "
                    ></i>

                </div>


                <div class="upload-title">

                    Upload File Excel

                </div>


                <div class="upload-note">

                    Format:

                    <strong>
                        XLS, XLSX, CSV
                    </strong>

                    • Maksimal 20 MB

                </div>


                <input
                    type="file"
                    name="excel_file"
                    id="excel_file"
                    class="form-control"
                    accept=".xls,.xlsx,.csv"
                    required
                >


                <button
                    type="submit"
                    class="
                        btn
                        btn-primary
                        mt-3
                        px-4
                    "
                >

                    <i
                        class="
                            bi
                            bi-upload
                            me-1
                        "
                    ></i>

                    Import Data

                </button>


            </div>


        </form>


        <!-- =================================================
             INFORMASI
        ================================================== -->

        <div class="info-box">


            <div class="info-box-title">

                <i
                    class="
                        bi
                        bi-info-circle-fill
                        me-1
                    "
                ></i>

                Struktur Import

            </div>


            <ul>

                <li>

                    Nama pekerja pada header Excel
                    akan dicocokkan dengan kolom
                    <strong>Nama</strong>
                    pada menu
                    <strong>Data Pekerja</strong>.

                </li>


                <li>

                    Data pekerja menggunakan struktur:

                    <strong>
                        No. Reg / ID,
                        Nama,
                        Departemen,
                        Keterangan,
                        Status.
                    </strong>

                </li>


                <li>

                    Import nilai skill
                    <strong>
                        tidak akan mengubah
                        Departemen,
                        Keterangan,
                        maupun Status
                    </strong>
                    pekerja yang sudah ada.

                </li>


                <li>

                    Jika nama pekerja belum ada,
                    sistem akan membuat pekerja baru
                    dengan status
                    <strong>Aktif</strong>
                    dan departemen
                    <strong>Belum Diisi</strong>.

                </li>


                <li>

                    Skill yang belum ada di database
                    akan otomatis dibuat sebagai
                    <strong>Skill Aktif</strong>.

                </li>


                <li>

                    Nilai dengan tahun yang sama
                    akan diperbarui.

                </li>


                <li>

                    Nilai tahun sebelumnya
                    tetap tersimpan.

                </li>


                <li>

                    Nilai kompetensi menggunakan
                    skala
                    <strong>0 sampai 5</strong>.

                </li>

            </ul>


            <div class="info-highlight">

                <strong>

                    <i
                        class="
                            bi
                            bi-lightbulb-fill
                            me-1
                        "
                    ></i>

                    Penting:

                </strong>

                Untuk hasil paling aman,
                pastikan nama pekerja di Excel
                sama dengan nama pada menu
                <strong>Data Pekerja</strong>.
                Perbedaan huruf besar/kecil tidak masalah,
                tetapi nama orang yang berbeda tetap dianggap
                sebagai pekerja berbeda.

            </div>


        </div>


    </div>


</div>


<!-- =======================================================
     JAVASCRIPT
======================================================= -->

<script>

document.addEventListener(
    'DOMContentLoaded',
    function () {


        const input =
            document.getElementById(
                'excel_file'
            );


        if (!input) {

            return;
        }


        input.addEventListener(
            'change',
            function () {


                const file =
                    this.files[0];


                if (!file) {

                    return;
                }


                /*
                 * Extension
                 */

                const allowed = [

                    'xls',

                    'xlsx',

                    'csv'

                ];


                const ext =
                    file.name
                        .split('.')
                        .pop()
                        .toLowerCase();


                if (
                    !allowed.includes(
                        ext
                    )
                ) {

                    alert(
                        'Format file harus XLS, XLSX, atau CSV.'
                    );


                    this.value =
                        '';


                    return;
                }


                /*
                 * Size
                 */

                if (
                    file.size >
                    20 *
                    1024 *
                    1024
                ) {

                    alert(
                        'Ukuran file maksimal 20 MB.'
                    );


                    this.value =
                        '';

                    return;
                }


            }
        );


    }
);

</script>


<?php

require __DIR__ .
    '/../partials/footer.php';

?>