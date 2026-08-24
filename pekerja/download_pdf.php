<?php

/* =========================================================
   DOWNLOAD PDF DETAIL PEKERJA
   GARUDAFOOD SKILL MONITORING
   ========================================================= */

error_reporting(E_ALL);
ini_set('display_errors', 1);


/* =========================================================
   PATH PROJECT
========================================================= */

$project_root = dirname(__DIR__);


/* =========================================================
   LOAD DATABASE CONNECTION
========================================================= */

$possible_connections = [

    $project_root . '/koneksi.php',

    $project_root . '/config/koneksi.php',

    $project_root . '/config/database.php',

    $project_root . '/database/koneksi.php',

    $project_root . '/includes/koneksi.php',

    $project_root . '/partials/koneksi.php',

];


$connection_loaded = false;

foreach ($possible_connections as $connection_file) {

    if (file_exists($connection_file)) {

        require_once $connection_file;

        $connection_loaded = true;

        break;
    }
}


if (!$connection_loaded) {

    die('
        <div style="
            font-family:Arial,sans-serif;
            padding:30px;
            max-width:800px;
            margin:30px auto;
            border:1px solid #ddd;
            border-radius:10px;
            background:#fff;
        ">

            <h2 style="color:#b42318;">
                Koneksi database tidak ditemukan
            </h2>

            <p>
                File <strong>koneksi.php</strong> tidak ditemukan.
            </p>

            <p>
                Pastikan file koneksi database berada di:
            </p>

            <code>
                ' . htmlspecialchars(
                    $project_root . '/koneksi.php',
                    ENT_QUOTES,
                    'UTF-8'
                ) . '
            </code>

        </div>
    ');

}


/* =========================================================
   CEK CONNECTION
========================================================= */

if (!isset($conn) || !($conn instanceof mysqli)) {

    die('
        <div style="
            font-family:Arial,sans-serif;
            padding:30px;
            max-width:800px;
            margin:30px auto;
            border:1px solid #ddd;
            border-radius:10px;
            background:#fff;
        ">

            <h2 style="color:#b42318;">
                Koneksi database tidak valid
            </h2>

            <p>
                File koneksi ditemukan, tetapi variable
                <strong>$conn</strong> bukan koneksi MySQLi.
            </p>

        </div>
    ');

}


/* =========================================================
   LOAD DOMPDF
========================================================= */

$autoload_file = $project_root . '/vendor/autoload.php';


if (!file_exists($autoload_file)) {

    die('
        <div style="
            font-family:Arial,sans-serif;
            padding:30px;
            max-width:800px;
            margin:30px auto;
            border:1px solid #ddd;
            border-radius:10px;
            background:#fff;
        ">

            <h2 style="color:#b42318;">
                Dompdf tidak ditemukan
            </h2>

            <p>
                File Composer berikut tidak ditemukan:
            </p>

            <code>
                ' . htmlspecialchars(
                    $autoload_file,
                    ENT_QUOTES,
                    'UTF-8'
                ) . '
            </code>

        </div>
    ');

}


require_once $autoload_file;


use Dompdf\Dompdf;
use Dompdf\Options;


/* =========================================================
   HELPER
========================================================= */

function e_pdf($value)
{
    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}


function tanggal_pdf($tanggal)
{
    if (empty($tanggal)) {
        return '-';
    }

    $time = strtotime($tanggal);

    if (!$time) {
        return '-';
    }

    return date('d-m-Y', $time);
}


/* =========================================================
   PARAMETER
========================================================= */

$id = (int)($_GET['id'] ?? 0);


if ($id <= 0) {

    die('ID pekerja tidak valid.');

}


/* =========================================================
   DATA PEKERJA
========================================================= */

$stmt = $conn->prepare("
    SELECT
        p.*
    FROM pekerja p
    WHERE p.id = ?
    LIMIT 1
");


if (!$stmt) {

    die(
        'Query pekerja gagal: ' .
        e_pdf($conn->error)
    );

}


$stmt->bind_param(
    'i',
    $id
);


$stmt->execute();


$pekerja = $stmt
    ->get_result()
    ->fetch_assoc();


$stmt->close();


if (!$pekerja) {

    die('Data pekerja tidak ditemukan.');

}


/* =========================================================
   TAHUN ASSESSMENT TERBARU
========================================================= */

$stmt = $conn->prepare("
    SELECT
        MAX(tahun) AS tahun_terbaru
    FROM penilaian_skill
    WHERE id_pekerja = ?
");


if (!$stmt) {

    die(
        'Query tahun assessment gagal: ' .
        e_pdf($conn->error)
    );

}


$stmt->bind_param(
    'i',
    $id
);


$stmt->execute();


$tmp = $stmt
    ->get_result()
    ->fetch_assoc();


$stmt->close();


$tahun_terbaru = (int)(
    $tmp['tahun_terbaru']
    ?? date('Y')
);


if ($tahun_terbaru <= 0) {

    $tahun_terbaru = (int)date('Y');

}


/* =========================================================
   SKILL TERBARU
========================================================= */

$stmt = $conn->prepare("
    SELECT

        s.id AS id_skill,

        s.nama_skill,

        ps.id AS id_penilaian,

        ps.tahun,

        ps.nilai,

        ps.tanggal_penilaian,

        ps.assessor,

        ps.catatan

    FROM skill s

    LEFT JOIN (

        SELECT
            ps1.*

        FROM penilaian_skill ps1

        INNER JOIN (

            SELECT

                id_skill,

                MAX(
                    CONCAT(
                        LPAD(
                            COALESCE(tahun, 0),
                            4,
                            '0'
                        ),
                        LPAD(
                            id,
                            10,
                            '0'
                        )
                    )
                ) AS latest_key

            FROM penilaian_skill

            WHERE id_pekerja = ?

            GROUP BY id_skill

        ) latest

            ON latest.id_skill = ps1.id_skill

            AND latest.latest_key =
                CONCAT(
                    LPAD(
                        COALESCE(ps1.tahun, 0),
                        4,
                        '0'
                    ),
                    LPAD(
                        ps1.id,
                        10,
                        '0'
                    )
                )

        WHERE ps1.id_pekerja = ?

    ) ps

        ON ps.id_skill = s.id

    WHERE s.status = 'Aktif'

    ORDER BY
        s.nama_skill ASC
");


if (!$stmt) {

    die(
        'Query skill gagal: ' .
        e_pdf($conn->error)
    );

}


$stmt->bind_param(
    'ii',
    $id,
    $id
);


$stmt->execute();


$skill_result = $stmt->get_result();


$skill_data = [];


/* =========================================================
   STATISTIK
========================================================= */

$total_skill = 0;

$total_kompeten = 0;

$total_peningkatan = 0;

$total_training = 0;

$total_actual = 0;

$total_target = 0;

$total_gap = 0;


/* =========================================================
   PROSES SKILL
========================================================= */

while ($row = $skill_result->fetch_assoc()) {

    $actual =
        $row['nilai'] !== null
            ? (float)$row['nilai']
            : 0;


    $target = 3;


    if ($row['nilai'] !== null) {

        $gap = max(
            0,
            $target - $actual
        );

    } else {

        $gap = 0;

    }


    /* STATUS */

    if ($row['nilai'] === null) {

        $status = 'Belum Dinilai';

        $status_class = 'neutral';

    } elseif ($gap >= 2) {

        $status = 'Perlu Training';

        $status_class = 'bad';

        $total_training++;

    } elseif ($gap > 0) {

        $status = 'Perlu Peningkatan';

        $status_class = 'warning';

        $total_peningkatan++;

    } else {

        $status = 'Kompeten';

        $status_class = 'good';

        $total_kompeten++;

    }


    /* STATISTIK */

    if ($row['nilai'] !== null) {

        $total_skill++;

        $total_actual += $actual;

        $total_target += $target;

        $total_gap += $gap;

    }


    $row['actual'] = $actual;

    $row['target'] = $target;

    $row['gap'] = $gap;

    $row['status_label'] = $status;

    $row['status_class'] = $status_class;


    $skill_data[] = $row;

}


$stmt->close();


/* =========================================================
   RATA-RATA
========================================================= */

$rata_rata =
    $total_skill > 0
        ? $total_actual / $total_skill
        : 0;


$rata_target =
    $total_skill > 0
        ? $total_target / $total_skill
        : 0;


/* =========================================================
   STATUS KESELURUHAN
========================================================= */

if ($total_training > 0) {

    $overall_status = 'Perlu Training';

    $overall_class = 'bad';

} elseif ($total_peningkatan > 0) {

    $overall_status = 'Perlu Peningkatan';

    $overall_class = 'warning';

} elseif ($total_skill > 0) {

    $overall_status = 'Kompeten';

    $overall_class = 'good';

} else {

    $overall_status = 'Belum Dinilai';

    $overall_class = 'neutral';

}


/* =========================================================
   RIWAYAT PENILAIAN
========================================================= */

$stmt = $conn->prepare("
    SELECT

        ps.*,

        s.nama_skill

    FROM penilaian_skill ps

    INNER JOIN skill s
        ON s.id = ps.id_skill

    WHERE ps.id_pekerja = ?

    ORDER BY

        ps.tahun DESC,

        ps.tanggal_penilaian DESC,

        ps.id DESC

    LIMIT 100
");


if (!$stmt) {

    die(
        'Query riwayat penilaian gagal: ' .
        e_pdf($conn->error)
    );

}


$stmt->bind_param(
    'i',
    $id
);


$stmt->execute();


$history_result = $stmt->get_result();


$stmt->close();


/* =========================================================
   RIWAYAT TRAINING
========================================================= */

$training_data = [];


$stmt_training = $conn->prepare("
    SELECT

        tp.*,

        t.nama_training,

        t.tanggal_mulai,

        t.tanggal_selesai,

        t.trainer,

        t.lokasi,

        t.status AS status_training

    FROM training_peserta tp

    INNER JOIN training t
        ON t.id = tp.id_training

    WHERE tp.id_pekerja = ?

    ORDER BY

        t.tanggal_mulai DESC,

        t.id DESC
");


if ($stmt_training) {

    $stmt_training->bind_param(
        'i',
        $id
    );


    $stmt_training->execute();


    $training_result =
        $stmt_training->get_result();


    while ($t = $training_result->fetch_assoc()) {

        $training_data[] = $t;

    }


    $stmt_training->close();

}


/* =========================================================
   PRIORITAS TRAINING
========================================================= */

$training_priority = array_filter(
    $skill_data,
    function ($item) {

        return
            $item['nilai'] !== null
            &&
            $item['gap'] > 0;

    }
);


usort(
    $training_priority,
    function ($a, $b) {

        return $b['gap'] <=> $a['gap'];

    }
);


$training_priority = array_slice(
    $training_priority,
    0,
    5
);


/* =========================================================
   LOGO GARUDAFOOD
=========================================================

   Tidak menampilkan teks GARUDAFOOD sebagai fallback.

   Sistem mencoba beberapa lokasi logo yang umum.
========================================================= */

$logo_data = '';

$possible_logos = [

    $project_root . '/assets/logo-garudafood.png',

    $project_root . '/assets/logo-garudafood.jpg',

    $project_root . '/assets/logo-garudafood.jpeg',

    $project_root . '/assets/logo.png',

    $project_root . '/assets/img/logo-garudafood.png',

    $project_root . '/assets/images/logo-garudafood.png',

    $project_root . '/img/logo-garudafood.png',

    $project_root . '/images/logo-garudafood.png',

    $project_root . '/uploads/logo-garudafood.png',

];


foreach ($possible_logos as $logo_path) {

    if (!file_exists($logo_path)) {
        continue;
    }


    $logo_binary = @file_get_contents(
        $logo_path
    );


    if ($logo_binary === false) {
        continue;
    }


    $logo_extension = strtolower(
        pathinfo(
            $logo_path,
            PATHINFO_EXTENSION
        )
    );


    switch ($logo_extension) {

        case 'jpg':
        case 'jpeg':

            $mime = 'image/jpeg';

            break;

        case 'webp':

            $mime = 'image/webp';

            break;

        case 'gif':

            $mime = 'image/gif';

            break;

        case 'svg':

            $mime = 'image/svg+xml';

            break;

        default:

            $mime = 'image/png';

            break;
    }


    $logo_data =
        'data:' .
        $mime .
        ';base64,' .
        base64_encode(
            $logo_binary
        );


    break;

}


/* =========================================================
   HTML PDF
========================================================= */

ob_start();

?>

<!DOCTYPE html>

<html lang="id">

<head>

<meta charset="UTF-8">

<style>

/* =========================================================
   PAGE
========================================================= */

@page {

    margin:
        28px
        35px
        35px
        35px;

}


* {

    box-sizing:
        border-box;

}


body {

    font-family:
        DejaVu Sans,
        sans-serif;

    color:
        #172033;

    font-size:
        8.5px;

    line-height:
        1.45;

    margin:
        0;

}


/* =========================================================
   KOP SURAT
========================================================= */

.letterhead {

    width:
        100%;

    border-collapse:
        collapse;

    margin-bottom:
        5px;

}


.letterhead td {

    vertical-align:
        middle;

}


.logo-cell {

    width:
        105px;

    text-align:
        left;

}


.logo {

    display:
        block;

    max-width:
        90px;

    max-height:
        48px;

}


.company-cell {

    text-align:
        center;

}


.company-name {

    font-size:
        15px;

    font-weight:
        bold;

    color:
        #092f63;

    letter-spacing:
        .2px;

}


.company-sub {

    font-size:
        7.5px;

    color:
        #657184;

    margin-top:
        2px;

}


.document-cell {

    width:
        105px;

    text-align:
        right;

    vertical-align:
        top !important;

}


.document-label {

    font-size:
        6.5px;

    color:
        #7b8491;

    text-transform:
        uppercase;

}


.document-year {

    font-size:
        11px;

    font-weight:
        bold;

    color:
        #123b72;

    margin-top:
        2px;

}


.header-line {

    height:
        3px;

    background:
        #123b72;

    margin-top:
        8px;

    margin-bottom:
        2px;

}


.header-line-thin {

    height:
        1px;

    background:
        #c8d0dc;

    margin-bottom:
        18px;

}


/* =========================================================
   TITLE
========================================================= */

.report-title {

    text-align:
        center;

    margin-bottom:
        17px;

}


.report-title-main {

    font-size:
        15px;

    font-weight:
        bold;

    color:
        #172033;

    text-transform:
        uppercase;

}


.report-title-sub {

    color:
        #7a8492;

    font-size:
        8px;

    margin-top:
        3px;

}


/* =========================================================
   SECTION
========================================================= */

.section {

    margin-top:
        16px;

    margin-bottom:
        8px;

}


.section-title {

    font-size:
        10px;

    font-weight:
        bold;

    color:
        #092f63;

    text-transform:
        uppercase;

    border-left:
        4px solid #123b72;

    padding-left:
        7px;

    margin-bottom:
        8px;

}


/* =========================================================
   PROFILE
========================================================= */

.profile {

    width:
        100%;

    border-collapse:
        collapse;

}


.profile td {

    padding:
        7px 9px;

    border:
        1px solid #dfe4eb;

    background:
        #fbfcfe;

}


.profile-label {

    font-size:
        6.5px;

    color:
        #788396;

    text-transform:
        uppercase;

    letter-spacing:
        .4px;

    margin-bottom:
        2px;

}


.profile-value {

    font-size:
        9px;

    font-weight:
        bold;

    color:
        #172033;

}


/* =========================================================
   STATUS
========================================================= */

.status {

    display:
        inline-block;

    padding:
        3px 7px;

    border-radius:
        4px;

    font-weight:
        bold;

    font-size:
        7px;

}


.status-good {

    color:
        #176b3a;

    background:
        #e8f6ee;

}


.status-warning {

    color:
        #8a6500;

    background:
        #fff5d6;

}


.status-bad {

    color:
        #a52834;

    background:
        #ffe9ec;

}


.status-neutral {

    color:
        #5f6875;

    background:
        #edf0f3;

}


/* =========================================================
   STATISTICS
========================================================= */

.stats {

    width:
        100%;

    border-collapse:
        separate;

    border-spacing:
        5px;

    margin-left:
        -5px;

}


.stat {

    width:
        25%;

    border:
        1px solid #dfe4eb;

    padding:
        9px;

    background:
        #fff;

}


.stat-label {

    font-size:
        6.5px;

    color:
        #788396;

    text-transform:
        uppercase;

}


.stat-value {

    font-size:
        17px;

    font-weight:
        bold;

    color:
        #123b72;

    margin-top:
        2px;

}


.stat-note {

    font-size:
        6.5px;

    color:
        #8a94a4;

}


/* =========================================================
   TABLE
========================================================= */

.data-table {

    width:
        100%;

    border-collapse:
        collapse;

    margin-top:
        3px;

}


.data-table th {

    background:
        #123b72;

    color:
        #fff;

    font-size:
        6.8px;

    font-weight:
        bold;

    text-transform:
        uppercase;

    padding:
        6px;

    border:
        1px solid #123b72;

    text-align:
        left;

}


.data-table td {

    padding:
        6px;

    border:
        1px solid #dfe4eb;

    vertical-align:
        middle;

    font-size:
        7.8px;

}


.data-table tr:nth-child(even) td {

    background:
        #f8fafc;

}


.center {

    text-align:
        center !important;

}


.right {

    text-align:
        right !important;

}


.small {

    font-size:
        6.5px;

    color:
        #788396;

}


/* =========================================================
   EMPTY
========================================================= */

.no-data {

    text-align:
        center;

    color:
        #8a94a4;

    padding:
        14px;

    border:
        1px solid #dfe4eb;

}


/* =========================================================
   SIGNATURE
========================================================= */

.signature {

    width:
        100%;

    margin-top:
        25px;

}


.signature td {

    width:
        50%;

    text-align:
        center;

    vertical-align:
        top;

}


.signature-title {

    font-size:
        7px;

    color:
        #788396;

}


.signature-space {

    height:
        42px;

}


.signature-line {

    border-top:
        1px solid #7b8491;

    width:
        150px;

    margin:
        0 auto;

}


/* =========================================================
   FOOTER
========================================================= */

.footer {

    margin-top:
        22px;

    border-top:
        1px solid #dfe4eb;

    padding-top:
        7px;

    color:
        #8a94a4;

    font-size:
        6.5px;

}


.footer-left {

    text-align:
        left;

}


.footer-right {

    text-align:
        right;

}

</style>

</head>


<body>


<!-- =====================================================
     KOP SURAT
===================================================== -->

<table class="letterhead">

<tr>

<td class="logo-cell">

<?php if (!empty($logo_data)): ?>

<img
    src="<?= $logo_data ?>"
    class="logo"
    alt="Logo"
>

<?php endif; ?>

</td>


<td class="company-cell">

<div class="company-name">
PT GARUDAFOOD PUTRA PUTRI JAYA Tbk
</div>

<div class="company-sub">
Skill Monitoring &amp; Competency Management System
</div>

</td>


<td class="document-cell">

<div class="document-label">
Dokumen
</div>

<div class="document-year">
<?= e_pdf($tahun_terbaru) ?>
</div>

</td>

</tr>

</table>


<div class="header-line"></div>

<div class="header-line-thin"></div>


<!-- =====================================================
     JUDUL
===================================================== -->

<div class="report-title">

<div class="report-title-main">
Laporan Detail Kompetensi Pekerja
</div>

<div class="report-title-sub">
Skill Assessment &amp; Competency Development Report
</div>

</div>


<!-- =====================================================
     01 PROFIL
===================================================== -->

<div class="section">

<div class="section-title">
01 &nbsp; Profil Pekerja
</div>


<table class="profile">

<tr>

<td width="50%">

<div class="profile-label">
Nama Pekerja
</div>

<div class="profile-value">
<?= e_pdf(
    $pekerja['nama'] ?? '-'
) ?>
</div>

</td>


<td width="50%">

<div class="profile-label">
Departemen
</div>

<div class="profile-value">
<?= e_pdf(
    $pekerja['departemen'] ?? '-'
) ?>
</div>

</td>

</tr>


<tr>

<td>

<div class="profile-label">
No. Reg / ID
</div>

<div class="profile-value">
<?= e_pdf(
    $pekerja['id'] ?? '-'
) ?>
</div>

</td>


<td>

<div class="profile-label">
Status Pekerja
</div>

<div class="profile-value">
<?= e_pdf(
    $pekerja['status'] ?? '-'
) ?>
</div>

</td>

</tr>


<tr>

<td>

<div class="profile-label">
Assessment Terakhir
</div>

<div class="profile-value">
<?= e_pdf($tahun_terbaru) ?>
</div>

</td>


<td>

<div class="profile-label">
Status Kompetensi
</div>

<span
    class="status status-<?= e_pdf(
        $overall_class
    ) ?>"
>

<?= e_pdf($overall_status) ?>

</span>

</td>

</tr>

</table>

</div>


<!-- =====================================================
     02 RINGKASAN
===================================================== -->

<div class="section">

<div class="section-title">
02 &nbsp; Ringkasan Kompetensi
</div>


<table class="stats">

<tr>

<td class="stat">

<div class="stat-label">
Rata-rata Skill
</div>

<div class="stat-value">
<?= number_format(
    $rata_rata,
    2
) ?>
</div>

<div class="stat-note">
Target
<?= number_format(
    $rata_target,
    2
) ?>
/ 5
</div>

</td>


<td class="stat">

<div class="stat-label">
Kompeten
</div>

<div class="stat-value">
<?= number_format(
    $total_kompeten
) ?>
</div>

<div class="stat-note">
Memenuhi target
</div>

</td>


<td class="stat">

<div class="stat-label">
Perlu Peningkatan
</div>

<div class="stat-value">
<?= number_format(
    $total_peningkatan
) ?>
</div>

<div class="stat-note">
Masih terdapat gap
</div>

</td>


<td class="stat">

<div class="stat-label">
Perlu Training
</div>

<div class="stat-value">
<?= number_format(
    $total_training
) ?>
</div>

<div class="stat-note">
Prioritas pengembangan
</div>

</td>

</tr>

</table>

</div>


<!-- =====================================================
     03 SKILL MAPPING
===================================================== -->

<div class="section">

<div class="section-title">
03 &nbsp; Skill Mapping
</div>


<table class="data-table">

<thead>

<tr>

<th
    width="5%"
    class="center"
>
No
</th>

<th width="35%">
Kompetensi
</th>

<th
    width="12%"
    class="center"
>
Actual
</th>

<th
    width="12%"
    class="center"
>
Target
</th>

<th
    width="12%"
    class="center"
>
Gap
</th>

<th width="24%">
Status
</th>

</tr>

</thead>


<tbody>

<?php if (!empty($skill_data)): ?>

<?php $no = 1; ?>


<?php foreach ($skill_data as $s): ?>

<tr>

<td class="center">

<?= $no++ ?>

</td>


<td>

<strong>

<?= e_pdf(
    $s['nama_skill']
) ?>

</strong>


<?php if (!empty($s['tanggal_penilaian'])): ?>

<div class="small">

Assessment:

<?= tanggal_pdf(
    $s['tanggal_penilaian']
) ?>

</div>

<?php endif; ?>

</td>


<td class="center">

<?php if ($s['nilai'] === null): ?>

-

<?php else: ?>

<strong>

<?= number_format(
    $s['actual'],
    0
) ?>

</strong>

<?php endif; ?>

</td>


<td class="center">

<strong>

<?= number_format(
    $s['target'],
    0
) ?>

</strong>

</td>


<td class="center">

<?php if ($s['nilai'] === null): ?>

-

<?php elseif ($s['gap'] > 0): ?>

<strong>

<?= number_format(
    $s['gap'],
    0
) ?>

</strong>

<?php else: ?>

0

<?php endif; ?>

</td>


<td>

<span
    class="status status-<?= e_pdf(
        $s['status_class']
    ) ?>"
>

<?= e_pdf(
    $s['status_label']
) ?>

</span>

</td>

</tr>

<?php endforeach; ?>


<?php else: ?>

<tr>

<td
    colspan="6"
    class="no-data"
>

Belum ada data kompetensi.

</td>

</tr>

<?php endif; ?>

</tbody>

</table>

</div>


<!-- =====================================================
     04 PRIORITAS TRAINING
===================================================== -->

<div class="section">

<div class="section-title">
04 &nbsp; Prioritas Training
</div>


<?php if (!empty($training_priority)): ?>


<table class="data-table">

<thead>

<tr>

<th
    width="8%"
    class="center"
>
No
</th>

<th>
Kompetensi
</th>

<th
    width="17%"
    class="center"
>
Actual
</th>

<th
    width="17%"
    class="center"
>
Target
</th>

<th
    width="17%"
    class="center"
>
Gap
</th>

<th width="20%">
Prioritas
</th>

</tr>

</thead>


<tbody>

<?php $no = 1; ?>


<?php foreach ($training_priority as $s): ?>

<tr>

<td class="center">

<?= $no++ ?>

</td>


<td>

<strong>

<?= e_pdf(
    $s['nama_skill']
) ?>

</strong>

</td>


<td class="center">

<?= number_format(
    $s['actual'],
    0
) ?>

</td>


<td class="center">

<?= number_format(
    $s['target'],
    0
) ?>

</td>


<td class="center">

<strong>

<?= number_format(
    $s['gap'],
    0
) ?>

</strong>

</td>


<td>

<?php if ($s['gap'] >= 2): ?>

<span class="status status-bad">
Tinggi
</span>

<?php else: ?>

<span class="status status-warning">
Sedang
</span>

<?php endif; ?>

</td>

</tr>

<?php endforeach; ?>

</tbody>

</table>


<?php else: ?>


<div class="no-data">

Tidak terdapat kompetensi yang membutuhkan
prioritas training.

</div>


<?php endif; ?>

</div>


<!-- =====================================================
     05 RIWAYAT PENILAIAN
===================================================== -->

<div class="section">

<div class="section-title">
05 &nbsp; Riwayat Penilaian Skill
</div>


<table class="data-table">

<thead>

<tr>

<th width="9%">
Tahun
</th>

<th width="14%">
Tanggal
</th>

<th>
Kompetensi
</th>

<th
    width="10%"
    class="center"
>
Nilai
</th>

<th width="18%">
Assessor
</th>

<th width="25%">
Catatan
</th>

</tr>

</thead>


<tbody>

<?php if (
    $history_result &&
    $history_result->num_rows > 0
): ?>


<?php while (
    $h = $history_result->fetch_assoc()
): ?>

<tr>

<td>

<?= e_pdf(
    $h['tahun']
) ?>

</td>


<td>

<?= tanggal_pdf(
    $h['tanggal_penilaian']
) ?>

</td>


<td>

<strong>

<?= e_pdf(
    $h['nama_skill']
) ?>

</strong>

</td>


<td class="center">

<strong>

<?= number_format(
    (float)$h['nilai'],
    0
) ?>

</strong>

 / 5

</td>


<td>

<?= e_pdf(
    $h['assessor'] ?: '-'
) ?>

</td>


<td>

<?= e_pdf(
    $h['catatan'] ?: '-'
) ?>

</td>

</tr>

<?php endwhile; ?>


<?php else: ?>

<tr>

<td
    colspan="6"
    class="no-data"
>

Belum ada riwayat penilaian.

</td>

</tr>

<?php endif; ?>

</tbody>

</table>

</div>


<!-- =====================================================
     06 RIWAYAT TRAINING
===================================================== -->

<div class="section">

<div class="section-title">
06 &nbsp; Riwayat Training
</div>


<?php if (!empty($training_data)): ?>


<table class="data-table">

<thead>

<tr>

<th>
Training
</th>

<th width="20%">
Tanggal
</th>

<th width="18%">
Trainer
</th>

<th width="17%">
Lokasi
</th>

<th width="17%">
Status
</th>

</tr>

</thead>


<tbody>


<?php foreach ($training_data as $t): ?>

<tr>

<td>

<strong>

<?= e_pdf(
    $t['nama_training']
) ?>

</strong>

</td>


<td>

<?= tanggal_pdf(
    $t['tanggal_mulai']
) ?>


<?php if (!empty($t['tanggal_selesai'])): ?>

&nbsp;s/d&nbsp;

<?= tanggal_pdf(
    $t['tanggal_selesai']
) ?>

<?php endif; ?>

</td>


<td>

<?= e_pdf(
    $t['trainer'] ?: '-'
) ?>

</td>


<td>

<?= e_pdf(
    $t['lokasi'] ?: '-'
) ?>

</td>


<td>

<?php

$status_training =
    $t['status_training']
    ?? '';


if ($status_training === 'Selesai') {

    $training_class = 'good';

} elseif ($status_training === 'Dibatalkan') {

    $training_class = 'bad';

} elseif ($status_training === 'Sedang Berlangsung') {

    $training_class = 'warning';

} else {

    $training_class = 'neutral';

}

?>


<span
    class="status status-<?= e_pdf(
        $training_class
    ) ?>"
>

<?= e_pdf(
    $status_training ?: 'Terjadwal'
) ?>

</span>


</td>

</tr>

<?php endforeach; ?>


</tbody>

</table>


<?php else: ?>


<div class="no-data">

Belum ada riwayat training.

</div>


<?php endif; ?>

</div>


<!-- =====================================================
     PENGESAHAN
===================================================== -->

<table class="signature">

<tr>

<td>

<div class="signature-title">
Dibuat oleh,
</div>

<div class="signature-space"></div>

<div class="signature-line"></div>

<div class="small">
Skill Monitoring / HR
</div>

</td>


<td>

<div class="signature-title">
Diverifikasi oleh,
</div>

<div class="signature-space"></div>

<div class="signature-line"></div>

<div class="small">
Supervisor / Atasan
</div>

</td>

</tr>

</table>


<!-- =====================================================
     FOOTER
===================================================== -->

<div class="footer">

<table width="100%">

<tr>

<td class="footer-left">

Garudafood Skill Monitoring System

</td>

<td class="footer-right">

Dokumen dibuat secara otomatis oleh sistem

</td>

</tr>

</table>

</div>


</body>

</html>

<?php

$html = ob_get_clean();


/* =========================================================
   DOMPDF OPTIONS
========================================================= */

$options = new Options();


$options->set(
    'isRemoteEnabled',
    true
);


$options->set(
    'isHtml5ParserEnabled',
    true
);


$options->set(
    'defaultFont',
    'DejaVu Sans'
);


/* =========================================================
   CREATE PDF
========================================================= */

$dompdf = new Dompdf(
    $options
);


$dompdf->loadHtml(
    $html,
    'UTF-8'
);


$dompdf->setPaper(
    'A4',
    'portrait'
);


$dompdf->render();


/* =========================================================
   NAMA FILE
========================================================= */

$nama_pekerja = preg_replace(
    '/[^A-Za-z0-9_-]/',
    '_',
    $pekerja['nama'] ?? 'Pekerja'
);


$nama_file =
    'Laporan_Kompetensi_' .
    $nama_pekerja .
    '_' .
    $tahun_terbaru .
    '.pdf';


/* =========================================================
   DOWNLOAD
========================================================= */

$dompdf->stream(
    $nama_file,
    [
        'Attachment' => true
    ]
);


exit;