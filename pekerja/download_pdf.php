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
   LOAD DATABASE
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
                File koneksi database tidak ditemukan.
            </p>

        </div>
    ');

}


/* =========================================================
   CEK CONNECTION
========================================================= */

if (
    !isset($conn) ||
    !($conn instanceof mysqli)
) {

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
                Variable <strong>$conn</strong>
                tidak tersedia sebagai koneksi MySQLi.
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
                Pastikan Composer dan Dompdf sudah terinstall.
            </p>

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
   SEMUA TAHUN PENILAIAN
   =========================================================
   PENTING:
   Tahun diurutkan ASCENDING.
   
   Contoh:
   2024
   2025
   2026
   
   Dengan begitu tahun terbaru selalu berada
   di kolom paling kanan.
========================================================= */

$years = [];

$stmt = $conn->prepare("
    SELECT DISTINCT
        tahun
    FROM penilaian_skill
    WHERE id_pekerja = ?
      AND tahun IS NOT NULL
      AND tahun > 0
    ORDER BY tahun ASC
");

if ($stmt) {

    $stmt->bind_param(
        'i',
        $id
    );

    $stmt->execute();

    $result_year = $stmt->get_result();

    while ($row = $result_year->fetch_assoc()) {

        $years[] = (int)$row['tahun'];

    }

    $stmt->close();

}


/* =========================================================
   PASTIKAN URUTAN TAHUN BENAR
   LAMA -> TERBARU
========================================================= */

$years = array_values(
    array_unique(
        array_map(
            'intval',
            $years
        )
    )
);

sort($years, SORT_NUMERIC);


/* =========================================================
   TAHUN TERBARU
========================================================= */

$tahun_terbaru = !empty($years)
    ? max($years)
    : (int)date('Y');


/* =========================================================
   JIKA BELUM ADA DATA TAHUN
========================================================= */

if (empty($years)) {

    $years = [
        $tahun_terbaru
    ];

}


/* =========================================================
   AMBIL SEMUA DATA PENILAIAN
========================================================= */

$history = [];

$stmt = $conn->prepare("
    SELECT
        ps.id,
        ps.id_skill,
        ps.tahun,
        ps.nilai,
        ps.tanggal_penilaian,
        ps.assessor,
        ps.catatan,
        s.nama_skill

    FROM penilaian_skill ps

    INNER JOIN skill s
        ON s.id = ps.id_skill

    WHERE ps.id_pekerja = ?

    ORDER BY
        s.nama_skill ASC,
        ps.tahun ASC,
        ps.id ASC
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


while ($row = $history_result->fetch_assoc()) {

    $skill_id = (int)$row['id_skill'];

    $tahun = (int)$row['tahun'];


    if (!isset($history[$skill_id])) {

        $history[$skill_id] = [

            'id_skill' =>
                $skill_id,

            'nama_skill' =>
                $row['nama_skill'],

            'years' => [],

            'latest' => null,

        ];

    }


    /*
       Simpan data per tahun.

       Kalau terdapat lebih dari satu penilaian
       dalam tahun yang sama, data dengan ID terbesar
       dianggap sebagai data terbaru.
    */

    if (
        !isset(
            $history[$skill_id]['years'][$tahun]
        )
        ||
        (int)$row['id'] >
        (int)$history[$skill_id]['years'][$tahun]['id']
    ) {

        $history[$skill_id]['years'][$tahun] = $row;

    }


    /*
       Tentukan data terbaru per skill.
    */

    if (
        $history[$skill_id]['latest'] === null
        ||
        $tahun >
        (int)$history[$skill_id]['latest']['tahun']
        ||
        (
            $tahun ===
            (int)$history[$skill_id]['latest']['tahun']
            &&
            (int)$row['id'] >
            (int)$history[$skill_id]['latest']['id']
        )
    ) {

        $history[$skill_id]['latest'] = $row;

    }

}

$stmt->close();


/* =========================================================
   AMBIL SKILL AKTIF
   TERMASUK SKILL YANG BELUM DINILAI
========================================================= */

$skill_data = [];

$stmt = $conn->prepare("
    SELECT
        s.id AS id_skill,
        s.nama_skill
    FROM skill s
    WHERE s.status = 'Aktif'
    ORDER BY s.nama_skill ASC
");

if ($stmt) {

    $stmt->execute();

    $skill_result = $stmt->get_result();

    while ($row = $skill_result->fetch_assoc()) {

        $id_skill = (int)$row['id_skill'];

        $latest = null;


        if (
            isset($history[$id_skill])
        ) {

            $latest =
                $history[$id_skill]['latest'];

        }


        $skill_data[] = [

            'id_skill' =>
                $id_skill,

            'nama_skill' =>
                $row['nama_skill'],

            'nilai' =>
                $latest['nilai']
                ?? null,

            'tahun' =>
                $latest['tahun']
                ?? null,

            'tanggal_penilaian' =>
                $latest['tanggal_penilaian']
                ?? null,

            'assessor' =>
                $latest['assessor']
                ?? null,

            'catatan' =>
                $latest['catatan']
                ?? null,

        ];

    }

    $stmt->close();

}


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
   PROSES SKILL TERBARU
========================================================= */

foreach ($skill_data as &$skill) {

    $nilai = $skill['nilai'] !== null
        ? (float)$skill['nilai']
        : null;


    /*
       TARGET DEFAULT
    */

    $target = 3;


    if ($nilai !== null) {

        $gap = max(
            0,
            $target - $nilai
        );

    } else {

        $gap = 0;

    }


    if ($nilai === null) {

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


    if ($nilai !== null) {

        $total_skill++;

        $total_actual += $nilai;

        $total_target += $target;

        $total_gap += $gap;

    }


    $skill['actual'] =
        $nilai !== null
            ? $nilai
            : 0;


    $skill['target'] =
        $target;


    $skill['gap'] =
        $gap;


    $skill['status_label'] =
        $status;


    $skill['status_class'] =
        $status_class;

}

unset($skill);


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
   URUTKAN SKILL
========================================================= */

usort(
    $skill_data,
    function ($a, $b) {

        return strcasecmp(
            $a['nama_skill'],
            $b['nama_skill']
        );

    }
);


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

        if (
            $a['gap'] ==
            $b['gap']
        ) {

            return
                $a['actual']
                <=>
                $b['actual'];

        }

        return
            $b['gap']
            <=>
            $a['gap'];

    }
);


$training_priority =
    array_slice(
        $training_priority,
        0,
        5
    );


/* =========================================================
   RINGKASAN PER TAHUN
   URUTAN TAHUN: LAMA -> TERBARU
========================================================= */

$year_summary = [];


foreach ($years as $year) {

    $total = 0;

    $count = 0;


    foreach ($history as $item) {

        if (
            isset(
                $item['years'][$year]
            )
            &&
            $item['years'][$year]['nilai']
            !== null
        ) {

            $total +=
                (float)$item['years'][$year]['nilai'];

            $count++;

        }

    }


    $year_summary[$year] =
        $count > 0
            ? $total / $count
            : null;

}


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


    while (
        $row =
            $training_result->fetch_assoc()
    ) {

        $training_data[] = $row;

    }


    $stmt_training->close();

}


/* =========================================================
   LOGO
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


    $logo_binary =
        @file_get_contents(
            $logo_path
        );


    if ($logo_binary === false) {
        continue;
    }


    $extension =
        strtolower(
            pathinfo(
                $logo_path,
                PATHINFO_EXTENSION
            )
        );


    switch ($extension) {

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
        25px
        28px
        30px
        28px;

}


* {
    box-sizing: border-box;
}


body {

    font-family:
        DejaVu Sans,
        sans-serif;

    color:
        #172033;

    font-size:
        8px;

    line-height:
        1.4;

    margin:
        0;

}


/* =========================================================
   HEADER
========================================================= */

.header-table {

    width: 100%;

    border-collapse:
        collapse;

}


.header-table td {

    vertical-align:
        middle;

}


.logo-cell {

    width:
        90px;

}


.logo {

    max-width:
        78px;

    max-height:
        42px;

}


.company-cell {

    text-align:
        center;

}


.company-name {

    color:
        #092f63;

    font-size:
        13px;

    font-weight:
        bold;

}


.company-sub {

    color:
        #788396;

    font-size:
        7px;

    margin-top:
        2px;

}


.doc-cell {

    width:
        90px;

    text-align:
        right;

}


.doc-label {

    color:
        #788396;

    font-size:
        6px;

    text-transform:
        uppercase;

}


.doc-year {

    color:
        #123b72;

    font-size:
        10px;

    font-weight:
        bold;

    margin-top:
        2px;

}


.header-line {

    height:
        3px;

    background:
        #123b72;

    margin-top:
        7px;

}


.header-line-thin {

    height:
        1px;

    background:
        #d8dee8;

    margin-bottom:
        14px;

}


/* =========================================================
   TITLE
========================================================= */

.title {

    text-align:
        center;

    margin-bottom:
        14px;

}


.title-main {

    color:
        #172033;

    font-size:
        14px;

    font-weight:
        bold;

    text-transform:
        uppercase;

}


.title-sub {

    color:
        #788396;

    font-size:
        7px;

    margin-top:
        2px;

}


/* =========================================================
   SECTION
========================================================= */

.section {

    margin-top:
        14px;

    margin-bottom:
        7px;

}


.section-title {

    color:
        #092f63;

    font-size:
        9px;

    font-weight:
        bold;

    border-left:
        4px solid #123b72;

    padding-left:
        6px;

    margin-bottom:
        7px;

    text-transform:
        uppercase;

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

    width:
        50%;

    padding:
        6px 8px;

    border:
        1px solid #dfe4eb;

    background:
        #fbfcfe;

}


.label {

    color:
        #788396;

    font-size:
        6px;

    text-transform:
        uppercase;

    margin-bottom:
        2px;

}


.value {

    color:
        #172033;

    font-size:
        8px;

    font-weight:
        bold;

}


/* =========================================================
   STATUS
========================================================= */

.status {

    display:
        inline-block;

    padding:
        3px 6px;

    border-radius:
        4px;

    font-size:
        6.5px;

    font-weight:
        bold;

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
   STATISTIC
========================================================= */

.stats {

    width:
        100%;

    border-collapse:
        separate;

    border-spacing:
        4px;

    margin-left:
        -4px;

}


.stat {

    width:
        25%;

    border:
        1px solid #dfe4eb;

    padding:
        7px;

    background:
        #fff;

}


.stat-label {

    color:
        #788396;

    font-size:
        6px;

    text-transform:
        uppercase;

}


.stat-value {

    color:
        #123b72;

    font-size:
        15px;

    font-weight:
        bold;

    margin-top:
        2px;

}


.stat-note {

    color:
        #8a94a4;

    font-size:
        6px;

}


/* =========================================================
   TABLE
========================================================= */

.data-table {

    width:
        100%;

    border-collapse:
        collapse;

}


.data-table th {

    color:
        #fff;

    background:
        #123b72;

    border:
        1px solid #123b72;

    padding:
        5px;

    font-size:
        6.5px;

    text-transform:
        uppercase;

}


.data-table td {

    border:
        1px solid #dfe4eb;

    padding:
        5px;

    font-size:
        7px;

    vertical-align:
        middle;

}


.data-table tr:nth-child(even) td {

    background:
        #f8fafc;

}


.center {

    text-align:
        center;

}


.right {

    text-align:
        right;

}


.small {

    color:
        #788396;

    font-size:
        6px;

}


/* =========================================================
   HISTORY MATRIX
========================================================= */

.history-table {

    width:
        100%;

    border-collapse:
        collapse;

    table-layout:
        fixed;

}


.history-table th {

    background:
        #123b72;

    color:
        #fff;

    border:
        1px solid #123b72;

    padding:
        6px 4px;

    font-size:
        6.5px;

    text-align:
        center;

}


.history-table th.skill-head {

    width:
        42%;

    text-align:
        left;

}


/*
   Setiap kolom tahun memiliki lebar yang sama.
*/

.history-table th:not(.skill-head),
.history-table td:not(:first-child) {

    width:
        auto;

}


.history-table td {

    border:
        1px solid #dfe4eb;

    padding:
        5px 4px;

    font-size:
        7px;

    vertical-align:
        middle;

}


.history-table tr:nth-child(even) td {

    background:
        #f8fafc;

}


.skill-name {

    font-weight:
        bold;

}


.history-score {

    text-align:
        center;

    font-size:
        8px;

    font-weight:
        bold;

    color:
        #123b72;

}


.history-empty {

    color:
        #9aa3af;

    text-align:
        center;

}


/* =========================================================
   MINI BAR
========================================================= */

.bar-wrap {

    width:
        100%;

    height:
        6px;

    background:
        #edf0f4;

    margin-top:
        3px;

}


.bar {

    height:
        6px;

    background:
        #123b72;

}


.bar-label {

    color:
        #788396;

    font-size:
        5.5px;

}


/* =========================================================
   CHART AREA
========================================================= */

.chart-table {

    width:
        100%;

    border-collapse:
        collapse;

}


.chart-table td {

    padding:
        4px 3px;

    border-bottom:
        1px solid #edf0f4;

    vertical-align:
        middle;

}


.chart-skill {

    width:
        30%;

    font-size:
        6.5px;

    font-weight:
        bold;

}


.chart-values {

    width:
        70%;

}


.chart-year {

    display:
        inline-block;

    width:
        42px;

    margin-right:
        4px;

    text-align:
        center;

    vertical-align:
        top;

}


.chart-year-label {

    font-size:
        5.5px;

    color:
        #788396;

}


.chart-number {

    font-size:
        7px;

    font-weight:
        bold;

    color:
        #123b72;

}


.chart-bar-bg {

    width:
        38px;

    height:
        35px;

    border:
        1px solid #dfe4eb;

    background:
        #f8fafc;

    position:
        relative;

    margin:
        2px auto;

}


.chart-bar-fill {

    position:
        absolute;

    bottom:
        0;

    left:
        0;

    width:
        100%;

    background:
        #123b72;

}


/* =========================================================
   EMPTY
========================================================= */

.empty {

    border:
        1px solid #dfe4eb;

    color:
        #8a94a4;

    padding:
        12px;

    text-align:
        center;

}


/* =========================================================
   SIGNATURE
========================================================= */

.signature {

    width:
        100%;

    border-collapse:
        collapse;

    margin-top:
        25px;

}


.signature td {

    width:
        50%;

    text-align:
        center;

}


.signature-title {

    color:
        #788396;

    font-size:
        6.5px;

}


.signature-space {

    height:
        38px;

}


.signature-line {

    width:
        140px;

    margin:
        0 auto;

    border-top:
        1px solid #7b8491;

}


/* =========================================================
   FOOTER
========================================================= */

.footer {

    margin-top:
        18px;

    border-top:
        1px solid #dfe4eb;

    padding-top:
        6px;

    color:
        #8a94a4;

    font-size:
        6px;

}


.footer-table {

    width:
        100%;

}


.footer-right {

    text-align:
        right;

}

</style>

</head>


<body>


<!-- =====================================================
     HEADER
===================================================== -->

<table class="header-table">

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


<td class="doc-cell">

<div class="doc-label">
Assessment Terakhir
</div>

<div class="doc-year">
<?= e_pdf($tahun_terbaru) ?>
</div>

</td>

</tr>

</table>


<div class="header-line"></div>

<div class="header-line-thin"></div>


<!-- =====================================================
     TITLE
===================================================== -->

<div class="title">

<div class="title-main">
Laporan Detail Pekerja
</div>

<div class="title-sub">
Skill Mapping, Assessment History &amp; Training Development
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

<td>

<div class="label">
Nama Pekerja
</div>

<div class="value">
<?= e_pdf(
    $pekerja['nama'] ?? '-'
) ?>
</div>

</td>


<td>

<div class="label">
Departemen
</div>

<div class="value">
<?= e_pdf(
    $pekerja['departemen'] ?? '-'
) ?>
</div>

</td>

</tr>


<tr>

<td>

<div class="label">
No. Reg / ID
</div>

<div class="value">
<?= e_pdf(
    $pekerja['id'] ?? '-'
) ?>
</div>

</td>


<td>

<div class="label">
Status Pekerja
</div>

<div class="value">
<?= e_pdf(
    $pekerja['status'] ?? '-'
) ?>
</div>

</td>

</tr>


<tr>

<td>

<div class="label">
Assessment Terakhir
</div>

<div class="value">
<?= e_pdf($tahun_terbaru) ?>
</div>

</td>


<td>

<div class="label">
Status Kompetensi
</div>

<span class="
    status
    status-<?= e_pdf($overall_class) ?>
">

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

Target rata-rata
<?= number_format(
    $rata_target,
    2
) ?>
/ 5

</div>

</td>


<td class="stat">

<div class="stat-label">
Skill Kompeten
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
     03 SKILL MAPPING TERBARU
===================================================== -->

<div class="section">

<div class="section-title">
03 &nbsp; Skill Mapping Terbaru
</div>


<table class="data-table">

<thead>

<tr>

<th width="5%" class="center">
No
</th>

<th width="38%">
Kompetensi / Skill
</th>

<th width="10%" class="center">
Nilai
</th>

<th width="10%" class="center">
Target
</th>

<th width="10%" class="center">
Gap
</th>

<th width="17%">
Status
</th>

<th width="10%">
Tanggal
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

</td>


<td class="center">

<?php if ($s['nilai'] === null): ?>

<span class="history-empty">
-
</span>

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

<span class="
    status
    status-<?= e_pdf(
        $s['status_class']
    ) ?>
">

<?= e_pdf(
    $s['status_label']
) ?>

</span>

</td>


<td class="center">

<?= tanggal_pdf(
    $s['tanggal_penilaian']
) ?>

</td>

</tr>

<?php endforeach; ?>


<?php else: ?>

<tr>

<td
    colspan="7"
    class="empty"
>

Belum ada data kompetensi.

</td>

</tr>

<?php endif; ?>

</tbody>

</table>

</div>


<!-- =====================================================
     04 RIWAYAT PENILAIAN
     
     URUTAN TAHUN:
     PALING LAMA -> PALING BARU
     
     Contoh:
     2024 | 2025 | 2026
                    ↑
              terbaru di kanan
===================================================== -->

<div class="section">

<div class="section-title">
04 &nbsp; Riwayat Penilaian Skill
</div>


<div class="small" style="margin-bottom:6px;">

Setiap tahun ditampilkan sebagai kolom.
Tahun terbaru selalu berada di sebelah kanan.

</div>


<?php if (!empty($history)): ?>


<table class="history-table">

<thead>

<tr>

<th class="skill-head">
Kompetensi / Skill
</th>


<?php
/*
   PENTING:
   $years SUDAH diurutkan ASCENDING.

   Jadi:
   2024 -> 2025 -> 2026

   bukan:
   2026 -> 2025 -> 2024
*/
?>

<?php foreach ($years as $year): ?>

<th>
<?= e_pdf($year) ?>
</th>

<?php endforeach; ?>

</tr>

</thead>


<tbody>

<?php foreach ($history as $item): ?>

<tr>

<td>

<div class="skill-name">

<?= e_pdf(
    $item['nama_skill']
) ?>

</div>

</td>


<?php foreach ($years as $year): ?>

<td>

<?php

$record =
    $item['years'][$year]
    ?? null;

?>


<?php if (
    $record
    &&
    $record['nilai'] !== null
): ?>

<div class="history-score">

<?= number_format(
    (float)$record['nilai'],
    0
) ?>

</div>


<div class="small center">

<?= tanggal_pdf(
    $record['tanggal_penilaian']
) ?>

</div>


<?php else: ?>

<div class="history-empty">
-
</div>

<?php endif; ?>

</td>

<?php endforeach; ?>

</tr>

<?php endforeach; ?>

</tbody>

</table>


<?php else: ?>

<div class="empty">

Belum ada riwayat penilaian skill.

</div>

<?php endif; ?>

</div>


<!-- =====================================================
     05 PERKEMBANGAN NILAI PER SKILL
     
     URUTAN:
     TAHUN LAMA -> TAHUN TERBARU
===================================================== -->

<div class="section">

<div class="section-title">
05 &nbsp; Perkembangan Nilai Per Skill
</div>


<div class="small" style="margin-bottom:6px;">

Perbandingan nilai setiap skill dari tahun ke tahun.
Skala nilai 1 sampai 5.

</div>


<?php if (!empty($history)): ?>


<table class="chart-table">

<?php foreach ($history as $item): ?>

<tr>

<td class="chart-skill">

<?= e_pdf(
    $item['nama_skill']
) ?>

</td>


<td class="chart-values">


<?php foreach ($years as $year): ?>

<?php

$record =
    $item['years'][$year]
    ?? null;


$nilai =
    (
        $record
        &&
        $record['nilai'] !== null
    )
        ? (float)$record['nilai']
        : null;


$height =
    $nilai !== null
        ? max(
            2,
            min(
                100,
                ($nilai / 5) * 100
            )
        )
        : 0;

?>


<div class="chart-year">

<div class="chart-year-label">
<?= e_pdf($year) ?>
</div>


<div class="chart-bar-bg">

<?php if ($nilai !== null): ?>

<div
    class="chart-bar-fill"
    style="
        height:
        <?= number_format(
            $height,
            0
        ) ?>%;
    "
></div>

<?php endif; ?>

</div>


<div class="chart-number">

<?php if ($nilai !== null): ?>

<?= number_format(
    $nilai,
    0
) ?>

<?php else: ?>

-

<?php endif; ?>

</div>

</div>

<?php endforeach; ?>


</td>

</tr>

<?php endforeach; ?>

</table>


<?php else: ?>

<div class="empty">

Belum ada data untuk grafik perkembangan.

</div>

<?php endif; ?>

</div>


<!-- =====================================================
     06 PRIORITAS TRAINING
===================================================== -->

<div class="section">

<div class="section-title">
06 &nbsp; Prioritas Training
</div>


<?php if (!empty($training_priority)): ?>


<table class="data-table">

<thead>

<tr>

<th
    width="7%"
    class="center"
>
No
</th>

<th>
Kompetensi
</th>

<th
    width="13%"
    class="center"
>
Nilai
</th>

<th
    width="13%"
    class="center"
>
Target
</th>

<th
    width="13%"
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


<?php foreach (
    $training_priority
    as $s
): ?>

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

<div class="empty">

Tidak terdapat kompetensi yang membutuhkan
prioritas training.

</div>

<?php endif; ?>

</div>


<!-- =====================================================
     07 RINGKASAN PERKEMBANGAN TAHUN
     
     URUTAN:
     TAHUN LAMA -> TERBARU
===================================================== -->

<div class="section">

<div class="section-title">
07 &nbsp; Ringkasan Perkembangan Tahunan
</div>


<table class="data-table">

<thead>

<tr>

<th width="25%">
Tahun
</th>

<th width="25%" class="center">
Jumlah Skill Dinilai
</th>

<th width="25%" class="center">
Rata-rata Nilai
</th>

<th width="25%" class="center">
Perubahan
</th>

</tr>

</thead>


<tbody>

<?php

/*
   Jangan menggunakan DESC di sini.

   $years sudah:
   2024
   2025
   2026

   sehingga perkembangan dihitung:
   2024 -> 2025 -> 2026
*/

$years_asc = $years;

sort(
    $years_asc,
    SORT_NUMERIC
);


$previous_average = null;

?>


<?php foreach (
    $years_asc
    as $year
): ?>

<?php

$average =
    $year_summary[$year]
    ?? null;


$change = null;


if (
    $average !== null
    &&
    $previous_average !== null
) {

    $change =
        $average -
        $previous_average;

}

?>


<tr>

<td>

<strong>
<?= e_pdf($year) ?>
</strong>

</td>


<td class="center">

<?php

$count_year = 0;


foreach ($history as $item) {

    if (
        isset(
            $item['years'][$year]
        )
        &&
        $item['years'][$year]['nilai']
        !== null
    ) {

        $count_year++;

    }

}

?>

<?= number_format(
    $count_year
) ?>

</td>


<td class="center">

<?php if ($average !== null): ?>

<strong>

<?= number_format(
    $average,
    2
) ?>

</strong>

/ 5

<?php else: ?>

-

<?php endif; ?>

</td>


<td class="center">

<?php if ($change === null): ?>

-

<?php elseif ($change > 0): ?>

<strong style="color:#176b3a;">

+<?= number_format(
    $change,
    2
) ?>

</strong>

<?php elseif ($change < 0): ?>

<strong style="color:#a52834;">

<?= number_format(
    $change,
    2
) ?>

</strong>

<?php else: ?>

0.00

<?php endif; ?>

</td>

</tr>


<?php

if ($average !== null) {

    $previous_average =
        $average;

}

?>


<?php endforeach; ?>

</tbody>

</table>

</div>


<!-- =====================================================
     08 RIWAYAT TRAINING
===================================================== -->

<div class="section">

<div class="section-title">
08 &nbsp; Riwayat Training
</div>


<?php if (!empty($training_data)): ?>


<table class="data-table">

<thead>

<tr>

<th width="30%">
Training
</th>

<th width="20%">
Tanggal
</th>

<th width="18%">
Trainer
</th>

<th width="15%">
Lokasi
</th>

<th width="17%">
Status
</th>

</tr>

</thead>


<tbody>


<?php foreach (
    $training_data
    as $t
): ?>

<?php

$status_training =
    $t['status_training']
    ?? '';


if (
    $status_training
    === 'Selesai'
) {

    $training_class =
        'good';

} elseif (
    $status_training
    === 'Dibatalkan'
) {

    $training_class =
        'bad';

} elseif (
    $status_training
    === 'Sedang Berlangsung'
) {

    $training_class =
        'warning';

} else {

    $training_class =
        'neutral';

}

?>


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


<?php if (
    !empty(
        $t['tanggal_selesai']
    )
): ?>

&nbsp;s/d&nbsp;

<?= tanggal_pdf(
    $t['tanggal_selesai']
) ?>

<?php endif; ?>

</td>


<td>

<?= e_pdf(
    $t['trainer']
    ?: '-'
) ?>

</td>


<td>

<?= e_pdf(
    $t['lokasi']
    ?: '-'
) ?>

</td>


<td>

<span class="
    status
    status-<?= e_pdf(
        $training_class
    ) ?>
">

<?= e_pdf(
    $status_training
    ?: 'Terjadwal'
) ?>

</span>

</td>

</tr>

<?php endforeach; ?>


</tbody>

</table>


<?php else: ?>

<div class="empty">

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

<table class="footer-table">

<tr>

<td>

Garudafood Skill Monitoring System

</td>


<td class="footer-right">

Dokumen dibuat otomatis oleh sistem

</td>

</tr>

</table>

</div>


</body>

</html>

<?php


$html =
    ob_get_clean();


/* =========================================================
   DOMPDF OPTIONS
========================================================= */

$options =
    new Options();


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

$dompdf =
    new Dompdf(
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

$nama_pekerja =
    preg_replace(
        '/[^A-Za-z0-9_-]/',
        '_',
        $pekerja['nama']
        ?? 'Pekerja'
    );


$nama_file =
    'Laporan_Detail_Pekerja_' .
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