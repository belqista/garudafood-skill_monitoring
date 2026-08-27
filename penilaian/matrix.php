<?php
/* =========================================================
   GARUDAFOOD SKILL MONITORING
   SKILL MATRIX

   FITUR:
   - Filter Tahun
   - Filter Pekerja
   - Filter Departemen
   - Tanpa filter = semua data tetap tampil
   - Rata-rata nilai per pekerja
   - Target skill
   - Gap kompetensi
   - Responsive / mobile
========================================================= */

$page_title = 'Skill Matrix';

require __DIR__ . '/../partials/header.php';


/* =========================================================
   PARAMETER FILTER
========================================================= */

$year = isset($_GET['tahun']) && $_GET['tahun'] !== ''
    ? (int) $_GET['tahun']
    : 0;

$pid = isset($_GET['pekerja']) && $_GET['pekerja'] !== ''
    ? (int) $_GET['pekerja']
    : 0;

$departemen = isset($_GET['departemen'])
    ? trim($_GET['departemen'])
    : '';


/* =========================================================
   JIKA TIDAK ADA TAHUN
   AMBIL TAHUN TERBARU SEBAGAI DEFAULT
========================================================= */

if ($year <= 0) {

    $qLatestYear = $conn->query("
        SELECT MAX(tahun) AS tahun
        FROM penilaian_skill
        WHERE tahun IS NOT NULL
    ");

    if ($qLatestYear) {

        $latestRow =
            $qLatestYear->fetch_assoc();

        $year =
            !empty($latestRow['tahun'])
                ? (int)$latestRow['tahun']
                : (int)date('Y');
    } else {

        $year =
            (int)date('Y');
    }
}


/* =========================================================
   ESCAPE FILTER DEPARTEMEN
========================================================= */

$departemenSafe =
    $conn->real_escape_string(
        $departemen
    );


/* =========================================================
   FILTER PEKERJA
========================================================= */

$wherePeople = '';

if ($pid > 0) {

    $wherePeople .=
        ' AND p.id = ' . $pid;
}


/* =========================================================
   FILTER DEPARTEMEN
========================================================= */

if ($departemen !== '') {

    $wherePeople .=
        " AND p.departemen = '{$departemenSafe}'";
}


/* =========================================================
   DATA PEKERJA
========================================================= */

$people = $conn->query("

    SELECT

        p.id,
        p.no_reg,
        p.nama,
        p.departemen,
        p.keterangan,
        p.status

    FROM pekerja p

    WHERE

        p.status = 'Aktif'

        {$wherePeople}

    ORDER BY

        p.nama ASC

");


/* =========================================================
   DATA SKILL
========================================================= */

$skills = $conn->query("

    SELECT

        id,
        nama_skill

    FROM skill

    WHERE

        status = 'Aktif'

    ORDER BY

        id ASC

");


$ss = [];


if ($skills) {

    while (
        $s = $skills->fetch_assoc()
    ) {

        $ss[] = $s;
    }
}


/* =========================================================
   DATA TARGET SKILL

   Default target = 4
========================================================= */

$targets = [];


$qTarget = $conn->query("

    SELECT

        id_skill,
        target

    FROM target_skill

");


if ($qTarget) {

    while (
        $t = $qTarget->fetch_assoc()
    ) {

        $targets[
            (int)$t['id_skill']
        ] = (float)$t['target'];
    }
}


/* =========================================================
   DATA PENILAIAN
========================================================= */

$score = [];


$q = $conn->query("

    SELECT

        ps.id_pekerja,
        ps.id_skill,
        ps.nilai,
        ps.tahun

    FROM penilaian_skill ps

    INNER JOIN pekerja p

        ON p.id = ps.id_pekerja

    WHERE

        ps.tahun = {$year}

        AND p.status = 'Aktif'

");


if ($q) {

    while (
        $r = $q->fetch_assoc()
    ) {

        $idPekerja =
            (int)$r['id_pekerja'];

        $idSkill =
            (int)$r['id_skill'];


        $score[
            $idPekerja
        ][
            $idSkill
        ] = [

            'nilai' =>
                $r['nilai'],

            'tahun' =>
                $r['tahun']

        ];
    }
}


/* =========================================================
   DATA PEKERJA UNTUK FILTER
========================================================= */

$workerFilter = $conn->query("

    SELECT

        id,
        no_reg,
        nama,
        departemen

    FROM pekerja

    WHERE

        status = 'Aktif'

    ORDER BY

        nama ASC

");


/* =========================================================
   DATA DEPARTEMEN UNTUK FILTER
========================================================= */

$departmentFilter = $conn->query("

    SELECT DISTINCT

        departemen

    FROM pekerja

    WHERE

        status = 'Aktif'

        AND departemen IS NOT NULL

        AND TRIM(departemen) <> ''

    ORDER BY

        departemen ASC

");


/* =========================================================
   STATISTIK
========================================================= */

$totalPeople = 0;


if ($people) {

    $totalPeople =
        $people->num_rows;
}


$totalSkills =
    count($ss);


$totalAssessment = 0;

$totalScore = 0;

$countScore = 0;


if (!empty($score)) {

    foreach (
        $score as $workerScores
    ) {

        foreach (
            $workerScores as $item
        ) {

            if (

                isset($item['nilai'])

                &&

                $item['nilai'] !== null

                &&

                $item['nilai'] !== ''

            ) {

                $totalAssessment++;

                $totalScore +=
                    (float)$item['nilai'];

                $countScore++;
            }
        }
    }
}


/* =========================================================
   RATA-RATA SEMUA ASSESSMENT
========================================================= */

$overallAverage = 0;


if ($countScore > 0) {

    $overallAverage =
        $totalScore / $countScore;
}


/* =========================================================
   QUERY STRING UNTUK RESET / FILTER
========================================================= */

$currentQuery = [

    'tahun' =>
        $year

];


if ($pid > 0) {

    $currentQuery['pekerja'] =
        $pid;
}


if ($departemen !== '') {

    $currentQuery['departemen'] =
        $departemen;
}

?>


<style>

/* =========================================================
   ROOT
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

    --gf-yellow: #b77900;

    --gf-yellow-bg: #fff4d8;

    --gf-red: #dc3545;

    --gf-red-bg: #ffecee;

}


/* =========================================================
   HERO
========================================================= */

.dashboard-heading {

    position: relative;

    overflow: hidden;

    margin-bottom: 25px;

    padding: 27px 30px;

    min-height: 145px;

    border-radius: 17px;

    background:
        linear-gradient(
            135deg,
            #123f7a 0%,
            #0d376f 55%,
            #092f63 100%
        );

    box-shadow:
        0 8px 25px
        rgba(9,47,99,.14);
}


.dashboard-heading::before {

    content: "";

    position: absolute;

    width: 180px;

    height: 180px;

    right: -45px;

    top: -85px;

    border-radius: 50%;

    background:
        rgba(255,255,255,.045);
}


.dashboard-heading::after {

    content: "";

    position: absolute;

    width: 125px;

    height: 125px;

    right: 70px;

    bottom: -75px;

    border-radius: 50%;

    background:
        rgba(255,255,255,.035);
}


.dashboard-heading > * {

    position: relative;

    z-index: 2;
}


.dashboard-eyebrow {

    display: inline-flex;

    align-items: center;

    gap: 7px;

    color:
        rgba(255,255,255,.92);

    font-size: 12px;

    font-weight: 700;

    text-transform: uppercase;

    letter-spacing: .08em;

    margin-bottom: 7px;
}


.dashboard-title {

    margin: 0;

    font-size: 28px;

    line-height: 1.2;

    font-weight: 800;

    color: #ffffff;
}


.dashboard-description {

    margin-top: 8px;

    color:
        rgba(255,255,255,.78);

    font-size: 13px;

    line-height: 1.6;

    max-width: 850px;
}


/* =========================================================
   CARD
========================================================= */

.cardx {

    background: #ffffff;

    border:
        1px solid
        var(--gf-border);

    border-radius: 17px;

    padding: 24px;

    box-shadow:
        0 5px 20px
        rgba(20,43,76,.045);

    margin-bottom: 20px;
}


/* =========================================================
   FILTER
========================================================= */

.form-label {

    font-size: 11px;

    font-weight: 750;

    color: #4a5568;

    text-transform: uppercase;

    letter-spacing: .05em;

    margin-bottom: 6px;
}


.form-control,
.form-select {

    border-color:
        #dbe2ef;

    border-radius: 9px;

    font-size: 13px;

    padding: 10px 14px;

    color: #2d3748;
}


.form-control:focus,
.form-select:focus {

    border-color:
        var(--gf-blue);

    box-shadow:
        0 0 0 3px
        rgba(18,63,122,.12);
}


.btn-primary {

    background:
        var(--gf-blue-dark) !important;

    border-color:
        var(--gf-blue-dark) !important;

    font-weight: 600;

    border-radius: 9px;

    padding: 10px 20px;

    font-size: 13px;
}


.btn-primary:hover {

    background:
        var(--gf-blue-hover) !important;

    border-color:
        var(--gf-blue-hover) !important;
}


/* =========================================================
   FILTER ACTIVE INFO
========================================================= */

.filter-active {

    margin-top: 15px;

    padding-top: 14px;

    border-top:
        1px solid
        #edf0f4;

    display: flex;

    align-items: center;

    gap: 7px;

    flex-wrap: wrap;
}


.filter-active-label {

    color:
        #8a94a4;

    font-size: 10px;

    font-weight: 700;

    text-transform: uppercase;
}


.filter-badge {

    display: inline-flex;

    align-items: center;

    gap: 5px;

    padding: 5px 8px;

    border-radius: 7px;

    background:
        var(--gf-blue-light);

    color:
        var(--gf-blue);

    font-size: 10px;

    font-weight: 700;
}


/* =========================================================
   STATISTIK
========================================================= */

.matrix-stat {

    background: #ffffff;

    border:
        1px solid
        var(--gf-border);

    border-radius: 13px;

    padding: 13px 15px;

    height: 100%;
}


.matrix-stat-label {

    color: #8a94a4;

    font-size: 10px;

    font-weight: 700;

    text-transform: uppercase;

    letter-spacing: .04em;
}


.matrix-stat-value {

    color: #172033;

    font-size: 20px;

    font-weight: 800;

    margin-top: 2px;
}


.matrix-stat-sub {

    color:
        #9aa3af;

    font-size:
        9px;

    margin-top:
        2px;
}


/* =========================================================
   TITLE
========================================================= */

.card-title-custom {

    color: #172033;

    font-size: 16px;

    font-weight: 750;

    margin-bottom: 5px;
}


.section-note {

    color: #8a94a4;

    font-size: 12px;
}


/* =========================================================
   MATRIX WRAPPER
========================================================= */

.matrix-wrap {

    width: 100%;

    overflow-x: auto;

    overflow-y: hidden;

    margin-top: 15px;

    border-radius: 12px;

    border:
        1px solid
        #e3e8ef;

    -webkit-overflow-scrolling:
        touch;
}


/* =========================================================
   MATRIX TABLE
========================================================= */

.matrix {

    margin-bottom: 0;

    white-space: nowrap;

    border-collapse:
        separate;

    border-spacing: 0;
}


/* =========================================================
   HEADER
========================================================= */

.matrix th {

    background:
        #fafbfd;

    color:
        #7d8796;

    font-size:
        10px;

    font-weight:
        750;

    text-transform:
        uppercase;

    letter-spacing:
        .04em;

    padding:
        11px 10px;

    border-bottom:
        1px solid #e3e8ef;

    border-right:
        1px solid #edf0f4;

    vertical-align:
        middle;

    text-align:
        center;
}


/* =========================================================
   KOLOM PEKERJA
========================================================= */

.matrix th:first-child,
.matrix td:first-child {

    position:
        sticky;

    left:
        0;

    background:
        #ffffff;

    z-index:
        2;

    text-align:
        left;

    min-width:
        240px;

    width:
        240px;

    box-shadow:
        2px 0 5px
        rgba(0,0,0,.02);
}


.matrix th:first-child {

    z-index:
        4;

    background:
        #fafbfd;
}


/* =========================================================
   KOLOM RATA-RATA
========================================================= */

.matrix th:nth-child(2),
.matrix td:nth-child(2) {

    min-width:
        100px;

    width:
        100px;

    position:
        sticky;

    left:
        240px;

    z-index:
        2;

    background:
        #ffffff;

    box-shadow:
        2px 0 5px
        rgba(0,0,0,.025);
}


.matrix th:nth-child(2) {

    z-index:
        4;

    background:
        #fafbfd;
}


.matrix tbody tr:hover td:nth-child(2) {

    background:
        #fafbfd;
}


/* =========================================================
   CELL
========================================================= */

.matrix td {

    padding:
        11px 10px;

    font-size:
        11px;

    border-bottom:
        1px solid #edf0f4;

    border-right:
        1px solid #edf0f4;

    vertical-align:
        middle;

    text-align:
        center;

    color:
        #384457;
}


.matrix tbody tr:hover td {

    background:
        #fafbfd;
}


.matrix tbody tr:hover td:first-child {

    background:
        #fafbfd;
}


/* =========================================================
   WORKER
========================================================= */

.worker-name {

    color:
        #172033;

    font-size:
        12px;

    font-weight:
        700;
}


.worker-meta {

    color:
        #8a94a4;

    font-size:
        9px;

    margin-top:
        3px;

    line-height:
        1.4;
}


.worker-dept {

    color:
        #123f7a;

    font-weight:
        600;
}


/* =========================================================
   AVERAGE
========================================================= */

.average-badge {

    display:
        inline-flex;

    align-items:
        center;

    justify-content:
        center;

    min-width:
        52px;

    height:
        32px;

    padding:
        0 8px;

    border-radius:
        8px;

    font-size:
        12px;

    font-weight:
        800;
}


.average-good {

    background:
        #e9f8f0;

    color:
        #198754;
}


.average-mid {

    background:
        #fff4d8;

    color:
        #b77900;
}


.average-bad {

    background:
        #ffecee;

    color:
        #dc3545;
}


.average-empty {

    background:
        #f1f3f5;

    color:
        #adb5bd;
}


/* =========================================================
   SCORE
========================================================= */

.score-badge {

    display:
        inline-flex;

    align-items:
        center;

    justify-content:
        center;

    width:
        31px;

    height:
        31px;

    border-radius:
        8px;

    font-weight:
        750;

    font-size:
        11px;
}


.score-5 {

    background:
        #e9f8f0;

    color:
        #198754;
}


.score-3 {

    background:
        #fff4d8;

    color:
        #b77900;
}


.score-1 {

    background:
        #ffecee;

    color:
        #dc3545;
}


.score-dash {

    background:
        #f1f3f5;

    color:
        #adb5bd;
}


/* =========================================================
   GAP
========================================================= */

.gap-text {

    font-size:
        8px;

    font-weight:
        700;

    margin-top:
        2px;
}


/* =========================================================
   LEGEND
========================================================= */

.legend-badge {

    display:
        inline-flex;

    align-items:
        center;

    gap:
        5px;

    border-radius:
        20px;

    padding:
        5px 9px;

    font-size:
        10px;

    font-weight:
        700;
}


.legend-green {

    background:
        #e9f8f0;

    color:
        #198754;
}


.legend-yellow {

    background:
        #fff4d8;

    color:
        #a86b00;
}


.legend-red {

    background:
        #ffecee;

    color:
        #dc3545;
}


/* =========================================================
   TARGET
========================================================= */

.target-info {

    color:
        #8a94a4;

    font-size:
        11px;
}


.target-info strong {

    color:
        #123f7a;
}


/* =========================================================
   EMPTY
========================================================= */

.matrix-empty {

    padding:
        45px 20px;

    text-align:
        center;

    color:
        #788396;
}


.matrix-empty-icon {

    width:
        55px;

    height:
        55px;

    border-radius:
        50%;

    display:
        flex;

    align-items:
        center;

    justify-content:
        center;

    margin:
        0 auto 12px;

    background:
        #f1f3f5;

    color:
        #9aa4b2;

    font-size:
        23px;
}


/* =========================================================
   MOBILE
========================================================= */

@media (max-width: 768px) {

    .dashboard-heading {

        padding:
            22px 20px;

        min-height:
            135px;

        border-radius:
            15px;
    }


    .dashboard-title {

        font-size:
            23px;
    }


    .dashboard-description {

        font-size:
            12px;
    }


    .cardx {

        padding:
            16px;
    }


    .matrix-wrap {

        margin-top:
            10px;

        border-radius:
            9px;

        width:
            100%;

        max-width:
            100%;

        overflow-x:
            auto;
    }


    .matrix {

        width:
            max-content;

        min-width:
            100%;

        table-layout:
            fixed;
    }


    /* PEKERJA */

    .matrix th:first-child,
    .matrix td:first-child {

        min-width:
            145px;

        width:
            145px;

        max-width:
            145px;

        padding:
            7px 8px;
    }


    /* RATA-RATA */

    .matrix th:nth-child(2),
    .matrix td:nth-child(2) {

        min-width:
            70px;

        width:
            70px;

        max-width:
            70px;

        left:
            145px;

        padding:
            6px 4px;
    }


    /* SKILL */

    .matrix th:nth-child(n+3),
    .matrix td:nth-child(n+3) {

        min-width:
            60px;

        width:
            60px;

        max-width:
            60px;

        padding:
            6px 4px;
    }


    .matrix th {

        font-size:
            8px;

        line-height:
            1.25;

        padding:
            7px 4px;

        white-space:
            normal;

        word-break:
            break-word;

        overflow-wrap:
            anywhere;
    }


    .matrix th div {

        font-size:
            7px !important;
    }


    .matrix td {

        font-size:
            9px;

        padding:
            6px 4px;
    }


    .worker-name {

        font-size:
            10px;

        line-height:
            1.25;

        white-space:
            normal;

        word-break:
            break-word;
    }


    .worker-meta {

        font-size:
            7.5px;
    }


    .worker-dept {

        font-size:
            7.5px;
    }


    .average-badge {

        min-width:
            38px;

        height:
            25px;

        border-radius:
            6px;

        font-size:
            9px;
    }


    .score-badge {

        width:
            25px;

        height:
            25px;

        min-width:
            25px;

        border-radius:
            6px;

        font-size:
            9px;
    }


    .gap-text {

        font-size:
            7px;
    }

}


/* =========================================================
   HP KECIL
========================================================= */

@media (max-width: 520px) {

    .dashboard-heading {

        padding:
            18px 16px;

        min-height:
            120px;

        margin-bottom:
            15px;
    }


    .dashboard-eyebrow {

        font-size:
            9px;
    }


    .dashboard-title {

        font-size:
            20px;
    }


    .dashboard-description {

        font-size:
            10px;

        line-height:
            1.45;
    }


    .cardx {

        padding:
            12px;

        border-radius:
            13px;
    }


    .matrix th:first-child,
    .matrix td:first-child {

        min-width:
            130px;

        width:
            130px;

        max-width:
            130px;

        padding:
            6px 7px;
    }


    .matrix th:nth-child(2),
    .matrix td:nth-child(2) {

        min-width:
            65px;

        width:
            65px;

        max-width:
            65px;

        left:
            130px;

        padding:
            5px 3px;
    }


    .matrix th:nth-child(n+3),
    .matrix td:nth-child(n+3) {

        min-width:
            55px;

        width:
            55px;

        max-width:
            55px;

        padding:
            5px 3px;
    }


    .matrix th {

        font-size:
            7px;

        padding:
            6px 3px;
    }


    .matrix th div {

        font-size:
            6.5px !important;
    }


    .matrix td {

        font-size:
            8px;

        padding:
            5px 3px;
    }


    .worker-name {

        font-size:
            9px;
    }


    .worker-meta {

        font-size:
            6.8px;
    }


    .worker-dept {

        font-size:
            6.8px;
    }


    .average-badge {

        min-width:
            34px;

        height:
            23px;

        font-size:
            8px;
    }


    .score-badge {

        width:
            23px;

        height:
            23px;

        min-width:
            23px;

        border-radius:
            5px;

        font-size:
            8px;
    }


    .gap-text {

        font-size:
            6.5px;
    }


    .legend-badge {

        padding:
            4px 6px;

        font-size:
            8px;
    }

}


/* =========================================================
   EXTRA SMALL
========================================================= */

@media (max-width: 380px) {

    .matrix th:first-child,
    .matrix td:first-child {

        min-width:
            120px;

        width:
            120px;

        max-width:
            120px;
    }


    .matrix th:nth-child(2),
    .matrix td:nth-child(2) {

        min-width:
            60px;

        width:
            60px;

        max-width:
            60px;

        left:
            120px;
    }


    .matrix th:nth-child(n+3),
    .matrix td:nth-child(n+3) {

        min-width:
            50px;

        width:
            50px;

        max-width:
            50px;
    }


    .matrix th {

        font-size:
            6.5px;
    }


    .matrix td {

        font-size:
            7.5px;
    }


    .worker-name {

        font-size:
            8.5px;
    }


    .worker-meta {

        font-size:
            6.3px;
    }


    .average-badge {

        min-width:
            32px;

        height:
            21px;

        font-size:
            7.5px;
    }


    .score-badge {

        width:
            21px;

        height:
            21px;

        min-width:
            21px;

        font-size:
            7.5px;
    }

}

</style>


<!-- =========================================================
     HERO
========================================================= -->

<div class="dashboard-heading">

    <div class="dashboard-eyebrow">

        <i class="bi bi-grid-3x3-gap-fill"></i>

        GARUDAFOOD • TEKNIK

    </div>


    <h1 class="dashboard-title">

        Skill Matrix

    </h1>


    <div class="dashboard-description">

        Matriks kompetensi dan pemetaan tingkat keahlian
        pekerja berdasarkan hasil assessment.

    </div>

</div>


<!-- =========================================================
     FILTER
========================================================= -->

<div class="cardx">

    <form
        method="get"
        class="row g-3 align-items-end"
    >


        <!-- ================================================
             TAHUN
        ================================================= -->

        <div class="col-lg-3 col-md-6">

            <label class="form-label">

                Tahun Penilaian

            </label>


            <select
                name="tahun"
                class="form-select"
            >

                <?php

                $years = [];


                $qYears = $conn->query("

                    SELECT DISTINCT tahun

                    FROM penilaian_skill

                    WHERE tahun IS NOT NULL

                    ORDER BY tahun ASC

                ");


                if ($qYears) {

                    while (
                        $yr =
                        $qYears->fetch_assoc()
                    ) {

                        $years[] =
                            (int)$yr['tahun'];
                    }
                }


                $currentYear =
                    (int)date('Y');


                if (
                    !in_array(
                        $currentYear,
                        $years
                    )
                ) {

                    $years[] =
                        $currentYear;
                }


                if (
                    !in_array(
                        $year,
                        $years
                    )
                ) {

                    $years[] =
                        $year;
                }


                sort($years);

                ?>


                <?php foreach (
                    $years
                    as $y
                ): ?>

                    <option
                        value="<?= $y ?>"
                        <?= $year == $y
                            ? 'selected'
                            : ''
                        ?>
                    >

                        <?= $y ?>

                    </option>

                <?php endforeach; ?>

            </select>

        </div>


        <!-- ================================================
             DEPARTEMEN
        ================================================= -->

        <div class="col-lg-3 col-md-6">

            <label class="form-label">

                Departemen

            </label>


            <select
                name="departemen"
                class="form-select"
            >

                <option value="">

                    -- Semua Departemen --

                </option>


                <?php if (
                    $departmentFilter
                ): ?>


                    <?php while (
                        $df =
                        $departmentFilter->fetch_assoc()
                    ): ?>


                        <option
                            value="<?= e(
                                $df['departemen']
                            ) ?>"
                            <?= $departemen ===
                                $df['departemen']
                                    ? 'selected'
                                    : ''
                            ?>
                        >

                            <?= e(
                                $df['departemen']
                            ) ?>

                        </option>


                    <?php endwhile; ?>


                <?php endif; ?>

            </select>

        </div>


        <!-- ================================================
             PEKERJA
        ================================================= -->

        <div class="col-lg-4 col-md-6">

            <label class="form-label">

                Pekerja

            </label>


            <select
                name="pekerja"
                class="form-select"
            >

                <option value="0">

                    -- Semua Pekerja --

                </option>


                <?php if (
                    $workerFilter
                ): ?>


                    <?php while (
                        $wf =
                        $workerFilter->fetch_assoc()
                    ): ?>


                        <option
                            value="<?= (int)$wf['id'] ?>"
                            <?= $pid ==
                                (int)$wf['id']
                                    ? 'selected'
                                    : ''
                            ?>
                        >

                            <?= e(
                                $wf['nama']
                            ) ?>


                            <?php if (
                                !empty(
                                    $wf['no_reg']
                                )
                            ): ?>

                                -
                                <?= e(
                                    $wf['no_reg']
                                ) ?>

                            <?php endif; ?>


                            <?php if (
                                !empty(
                                    $wf['departemen']
                                )
                            ): ?>

                                -
                                <?= e(
                                    $wf['departemen']
                                ) ?>

                            <?php endif; ?>

                        </option>


                    <?php endwhile; ?>


                <?php endif; ?>

            </select>

        </div>


        <!-- ================================================
             BUTTON
        ================================================= -->

        <div class="col-lg-2 col-md-6">

            <button
                type="submit"
                class="
                    btn
                    btn-primary
                    w-100
                "
            >

                <i
                    class="
                        bi
                        bi-filter
                        me-1
                    "
                ></i>

                Tampilkan

            </button>

        </div>


    </form>


    <!-- =====================================================
         FILTER AKTIF
    ====================================================== -->

    <div class="filter-active">

        <span
            class="
                filter-active-label
            "
        >

            Filter aktif:

        </span>


        <!-- TAHUN -->

        <span class="filter-badge">

            <i class="bi bi-calendar3"></i>

            Tahun <?= e($year) ?>

        </span>


        <!-- DEPARTEMEN -->

        <?php if (
            $departemen !== ''
        ): ?>

            <span class="filter-badge">

                <i class="bi bi-building"></i>

                <?= e($departemen) ?>

            </span>

        <?php else: ?>

            <span
                class="filter-badge"
                style="
                    background:#f1f3f5;
                    color:#6c757d;
                "
            >

                <i class="bi bi-building"></i>

                Semua Departemen

            </span>

        <?php endif; ?>


        <!-- PEKERJA -->

        <?php if (
            $pid > 0
        ): ?>

            <span
                class="filter-badge"
            >

                <i class="bi bi-person"></i>

                Pekerja dipilih

            </span>

        <?php else: ?>

            <span
                class="filter-badge"
                style="
                    background:#f1f3f5;
                    color:#6c757d;
                "
            >

                <i class="bi bi-people"></i>

                Semua Pekerja

            </span>

        <?php endif; ?>


        <!-- RESET -->

        <?php if (
            $pid > 0
            ||
            $departemen !== ''
            ||
            isset($_GET['tahun'])
        ): ?>

            <a
                href="matrix.php"
                class="
                    filter-badge
                    text-decoration-none
                "
                style="
                    background:#ffecee;
                    color:#dc3545;
                "
            >

                <i
                    class="
                        bi
                        bi-x-circle
                    "
                ></i>

                Reset

            </a>

        <?php endif; ?>

    </div>

</div>


<!-- =========================================================
     STATISTIK
========================================================= -->

<div class="row g-3 mb-3">


    <!-- PEKERJA -->

    <div class="col-6 col-lg-3">

        <div class="matrix-stat">

            <div class="matrix-stat-label">

                Pekerja Ditampilkan

            </div>


            <div class="matrix-stat-value">

                <?= number_format(
                    $totalPeople
                ) ?>

            </div>


            <div class="matrix-stat-sub">

                Sesuai filter

            </div>

        </div>

    </div>


    <!-- SKILL -->

    <div class="col-6 col-lg-3">

        <div class="matrix-stat">

            <div class="matrix-stat-label">

                Total Skill

            </div>


            <div class="matrix-stat-value">

                <?= number_format(
                    $totalSkills
                ) ?>

            </div>


            <div class="matrix-stat-sub">

                Skill aktif

            </div>

        </div>

    </div>


    <!-- ASSESSMENT -->

    <div class="col-6 col-lg-3">

        <div class="matrix-stat">

            <div class="matrix-stat-label">

                Assessment Terisi

            </div>


            <div class="matrix-stat-value">

                <?= number_format(
                    $totalAssessment
                ) ?>

            </div>


            <div class="matrix-stat-sub">

                Tahun <?= e($year) ?>

            </div>

        </div>

    </div>


    <!-- RATA-RATA -->

    <div class="col-6 col-lg-3">

        <div class="matrix-stat">

            <div class="matrix-stat-label">

                Rata-rata Nilai

            </div>


            <div class="matrix-stat-value">

                <?= $countScore > 0
                    ? number_format(
                        $overallAverage,
                        2,
                        ',',
                        '.'
                    )
                    : '-'
                ?>

            </div>


            <div class="matrix-stat-sub">

                Semua assessment terisi

            </div>

        </div>

    </div>


</div>


<!-- =========================================================
     MATRIX
========================================================= -->

<div class="cardx">


    <!-- HEADER -->

    <div
        class="
            d-flex
            justify-content-between
            align-items-center
            flex-wrap
            gap-3
            mb-3
        "
    >


        <div>

            <div class="card-title-custom">

                Matrix Kompetensi
                Tahun <?= e($year) ?>

            </div>


            <div class="section-note">

                Nilai setiap skill dibandingkan
                dengan target skill masing-masing.

            </div>

        </div>


        <!-- LEGEND -->

        <div
            class="
                d-flex
                gap-2
                flex-wrap
            "
        >

            <span
                class="
                    legend-badge
                    legend-green
                "
            >

                <i
                    class="
                        bi
                        bi-check-circle-fill
                    "
                ></i>

                Kompeten

            </span>


            <span
                class="
                    legend-badge
                    legend-yellow
                "
            >

                <i
                    class="
                        bi
                        bi-exclamation-circle-fill
                    "
                ></i>

                Perlu Peningkatan

            </span>


            <span
                class="
                    legend-badge
                    legend-red
                "
            >

                <i
                    class="
                        bi
                        bi-exclamation-octagon-fill
                    "
                ></i>

                Perlu Training

            </span>

        </div>

    </div>


    <!-- =====================================================
         INFO TARGET
    ====================================================== -->

    <div class="target-info mb-2">

        <i
            class="
                bi
                bi-info-circle
                me-1
            "
        ></i>

        Target default:

        <strong>4</strong>

        jika target skill belum diatur.

    </div>


    <!-- =====================================================
         MATRIX TABLE
    ====================================================== -->

    <div class="matrix-wrap">

        <table class="table matrix">


            <!-- =================================================
                 HEADER
            ================================================== -->

            <thead>

            <tr>


                <!-- PEKERJA -->

                <th>

                    Pekerja

                </th>


                <!-- RATA-RATA -->

                <th>

                    Rata-rata

                </th>


                <!-- SKILL -->

                <?php foreach (
                    $ss
                    as $s
                ): ?>


                    <?php

                    $skillTarget =
                        $targets[
                            (int)$s['id']
                        ]
                        ?? 4;

                    ?>


                    <th>

                        <?= e(
                            $s['nama_skill']
                        ) ?>


                        <div
                            style="
                                font-size:8px;
                                color:#a0a8b5;
                                margin-top:3px;
                                text-transform:none;
                                letter-spacing:0;
                            "
                        >

                            Target:

                            <?= number_format(
                                $skillTarget,
                                1
                            ) ?>

                        </div>

                    </th>


                <?php endforeach; ?>


            </tr>

            </thead>


            <!-- =================================================
                 BODY
            ================================================== -->

            <tbody>


            <?php if (
                $people
                &&
                $people->num_rows > 0
            ): ?>


                <?php while (
                    $p =
                    $people->fetch_assoc()
                ): ?>


                    <?php

                    /* =========================================
                       HITUNG RATA-RATA PEKERJA
                    ========================================== */

                    $workerId =
                        (int)$p['id'];

                    $workerTotal =
                        0;

                    $workerCount =
                        0;


                    if (
                        isset(
                            $score[$workerId]
                        )
                    ) {

                        foreach (
                            $score[$workerId]
                            as $workerScore
                        ) {

                            if (

                                isset(
                                    $workerScore['nilai']
                                )

                                &&

                                $workerScore['nilai'] !== null

                                &&

                                $workerScore['nilai'] !== ''

                            ) {

                                $workerTotal +=
                                    (float)$workerScore['nilai'];

                                $workerCount++;
                            }
                        }
                    }


                    $workerAverage =
                        $workerCount > 0
                            ? $workerTotal / $workerCount
                            : null;


                    /* =========================================
                       CLASS RATA-RATA
                    ========================================== */

                    if (
                        $workerAverage === null
                    ) {

                        $averageClass =
                            'average-empty';

                    } elseif (
                        $workerAverage >= 4
                    ) {

                        $averageClass =
                            'average-good';

                    } elseif (
                        $workerAverage >= 3
                    ) {

                        $averageClass =
                            'average-mid';

                    } else {

                        $averageClass =
                            'average-bad';
                    }

                    ?>


                    <tr>


                        <!-- =====================================
                             DATA PEKERJA
                        ====================================== -->

                        <td>


                            <div
                                class="worker-name"
                            >

                                <?= e(
                                    $p['nama']
                                ) ?>

                            </div>


                            <div
                                class="worker-meta"
                            >


                                <?php if (
                                    !empty(
                                        $p['no_reg']
                                    )
                                ): ?>

                                    No. Reg:

                                    <strong>

                                        <?= e(
                                            $p['no_reg']
                                        ) ?>

                                    </strong>

                                <?php endif; ?>


                                <?php if (
                                    !empty(
                                        $p['departemen']
                                    )
                                ): ?>


                                    <?php if (
                                        !empty(
                                            $p['no_reg']
                                        )
                                    ): ?>

                                        <span
                                            class="mx-1"
                                        >
                                            •
                                        </span>

                                    <?php endif; ?>


                                    <span
                                        class="worker-dept"
                                    >

                                        <?= e(
                                            $p['departemen']
                                        ) ?>

                                    </span>


                                <?php endif; ?>


                            </div>


                            <?php if (
                                !empty(
                                    $p['keterangan']
                                )
                            ): ?>

                                <div
                                    class="worker-meta"
                                >

                                    <i
                                        class="
                                            bi
                                            bi-info-circle
                                            me-1
                                        "
                                    ></i>

                                    <?= e(
                                        $p['keterangan']
                                    ) ?>

                                </div>

                            <?php endif; ?>


                        </td>


                        <!-- =====================================
                             RATA-RATA
                        ====================================== -->

                        <td>


                            <?php if (
                                $workerAverage !== null
                            ): ?>


                                <span
                                    class="
                                        average-badge
                                        <?= $averageClass ?>
                                    "
                                    title="
                                        Rata-rata
                                        dari
                                        <?= $workerCount ?>
                                        assessment
                                    "
                                >

                                    <?= number_format(
                                        $workerAverage,
                                        2,
                                        ',',
                                        '.'
                                    ) ?>

                                </span>


                                <div
                                    style="
                                        font-size:8px;
                                        color:#9aa3af;
                                        margin-top:3px;
                                    "
                                >

                                    <?= $workerCount ?>

                                    nilai

                                </div>


                            <?php else: ?>


                                <span
                                    class="
                                        average-badge
                                        average-empty
                                    "
                                >

                                    -

                                </span>


                                <div
                                    style="
                                        font-size:8px;
                                        color:#adb5bd;
                                        margin-top:3px;
                                    "
                                >

                                    Belum ada nilai

                                </div>


                            <?php endif; ?>


                        </td>


                        <!-- =====================================
                             SKILL
                        ====================================== -->

                        <?php foreach (
                            $ss
                            as $s
                        ): ?>


                            <?php

                            $skillId =
                                (int)$s['id'];


                            $x =
                                $score[
                                    $workerId
                                ][
                                    $skillId
                                ]
                                ?? null;


                            $v =
                                $x['nilai']
                                ?? null;


                            if (
                                $v === null
                                ||
                                $v === ''
                            ) {

                                $v = null;

                            } else {

                                $v =
                                    (float)$v;
                            }


                            $t =
                                $targets[
                                    $skillId
                                ]
                                ?? 4;


                            /* =================================
                               WARNA NILAI
                            ================================= */

                            if (
                                $v === null
                            ) {

                                $cls =
                                    'score-dash';

                            } elseif (
                                $v >= $t
                            ) {

                                $cls =
                                    'score-5';

                            } elseif (
                                $v >= 3
                            ) {

                                $cls =
                                    'score-3';

                            } else {

                                $cls =
                                    'score-1';
                            }

                            ?>


                            <td>


                                <div
                                    class="
                                        d-inline-flex
                                        flex-column
                                        align-items-center
                                    "
                                >


                                    <!-- NILAI -->

                                    <span
                                        class="
                                            score-badge
                                            <?= $cls ?>
                                        "
                                    >

                                        <?php if (
                                            $v === null
                                        ): ?>

                                            -

                                        <?php else: ?>

                                            <?= number_format(
                                                $v,
                                                1,
                                                ',',
                                                '.'
                                            ) ?>

                                        <?php endif; ?>

                                    </span>


                                    <!-- GAP -->

                                    <?php if (
                                        $v !== null
                                        &&
                                        $v < $t
                                    ): ?>


                                        <span
                                            class="
                                                text-danger
                                                gap-text
                                            "
                                        >

                                            Gap
                                            -<?= number_format(
                                                $t - $v,
                                                1,
                                                ',',
                                                '.'
                                            ) ?>

                                        </span>


                                    <?php elseif (
                                        $v !== null
                                        &&
                                        $v >= $t
                                    ): ?>


                                        <span
                                            style="
                                                font-size:8px;
                                                font-weight:700;
                                                color:#198754;
                                                margin-top:2px;
                                            "
                                        >

                                            OK

                                        </span>


                                    <?php endif; ?>


                                </div>


                            </td>


                        <?php endforeach; ?>


                    </tr>


                <?php endwhile; ?>


            <?php else: ?>


                <!-- =========================================
                     EMPTY
                ========================================== -->

                <tr>

                    <td
                        colspan="<?= count($ss) + 2 ?>"
                    >


                        <div
                            class="matrix-empty"
                        >


                            <div
                                class="
                                    matrix-empty-icon
                                "
                            >

                                <i
                                    class="
                                        bi
                                        bi-people
                                    "
                                ></i>

                            </div>


                            <strong>

                                Tidak ada data pekerja

                            </strong>


                            <div
                                class="mt-1"
                            >

                                Tidak ada pekerja aktif
                                yang sesuai dengan filter.

                            </div>


                        </div>


                    </td>

                </tr>


            <?php endif; ?>


            </tbody>


        </table>

    </div>


</div>


<script>

/* =========================================================
   FILTER PEKERJA BERDASARKAN DEPARTEMEN
========================================================= */

document.addEventListener(
    'DOMContentLoaded',
    function () {

        const departmentSelect =
            document.querySelector(
                'select[name="departemen"]'
            );

        const workerSelect =
            document.querySelector(
                'select[name="pekerja"]'
            );


        if (
            !departmentSelect ||
            !workerSelect
        ) {

            return;
        }


        /*
         * Simpan semua option pekerja
         */

        const originalWorkers =
            Array.from(
                workerSelect.options
            ).map(function (option) {

                return {

                    value:
                        option.value,

                    text:
                        option.text,

                    departemen:
                        option.dataset
                            .departemen || ''

                };

            });


        /*
         * Departemen dari option
         * pekerja belum tersedia karena
         * option lama tidak punya dataset.
         *
         * Jadi filtering hanya dilakukan
         * saat user memilih departemen.
         */

        departmentSelect.addEventListener(
            'change',
            function () {

                /*
                 * Form akan submit normal.
                 *
                 * Tidak mengubah pilihan pekerja
                 * secara otomatis agar tidak
                 * menghilangkan pilihan user.
                 */

            }
        );

    }
);

</script>


<?php

require __DIR__ . '/../partials/footer.php';

?>