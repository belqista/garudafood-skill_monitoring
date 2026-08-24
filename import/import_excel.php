<?php

/* =========================================================
   IMPORT EXCEL - SKILL MONITORING
   GARUDAFOOD • TEKNIK
========================================================= */

$page_title = 'Import Data Excel';

require __DIR__ . '/../partials/header.php';

/* =========================================================
   PHPSPREADSHEET
========================================================= */

$autoload = __DIR__ . '/../vendor/autoload.php';

if (!file_exists($autoload)) {
    echo '
    <div class="alert alert-danger">
        <strong>PhpSpreadsheet belum terpasang.</strong><br>
        Jalankan:
        <code>composer require phpoffice/phpspreadsheet</code>
    </div>
    ';
    require __DIR__ . '/../partials/footer.php';
    exit;
}

require_once $autoload;

use PhpOffice\PhpSpreadsheet\IOFactory;


/* =========================================================
   HELPER
========================================================= */

function clean_text($value)
{
    $value = trim((string)$value);

    $value = preg_replace('/\s+/', ' ', $value);

    return $value;
}


function normalize_name($value)
{
    $value = clean_text($value);

    return strtolower($value);
}


function normalize_skill($value)
{
    $value = clean_text($value);

    return strtolower($value);
}


/* =========================================================
   CARI JABATAN
========================================================= */

function getJabatanId($conn, $nama)
{
    $nama = clean_text($nama);

    if ($nama === '') {
        return 0;
    }

    $stmt = $conn->prepare("
        SELECT id
        FROM jabatan
        WHERE LOWER(nama_jabatan) = LOWER(?)
        LIMIT 1
    ");

    $stmt->bind_param("s", $nama);

    $stmt->execute();

    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        return (int)$row['id'];
    }

    $stmt = $conn->prepare("
        INSERT INTO jabatan
        (nama_jabatan)
        VALUES (?)
    ");

    $stmt->bind_param("s", $nama);

    $stmt->execute();

    return (int)$conn->insert_id;
}


/* =========================================================
   CARI / BUAT PEKERJA
========================================================= */

function getPekerjaId($conn, $nama, $jabatanId)
{
    $nama = clean_text($nama);

    if ($nama === '') {
        return 0;
    }

    $stmt = $conn->prepare("
        SELECT id
        FROM pekerja
        WHERE LOWER(TRIM(nama)) = LOWER(TRIM(?))
        LIMIT 1
    ");

    $stmt->bind_param("s", $nama);

    $stmt->execute();

    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {

        $id = (int)$row['id'];

        /*
         * Update jabatan jika berbeda
         */
        if ($jabatanId > 0) {

            $update = $conn->prepare("
                UPDATE pekerja
                SET id_jabatan = ?
                WHERE id = ?
            ");

            $update->bind_param(
                "ii",
                $jabatanId,
                $id
            );

            $update->execute();
        }

        return $id;
    }


    /*
     * Buat pekerja baru
     */

    $status = 'Aktif';

    $stmt = $conn->prepare("
        INSERT INTO pekerja
        (
            nama,
            id_jabatan,
            status
        )
        VALUES
        (?, ?, ?)
    ");

    $stmt->bind_param(
        "sis",
        $nama,
        $jabatanId,
        $status
    );

    $stmt->execute();

    return (int)$conn->insert_id;
}


/* =========================================================
   CARI / BUAT SKILL
========================================================= */

function getSkillId($conn, $namaSkill)
{
    $namaSkill = clean_text($namaSkill);

    if ($namaSkill === '') {
        return 0;
    }

    $stmt = $conn->prepare("
        SELECT id
        FROM skill
        WHERE LOWER(TRIM(nama_skill)) = LOWER(TRIM(?))
        LIMIT 1
    ");

    $stmt->bind_param(
        "s",
        $namaSkill
    );

    $stmt->execute();

    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        return (int)$row['id'];
    }


    /*
     * Buat skill baru
     */

    $status = 'Aktif';

    $stmt = $conn->prepare("
        INSERT INTO skill
        (
            nama_skill,
            status
        )
        VALUES
        (?, ?)
    ");

    $stmt->bind_param(
        "ss",
        $namaSkill,
        $status
    );

    $stmt->execute();

    return (int)$conn->insert_id;
}


/* =========================================================
   SIMPAN NILAI SKILL
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
        return false;
    }

    if ($nilai === null || $nilai === '') {
        return false;
    }


    /*
     * Normalisasi nilai
     */

    $nilai = str_replace(
        ',',
        '.',
        trim((string)$nilai)
    );

    if (!is_numeric($nilai)) {
        return false;
    }

    $nilai = (float)$nilai;


    /*
     * Batasi 0 - 5
     */

    if ($nilai < 0) {
        $nilai = 0;
    }

    if ($nilai > 5) {
        $nilai = 5;
    }


    /*
     * Cek data tahun yang sama
     */

    $stmt = $conn->prepare("
        SELECT id
        FROM penilaian_skill
        WHERE id_pekerja = ?
        AND id_skill = ?
        AND tahun = ?
        LIMIT 1
    ");

    $stmt->bind_param(
        "iii",
        $pekerjaId,
        $skillId,
        $tahun
    );

    $stmt->execute();

    $result = $stmt->get_result();


    if ($row = $result->fetch_assoc()) {

        /*
         * UPDATE
         */

        $id = (int)$row['id'];

        $stmt = $conn->prepare("
            UPDATE penilaian_skill
            SET nilai = ?
            WHERE id = ?
        ");

        $stmt->bind_param(
            "di",
            $nilai,
            $id
        );

        return $stmt->execute();
    }


    /*
     * INSERT
     */

    $stmt = $conn->prepare("
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

    $stmt->bind_param(
        "iidi",
        $pekerjaId,
        $skillId,
        $nilai,
        $tahun
    );

    return $stmt->execute();
}


/* =========================================================
   DETEKSI TAHUN DARI NAMA SHEET
========================================================= */

function detectYear($sheetName, $fileName = '')
{
    /*
     * Contoh:
     * Skill Pelaksana 25
     * Skill Pelaksana 26
     * Leader 2025
     * Leader 2026
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
            '/\b(\d{2})\b/',
            $sheetName,
            $m
        )
    ) {

        $yy = (int)$m[1];

        if ($yy >= 0 && $yy <= 99) {
            return 2000 + $yy;
        }
    }


    /*
     * Default tahun sekarang
     */

    return (int)date('Y');
}


/* =========================================================
   DETEKSI JABATAN DARI SHEET
========================================================= */

function detectJabatan($sheetName)
{
    $name = strtolower(
        clean_text($sheetName)
    );


    /*
     * LEADER
     */

    if (
        strpos($name, 'leader') !== false ||
        strpos($name, 'lead') !== false ||
        strpos($name, 'supervisor') !== false
    ) {

        return 'Leader';
    }


    /*
     * PELAKSANA
     */

    if (
        strpos($name, 'pelaksana') !== false
    ) {

        return 'Pelaksana Teknik';
    }


    /*
     * Default
     */

    return 'Pelaksana Teknik';
}


/* =========================================================
   CARI BARIS HEADER
========================================================= */

function findHeaderRow($sheet)
{
    $highestRow = $sheet->getHighestRow();
    $highestCol = $sheet->getHighestColumn();

    for ($row = 1; $row <= min($highestRow, 15); $row++) {

        $values = [];

        for (
            $col = 1;
            $col <= \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestCol);
            $col++
        ) {

            $value = clean_text(
                $sheet->getCellByColumnAndRow(
                    $col,
                    $row
                )->getValue()
            );

            if ($value !== '') {
                $values[] = strtolower($value);
            }
        }


        /*
         * Cari kata skill / kompetensi
         */

        foreach ($values as $value) {

            if (
                strpos($value, 'skill') !== false ||
                strpos($value, 'kompetensi') !== false ||
                strpos($value, 'nama') !== false
            ) {

                return $row;
            }
        }
    }


    return 1;
}


/* =========================================================
   IMPORT FILE
========================================================= */

$success = false;

$message = '';

$errors = [];

$summary = [
    'sheet'   => 0,
    'pekerja' => 0,
    'skill'   => 0,
    'nilai'   => 0,
    'update'  => 0
];


if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_FILES['excel_file'])
) {

    $file = $_FILES['excel_file'];


    /*
     * Validasi upload
     */

    if ($file['error'] !== UPLOAD_ERR_OK) {

        $errors[] =
            'File gagal diupload.';
    }


    /*
     * Validasi ukuran
     */

    if (
        $file['size'] >
        20 * 1024 * 1024
    ) {

        $errors[] =
            'Ukuran file maksimal 20 MB.';
    }


    /*
     * Validasi extension
     */

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


    if (empty($errors)) {

        try {

            /*
             * Load Excel
             */

            $spreadsheet =
                IOFactory::load(
                    $file['tmp_name']
                );


            $conn->begin_transaction();


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
                 * Jabatan
                 */

                $namaJabatan =
                    detectJabatan(
                        $sheetName
                    );


                $jabatanId =
                    getJabatanId(
                        $conn,
                        $namaJabatan
                    );


                /*
                 * Header
                 */

                $headerRow =
                    findHeaderRow(
                        $sheet
                    );


                $highestRow =
                    $sheet->getHighestRow();

                $highestColumn =
                    $sheet->getHighestColumn();

                $highestColumnIndex =
                    \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString(
                        $highestColumn
                    );


                /*
                 * Ambil header
                 */

                $headers = [];


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


                    $headers[$col] =
                        $header;
                }


                /*
                 * Cari kolom skill
                 */

                $skillColumn = 1;


                for (
                    $col = 1;
                    $col <= $highestColumnIndex;
                    $col++
                ) {

                    $h =
                        strtolower(
                            $headers[$col] ?? ''
                        );


                    if (
                        strpos($h, 'skill') !== false ||
                        strpos($h, 'kompetensi') !== false
                    ) {

                        $skillColumn =
                            $col;

                        break;
                    }
                }


                /*
                 * Buat daftar pekerja
                 *
                 * Nama pekerja berada
                 * di header kolom.
                 */

                $workers = [];


                for (
                    $col = 1;
                    $col <= $highestColumnIndex;
                    $col++
                ) {

                    if (
                        $col === $skillColumn
                    ) {
                        continue;
                    }


                    $workerName =
                        clean_text(
                            $headers[$col] ?? ''
                        );


                    if (
                        $workerName === ''
                    ) {
                        continue;
                    }


                    /*
                     * Lewati header umum
                     */

                    $ignore = [
                        'nama',
                        'pekerja',
                        'karyawan',
                        'employee',
                        'nik',
                        'jabatan',
                        'total',
                        'rata-rata',
                        'average',
                        'avg'
                    ];


                    if (
                        in_array(
                            strtolower($workerName),
                            $ignore,
                            true
                        )
                    ) {
                        continue;
                    }


                    /*
                     * Cari / buat pekerja
                     */

                    $pekerjaId =
                        getPekerjaId(
                            $conn,
                            $workerName,
                            $jabatanId
                        );


                    if (
                        $pekerjaId > 0
                    ) {

                        $workers[$col] =
                            $pekerjaId;

                        $summary['pekerja']++;
                    }
                }


                /*
                 * Baca baris skill
                 */

                for (
                    $row = $headerRow + 1;
                    $row <= $highestRow;
                    $row++
                ) {

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
                     * Lewati baris kosong
                     */

                    if (
                        $skillName === ''
                    ) {
                        continue;
                    }


                    /*
                     * Lewati total / rata-rata
                     */

                    $lowerSkill =
                        strtolower(
                            $skillName
                        );


                    if (
                        strpos(
                            $lowerSkill,
                            'total'
                        ) !== false ||
                        strpos(
                            $lowerSkill,
                            'rata-rata'
                        ) !== false ||
                        strpos(
                            $lowerSkill,
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
                     * Baca nilai setiap pekerja
                     */

                    foreach (
                        $workers as $col => $pekerjaId
                    ) {

                        $nilai =
                            $sheet
                                ->getCellByColumnAndRow(
                                    $col,
                                    $row
                                )
                                ->getCalculatedValue();


                        if (
                            $nilai === null ||
                            $nilai === ''
                        ) {
                            continue;
                        }


                        if (
                            saveNilai(
                                $conn,
                                $pekerjaId,
                                $skillId,
                                $nilai,
                                $tahun
                            )
                        ) {

                            $summary['nilai']++;
                        }
                    }
                }
            }


            /*
             * Commit
             */

            $conn->commit();


            $success = true;


            $message =
                'Import berhasil. Data Excel sudah masuk ke database dan otomatis dapat dibaca oleh sistem.';


        } catch (
            Throwable $e
        ) {

            /*
             * Rollback jika error
             */

            try {
                $conn->rollback();
            } catch (Throwable $ignore) {
            }


            $errors[] =
                'Import gagal: ' .
                $e->getMessage();
        }
    }
}

?>


<style>

.import-page {
    max-width: 1100px;
}


.import-hero {
    background:
        linear-gradient(
            135deg,
            #092f63,
            #123f7a
        );

    border-radius: 18px;

    padding: 28px;

    color: #fff;

    margin-bottom: 20px;

    box-shadow:
        0 10px 30px rgba(9,47,99,.14);
}


.import-hero h2 {
    font-size: 23px;

    font-weight: 700;

    margin: 0 0 7px;
}


.import-hero p {
    margin: 0;

    color: rgba(255,255,255,.78);

    font-size: 13px;
}


.import-card {
    background: #fff;

    border: 1px solid #e7ebf1;

    border-radius: 17px;

    padding: 25px;

    box-shadow:
        0 5px 20px rgba(20,43,76,.045);
}


.upload-box {
    border: 2px dashed #cbd7e8;

    border-radius: 15px;

    padding: 38px 25px;

    text-align: center;

    background: #f8fbff;

    transition: .2s ease;
}


.upload-box:hover {
    border-color: #123f7a;

    background: #f1f6ff;
}


.upload-icon {
    width: 60px;
    height: 60px;

    margin: 0 auto 14px;

    border-radius: 15px;

    display: flex;
    align-items: center;
    justify-content: center;

    background: #eaf2ff;

    color: #123f7a;

    font-size: 27px;
}


.upload-title {
    font-weight: 700;

    color: #172033;

    margin-bottom: 5px;
}


.upload-note {
    color: #7d8796;

    font-size: 12px;

    margin-bottom: 18px;
}


.import-result {
    border-radius: 14px;

    padding: 18px;

    margin-bottom: 20px;
}


.import-result.success {
    background: #e9f8f0;

    border: 1px solid #c9ecd9;

    color: #176c43;
}


.import-result.error {
    background: #ffecee;

    border: 1px solid #f5c9cf;

    color: #9b1c2b;
}


.summary-grid {
    display: grid;

    grid-template-columns:
        repeat(4, 1fr);

    gap: 12px;

    margin-top: 15px;
}


.summary-item {
    background: #f8fafc;

    border: 1px solid #e7ebf1;

    border-radius: 12px;

    padding: 13px;
}


.summary-item small {
    display: block;

    color: #7d8796;

    font-size: 10px;

    font-weight: 700;

    text-transform: uppercase;
}


.summary-item strong {
    display: block;

    margin-top: 4px;

    font-size: 21px;

    color: #092f63;
}


.info-box {
    margin-top: 20px;

    padding: 17px;

    background: #f8fafc;

    border: 1px solid #e7ebf1;

    border-radius: 13px;
}


.info-box-title {
    font-size: 13px;

    font-weight: 700;

    color: #172033;

    margin-bottom: 9px;
}


.info-box ul {
    margin: 0;

    padding-left: 18px;

    color: #687386;

    font-size: 12px;

    line-height: 1.8;
}


@media(max-width:768px) {

    .summary-grid {
        grid-template-columns:
            repeat(2, 1fr);
    }

    .import-hero {
        padding: 22px;
    }

}

</style>


<div class="import-page">


    <!-- =====================================================
         HERO
    ====================================================== -->

    <div class="import-hero">

        <h2>

            <i class="bi bi-file-earmark-spreadsheet me-2"></i>

            Import Data Skill

        </h2>

        <p>

            Masukkan file Excel terbaru untuk memperbarui
            data pekerja dan nilai kompetensi secara otomatis.

        </p>

    </div>


    <!-- =====================================================
         RESULT
    ====================================================== -->

    <?php if ($success): ?>

        <div class="import-result success">

            <strong>
                <i class="bi bi-check-circle-fill me-1"></i>

                Import berhasil
            </strong>

            <div class="mt-1">
                <?= e($message) ?>
            </div>


            <div class="summary-grid">

                <div class="summary-item">

                    <small>Sheet Dibaca</small>

                    <strong>
                        <?= number_format($summary['sheet']) ?>
                    </strong>

                </div>


                <div class="summary-item">

                    <small>Pekerja</small>

                    <strong>
                        <?= number_format($summary['pekerja']) ?>
                    </strong>

                </div>


                <div class="summary-item">

                    <small>Skill</small>

                    <strong>
                        <?= number_format($summary['skill']) ?>
                    </strong>

                </div>


                <div class="summary-item">

                    <small>Nilai</small>

                    <strong>
                        <?= number_format($summary['nilai']) ?>
                    </strong>

                </div>

            </div>

        </div>

    <?php endif; ?>


    <?php if (!empty($errors)): ?>

        <div class="import-result error">

            <strong>
                <i class="bi bi-exclamation-triangle-fill me-1"></i>

                Import gagal
            </strong>

            <ul class="mb-0 mt-2">

                <?php foreach ($errors as $error): ?>

                    <li>
                        <?= e($error) ?>
                    </li>

                <?php endforeach; ?>

            </ul>

        </div>

    <?php endif; ?>


    <!-- =====================================================
         UPLOAD
    ====================================================== -->

    <div class="import-card">


        <form
            method="POST"
            enctype="multipart/form-data"
        >


            <div class="upload-box">


                <div class="upload-icon">

                    <i class="bi bi-cloud-arrow-up-fill"></i>

                </div>


                <div class="upload-title">

                    Upload Excel Terbaru

                </div>


                <div class="upload-note">

                    Format yang didukung:
                    <strong>XLS, XLSX, CSV</strong>
                    maksimal 20 MB.

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
                    class="btn btn-primary mt-3 px-4"
                >

                    <i class="bi bi-upload me-1"></i>

                    Import Data

                </button>

            </div>


        </form>


        <!-- =================================================
             INFO
        ================================================== -->

        <div class="info-box">

            <div class="info-box-title">

                <i class="bi bi-info-circle me-1"></i>

                Cara kerja Import

            </div>


            <ul>

                <li>
                    Sistem membaca semua sheet dalam file Excel.
                </li>

                <li>
                    Tahun akan dideteksi dari nama sheet,
                    misalnya <strong>Pelaksana Teknik 25</strong>
                    atau <strong>Leader 2026</strong>.
                </li>

                <li>
                    Nama pekerja yang berada pada header kolom
                    akan otomatis dicari di database.
                </li>

                <li>
                    Pekerja baru akan otomatis dibuat.
                </li>

                <li>
                    Skill baru akan otomatis dibuat.
                </li>

                <li>
                    Nilai untuk tahun yang sama akan diperbarui,
                    sedangkan tahun sebelumnya tetap tersimpan.
                </li>

                <li>
                    Dashboard dan Skill Matrix akan langsung membaca
                    nilai terbaru dari database.
                </li>

                <li>
                    Nilai 0–5 akan digunakan sebagai skala kompetensi.
                </li>

            </ul>

        </div>


    </div>

</div>


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
                    !allowed.includes(ext)
                ) {

                    alert(
                        'Format file harus XLS, XLSX, atau CSV.'
                    );

                    this.value = '';

                    return;
                }


                if (
                    file.size >
                    20 * 1024 * 1024
                ) {

                    alert(
                        'Ukuran file maksimal 20 MB.'
                    );

                    this.value = '';

                    return;
                }

            }
        );

    }
);

</script>


<?php

require __DIR__ . '/../partials/footer.php';

?>