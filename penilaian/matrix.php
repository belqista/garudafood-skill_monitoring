<?php
/* =========================================================
   GARUDAFOOD SKILL MONITORING
   SKILL MATRIX

   STRUKTUR PEKERJA:
   - id
   - no_reg
   - nama
   - departemen
   - keterangan
   - status

   TIDAK MENGGUNAKAN:
   - id_jabatan
   - nik
   - tabel jabatan
========================================================= */

$page_title = 'Skill Matrix';

require __DIR__ . '/../partials/header.php';


/* =========================================================
   PARAMETER FILTER
========================================================= */

$year = isset($_GET['tahun'])
    ? (int) $_GET['tahun']
    : (int) date('Y');

$pid = isset($_GET['pekerja'])
    ? (int) $_GET['pekerja']
    : 0;


/* =========================================================
   VALIDASI TAHUN
========================================================= */

if ($year <= 0) {
    $year = (int) date('Y');
}


/* =========================================================
   FILTER PEKERJA
========================================================= */

$wherePeople = '';

if ($pid > 0) {

    $wherePeople = ' AND p.id = ' . $pid;

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

        $wherePeople

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

    while ($s = $skills->fetch_assoc()) {

        $ss[] = $s;

    }

}


/* =========================================================
   DATA TARGET SKILL

   Struktur:
   target_skill
   - id
   - id_skill
   - target

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

    while ($t = $qTarget->fetch_assoc()) {

        $targets[
            (int) $t['id_skill']
        ] = (float) $t['target'];

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

        ps.tahun = $year

        AND p.status = 'Aktif'

");


if ($q) {

    while ($r = $q->fetch_assoc()) {

        $idPekerja =
            (int) $r['id_pekerja'];

        $idSkill =
            (int) $r['id_skill'];


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

            }

        }

    }

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

}


/* =========================================================
   HERO BLUE CARD
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
        rgba(9, 47, 99, .14);

}


/* =========================================================
   DEKORASI LINGKARAN
========================================================= */

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


/* =========================================================
   ISI HERO
========================================================= */

.dashboard-heading > * {

    position: relative;

    z-index: 2;

}


/* =========================================================
   EYEBROW
========================================================= */

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


.dashboard-eyebrow i {

    font-size: 13px;

}


/* =========================================================
   TITLE
========================================================= */

.dashboard-title {

    margin: 0;

    font-size: 28px;

    line-height: 1.2;

    font-weight: 800;

    color: #ffffff;

}


/* =========================================================
   DESCRIPTION
========================================================= */

.dashboard-description {

    margin-top: 8px;

    color:
        rgba(255,255,255,.78);

    font-size: 13px;

    line-height: 1.6;

    max-width: 850px;

}


/* =========================================================
   CARD UMUMUM
========================================================= */

.cardx {

    background: #ffffff;

    border: 1px solid var(--gf-border);

    border-radius: 17px;

    padding: 24px;

    box-shadow:
        0 5px 20px
        rgba(
            20,
            43,
            76,
            .045
        );

    margin-bottom: 20px;

}


/* =========================================================
   FORM
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

    border-color: #dbe2ef;

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
        rgba(
            18,
            63,
            122,
            0.12
        );

}


/* =========================================================
   BUTTON PRIMARY
========================================================= */

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
   STATISTIK
========================================================= */

.matrix-stat {

    background: #ffffff;

    border: 1px solid var(--gf-border);

    border-radius: 13px;

    padding: 13px 15px;

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


/* =========================================================
   TITLE CARD
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

    border: 1px solid #e3e8ef;

    -webkit-overflow-scrolling: touch;

}


/* =========================================================
   MATRIX TABLE
========================================================= */

.matrix {

    margin-bottom: 0;

    white-space: nowrap;

    border-collapse: separate;

    border-spacing: 0;

}


/* =========================================================
   HEADER TABLE
========================================================= */

.matrix th {

    background: #fafbfd;

    color: #7d8796;

    font-size: 11px;

    font-weight: 750;

    text-transform: uppercase;

    letter-spacing: .05em;

    padding: 12px 14px;

    border-bottom:
        1px solid #e3e8ef;

    border-right:
        1px solid #edf0f4;

    vertical-align: middle;

    text-align: center;

}


/* =========================================================
   KOLOM PEKERJA
========================================================= */

.matrix th:first-child,
.matrix td:first-child {

    position: sticky;

    left: 0;

    background: #ffffff;

    z-index: 2;

    text-align: left;

    min-width: 250px;

    box-shadow:
        2px 0 5px
        rgba(
            0,
            0,
            0,
            0.02
        );

}


.matrix th:first-child {

    z-index: 3;

    background: #fafbfd;

}


/* =========================================================
   CELL
========================================================= */

.matrix td {

    padding: 12px 14px;

    font-size: 12px;

    border-bottom:
        1px solid #edf0f4;

    border-right:
        1px solid #edf0f4;

    vertical-align: middle;

    text-align: center;

    color: #384457;

}


.matrix tbody tr:hover td {

    background: #fafbfd;

}


.matrix tbody tr:hover td:first-child {

    background: #fafbfd;

}


/* =========================================================
   PEKERJA
========================================================= */

.worker-name {

    color: #172033;

    font-size: 13px;

    font-weight: 700;

}


.worker-meta {

    color: #8a94a4;

    font-size: 10px;

    margin-top: 3px;

}


.worker-dept {

    color: #123f7a;

    font-weight: 600;

}


/* =========================================================
   SCORE BADGE
========================================================= */

.score-badge {

    display: inline-flex;

    align-items: center;

    justify-content: center;

    width: 32px;

    height: 32px;

    border-radius: 8px;

    font-weight: 750;

    font-size: 12px;

}


.score-5 {

    background: #e9f8f0;

    color: #198754;

}


.score-3 {

    background: #fff4d8;

    color: #b77900;

}


.score-1 {

    background: #ffecee;

    color: #dc3545;

}


.score-dash {

    background: #f1f3f5;

    color: #adb5bd;

}


/* =========================================================
   GAP
========================================================= */

.gap-text {

    font-size: 9px;

    font-weight: 700;

    margin-top: 2px;

}


/* =========================================================
   LEGEND
========================================================= */

.legend-badge {

    display: inline-flex;

    align-items: center;

    gap: 5px;

    border-radius: 20px;

    padding: 5px 9px;

    font-size: 10px;

    font-weight: 700;

}


.legend-green {

    background: #e9f8f0;

    color: #198754;

}


.legend-yellow {

    background: #fff4d8;

    color: #a86b00;

}


.legend-red {

    background: #ffecee;

    color: #dc3545;

}


/* =========================================================
   TARGET
========================================================= */

.target-info {

    color: #8a94a4;

    font-size: 11px;

}


.target-info strong {

    color: #123f7a;

}


/* =========================================================
   EMPTY
========================================================= */

.matrix-empty {

    padding: 45px 20px;

    text-align: center;

    color: #788396;

}


.matrix-empty-icon {

    width: 55px;

    height: 55px;

    border-radius: 50%;

    display: flex;

    align-items: center;

    justify-content: center;

    margin:
        0 auto 12px;

    background: #f1f3f5;

    color: #9aa4b2;

    font-size: 23px;

}


/* =========================================================
   RESPONSIVE TABLET
========================================================= */

@media (max-width: 768px) {

    .dashboard-heading {

        padding: 22px 20px;

        min-height: 135px;

        border-radius: 15px;

    }


    .dashboard-title {

        font-size: 23px;

    }


    .dashboard-description {

        font-size: 12px;

        max-width: 100%;

    }


    .cardx {

        padding: 16px;

    }


    /* =====================================================
       MATRIX MOBILE
    ===================================================== */

    .matrix-wrap {

        margin-top: 10px;

        border-radius: 9px;

        width: 100%;

        max-width: 100%;

        overflow-x: auto;

        overflow-y: hidden;

    }


    .matrix {

        width: max-content;

        min-width: 100%;

        table-layout: fixed;

    }


    /* KOLOM PEKERJA */

    .matrix th:first-child,
    .matrix td:first-child {

        min-width: 145px;

        width: 145px;

        max-width: 145px;

        padding: 7px 8px;

    }


    /* KOLOM SKILL */

    .matrix th:not(:first-child),
    .matrix td:not(:first-child) {

        min-width: 60px;

        width: 60px;

        max-width: 60px;

        padding: 6px 4px;

    }


    /* HEADER SKILL */

    .matrix th {

        font-size: 8px;

        line-height: 1.25;

        letter-spacing: .02em;

        padding: 7px 4px;

        white-space: normal;

        word-break: break-word;

        overflow-wrap: anywhere;

    }


    /* TARGET DI HEADER */

    .matrix th div {

        font-size: 7px !important;

        line-height: 1.2;

        margin-top: 2px !important;

    }


    /* CELL */

    .matrix td {

        font-size: 9px;

        line-height: 1.25;

        padding: 6px 4px;

    }


    /* PEKERJA */

    .worker-name {

        font-size: 10px;

        line-height: 1.25;

        white-space: normal;

        word-break: break-word;

    }


    .worker-meta {

        font-size: 7.5px;

        line-height: 1.3;

        margin-top: 2px;

        white-space: normal;

        word-break: break-word;

    }


    .worker-dept {

        font-size: 7.5px;

    }


    /* SCORE */

    .score-badge {

        width: 25px;

        height: 25px;

        min-width: 25px;

        border-radius: 6px;

        font-size: 9px;

        font-weight: 800;

    }


    /* GAP */

    .gap-text {

        font-size: 7px;

        line-height: 1.1;

        margin-top: 1px;

    }


    /* OK */

    .matrix td div > span[style] {

        font-size: 7px !important;

        margin-top: 1px !important;

    }

}


/* =========================================================
   RESPONSIVE HP KECIL
========================================================= */

@media (max-width: 520px) {

    .dashboard-heading {

        padding: 18px 16px;

        min-height: 120px;

        margin-bottom: 15px;

    }


    .dashboard-eyebrow {

        font-size: 9px;

        gap: 5px;

    }


    .dashboard-title {

        font-size: 20px;

    }


    .dashboard-description {

        font-size: 10px;

        line-height: 1.45;

        margin-top: 6px;

    }


    .cardx {

        padding: 12px;

        border-radius: 13px;

    }


    /* =====================================================
       TABLE SUPER COMPACT
    ===================================================== */

    .matrix-wrap {

        margin-left: 0;

        margin-right: 0;

        width: 100%;

        border-radius: 8px;

    }


    .matrix {

        width: max-content;

        min-width: 100%;

        table-layout: fixed;

    }


    /* PEKERJA 130PX */

    .matrix th:first-child,
    .matrix td:first-child {

        min-width: 130px;

        width: 130px;

        max-width: 130px;

        padding: 6px 7px;

    }


    /* SKILL 55PX */

    .matrix th:not(:first-child),
    .matrix td:not(:first-child) {

        min-width: 55px;

        width: 55px;

        max-width: 55px;

        padding: 5px 3px;

    }


    /* HEADER */

    .matrix th {

        font-size: 7px;

        line-height: 1.2;

        padding: 6px 3px;

    }


    .matrix th div {

        font-size: 6.5px !important;

        line-height: 1.15;

    }


    /* CELL */

    .matrix td {

        font-size: 8px;

        padding: 5px 3px;

    }


    /* NAMA PEKERJA */

    .worker-name {

        font-size: 9px;

        line-height: 1.2;

    }


    .worker-meta {

        font-size: 6.8px;

        line-height: 1.25;

    }


    .worker-dept {

        font-size: 6.8px;

    }


    /* SCORE */

    .score-badge {

        width: 23px;

        height: 23px;

        min-width: 23px;

        border-radius: 5px;

        font-size: 8px;

    }


    .gap-text {

        font-size: 6.5px;

    }


    .matrix td div > span[style] {

        font-size: 6.5px !important;

    }


    /* =====================================================
       MATRIX HEADER AREA
    ===================================================== */

    .card-title-custom {

        font-size: 13px;

    }


    .section-note {

        font-size: 9px;

    }


    .target-info {

        font-size: 8px;

        line-height: 1.4;

    }


    .legend-badge {

        padding: 4px 6px;

        font-size: 8px;

    }


    .legend-badge i {

        font-size: 8px;

    }

}


/* =========================================================
   EXTRA SMALL PHONE
========================================================= */

@media (max-width: 380px) {

    .matrix th:first-child,
    .matrix td:first-child {

        min-width: 120px;

        width: 120px;

        max-width: 120px;

        padding: 5px 6px;

    }


    .matrix th:not(:first-child),
    .matrix td:not(:first-child) {

        min-width: 50px;

        width: 50px;

        max-width: 50px;

        padding: 4px 2px;

    }


    .matrix th {

        font-size: 6.5px;

    }


    .matrix td {

        font-size: 7.5px;

    }


    .worker-name {

        font-size: 8.5px;

    }


    .worker-meta {

        font-size: 6.3px;

    }


    .worker-dept {

        font-size: 6.3px;

    }


    .score-badge {

        width: 21px;

        height: 21px;

        min-width: 21px;

        font-size: 7.5px;

    }


    .gap-text {

        font-size: 6px;

    }

}

</style>


<!-- =========================================================
     HEADER HALAMAN
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
        pekerja berdasarkan tahun assessment.

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


        <!-- TAHUN -->

        <div class="col-md-3">

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


                foreach (
                    $years
                    as $y
                ):

                ?>

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


        <!-- PEKERJA -->

        <div class="col-md-5">

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


        <!-- BUTTON -->

        <div class="col-auto">

            <button
                type="submit"
                class="btn btn-primary"
            >

                <i
                    class="bi bi-filter me-1"
                ></i>

                Tampilkan Matrix

            </button>

        </div>


        <!-- RESET -->

        <?php if (
            $pid > 0
            ||
            isset($_GET['tahun'])
        ): ?>

            <div class="col-auto">

                <a
                    href="matrix.php"
                    class="btn btn-light border"
                    style="
                        border-radius:9px;
                        padding:10px 16px;
                        font-size:13px;
                    "
                >

                    <i
                        class="
                            bi
                            bi-arrow-counterclockwise
                            me-1
                        "
                    ></i>

                    Reset

                </a>

            </div>

        <?php endif; ?>


    </form>

</div>


<!-- =========================================================
     STATISTIK
========================================================= -->

<div class="row g-3 mb-3">


    <!-- PEKERJA -->

    <div class="col-md-4">

        <div class="matrix-stat">

            <div class="matrix-stat-label">

                Pekerja Aktif

            </div>


            <div class="matrix-stat-value">

                <?= number_format(
                    $totalPeople
                ) ?>

            </div>

        </div>

    </div>


    <!-- SKILL -->

    <div class="col-md-4">

        <div class="matrix-stat">

            <div class="matrix-stat-label">

                Total Skill

            </div>


            <div class="matrix-stat-value">

                <?= number_format(
                    $totalSkills
                ) ?>

            </div>

        </div>

    </div>


    <!-- ASSESSMENT -->

    <div class="col-md-4">

        <div class="matrix-stat">

            <div class="matrix-stat-label">

                Assessment Terisi
                Tahun <?= e($year) ?>

            </div>


            <div class="matrix-stat-value">

                <?= number_format(
                    $totalAssessment
                ) ?>

            </div>

        </div>

    </div>


</div>


<!-- =========================================================
     MATRIX
========================================================= -->

<div class="cardx">


    <!-- HEADER MATRIX -->

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

                Nilai dibandingkan dengan
                target masing-masing skill.

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


            <!-- KOMPETEN -->

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


            <!-- PENINGKATAN -->

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


            <!-- TRAINING -->

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
         TABLE
    ====================================================== -->

    <div class="matrix-wrap">

        <table class="table matrix">


            <!-- =================================================
                 HEADER TABLE
            ================================================== -->

            <thead>

            <tr>


                <!-- PEKERJA -->

                <th>

                    Pekerja

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
                                font-size:9px;
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


                    <tr>


                        <!-- =====================================
                             DATA PEKERJA
                        ====================================== -->

                        <td>


                            <!-- NAMA -->

                            <div
                                class="worker-name"
                            >

                                <?= e(
                                    $p['nama']
                                ) ?>

                            </div>


                            <!-- META -->

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


                            <!-- KETERANGAN -->

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
                             SKILL
                        ====================================== -->

                        <?php foreach (
                            $ss
                            as $s
                        ): ?>


                            <?php

                            $workerId =
                                (int)$p['id'];


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

                            if ($v === null) {

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
                                                1
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
                                                1
                                            ) ?>

                                        </span>


                                    <?php elseif (
                                        $v !== null
                                        &&
                                        $v >= $t
                                    ): ?>


                                        <span
                                            style="
                                                font-size:9px;
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
                     EMPTY DATA
                ========================================== -->

                <tr>

                    <td
                        colspan="<?= count($ss) + 1 ?>"
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


<?php

require __DIR__ . '/../partials/footer.php';

?>