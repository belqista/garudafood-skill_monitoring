<?php
/* =========================================================
   LAPORAN KEBUTUHAN TRAINING
   GARUDAFOOD SKILL MONITORING

   File:
   /skill_monitoring/training/laporan_training.php

   FORMAT:
   ?format=pdf  -> PDF
   ?format=xls  -> Excel
   tanpa format -> tampilan laporan
========================================================= */

require __DIR__ . '/../config/database.php';


/* =========================================================
   HELPER
========================================================= */

function h($value)
{
    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}


/* =========================================================
   FUNGSI KEBUTUHAN TRAINING
========================================================= */

function kebutuhanTraining($nilai)
{
    $nilai = (float)$nilai;

    if ($nilai <= 1.5) {
        return 'Sangat Membutuhkan Training';
    }

    if ($nilai <= 2.5) {
        return 'Membutuhkan Training';
    }

    return 'Kompeten';
}


/* =========================================================
   TAHUN ASSESSMENT TERBARU
========================================================= */

$qYear = $conn->query("
    SELECT MAX(tahun) AS tahun
    FROM penilaian_skill
");

$yearRow = $qYear
    ? $qYear->fetch_assoc()
    : [];

$latestYear = (int)($yearRow['tahun'] ?? 0);

if ($latestYear <= 0) {
    $latestYear = (int)date('Y');
}


/* =========================================================
   AMBIL DATA PEKERJA
   HANYA YANG RATA-RATA <= 2.5

   Keterangan:
   Diambil langsung dari tabel pekerja.keterangan

   Kebutuhan:
   Dihitung dari rata-rata nilai assessment
========================================================= */

$rows = [];


$stmt = $conn->prepare("

    SELECT

        p.id,
        p.no_reg,
        p.nama,
        p.departemen,
        p.keterangan,

        COUNT(ps.id) AS jumlah_skill,

        SUM(
            CASE
                WHEN ps.nilai <= 2.5
                THEN 1
                ELSE 0
            END
        ) AS skill_bawah_25,

        ROUND(
            AVG(ps.nilai),
            2
        ) AS rata_skill

    FROM pekerja p

    INNER JOIN penilaian_skill ps
        ON ps.id_pekerja = p.id
        AND ps.tahun = ?

    WHERE
        p.status = 'Aktif'

        AND ps.nilai IS NOT NULL

    GROUP BY
        p.id,
        p.no_reg,
        p.nama,
        p.departemen,
        p.keterangan

    HAVING
        AVG(ps.nilai) <= 2.5

    ORDER BY
        rata_skill ASC,
        skill_bawah_25 DESC,
        p.nama ASC

");


if (!$stmt) {

    die(
        'Gagal mengambil data laporan training: ' .
        h($conn->error)
    );
}


$stmt->bind_param(
    'i',
    $latestYear
);


$stmt->execute();


$result = $stmt->get_result();


while ($r = $result->fetch_assoc()) {

    $r['kebutuhan'] =
        kebutuhanTraining(
            $r['rata_skill']
        );

    $rows[] = $r;
}


$stmt->close();


/* =========================================================
   JUMLAH DATA
========================================================= */

$totalTraining = count($rows);


/* =========================================================
   FORMAT
========================================================= */

$format = strtolower(
    trim($_GET['format'] ?? '')
);


/* =========================================================
   LOGO
========================================================= */

$logoPath =
    __DIR__ .
    '/../assets/img/logo-garudafood.png';

$logoBase64 = '';


if (file_exists($logoPath)) {

    $imageInfo =
        @getimagesize($logoPath);

    if ($imageInfo !== false) {

        $mime =
            $imageInfo['mime'];

        $logoBase64 =
            'data:' .
            $mime .
            ';base64,' .
            base64_encode(
                file_get_contents(
                    $logoPath
                )
            );
    }
}


/* =========================================================
   DOWNLOAD EXCEL
========================================================= */

if ($format === 'xls') {

    $filename =
        'Laporan_Kebutuhan_Training_' .
        $latestYear .
        '.xls';


    /*
     * Bersihkan output buffer
     */

    while (ob_get_level() > 0) {
        ob_end_clean();
    }


    header(
        'Content-Type: application/vnd.ms-excel; charset=UTF-8'
    );

    header(
        'Content-Disposition: attachment; filename="' .
        $filename .
        '"'
    );

    header(
        'Cache-Control: max-age=0'
    );

    header(
        'Pragma: public'
    );

    ?>
<!DOCTYPE html>

<html lang="id">

<head>

<meta charset="UTF-8">

<style>

body {
    font-family: Arial, sans-serif;
    font-size: 11pt;
}

.title {
    font-size: 18pt;
    font-weight: bold;
}

.subtitle {
    font-size: 11pt;
}

table {
    border-collapse: collapse;
    width: 100%;
}

th {
    background: #123f7a;
    color: #ffffff;
    font-weight: bold;
    border: 1px solid #000000;
    padding: 8px;
}

td {
    border: 1px solid #000000;
    padding: 7px;
}

.center {
    text-align: center;
}

.red {
    color: #dc3545;
    font-weight: bold;
}

.need-critical {
    color: #dc3545;
    font-weight: bold;
}

.need-warning {
    color: #b36b00;
    font-weight: bold;
}

</style>

</head>

<body>

<table>

    <tr>

        <td
            colspan="9"
            class="title"
        >
            LAPORAN KEBUTUHAN TRAINING
        </td>

    </tr>


    <tr>

        <td
            colspan="9"
            class="subtitle"
        >
            GARUDAFOOD - TEKNIK
        </td>

    </tr>


    <tr>

        <td
            colspan="9"
            class="subtitle"
        >
            Berdasarkan Assessment Tahun
            <?= h($latestYear) ?>
        </td>

    </tr>


    <tr>

        <td colspan="9">
            &nbsp;
        </td>

    </tr>


    <tr>

        <th>
            No
        </th>

        <th>
            No. Reg / ID
        </th>

        <th>
            Nama
        </th>

        <th>
            Departemen
        </th>

        <th>
            Keterangan
        </th>

        <th>
            Rata-rata Skill
        </th>

        <th>
            Jumlah Skill
        </th>

        <th>
            Skill ≤ 2,5
        </th>

        <th>
            Kebutuhan
        </th>

    </tr>


    <?php if (!empty($rows)): ?>

        <?php $no = 1; ?>

        <?php foreach ($rows as $r): ?>

            <?php

            $nilai =
                (float)$r['rata_skill'];

            $kebutuhan =
                $r['kebutuhan'];

            $needClass =
                $nilai <= 1.5
                    ? 'need-critical'
                    : 'need-warning';

            ?>


            <tr>

                <td class="center">
                    <?= $no++ ?>
                </td>


                <td class="center">

                    <?= h(
                        $r['no_reg']
                        ?: $r['id']
                    ) ?>

                </td>


                <td>
                    <?= h($r['nama']) ?>
                </td>


                <td>

                    <?= h(
                        $r['departemen']
                        ?: '-'
                    ) ?>

                </td>


                <!-- KETERANGAN -->

                <td>

                    <?= h(
                        trim(
                            $r['keterangan'] ?? ''
                        ) !== ''
                            ? $r['keterangan']
                            : '-'
                    ) ?>

                </td>


                <!-- RATA-RATA -->

                <td
                    class="<?= $nilai <= 2.5
                        ? 'red'
                        : ''
                    ?>"
                >

                    <?= number_format(
                        $nilai,
                        2
                    ) ?>

                </td>


                <!-- JUMLAH SKILL -->

                <td class="center">

                    <?= (int)$r['jumlah_skill'] ?>

                </td>


                <!-- SKILL <= 2.5 -->

                <td class="center">

                    <?= (int)$r['skill_bawah_25'] ?>

                </td>


                <!-- KEBUTUHAN -->

                <td
                    class="<?= $needClass ?>"
                >

                    <?= h(
                        $kebutuhan
                    ) ?>

                </td>

            </tr>

        <?php endforeach; ?>

    <?php else: ?>

        <tr>

            <td
                colspan="9"
                class="center"
            >

                Tidak ada pekerja dengan
                rata-rata nilai ≤ 2,5.

            </td>

        </tr>

    <?php endif; ?>

</table>

</body>

</html>

<?php

    exit;
}


/* =========================================================
   DOWNLOAD PDF
========================================================= */

if ($format === 'pdf') {

    /*
     * DOMPDF VIA COMPOSER
     *
     * Struktur:
     * skill_monitoring/
     * ├── vendor/
     * │   └── autoload.php
     * └── training/
     *     └── laporan_training.php
     */

    $autoloadPath =
        __DIR__ .
        '/../vendor/autoload.php';


    if (!file_exists($autoloadPath)) {

        http_response_code(500);

        ?>

        <!DOCTYPE html>

        <html lang="id">

        <head>

            <meta charset="UTF-8">

            <title>Dompdf Tidak Ditemukan</title>

            <style>

                body {
                    font-family: Arial, sans-serif;
                    background: #f5f7fb;
                    padding: 40px;
                }

                .box {
                    max-width: 700px;
                    margin: auto;
                    background: white;
                    padding: 30px;
                    border-radius: 12px;
                    box-shadow:
                        0 5px 20px
                        rgba(0,0,0,.08);
                }

                h2 {
                    color: #123f7a;
                }

                code {
                    background: #f1f3f5;
                    padding: 3px 6px;
                    border-radius: 4px;
                }

            </style>

        </head>

        <body>

        <div class="box">

            <h2>
                Dompdf belum ditemukan
            </h2>

            <p>
                File Composer autoload belum ditemukan.
            </p>

            <p>
                Pastikan kamu sudah menjalankan:
            </p>

            <p>

                <code>
                    composer require dompdf/dompdf
                </code>

            </p>

            <p>
                dan folder berikut tersedia:
            </p>

            <p>

                <code>
                    C:\xampp\htdocs\skill_monitoring\vendor\
                </code>

            </p>

            <br>

            <a
                href="laporan_training.php"
                style="
                    display:inline-block;
                    padding:10px 16px;
                    background:#123f7a;
                    color:white;
                    text-decoration:none;
                    border-radius:7px;
                "
            >

                Kembali ke Laporan

            </a>

        </div>

        </body>

        </html>

        <?php

        exit;
    }


    /*
     * Load Composer
     */

    require_once $autoloadPath;


    /*
     * Pastikan class tersedia
     */

    if (!class_exists('\Dompdf\Dompdf')) {

        http_response_code(500);

        die(
            'Class Dompdf tidak ditemukan. ' .
            'Jalankan kembali composer require dompdf/dompdf.'
        );
    }


    /* =====================================================
       HTML PDF
    ===================================================== */

    ob_start();

    ?>

<!DOCTYPE html>

<html lang="id">

<head>

<meta charset="UTF-8">

<style>

@page {

    size: A4 landscape;

    margin:
        18mm
        15mm
        15mm
        15mm;

}


body {

    font-family:
        DejaVu Sans,
        Arial,
        sans-serif;

    font-size: 9px;

    color: #172033;

}


/* =========================================================
   KOP
========================================================= */

.header-table {

    width: 100%;

    border-collapse: collapse;

    margin-bottom: 8px;

}

.header-table td {

    border: none;

    vertical-align: middle;

}


.logo-cell {

    width: 110px;

    text-align: left;

}


.logo {

    max-width: 90px;

    max-height: 55px;

}


.company {

    font-size: 16px;

    font-weight: bold;

    color: #123f7a;

}


.company-sub {

    font-size: 9px;

    color: #555555;

}


.line {

    border-bottom:
        2px solid #123f7a;

    margin-bottom: 10px;

}


/* =========================================================
   JUDUL
========================================================= */

.report-title {

    text-align: center;

    font-size: 15px;

    font-weight: bold;

    margin-bottom: 4px;

}


.report-subtitle {

    text-align: center;

    font-size: 9px;

    color: #555555;

    margin-bottom: 12px;

}


/* =========================================================
   SUMMARY
========================================================= */

.summary {

    width: 100%;

    border-collapse: collapse;

    margin-bottom: 12px;

}


.summary td {

    border:
        1px solid #d7dce4;

    padding: 6px;

}


.summary-label {

    color: #687385;

    width: 150px;

}


.summary-value {

    font-weight: bold;

}


/* =========================================================
   DATA TABLE
========================================================= */

.data-table {

    width: 100%;

    border-collapse: collapse;

}


.data-table th {

    background: #123f7a;

    color: #ffffff;

    border:
        1px solid #0b2c57;

    padding:
        7px 5px;

    font-size: 8px;

    text-align: center;

}


.data-table td {

    border:
        1px solid #d5dae2;

    padding:
        6px 5px;

    font-size: 8px;

    vertical-align: middle;

}


.center {

    text-align: center;

}


.low {

    color: #dc3545;

    font-weight: bold;

}


.need {

    font-weight: bold;

}


.need-critical {

    color: #dc3545;

    font-weight: bold;

}


.need-warning {

    color: #b36b00;

    font-weight: bold;

}


.no-data {

    text-align: center;

    padding: 20px;

}


.footer {

    margin-top: 15px;

    font-size: 8px;

    color: #6f7886;

}

</style>

</head>

<body>


<!-- =====================================================
     KOP
====================================================== -->

<table class="header-table">

<tr>

    <td class="logo-cell">

        <?php if ($logoBase64 !== ''): ?>

            <img
                src="<?= $logoBase64 ?>"
                class="logo"
            >

        <?php endif; ?>

    </td>


    <td>

        <div class="company">

            PT GARUDAFOOD PUTRA PUTRI JAYA Tbk

        </div>


        <div class="company-sub">

            DEPARTEMEN TEKNIK

        </div>


        <div class="company-sub">

            Skill Monitoring &amp; Training

        </div>

    </td>

</tr>

</table>


<div class="line"></div>


<!-- =====================================================
     JUDUL
====================================================== -->

<div class="report-title">

    LAPORAN KEBUTUHAN TRAINING

</div>


<div class="report-subtitle">

    Berdasarkan hasil assessment kompetensi
    tahun <?= h($latestYear) ?>

</div>


<!-- =====================================================
     SUMMARY
====================================================== -->

<table class="summary">

<tr>

    <td class="summary-label">

        Tahun Assessment

    </td>

    <td class="summary-value">

        <?= h($latestYear) ?>

    </td>


    <td class="summary-label">

        Jumlah Pekerja

    </td>

    <td class="summary-value">

        <?= number_format(
            $totalTraining
        ) ?>

    </td>


    <td class="summary-label">

        Batas Training

    </td>

    <td class="summary-value">

        ≤ 2,5

    </td>

</tr>

</table>


<!-- =====================================================
     DATA TABLE
====================================================== -->

<table class="data-table">

<thead>

<tr>

    <th width="4%">
        No
    </th>

    <th width="9%">
        No. Reg / ID
    </th>

    <th width="17%">
        Nama
    </th>

    <th width="12%">
        Departemen
    </th>

    <th width="16%">
        Keterangan
    </th>

    <th width="10%">
        Rata-rata Skill
    </th>

    <th width="8%">
        Jumlah Skill
    </th>

    <th width="8%">
        Skill ≤ 2,5
    </th>

    <th width="16%">
        Kebutuhan
    </th>

</tr>

</thead>


<tbody>

<?php if (!empty($rows)): ?>

    <?php $no = 1; ?>


    <?php foreach ($rows as $r): ?>

        <?php

        $nilai =
            (float)$r['rata_skill'];

        $kebutuhan =
            $r['kebutuhan'];

        $needClass =
            $nilai <= 1.5
                ? 'need-critical'
                : 'need-warning';

        ?>


        <tr>

            <!-- NO -->

            <td class="center">

                <?= $no++ ?>

            </td>


            <!-- NO REG -->

            <td class="center">

                <?= h(
                    $r['no_reg']
                    ?: $r['id']
                ) ?>

            </td>


            <!-- NAMA -->

            <td>

                <?= h(
                    $r['nama']
                ) ?>

            </td>


            <!-- DEPARTEMEN -->

            <td>

                <?= h(
                    $r['departemen']
                    ?: '-'
                ) ?>

            </td>


            <!-- KETERANGAN -->

            <td>

                <?= h(
                    trim(
                        $r['keterangan'] ?? ''
                    ) !== ''
                        ? $r['keterangan']
                        : '-'
                ) ?>

            </td>


            <!-- RATA-RATA -->

            <td
                class="<?= $nilai <= 2.5
                    ? 'low'
                    : ''
                ?> center"
            >

                <?= number_format(
                    $nilai,
                    2
                ) ?>

            </td>


            <!-- JUMLAH SKILL -->

            <td class="center">

                <?= (int)$r['jumlah_skill'] ?>

            </td>


            <!-- SKILL <= 2.5 -->

            <td class="center">

                <?= (int)$r['skill_bawah_25'] ?>

            </td>


            <!-- KEBUTUHAN -->

            <td
                class="<?= $needClass ?>"
            >

                <?= h(
                    $kebutuhan
                ) ?>

            </td>

        </tr>

    <?php endforeach; ?>


<?php else: ?>

    <tr>

        <td
            colspan="9"
            class="no-data"
        >

            Tidak ada pekerja dengan
            rata-rata nilai ≤ 2,5.

        </td>

    </tr>

<?php endif; ?>

</tbody>

</table>


<div class="footer">

    Laporan ini dihasilkan otomatis oleh
    Garudafood Skill Monitoring System.
    Nilai kebutuhan training ditentukan berdasarkan
    rata-rata hasil assessment tahun
    <?= h($latestYear) ?>.

</div>


</body>

</html>

<?php

    $html =
        ob_get_clean();


    /* =====================================================
       RENDER PDF
    ===================================================== */

    $options =
        new \Dompdf\Options();


    $options->set(
        'isRemoteEnabled',
        true
    );


    $options->set(
        'isHtml5ParserEnabled',
        true
    );


    $dompdf =
        new \Dompdf\Dompdf(
            $options
        );


    $dompdf->loadHtml(
        $html,
        'UTF-8'
    );


    $dompdf->setPaper(
        'A4',
        'landscape'
    );


    $dompdf->render();


    $filename =
        'Laporan_Kebutuhan_Training_' .
        $latestYear .
        '.pdf';


    $dompdf->stream(
        $filename,
        [
            'Attachment' => true
        ]
    );


    exit;
}


/* =========================================================
   TAMPILAN WEB
========================================================= */

require __DIR__ . '/../partials/header.php';

?>


<style>

/* =========================================================
   LAPORAN TRAINING
========================================================= */

.report-page {

    background: #f5f7fb;

}


.report-paper {

    background: #ffffff;

    border:
        1px solid #e4e8ee;

    border-radius: 17px;

    box-shadow:
        0 6px 25px
        rgba(
            20,
            43,
            76,
            .05
        );

    overflow: hidden;

}


/* =========================================================
   KOP
========================================================= */

.report-header {

    padding:
        25px 28px 20px;

}


.report-logo {

    width: 105px;

    max-height: 70px;

    object-fit: contain;

}


.report-company {

    font-size: 18px;

    font-weight: 800;

    color: #123f7a;

}


.report-department {

    font-size: 12px;

    color: #6f7886;

    margin-top: 3px;

}


.report-line {

    height: 3px;

    background: #123f7a;

    margin:
        0 28px;

}


/* =========================================================
   TITLE
========================================================= */

.report-title-web {

    text-align: center;

    padding:
        22px 20px 5px;

    font-size: 20px;

    font-weight: 800;

    color: #172033;

}


.report-subtitle-web {

    text-align: center;

    color: #788396;

    font-size: 12px;

    padding-bottom: 20px;

}


/* =========================================================
   ACTION
========================================================= */

.report-actions {

    padding:
        0 28px 20px;

}


.report-actions .btn {

    border-radius: 9px;

    font-weight: 600;

}


/* =========================================================
   SUMMARY
========================================================= */

.report-summary {

    display: grid;

    grid-template-columns:
        repeat(3, 1fr);

    gap: 12px;

    padding:
        0 28px 22px;

}


.summary-box {

    border:
        1px solid #e5e9ef;

    border-radius: 11px;

    padding:
        13px 15px;

    background: #fafbfd;

}


.summary-label {

    color: #8a94a4;

    font-size: 10px;

    font-weight: 700;

    text-transform: uppercase;

    letter-spacing: .05em;

}


.summary-value {

    color: #172033;

    font-size: 18px;

    font-weight: 800;

    margin-top: 3px;

}


/* =========================================================
   TABLE
========================================================= */

.report-table-wrap {

    padding:
        0 28px 28px;

}


.report-table {

    margin: 0;

}


.report-table thead th {

    background: #123f7a;

    color: #ffffff;

    border: none;

    font-size: 10px;

    text-transform: uppercase;

    letter-spacing: .04em;

    padding:
        12px 10px;

    white-space: nowrap;

}


.report-table tbody td {

    border-bottom:
        1px solid #edf0f4;

    padding:
        12px 10px;

    font-size: 12px;

    vertical-align: middle;

}


.report-table tbody tr:hover {

    background: #f8fafc;

}


.value-low {

    color: #dc3545;

    font-weight: 800;

}


.need-text {

    color: #123f7a;

    font-weight: 650;

}


.need-critical {

    color: #dc3545;

    font-weight: 800;

}


.need-warning {

    color: #b36b00;

    font-weight: 800;

}


.empty-report {

    text-align: center;

    padding:
        45px 20px;

    color: #788396;

}


/* =========================================================
   RESPONSIVE
========================================================= */

@media(max-width: 768px) {

    .report-header {

        padding: 20px;

    }


    .report-line {

        margin:
            0 20px;

    }


    .report-title-web {

        font-size: 17px;

    }


    .report-summary {

        grid-template-columns: 1fr;

        padding:
            0 20px 20px;

    }


    .report-actions {

        padding:
            0 20px 20px;

    }


    .report-table-wrap {

        padding:
            0 10px 20px;

    }

}

</style>


<div class="report-page">


    <!-- =====================================================
         HEADER
    ====================================================== -->

    <div class="report-paper">


        <div class="report-header">

            <div
                class="
                    d-flex
                    align-items-center
                    gap-4
                "
            >

                <?php if ($logoBase64 !== ''): ?>

                    <img
                        src="<?= $logoBase64 ?>"
                        class="report-logo"
                        alt="Garudafood"
                    >

                <?php else: ?>

                    <div
                        style="
                            width:105px;
                            height:65px;
                            display:flex;
                            align-items:center;
                            justify-content:center;
                            background:#eaf2ff;
                            color:#123f7a;
                            border-radius:10px;
                            font-weight:800;
                        "
                    >

                        GARUDAFOOD

                    </div>

                <?php endif; ?>


                <div>

                    <div class="report-company">

                        PT GARUDAFOOD PUTRA PUTRI JAYA Tbk

                    </div>


                    <div class="report-department">

                        DEPARTEMEN TEKNIK

                    </div>


                    <div class="report-department">

                        Skill Monitoring &amp; Training

                    </div>

                </div>

            </div>

        </div>


        <div class="report-line"></div>


        <!-- =================================================
             TITLE
        ================================================== -->

        <div class="report-title-web">

            LAPORAN KEBUTUHAN TRAINING

        </div>


        <div class="report-subtitle-web">

            Berdasarkan hasil assessment kompetensi
            tahun <?= h($latestYear) ?>

        </div>


        <!-- =================================================
             ACTION BUTTON
        ================================================== -->

        <div class="report-actions">

            <div class="d-flex gap-2 flex-wrap">

                <a
                    href="laporan_training.php"
                    class="btn btn-light border"
                >

                    <i class="bi bi-arrow-left me-1"></i>

                    Kembali

                </a>


                <a
                    href="laporan_training.php?format=xls"
                    class="btn btn-success"
                >

                    <i
                        class="
                            bi
                            bi-file-earmark-excel
                            me-1
                        "
                    ></i>

                    Download Excel

                </a>


                <a
                    href="laporan_training.php?format=pdf"
                    class="btn btn-danger"
                >

                    <i
                        class="
                            bi
                            bi-file-earmark-pdf
                            me-1
                        "
                    ></i>

                    Download PDF

                </a>

            </div>

        </div>


        <!-- =================================================
             SUMMARY
        ================================================== -->

        <div class="report-summary">


            <div class="summary-box">

                <div class="summary-label">

                    Tahun Assessment

                </div>


                <div class="summary-value">

                    <?= h($latestYear) ?>

                </div>

            </div>


            <div class="summary-box">

                <div class="summary-label">

                    Pekerja Membutuhkan Training

                </div>


                <div class="summary-value">

                    <?= number_format(
                        $totalTraining
                    ) ?>

                </div>

            </div>


            <div class="summary-box">

                <div class="summary-label">

                    Batas Nilai

                </div>


                <div class="summary-value">

                    ≤ 2,5

                </div>

            </div>


        </div>


        <!-- =================================================
             TABLE
        ================================================== -->

        <div class="report-table-wrap">

            <div class="table-responsive">

                <table
                    class="
                        table
                        report-table
                        align-middle
                    "
                >

                    <thead>

                    <tr>

                        <th width="45">
                            No
                        </th>

                        <th>
                            No. Reg / ID
                        </th>

                        <th>
                            Nama
                        </th>

                        <th>
                            Departemen
                        </th>

                        <th>
                            Keterangan
                        </th>

                        <th>
                            Rata-rata Skill
                        </th>

                        <th>
                            Jumlah Skill
                        </th>

                        <th>
                            Skill ≤ 2,5
                        </th>

                        <th>
                            Kebutuhan
                        </th>

                    </tr>

                    </thead>


                    <tbody>

                    <?php if (!empty($rows)): ?>

                        <?php $no = 1; ?>


                        <?php foreach ($rows as $r): ?>

                            <?php

                            $nilai =
                                (float)$r['rata_skill'];

                            $needClass =
                                $nilai <= 1.5
                                    ? 'need-critical'
                                    : 'need-warning';

                            ?>


                            <tr>

                                <!-- NO -->

                                <td>

                                    <?= $no++ ?>

                                </td>


                                <!-- NO REG -->

                                <td>

                                    <span
                                        class="
                                            badge
                                            bg-light
                                            text-dark
                                            border
                                        "
                                    >

                                        <?= h(
                                            $r['no_reg']
                                            ?: $r['id']
                                        ) ?>

                                    </span>

                                </td>


                                <!-- NAMA -->

                                <td>

                                    <a
                                        href="../pekerja/detail.php?id=<?= (int)$r['id'] ?>"
                                        class="
                                            text-decoration-none
                                            fw-semibold
                                        "
                                        style="
                                            color:#123f7a;
                                        "
                                    >

                                        <?= h(
                                            $r['nama']
                                        ) ?>

                                    </a>

                                </td>


                                <!-- DEPARTEMEN -->

                                <td>

                                    <?= h(
                                        $r['departemen']
                                        ?: '-'
                                    ) ?>

                                </td>


                                <!-- KETERANGAN -->

                                <td>

                                    <span
                                        class="text-muted"
                                    >

                                        <?= h(
                                            trim(
                                                $r['keterangan']
                                                ?? ''
                                            ) !== ''
                                                ? $r['keterangan']
                                                : '-'
                                        ) ?>

                                    </span>

                                </td>


                                <!-- RATA-RATA -->

                                <td>

                                    <span
                                        class="<?= $nilai <= 2.5
                                            ? 'value-low'
                                            : ''
                                        ?>"
                                    >

                                        <?= number_format(
                                            $nilai,
                                            2
                                        ) ?>

                                    </span>

                                </td>


                                <!-- JUMLAH SKILL -->

                                <td>

                                    <?= (int)
                                        $r['jumlah_skill']
                                    ?>

                                </td>


                                <!-- SKILL <= 2.5 -->

                                <td>

                                    <span
                                        class="
                                            badge-status
                                            status-bad
                                        "
                                    >

                                        <?= (int)
                                            $r['skill_bawah_25']
                                        ?>

                                    </span>

                                </td>


                                <!-- KEBUTUHAN -->

                                <td>

                                    <span
                                        class="
                                            need-text
                                            <?= $needClass ?>
                                        "
                                    >

                                        <?= h(
                                            $r['kebutuhan']
                                        ) ?>

                                    </span>

                                </td>

                            </tr>

                        <?php endforeach; ?>


                    <?php else: ?>

                        <tr>

                            <td
                                colspan="9"
                                class="empty-report"
                            >

                                <i
                                    class="
                                        bi
                                        bi-check-circle-fill
                                        fs-2
                                        d-block
                                        mb-2
                                    "
                                    style="
                                        color:#198754;
                                    "
                                ></i>


                                <strong>

                                    Tidak ada pekerja yang
                                    membutuhkan training.

                                </strong>


                                <div class="small mt-1">

                                    Tidak ditemukan pekerja
                                    dengan rata-rata nilai
                                    ≤ 2,5.

                                </div>

                            </td>

                        </tr>

                    <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </div>


    </div>

</div>


<?php

require __DIR__ .
    '/../partials/footer.php';

?>