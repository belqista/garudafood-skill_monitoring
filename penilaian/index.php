<?php

/*
|--------------------------------------------------------------------------
| PENILAIAN SKILL
|--------------------------------------------------------------------------
| File : penilaian/index.php
|--------------------------------------------------------------------------
| FUNGSI:
| - Tambah nilai skill terbaru
| - Tidak menimpa history nilai sebelumnya
| - Terhubung dengan Data Pekerja
| - Menampilkan nilai terbaru
| - Menampilkan history penilaian
|--------------------------------------------------------------------------
*/

mysqli_report(MYSQLI_REPORT_OFF);

/* =========================================================
   PAGE TITLE
========================================================= */

$page_title = 'Penilaian Skill';

/* =========================================================
   KONEKSI DATABASE
========================================================= */

require_once __DIR__ . '/../config/database.php';

/* =========================================================
   CEK KONEKSI
========================================================= */

if (!isset($conn) || !($conn instanceof mysqli)) {

    die(
        'Koneksi database tidak tersedia. ' .
        'Periksa config/database.php'
    );
}

if ($conn->connect_errno) {

    die(
        'Koneksi database gagal: ' .
        $conn->connect_error
    );
}

/* =========================================================
   HELPER
========================================================= */

if (!function_exists('e')) {

    function e($value)
    {
        return htmlspecialchars(
            (string)$value,
            ENT_QUOTES,
            'UTF-8'
        );
    }
}

/* =========================================================
   VARIABEL
========================================================= */

$error   = "";
$success = "";

/* =========================================================
   PROSES TAMBAH NILAI TERBARU
========================================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /* =====================================================
       AMBIL INPUT
    ===================================================== */

    $id_pekerja = isset($_POST['id_pekerja'])
        ? (int)$_POST['id_pekerja']
        : 0;

    $id_skill = isset($_POST['id_skill'])
        ? (int)$_POST['id_skill']
        : 0;

    $tahun = isset($_POST['tahun'])
        ? (int)$_POST['tahun']
        : 0;

    $nilai = isset($_POST['nilai'])
        ? (int)$_POST['nilai']
        : 0;

    $tanggal_penilaian =
        isset($_POST['tanggal_penilaian'])
            ? trim($_POST['tanggal_penilaian'])
            : '';

    $assessor =
        isset($_POST['assessor'])
            ? trim($_POST['assessor'])
            : '';

    $catatan =
        isset($_POST['catatan'])
            ? trim($_POST['catatan'])
            : '';

    /* =====================================================
       VALIDASI
    ===================================================== */

    if ($id_pekerja <= 0) {

        $error = "Pekerja wajib dipilih.";

    } elseif ($id_skill <= 0) {

        $error = "Skill wajib dipilih.";

    } elseif ($tahun < 2000 || $tahun > 2100) {

        $error = "Tahun penilaian tidak valid.";

    } elseif ($nilai < 1 || $nilai > 5) {

        $error =
            "Nilai skill harus berupa 1, 2, 3, 4, atau 5.";

    } elseif ($tanggal_penilaian === '') {

        $error = "Tanggal penilaian wajib diisi.";

    } elseif ($assessor === '') {

        $error = "Assessor wajib diisi.";
    }

    /* =====================================================
       CEK PEKERJA
    ===================================================== */

    if ($error === '') {

        $stmt = $conn->prepare("
            SELECT
                id,
                nama,
                no_reg,
                departemen,
                keterangan
            FROM pekerja
            WHERE id = ?
              AND status = 'Aktif'
            LIMIT 1
        ");

        if (!$stmt) {

            $error =
                "GAGAL MEMERIKSA DATA PEKERJA: " .
                $conn->error;

        } else {

            $stmt->bind_param(
                "i",
                $id_pekerja
            );

            if (!$stmt->execute()) {

                $error =
                    "GAGAL MEMERIKSA PEKERJA: " .
                    $stmt->error;

            } else {

                $stmt->store_result();

                if ($stmt->num_rows === 0) {

                    $error =
                        "Pekerja dengan ID " .
                        $id_pekerja .
                        " tidak ditemukan atau statusnya Nonaktif.";
                }
            }

            $stmt->close();
        }
    }

    /* =====================================================
       CEK SKILL
    ===================================================== */

    if ($error === '') {

        $stmt = $conn->prepare("
            SELECT
                id,
                nama_skill
            FROM skill
            WHERE id = ?
              AND status = 'Aktif'
            LIMIT 1
        ");

        if (!$stmt) {

            $error =
                "GAGAL MEMERIKSA DATA SKILL: " .
                $conn->error;

        } else {

            $stmt->bind_param(
                "i",
                $id_skill
            );

            if (!$stmt->execute()) {

                $error =
                    "GAGAL MEMERIKSA SKILL: " .
                    $stmt->error;

            } else {

                $stmt->store_result();

                if ($stmt->num_rows === 0) {

                    $error =
                        "Skill dengan ID " .
                        $id_skill .
                        " tidak ditemukan atau statusnya Nonaktif.";
                }
            }

            $stmt->close();
        }
    }

    /* =====================================================
       INSERT NILAI TERBARU

       PENTING:
       TIDAK ADA UPDATE.

       Setiap penilaian baru akan menjadi record baru,
       sehingga history nilai sebelumnya tetap tersimpan.
    ===================================================== */

    if ($error === '') {

        $stmt = $conn->prepare("
            INSERT INTO penilaian_skill
            (
                id_pekerja,
                id_skill,
                tahun,
                nilai,
                tanggal_penilaian,
                assessor,
                catatan
            )
            VALUES
            (
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?
            )
        ");

        if (!$stmt) {

            $error =
                "GAGAL MENYIAPKAN SIMPAN DATA: " .
                $conn->error;

        } else {

            $stmt->bind_param(
                "iiiisss",
                $id_pekerja,
                $id_skill,
                $tahun,
                $nilai,
                $tanggal_penilaian,
                $assessor,
                $catatan
            );

            if (!$stmt->execute()) {

                $error =
                    "GAGAL MENYIMPAN DATA PENILAIAN: " .
                    $stmt->error;

                $stmt->close();

            } else {

                $new_id = (int)$stmt->insert_id;

                $stmt->close();

                /* =========================================
                   VERIFIKASI DATA
                ========================================== */

                $check = $conn->prepare("
                    SELECT
                        ps.id,
                        ps.id_pekerja,
                        ps.id_skill,
                        ps.tahun,
                        ps.nilai,
                        p.nama,
                        s.nama_skill
                    FROM penilaian_skill ps

                    INNER JOIN pekerja p
                        ON p.id = ps.id_pekerja

                    INNER JOIN skill s
                        ON s.id = ps.id_skill

                    WHERE ps.id = ?

                    LIMIT 1
                ");

                $saved_ok = false;

                if ($check) {

                    $check->bind_param(
                        "i",
                        $new_id
                    );

                    if ($check->execute()) {

                        $check->store_result();

                        if ($check->num_rows > 0) {

                            $saved_ok = true;
                        }
                    }

                    $check->close();
                }

                if ($saved_ok) {

                    header(
                        "Location: index.php?saved=inserted"
                    );

                    exit;

                } else {

                    $error =
                        "Data berhasil INSERT tetapi " .
                        "tidak dapat diverifikasi kembali. " .
                        "Periksa tabel penilaian_skill.";
                }
            }
        }
    }
}

/* =========================================================
   PESAN SUKSES
========================================================= */

if (isset($_GET['saved'])) {

    if ($_GET['saved'] === 'inserted') {

        $success =
            "Nilai terbaru berhasil ditambahkan. " .
            "Nilai sebelumnya tetap tersimpan di history.";
    }
}

/* =========================================================
   AMBIL DATA PEKERJA
========================================================= */

$workers = [];

$sqlWorkers = "

    SELECT
        id,
        no_reg,
        nama,
        departemen,
        keterangan,
        status

    FROM pekerja

    WHERE status = 'Aktif'

    ORDER BY
        nama ASC
";

$resultWorkers = $conn->query(
    $sqlWorkers
);

if ($resultWorkers === false) {

    if ($error === '') {

        $error =
            "GAGAL MEMUAT DATA PEKERJA: " .
            $conn->error;
    }

} else {

    while ($row = $resultWorkers->fetch_assoc()) {

        $workers[] = $row;
    }
}

/* =========================================================
   AMBIL DATA SKILL
========================================================= */

$skills = [];

$sqlSkills = "

    SELECT
        id,
        nama_skill,
        status

    FROM skill

    WHERE status = 'Aktif'

    ORDER BY
        nama_skill ASC
";

$resultSkills = $conn->query(
    $sqlSkills
);

if ($resultSkills === false) {

    if ($error === '') {

        $error =
            "GAGAL MEMUAT DATA SKILL: " .
            $conn->error;
    }

} else {

    while ($row = $resultSkills->fetch_assoc()) {

        $skills[] = $row;
    }
}

/* =========================================================
   HISTORY PENILAIAN
========================================================= */

$history = [];

$sqlHistory = "

    SELECT

        ps.id,
        ps.id_pekerja,
        ps.id_skill,
        ps.tahun,
        ps.nilai,
        ps.tanggal_penilaian,
        ps.assessor,
        ps.catatan,
        ps.created_at,

        p.no_reg,
        p.nama,
        p.departemen,
        p.keterangan,

        s.nama_skill

    FROM penilaian_skill ps

    LEFT JOIN pekerja p
        ON p.id = ps.id_pekerja

    LEFT JOIN skill s
        ON s.id = ps.id_skill

    ORDER BY

        ps.tahun DESC,
        ps.tanggal_penilaian DESC,
        ps.id DESC

    LIMIT 30
";

$resultHistory = $conn->query(
    $sqlHistory
);

if ($resultHistory === false) {

    if ($error === '') {

        $error =
            "GAGAL MEMUAT HISTORY PENILAIAN: " .
            $conn->error;
    }

} else {

    while ($row = $resultHistory->fetch_assoc()) {

        $history[] = $row;
    }
}

/* =========================================================
   HEADER
========================================================= */

require __DIR__ . '/../partials/header.php';

?>

<style>

/* =========================================================
   GARUDAFOOD THEME
========================================================= */

:root {

    --gf-blue-dark: #092f63;

    --gf-blue: #123f7a;

    --gf-blue-light: #eaf2ff;

    --gf-blue-hover: #082952;

    --gf-text: #172033;

    --gf-muted: #788396;

    --gf-border: #e7ebf1;

    --gf-bg: #f5f7fb;

    --gf-green: #198754;

    --gf-green-bg: #e9f8f0;

    --gf-red: #dc3545;

    --gf-red-bg: #ffecee;

    --gf-yellow: #996c00;

    --gf-yellow-bg: #fff4d6;
}

/* =========================================================
   PAGE
========================================================= */

body {

    background:
        var(--gf-bg);

    color:
        var(--gf-text);

    font-family:
        "Poppins",
        "Segoe UI",
        Arial,
        sans-serif;
}

.page-wrapper {

    padding:
        24px;

}

/* =========================================================
   HERO
========================================================= */

.hero-card {

    position:
        relative;

    overflow:
        hidden;

    background:
        linear-gradient(
            135deg,
            var(--gf-blue-dark) 0%,
            var(--gf-blue) 100%
        );

    color:
        #ffffff;

    border-radius:
        20px;

    padding:
        28px 30px;

    margin-bottom:
        24px;

    box-shadow:
        0 10px 28px
        rgba(9, 47, 99, .16);
}

.hero-card::after {

    content:
        "";

    position:
        absolute;

    width:
        220px;

    height:
        220px;

    right:
        -80px;

    top:
        -120px;

    background:
        rgba(255,255,255,.06);

    border-radius:
        50%;
}

.hero-card .eyebrow {

    position:
        relative;

    z-index:
        1;

    font-size:
        11px;

    font-weight:
        700;

    letter-spacing:
        .1em;

    opacity:
        .8;

    margin-bottom:
        7px;

    text-transform:
        uppercase;
}

.hero-card h1 {

    position:
        relative;

    z-index:
        1;

    font-size:
        28px;

    font-weight:
        750;

    margin:
        0 0 8px;
}

.hero-card p {

    position:
        relative;

    z-index:
        1;

    margin:
        0;

    font-size:
        13px;

    line-height:
        1.6;

    opacity:
        .82;

}

/* =========================================================
   CARD
========================================================= */

.card-custom {

    background:
        #ffffff;

    border:
        1px solid var(--gf-border);

    border-radius:
        17px;

    padding:
        24px;

    margin-bottom:
        24px;

    box-shadow:
        0 5px 20px
        rgba(20,43,76,.045);
}

/* =========================================================
   CARD HEADER
========================================================= */

.card-header-custom {

    display:
        flex;

    justify-content:
        space-between;

    align-items:
        center;

    gap:
        15px;

    flex-wrap:
        wrap;

    margin-bottom:
        22px;
}

.card-title-custom {

    font-size:
        17px;

    font-weight:
        750;

    color:
        var(--gf-text);

    margin:
        0;
}

.card-title-custom i {

    color:
        var(--gf-blue);
}

.card-description {

    margin-top:
        5px;

    color:
        var(--gf-muted);

    font-size:
        12px;

    line-height:
        1.5;
}

/* =========================================================
   NEW BADGE
========================================================= */

.badge-new {

    display:
        inline-flex;

    align-items:
        center;

    gap:
        6px;

    padding:
        7px 11px;

    border-radius:
        20px;

    background:
        var(--gf-blue-light);

    color:
        var(--gf-blue-dark);

    font-size:
        11px;

    font-weight:
        700;
}

/* =========================================================
   FORM
========================================================= */

.form-label {

    font-size:
        12px;

    font-weight:
        650;

    color:
        var(--gf-text);

    margin-bottom:
        7px;
}

.required {

    color:
        var(--gf-red);
}

.form-control,
.form-select {

    border:
        1px solid var(--gf-border);

    border-radius:
        10px;

    min-height:
        44px;

    font-size:
        13px;

    color:
        var(--gf-text);

}

.form-control::placeholder {

    color:
        #a0a8b5;

}

.form-control:focus,
.form-select:focus {

    border-color:
        var(--gf-blue);

    box-shadow:
        0 0 0 .2rem
        rgba(18,63,122,.12);

}

/* =========================================================
   SEARCH
========================================================= */

.search-wrapper {

    position:
        relative;
}

.search-wrapper i {

    position:
        absolute;

    left:
        13px;

    top:
        50%;

    transform:
        translateY(-50%);

    color:
        #9aa4b2;

    z-index:
        2;
}

.search-wrapper .form-control {

    padding-left:
        38px;

}

/* =========================================================
   SINGLE SEARCHABLE PICKER
========================================================= */

.picker {
    position: relative;
}

.picker-control {
    position: relative;
}

.picker-control .picker-icon {
    position: absolute;
    left: 13px;
    top: 50%;
    transform: translateY(-50%);
    color: #9aa4b2;
    z-index: 2;
    pointer-events: none;
}

.picker-control .picker-arrow {
    position: absolute;
    right: 13px;
    top: 50%;
    transform: translateY(-50%);
    color: #7d8796;
    z-index: 2;
    pointer-events: none;
    transition: .2s ease;
}

.picker.open .picker-arrow {
    transform: translateY(-50%) rotate(180deg);
}

.picker-input {
    width: 100%;
    padding: 0 42px 0 38px !important;
    cursor: text;
}

.picker-dropdown {
    display: none;
    position: absolute;
    left: 0;
    right: 0;
    top: calc(100% + 7px);
    z-index: 1050;
    background: #ffffff;
    border: 1px solid var(--gf-border);
    border-radius: 12px;
    padding: 6px;
    max-height: 270px;
    overflow-y: auto;
    box-shadow: 0 12px 30px rgba(20,43,76,.12);
}

.picker.open .picker-dropdown {
    display: block;
}

.picker-option {
    display: block;
    width: 100%;
    text-align: left;
    border: 0;
    background: transparent;
    border-radius: 9px;
    padding: 10px 11px;
    color: var(--gf-text);
    cursor: pointer;
    transition: .15s ease;
}

.picker-option:hover,
.picker-option.active {
    background: var(--gf-blue-light);
}

.picker-option-name {
    display: block;
    font-size: 12px;
    font-weight: 700;
    line-height: 1.4;
}

.picker-option-meta {
    display: block;
    margin-top: 2px;
    color: var(--gf-muted);
    font-size: 10px;
    line-height: 1.4;
}

.picker-empty {
    padding: 15px 12px;
    color: var(--gf-muted);
    text-align: center;
    font-size: 11px;
}

.picker-input.is-selected {
    background: #f8faff;
    border-color: #cddcf0;
}

.picker-selected-clear {
    position: absolute;
    right: 35px;
    top: 50%;
    transform: translateY(-50%);
    border: 0;
    background: transparent;
    color: #8b95a4;
    display: none;
    padding: 2px 5px;
    z-index: 3;
}

.picker.has-value .picker-selected-clear {
    display: block;
}

.picker.has-value .picker-arrow {
    right: 11px;
}

@media (max-width: 768px) {
    .picker-dropdown {
        max-height: 230px;
    }
}

/* =========================================================
   HELP TEXT
========================================================= */

.form-help {

    margin-top:
        6px;

    color:
        var(--gf-muted);

    font-size:
        11px;

}

/* =========================================================
   SCORE BOX
========================================================= */

.score-box {

    background:
        #f8faff;

    border:
        1px solid #dce7f7;

    border-radius:
        13px;

    padding:
        15px;

}

.score-box-label {

    color:
        var(--gf-muted);

    font-size:
        11px;

    font-weight:
        600;

    margin-bottom:
        7px;
}

.score-input {

    font-size:
        20px !important;

    font-weight:
        750;

    text-align:
        center;

    color:
        var(--gf-blue-dark) !important;

}

/* =========================================================
   SCORE GUIDE
========================================================= */

.score-guide {

    display:
        flex;

    gap:
        7px;

    flex-wrap:
        wrap;

    margin-top:
        10px;
}

.score-guide span {

    display:
        inline-flex;

    align-items:
        center;

    justify-content:
        center;

    min-width:
        30px;

    height:
        27px;

    border-radius:
        7px;

    background:
        #eef2f7;

    color:
        #586273;

    font-size:
        11px;

    font-weight:
        700;
}

.score-guide span.active {

    background:
        var(--gf-blue);

    color:
        #ffffff;
}

/* =========================================================
   LATEST VALUE BOX
========================================================= */

.latest-box {

    display:
        none;

    background:
        linear-gradient(
            135deg,
            #f7faff,
            #eef5ff
        );

    border:
        1px solid #dbe7f8;

    border-radius:
        14px;

    padding:
        16px;

    margin-top:
        16px;
}

.latest-box.show {

    display:
        block;
}

.latest-title {

    color:
        var(--gf-blue-dark);

    font-size:
        12px;

    font-weight:
        700;

    margin-bottom:
        8px;
}

.latest-content {

    display:
        flex;

    align-items:
        center;

    justify-content:
        space-between;

    gap:
        12px;

}

.latest-score {

    width:
        48px;

    height:
        48px;

    display:
        flex;

    align-items:
        center;

    justify-content:
        center;

    border-radius:
        12px;

    background:
        var(--gf-blue);

    color:
        #ffffff;

    font-size:
        19px;

    font-weight:
        750;

}

.latest-info {

    flex:
        1;
}

.latest-info strong {

    display:
        block;

    color:
        var(--gf-text);

    font-size:
        12px;

}

.latest-info span {

    display:
        block;

    color:
        var(--gf-muted);

    font-size:
        11px;

    margin-top:
        3px;

}

/* =========================================================
   BUTTON
========================================================= */

.btn-gf {

    display:
        inline-flex;

    align-items:
        center;

    justify-content:
        center;

    gap:
        7px;

    background:
        var(--gf-blue);

    border:
        1px solid var(--gf-blue);

    color:
        #ffffff;

    border-radius:
        10px;

    padding:
        11px 21px;

    font-size:
        13px;

    font-weight:
        650;

    transition:
        .2s ease;

}

.btn-gf:hover {

    background:
        var(--gf-blue-hover);

    border-color:
        var(--gf-blue-hover);

    color:
        #ffffff;

    transform:
        translateY(-1px);

}

.btn-secondary-custom {

    background:
        #f2f4f8;

    border:
        1px solid #e1e6ee;

    color:
        #596579;

    border-radius:
        10px;

    padding:
        11px 18px;

    font-size:
        13px;

    font-weight:
        600;

}

.btn-secondary-custom:hover {

    background:
        #e8ecf2;

    color:
        #354052;

}

/* =========================================================
   ALERT
========================================================= */

.alert-custom {

    border:
        0;

    border-radius:
        12px;

    font-size:
        12px;

}

/* =========================================================
   HISTORY HEADER
========================================================= */

.history-header {

    display:
        flex;

    justify-content:
        space-between;

    align-items:
        center;

    gap:
        10px;

    flex-wrap:
        wrap;

    margin-bottom:
        18px;

}

.history-count {

    background:
        #f0f3f7;

    color:
        #667184;

    border-radius:
        20px;

    padding:
        6px 10px;

    font-size:
        11px;

    font-weight:
        650;
}

/* =========================================================
   TABLE
========================================================= */

.table-custom {

    margin:
        0;

    vertical-align:
        middle;

}

.table-custom thead th {

    background:
        var(--gf-blue-light);

    color:
        var(--gf-blue-dark);

    font-size:
        10px;

    font-weight:
        750;

    text-transform:
        uppercase;

    letter-spacing:
        .04em;

    white-space:
        nowrap;

    padding:
        12px 14px;

    border:
        0;

}

.table-custom tbody td {

    color:
        var(--gf-text);

    font-size:
        12px;

    padding:
        13px 14px;

    border-bottom:
        1px solid #edf0f4;

}

.table-custom tbody tr:hover {

    background:
        #fafcff;

}

.table-custom tbody tr:last-child td {

    border-bottom:
        0;

}

/* =========================================================
   SCORE BADGE
========================================================= */

.badge-score {

    display:
        inline-flex;

    align-items:
        center;

    justify-content:
        center;

    min-width:
        38px;

    height:
        29px;

    border-radius:
        8px;

    font-size:
        12px;

    font-weight:
        750;

}

.badge-score.low {

    background:
        var(--gf-red-bg);

    color:
        var(--gf-red);
}

.badge-score.mid {

    background:
        var(--gf-yellow-bg);

    color:
        var(--gf-yellow);
}

.badge-score.high {

    background:
        var(--gf-green-bg);

    color:
        var(--gf-green);
}

/* =========================================================
   LATEST BADGE
========================================================= */

.latest-badge {

    display:
        inline-flex;

    align-items:
        center;

    gap:
        4px;

    padding:
        4px 7px;

    border-radius:
        6px;

    background:
        var(--gf-green-bg);

    color:
        var(--gf-green);

    font-size:
        9px;

    font-weight:
        700;

    margin-left:
        5px;

}

/* =========================================================
   WORKER
========================================================= */

.worker-name {

    font-weight:
        700;

    color:
        var(--gf-text);

}

.worker-meta {

    color:
        var(--gf-muted);

    font-size:
        10px;

    margin-top:
        3px;

    line-height:
        1.5;

}

/* =========================================================
   YEAR
========================================================= */

.year-badge {

    display:
        inline-flex;

    align-items:
        center;

    justify-content:
        center;

    min-width:
        48px;

    padding:
        5px 8px;

    border-radius:
        7px;

    background:
        #f0f3f8;

    color:
        #536074;

    font-size:
        11px;

    font-weight:
        700;

}

/* =========================================================
   RESPONSIVE
========================================================= */

@media (max-width: 768px) {

    .page-wrapper {

        padding:
            15px;

    }

    .hero-card {

        padding:
            22px;

        border-radius:
            15px;

    }

    .hero-card h1 {

        font-size:
            23px;

    }

    .card-custom {

        padding:
            17px;

        border-radius:
            14px;

    }

    .card-header-custom {

        align-items:
            flex-start;

    }

    .table-custom {

        min-width:
            900px;

    }

}

</style>

<div class="page-wrapper">

    <!-- =====================================================
         HERO
    ====================================================== -->

    <div class="hero-card">

        <div class="eyebrow">

            GARUDAFOOD • TEKNIK

        </div>

        <h1>

            Penilaian Skill

        </h1>

        <p>

            Tambahkan nilai kompetensi terbaru pekerja
            tanpa menghapus atau menimpa history penilaian sebelumnya.

        </p>

    </div>

    <!-- =====================================================
         SUCCESS
    ====================================================== -->

    <?php if ($success !== ''): ?>

        <div
            class="
                alert
                alert-success
                alert-custom
                alert-dismissible
                fade
                show
                mb-4
            "
            role="alert"
        >

            <i class="bi bi-check-circle-fill me-2"></i>

            <?= e($success) ?>

            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="alert"
            ></button>

        </div>

    <?php endif; ?>

    <!-- =====================================================
         ERROR
    ====================================================== -->

    <?php if ($error !== ''): ?>

        <div
            class="
                alert
                alert-danger
                alert-custom
                alert-dismissible
                fade
                show
                mb-4
            "
            role="alert"
        >

            <i class="bi bi-exclamation-triangle-fill me-2"></i>

            <strong>Gagal:</strong>

            <?= e($error) ?>

            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="alert"
            ></button>

        </div>

    <?php endif; ?>

    <!-- =====================================================
         TAMBAH NILAI TERBARU
    ====================================================== -->

    <div class="card-custom">

        <div class="card-header-custom">

            <div>

                <div class="card-title-custom">

                    <i class="bi bi-plus-circle-fill me-2"></i>

                    Tambah Nilai Terbaru

                </div>

                <div class="card-description">

                    Masukkan hasil penilaian terbaru.
                    Nilai lama tidak akan dihapus.

                </div>

            </div>

            <div class="badge-new">

                <i class="bi bi-stars"></i>

                Penilaian Baru

            </div>

        </div>

        <form
            method="POST"
            action=""
            id="assessmentForm"
        >

            <div class="row g-4">

                <!-- =================================================
                     PEKERJA
                ================================================== -->

                <div class="col-lg-6">

                    <label class="form-label">
                        Pekerja
                        <span class="required">*</span>
                    </label>

                    <?php
                    $selectedWorkerId = isset($_POST['id_pekerja'])
                        ? (int)$_POST['id_pekerja']
                        : 0;

                    $selectedWorker = null;

                    foreach ($workers as $worker) {
                        if ((int)$worker['id'] === $selectedWorkerId) {
                            $selectedWorker = $worker;
                            break;
                        }
                    }
                    ?>

                    <div
                        class="picker <?= $selectedWorker ? 'has-value' : '' ?>"
                        id="workerPicker"
                    >

                        <input
                            type="hidden"
                            name="id_pekerja"
                            id="id_pekerja"
                            value="<?= $selectedWorker ? (int)$selectedWorker['id'] : '' ?>"
                        >

                        <div class="picker-control">

                            <i class="bi bi-search picker-icon"></i>

                            <input
                                type="text"
                                class="form-control picker-input <?= $selectedWorker ? 'is-selected' : '' ?>"
                                id="workerPickerInput"
                                placeholder="Cari & pilih pekerja..."
                                autocomplete="off"
                                value="<?= $selectedWorker
                                    ? e(
                                        $selectedWorker['nama'] .
                                        ' - ' .
                                        $selectedWorker['no_reg']
                                    )
                                    : '' ?>"
                            >

                            <button
                                type="button"
                                class="picker-selected-clear"
                                id="workerPickerClear"
                                aria-label="Hapus pilihan pekerja"
                            >
                                <i class="bi bi-x-circle-fill"></i>
                            </button>

                            <i class="bi bi-chevron-down picker-arrow"></i>

                        </div>

                        <div class="picker-dropdown" id="workerPickerDropdown">

                            <?php foreach ($workers as $worker): ?>

                                <button
                                    type="button"
                                    class="picker-option"
                                    data-id="<?= (int)$worker['id'] ?>"
                                    data-search="<?= e(
                                        $worker['nama'] . ' ' .
                                        $worker['no_reg'] . ' ' .
                                        $worker['departemen'] . ' ' .
                                        $worker['keterangan']
                                    ) ?>"
                                    data-label="<?= e(
                                        $worker['nama'] .
                                        ' - ' .
                                        $worker['no_reg']
                                    ) ?>"
                                >
                                    <span class="picker-option-name">
                                        <?= e($worker['nama']) ?>
                                    </span>

                                    <span class="picker-option-meta">
                                        No. Reg: <?= e($worker['no_reg'] ?: '-') ?>

                                        <?php if (!empty($worker['departemen'])): ?>
                                            • <?= e($worker['departemen']) ?>
                                        <?php endif; ?>

                                        <?php if (!empty($worker['keterangan'])): ?>
                                            • <?= e($worker['keterangan']) ?>
                                        <?php endif; ?>
                                    </span>
                                </button>

                            <?php endforeach; ?>

                            <div
                                class="picker-empty"
                                id="workerPickerEmpty"
                                style="display:none;"
                            >
                                Pekerja tidak ditemukan.
                            </div>

                        </div>

                    </div>

                    <div class="form-help">
                        Ketik nama, No. Reg, departemen, atau keterangan untuk mencari pekerja.
                    </div>

                    <!-- NILAI TERAKHIR -->

                    <div
                        class="latest-box"
                        id="latestBox"
                    >

                        <div class="latest-title">
                            <i class="bi bi-clock-history me-1"></i>
                            Nilai Terakhir Pekerja
                        </div>

                        <div class="latest-content">

                            <div
                                class="latest-score"
                                id="latestScore"
                            >
                                -
                            </div>

                            <div class="latest-info">

                                <strong id="latestSkill">
                                    Pilih skill terlebih dahulu
                                </strong>

                                <span id="latestDetail">
                                    -
                                </span>

                            </div>

                        </div>

                    </div>

                </div>

                <!-- =================================================
                     SKILL
                ================================================== -->

                <div class="col-lg-6">

                    <label class="form-label">
                        Skill / Kompetensi
                        <span class="required">*</span>
                    </label>

                    <?php
                    $selectedSkillId = isset($_POST['id_skill'])
                        ? (int)$_POST['id_skill']
                        : 0;

                    $selectedSkill = null;

                    foreach ($skills as $skill) {
                        if ((int)$skill['id'] === $selectedSkillId) {
                            $selectedSkill = $skill;
                            break;
                        }
                    }
                    ?>

                    <div
                        class="picker <?= $selectedSkill ? 'has-value' : '' ?>"
                        id="skillPicker"
                    >

                        <input
                            type="hidden"
                            name="id_skill"
                            id="id_skill"
                            value="<?= $selectedSkill ? (int)$selectedSkill['id'] : '' ?>"
                        >

                        <div class="picker-control">

                            <i class="bi bi-search picker-icon"></i>

                            <input
                                type="text"
                                class="form-control picker-input <?= $selectedSkill ? 'is-selected' : '' ?>"
                                id="skillPickerInput"
                                placeholder="Cari & pilih skill..."
                                autocomplete="off"
                                value="<?= $selectedSkill
                                    ? e($selectedSkill['nama_skill'])
                                    : '' ?>"
                            >

                            <button
                                type="button"
                                class="picker-selected-clear"
                                id="skillPickerClear"
                                aria-label="Hapus pilihan skill"
                            >
                                <i class="bi bi-x-circle-fill"></i>
                            </button>

                            <i class="bi bi-chevron-down picker-arrow"></i>

                        </div>

                        <div class="picker-dropdown" id="skillPickerDropdown">

                            <?php foreach ($skills as $skill): ?>

                                <button
                                    type="button"
                                    class="picker-option"
                                    data-id="<?= (int)$skill['id'] ?>"
                                    data-search="<?= e($skill['nama_skill']) ?>"
                                    data-label="<?= e($skill['nama_skill']) ?>"
                                >
                                    <span class="picker-option-name">
                                        <?= e($skill['nama_skill']) ?>
                                    </span>

                                    <span class="picker-option-meta">
                                        Skill / Kompetensi Aktif
                                    </span>
                                </button>

                            <?php endforeach; ?>

                            <div
                                class="picker-empty"
                                id="skillPickerEmpty"
                                style="display:none;"
                            >
                                Skill tidak ditemukan.
                            </div>

                        </div>

                    </div>

                    <div class="form-help">
                        Ketik nama skill untuk mencari dan memilih kompetensi.
                    </div>

                </div>

                <!-- =================================================
                     TAHUN
                ================================================== -->


                <div class="col-md-4">

                    <label class="form-label">

                        Tahun Penilaian

                        <span class="required">*</span>

                    </label>

                    <input
                        type="number"
                        name="tahun"
                        id="tahun"
                        class="form-control"
                        min="2000"
                        max="2100"
                        value="<?= e(
                            isset($_POST['tahun'])
                                ? $_POST['tahun']
                                : date('Y')
                        ) ?>"
                        required
                    >

                    <div class="form-help">

                        Tahun penilaian terbaru.

                    </div>

                </div>

                <!-- =================================================
                     NILAI
                ================================================== -->

                <div class="col-md-4">

                    <div class="score-box">

                        <div class="score-box-label">

                            Nilai Skill

                            <span class="required">*</span>

                        </div>

                        <input
                            type="number"
                            name="nilai"
                            id="nilai"
                            class="form-control score-input"
                            min="1"
                            max="5"
                            step="1"
                            value="<?= e(
                                isset($_POST['nilai'])
                                    ? $_POST['nilai']
                                    : ''
                            ) ?>"
                            placeholder="1 - 5"
                            required
                        >

                        <div class="score-guide">

                            <span data-score="1">1</span>

                            <span data-score="2">2</span>

                            <span data-score="3">3</span>

                            <span data-score="4">4</span>

                            <span data-score="5">5</span>

                        </div>

                    </div>

                </div>

                <!-- =================================================
                     TANGGAL
                ================================================== -->

                <div class="col-md-4">

                    <label class="form-label">

                        Tanggal Penilaian

                        <span class="required">*</span>

                    </label>

                    <input
                        type="date"
                        name="tanggal_penilaian"
                        id="tanggal_penilaian"
                        class="form-control"
                        value="<?= e(
                            isset($_POST['tanggal_penilaian'])
                                ? $_POST['tanggal_penilaian']
                                : date('Y-m-d')
                        ) ?>"
                        required
                    >

                    <div class="form-help">

                        Tanggal dilakukan penilaian.

                    </div>

                </div>

                <!-- =================================================
                     ASSESSOR
                ================================================== -->

                <div class="col-md-6">

                    <label class="form-label">

                        Assessor

                        <span class="required">*</span>

                    </label>

                    <input
                        type="text"
                        name="assessor"
                        class="form-control"
                        value="<?= e(
                            isset($_POST['assessor'])
                                ? $_POST['assessor']
                                : ''
                        ) ?>"
                        placeholder="Nama assessor"
                        required
                    >

                </div>

                <!-- =================================================
                     CATATAN
                ================================================== -->

                <div class="col-md-6">

                    <label class="form-label">

                        Catatan

                    </label>

                    <input
                        type="text"
                        name="catatan"
                        class="form-control"
                        value="<?= e(
                            isset($_POST['catatan'])
                                ? $_POST['catatan']
                                : ''
                        ) ?>"
                        placeholder="Catatan penilaian (opsional)"
                    >

                </div>

                <!-- =================================================
                     BUTTON
                ================================================== -->

                <div class="col-12">

                    <hr
                        class="my-1"
                        style="border-color:#edf0f4;"
                    >

                    <div
                        class="
                            d-flex
                            justify-content-between
                            align-items-center
                            flex-wrap
                            gap-2
                            mt-3
                        "
                    >

                        <div class="form-help m-0">

                            <i class="bi bi-info-circle me-1"></i>

                            Setiap penilaian baru akan tersimpan
                            sebagai history.

                        </div>

                        <div
                            class="
                                d-flex
                                gap-2
                            "
                        >

                            <button
                                type="reset"
                                class="btn btn-secondary-custom"
                                id="resetForm"
                            >

                                <i class="bi bi-arrow-counterclockwise"></i>

                                Reset

                            </button>

                            <button
                                type="submit"
                                class="btn btn-gf"
                            >

                                <i class="bi bi-plus-circle"></i>

                                Tambah Nilai Terbaru

                            </button>

                        </div>

                    </div>

                </div>

            </div>

        </form>

    </div>

    <!-- =====================================================
         HISTORY
    ====================================================== -->

    <div class="card-custom">

        <div class="history-header">

            <div>

                <div class="card-title-custom">

                    <i class="bi bi-clock-history me-2"></i>

                    History Penilaian

                </div>

                <div class="card-description">

                    Menampilkan penilaian terbaru yang sudah
                    tersimpan di database.

                </div>

            </div>

            <div class="history-count">

                <?= count($history) ?> data terbaru

            </div>

        </div>

        <div class="table-responsive">

            <table
                class="
                    table
                    table-hover
                    table-custom
                "
            >

                <thead>

                    <tr>

                        <th>

                            Tanggal

                        </th>

                        <th>

                            Pekerja

                        </th>

                        <th>

                            Skill

                        </th>

                        <th>

                            Tahun

                        </th>

                        <th>

                            Nilai

                        </th>

                        <th>

                            Assessor

                        </th>

                        <th>

                            Catatan

                        </th>

                    </tr>

                </thead>

                <tbody>

                <?php if (!empty($history)): ?>

                    <?php

                    $historyLatestKey = [];

                    ?>

                    <?php foreach (
                        $history
                        as $item
                    ): ?>

                        <?php

                        $score =
                            (int)$item['nilai'];

                        if ($score <= 2) {

                            $scoreClass =
                                'low';

                        } elseif ($score == 3) {

                            $scoreClass =
                                'mid';

                        } else {

                            $scoreClass =
                                'high';
                        }

                        /*
                         * Penanda apakah record ini adalah
                         * penilaian terakhir untuk kombinasi
                         * pekerja + skill.
                         */

                        $latestKey =
                            (int)$item['id_pekerja'] .
                            '-' .
                            (int)$item['id_skill'];

                        $isLatest = false;

                        if (
                            !isset(
                                $historyLatestKey[
                                    $latestKey
                                ]
                            )
                        ) {

                            $historyLatestKey[
                                $latestKey
                            ] = true;

                            $isLatest = true;
                        }

                        ?>

                        <tr>

                            <!-- TANGGAL -->

                            <td>

                                <div class="fw-semibold">

                                    <?= e(
                                        $item[
                                            'tanggal_penilaian'
                                        ] ?: '-'
                                    ) ?>

                                </div>

                            </td>

                            <!-- PEKERJA -->

                            <td>

                                <?php if (
                                    !empty(
                                        $item['nama']
                                    )
                                ): ?>

                                    <div
                                        class="
                                            worker-name
                                        "
                                    >

                                        <?= e(
                                            $item['nama']
                                        ) ?>


                                    </div>

                                    <div
                                        class="
                                            worker-meta
                                        "
                                    >

                                        No. Reg:
                                        <?= e(
                                            $item['no_reg']
                                        ) ?>

                                        <?php if (
                                            !empty(
                                                $item[
                                                    'departemen'
                                                ]
                                            )
                                        ): ?>

                                            •
                                            <?= e(
                                                $item[
                                                    'departemen'
                                                ]
                                            ) ?>

                                        <?php endif; ?>

                                        <?php if (
                                            !empty(
                                                $item[
                                                    'keterangan'
                                                ]
                                            )
                                        ): ?>

                                            •
                                            <?= e(
                                                $item[
                                                    'keterangan'
                                                ]
                                            ) ?>

                                        <?php endif; ?>

                                    </div>

                                <?php else: ?>

                                    <span class="text-danger">

                                        Data pekerja tidak ditemukan

                                        (ID:
                                        <?= (int)
                                            $item[
                                                'id_pekerja'
                                            ]
                                        ?>
                                        )

                                    </span>

                                <?php endif; ?>

                            </td>

                            <!-- SKILL -->

                            <td>

                                <?php if (
                                    !empty(
                                        $item[
                                            'nama_skill'
                                        ]
                                    )
                                ): ?>

                                    <div class="fw-semibold">

                                        <?= e(
                                            $item[
                                                'nama_skill'
                                            ]
                                        ) ?>

                                    </div>

                                <?php else: ?>

                                    <span class="text-danger">

                                        Skill tidak ditemukan

                                        (ID:
                                        <?= (int)
                                            $item[
                                                'id_skill'
                                            ]
                                        ?>
                                        )

                                    </span>

                                <?php endif; ?>

                            </td>

                            <!-- TAHUN -->

                            <td>

                                <span
                                    class="
                                        year-badge
                                    "
                                >

                                    <?= e(
                                        $item['tahun']
                                    ) ?>

                                </span>

                            </td>

                            <!-- NILAI -->

                            <td>

                                <span
                                    class="
                                        badge-score
                                        <?= $scoreClass ?>
                                    "
                                >

                                    <?= $score ?>

                                </span>

                            </td>

                            <!-- ASSESSOR -->

                            <td>

                                <?= !empty(
                                    $item['assessor']
                                )
                                    ? e(
                                        $item['assessor']
                                    )
                                    : '-'
                                ?>

                            </td>

                            <!-- CATATAN -->

                            <td>

                                <?php if (
                                    !empty(
                                        $item['catatan']
                                    )
                                ): ?>

                                    <?= e(
                                        $item['catatan']
                                    ) ?>

                                <?php else: ?>

                                    <span
                                        class="text-muted"
                                    >

                                        -

                                    </span>

                                <?php endif; ?>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php else: ?>

                    <tr>

                        <td
                            colspan="7"
                            class="
                                text-center
                                text-muted
                                py-5
                            "
                        >

                            <i
                                class="
                                    bi
                                    bi-clipboard-x
                                    d-block
                                    fs-2
                                    mb-2
                                "
                            ></i>

                            Belum ada history penilaian.

                        </td>

                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>

</div>

<script>

/* =========================================================
   SINGLE SEARCHABLE PICKER
========================================================= */

        function setupPicker(config) {

            const picker = document.getElementById(config.pickerId);
            const input = document.getElementById(config.inputId);
            const hidden = document.getElementById(config.hiddenId);
            const dropdown = document.getElementById(config.dropdownId);
            const clearButton = document.getElementById(config.clearId);
            const empty = document.getElementById(config.emptyId);

            if (
                !picker ||
                !input ||
                !hidden ||
                !dropdown
            ) {
                return null;
            }

            const options = Array.from(
                dropdown.querySelectorAll('.picker-option')
            );

            function openPicker() {
                picker.classList.add('open');
                filterOptions();
            }

            function closePicker() {
                picker.classList.remove('open');
            }

            function filterOptions() {

                const keyword = input.value
                    .toLowerCase()
                    .trim();

                let visibleCount = 0;

                options.forEach(function (option) {

                    const text = (
                        option.textContent +
                        ' ' +
                        (option.dataset.search || '')
                    ).toLowerCase();

                    const matched =
                        keyword === '' ||
                        text.includes(keyword);

                    option.style.display =
                        matched ? '' : 'none';

                    if (matched) {
                        visibleCount++;
                    }

                });

                if (empty) {
                    empty.style.display =
                        visibleCount === 0
                            ? 'block'
                            : 'none';
                }
            }

            function selectOption(option) {

                hidden.value = option.dataset.id || '';
                input.value = option.dataset.label || '';
                input.classList.add('is-selected');
                picker.classList.add('has-value');

                options.forEach(function (item) {
                    item.classList.remove('active');
                });

                option.classList.add('active');

                closePicker();

                input.dispatchEvent(
                    new Event('change', {
                        bubbles: true
                    })
                );
            }

            function clearPicker() {

                hidden.value = '';
                input.value = '';
                input.classList.remove('is-selected');
                picker.classList.remove('has-value');

                options.forEach(function (option) {
                    option.classList.remove('active');
                    option.style.display = '';
                });

                if (empty) {
                    empty.style.display = 'none';
                }

                input.focus();
            }

            input.addEventListener(
                'focus',
                function () {
                    openPicker();
                }
            );

            input.addEventListener(
                'click',
                function () {
                    openPicker();
                }
            );

            input.addEventListener(
                'input',
                function () {

                    /*
                     * Begitu user mengubah teks pilihan,
                     * ID lama harus dihapus agar tidak salah kirim.
                     */
                    hidden.value = '';
                    input.classList.remove('is-selected');
                    picker.classList.remove('has-value');

                    options.forEach(function (option) {
                        option.classList.remove('active');
                    });

                    openPicker();
                }
            );

            options.forEach(function (option) {

                option.addEventListener(
                    'mousedown',
                    function (event) {
                        /*
                         * Mencegah input kehilangan fokus
                         * sebelum pilihan diproses.
                         */
                        event.preventDefault();
                    }
                );

                option.addEventListener(
                    'click',
                    function () {
                        selectOption(option);
                    }
                );

            });

            if (clearButton) {

                clearButton.addEventListener(
                    'mousedown',
                    function (event) {
                        event.preventDefault();
                    }
                );

                clearButton.addEventListener(
                    'click',
                    function () {
                        clearPicker();
                    }
                );
            }

            /*
             * Tandai pilihan yang berasal dari POST
             * ketika halaman kembali setelah validasi gagal.
             */
            if (hidden.value !== '') {

                const selected =
                    options.find(function (option) {
                        return option.dataset.id === hidden.value;
                    });

                if (selected) {
                    selected.classList.add('active');
                }

            }

            document.addEventListener(
                'click',
                function (event) {

                    if (!picker.contains(event.target)) {
                        closePicker();
                    }

                }
            );

            return {
                clear: clearPicker,
                close: closePicker
            };
        }

        const workerPicker = setupPicker({
            pickerId: 'workerPicker',
            inputId: 'workerPickerInput',
            hiddenId: 'id_pekerja',
            dropdownId: 'workerPickerDropdown',
            clearId: 'workerPickerClear',
            emptyId: 'workerPickerEmpty'
        });

        const skillPicker = setupPicker({
            pickerId: 'skillPicker',
            inputId: 'skillPickerInput',
            hiddenId: 'id_skill',
            dropdownId: 'skillPickerDropdown',
            clearId: 'skillPickerClear',
            emptyId: 'skillPickerEmpty'
        });

/* =====================================================
           NILAI GUIDE
        ===================================================== */

        const nilaiInput =
            document.getElementById(
                'nilai'
            );

        const scoreGuide =
            document.querySelectorAll(
                '.score-guide span'
            );

        function updateScoreGuide() {

            const value =
                parseInt(
                    nilaiInput.value,
                    10
                );

            scoreGuide.forEach(
                function (item) {

                    const score =
                        parseInt(
                            item.dataset.score,
                            10
                        );

                    item.classList.toggle(
                        'active',
                        score === value
                    );

                }
            );

        }

        if (nilaiInput) {

            nilaiInput.addEventListener(
                'input',
                updateScoreGuide
            );

            scoreGuide.forEach(
                function (item) {

                    item.addEventListener(
                        'click',
                        function () {

                            nilaiInput.value =
                                this.dataset.score;

                            updateScoreGuide();

                        }
                    );

                }
            );

            updateScoreGuide();

        }

        /* =====================================================
           RESET
        ===================================================== */

        const form =
            document.getElementById(
                'assessmentForm'
            );

        const resetButton =
            document.getElementById(
                'resetForm'
            );

        if (
            resetButton &&
            form
        ) {

            resetButton.addEventListener(
                'click',
                function () {

                    setTimeout(
                        function () {

                            const latestBox =
                                document.getElementById(
                                    'latestBox'
                                );

                            if (latestBox) {
                                latestBox.classList.remove(
                                    'show'
                                );
                            }

                            if (workerPicker) {
                                workerPicker.clear();
                            }

                            if (skillPicker) {
                                skillPicker.clear();
                            }

                            if (nilaiInput) {
                                nilaiInput.value = '';
                                updateScoreGuide();
                            }

                            /*
                             * Tetapkan kembali tanggal dan tahun
                             * ke nilai hari/tahun sekarang.
                             */
                            const yearInput =
                                document.getElementById('tahun');

                            const dateInput =
                                document.getElementById('tanggal_penilaian');

                            if (yearInput) {
                                yearInput.value =
                                    new Date().getFullYear();
                            }

                            if (dateInput) {

                                const now = new Date();

                                const year =
                                    now.getFullYear();

                                const month =
                                    String(
                                        now.getMonth() + 1
                                    ).padStart(2, '0');

                                const day =
                                    String(
                                        now.getDate()
                                    ).padStart(2, '0');

                                dateInput.value =
                                    year + '-' +
                                    month + '-' +
                                    day;
                            }

                        },
                        10
                    );

                }
            );

        }

/* =====================================================
           FORM VALIDATION
        ===================================================== */

        if (form) {

            form.addEventListener(
                'submit',
                function (event) {

                    const worker =
                        document.getElementById(
                            'id_pekerja'
                        );

                    const skill =
                        document.getElementById(
                            'id_skill'
                        );

                    const tahun =
                        document.getElementById(
                            'tahun'
                        );

                    const nilai =
                        document.getElementById(
                            'nilai'
                        );

                    const tanggal =
                        document.getElementById(
                            'tanggal_penilaian'
                        );

                    if (!worker.value) {

                        event.preventDefault();

                        alert(
                            'Silakan pilih pekerja terlebih dahulu.'
                        );

                        worker.focus();

                        return;
                    }

                    if (!skill.value) {

                        event.preventDefault();

                        alert(
                            'Silakan pilih skill terlebih dahulu.'
                        );

                        skill.focus();

                        return;
                    }

                    const year =
                        parseInt(
                            tahun.value,
                            10
                        );

                    if (
                        isNaN(year) ||
                        year < 2000 ||
                        year > 2100
                    ) {

                        event.preventDefault();

                        alert(
                            'Tahun penilaian tidak valid.'
                        );

                        tahun.focus();

                        return;
                    }

                    const score =
                        parseInt(
                            nilai.value,
                            10
                        );

                    if (
                        isNaN(score) ||
                        score < 1 ||
                        score > 5
                    ) {

                        event.preventDefault();

                        alert(
                            'Nilai harus berupa 1, 2, 3, 4, atau 5.'
                        );

                        nilai.focus();

                        return;
                    }

                    if (!tanggal.value) {

                        event.preventDefault();

                        alert(
                            'Tanggal penilaian wajib diisi.'
                        );

                        tanggal.focus();

                        return;
                    }

                    const confirmation =
                        confirm(
                            'Tambahkan nilai ini sebagai penilaian terbaru?\\n\\n' +
                            'Nilai sebelumnya tidak akan dihapus.'
                        );

                    if (!confirmation) {

                        event.preventDefault();

                    }

                }
            );

        }

</script>

<?php

/* =========================================================
   FOOTER
========================================================= */

require __DIR__ . '/../partials/footer.php';

?>
