<?php
/* =========================================================
   GARUDAFOOD SKILL MONITORING
   PERKEMBANGAN SKILL
========================================================= */

$page_title = 'Perkembangan Skill';

require __DIR__ . '/../partials/header.php';


/* =========================================================
   DATA PERKEMBANGAN PEKERJA

   URUTAN:
   1. Peningkatan terbesar
   2. Peningkatan berikutnya
   3. Nilai tetap
   4. Penurunan
   5. Belum lengkap

   Struktur database:
   - pekerja.id
   - pekerja.nama
   - pekerja.status
   - penilaian_skill.id_pekerja
   - penilaian_skill.tahun
   - penilaian_skill.nilai

   TIDAK menggunakan:
   - pekerja.id_jabatan
   - tabel jabatan
========================================================= */

$rows = $conn->query("
    SELECT
        p.id,
        p.nama,

        ROUND(
            AVG(
                CASE
                    WHEN ps.tahun = 2025
                    THEN ps.nilai
                END
            ),
            2
        ) AS y25,

        ROUND(
            AVG(
                CASE
                    WHEN ps.tahun = 2026
                    THEN ps.nilai
                END
            ),
            2
        ) AS y26

    FROM pekerja p

    LEFT JOIN penilaian_skill ps
        ON ps.id_pekerja = p.id

    WHERE
        p.status = 'Aktif'

    GROUP BY
        p.id,
        p.nama

    ORDER BY

        /* =================================================
           PEKERJA YANG MEMILIKI 2025 & 2026
           DIURUTKAN BERDASARKAN PENINGKATAN TERBESAR
        ================================================= */

        CASE
            WHEN
                AVG(
                    CASE
                        WHEN ps.tahun = 2025
                        THEN ps.nilai
                    END
                ) IS NOT NULL
                AND
                AVG(
                    CASE
                        WHEN ps.tahun = 2026
                        THEN ps.nilai
                    END
                ) IS NOT NULL
            THEN 0

            ELSE 1
        END ASC,

        (
            AVG(
                CASE
                    WHEN ps.tahun = 2026
                    THEN ps.nilai
                END
            )
            -
            AVG(
                CASE
                    WHEN ps.tahun = 2025
                    THEN ps.nilai
                END
            )
        ) DESC,

        p.nama ASC
");


/* =========================================================
   DATA GRAFIK
========================================================= */

$chart = $conn->query("
    SELECT
        tahun,
        ROUND(AVG(nilai), 2) AS avg_nilai

    FROM penilaian_skill

    GROUP BY tahun

    ORDER BY tahun ASC
");


$labels = [];
$vals   = [];


if ($chart) {

    while ($r = $chart->fetch_assoc()) {

        $labels[] = $r['tahun'];

        $vals[] = (float) $r['avg_nilai'];

    }

}


/* =========================================================
   STATISTIK
========================================================= */

$totalWorkers     = 0;
$totalIncrease     = 0;
$totalDecrease     = 0;
$totalSame         = 0;
$totalIncomplete   = 0;


$qTotalWorkers = $conn->query("
    SELECT
        COUNT(*) AS total

    FROM pekerja

    WHERE
        status = 'Aktif'
");


if ($qTotalWorkers) {

    $rw = $qTotalWorkers->fetch_assoc();

    $totalWorkers =
        (int) ($rw['total'] ?? 0);

}


/* =========================================================
   HITUNG STATUS PERKEMBANGAN
========================================================= */

if ($rows) {

    $rows->data_seek(0);

    while ($temp = $rows->fetch_assoc()) {

        $has25 =
            $temp['y25'] !== null &&
            $temp['y25'] !== '';

        $has26 =
            $temp['y26'] !== null &&
            $temp['y26'] !== '';


        if ($has25 && $has26) {

            $delta =
                (float) $temp['y26']
                -
                (float) $temp['y25'];


            if ($delta > 0) {

                $totalIncrease++;

            } elseif ($delta < 0) {

                $totalDecrease++;

            } else {

                $totalSame++;

            }

        } else {

            $totalIncomplete++;

        }

    }

    $rows->data_seek(0);

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
   PAGE HEADER
========================================================= */

.dashboard-heading {

    margin-bottom: 25px;

}


/* =========================================================
   BLUE HERO CARD
========================================================= */

.skill-hero {

    position: relative;

    overflow: hidden;

    background:
        linear-gradient(
            135deg,
            #123f7a 0%,
            #174b8d 55%,
            #0d376f 100%
        );

    border-radius: 17px;

    padding: 27px 30px;

    min-height: 145px;

    margin-bottom: 25px;

    box-shadow:
        0 8px 25px
        rgba(18, 63, 122, .16);

}


/* =========================================================
   HERO CONTENT
========================================================= */

.skill-hero-content {

    position: relative;

    z-index: 3;

    max-width: 850px;

}


.skill-hero-eyebrow {

    display: inline-flex;

    align-items: center;

    gap: 7px;

    color: rgba(255,255,255,.92);

    font-size: 11px;

    font-weight: 700;

    text-transform: uppercase;

    letter-spacing: .08em;

    margin-bottom: 8px;

}


.skill-hero-title {

    margin: 0;

    color: #ffffff;

    font-size: 29px;

    line-height: 1.2;

    font-weight: 800;

}


.skill-hero-description {

    margin-top: 8px;

    margin-bottom: 0;

    color: rgba(255,255,255,.82);

    font-size: 13px;

    line-height: 1.6;

}


/* =========================================================
   HERO DECORATION
========================================================= */

.skill-hero-circle {

    position: absolute;

    border-radius: 50%;

    pointer-events: none;

}


.skill-hero-circle-1 {

    width: 180px;

    height: 180px;

    right: 65px;

    top: -75px;

    background:
        rgba(255,255,255,.045);

}


.skill-hero-circle-2 {

    width: 115px;

    height: 115px;

    right: -22px;

    bottom: -50px;

    background:
        rgba(255,255,255,.055);

}


.skill-hero-circle-3 {

    width: 55px;

    height: 55px;

    right: 145px;

    bottom: -20px;

    background:
        rgba(255,255,255,.035);

}


/* =========================================================
   CARD
========================================================= */

.cardx {

    background: #ffffff;

    border: 1px solid var(--gf-border);

    border-radius: 17px;

    padding: 24px;

    box-shadow:
        0 5px 20px
        rgba(20, 43, 76, .045);

    margin-bottom: 20px;

}


/* =========================================================
   CARD TITLE
========================================================= */

.card-title-custom {

    color: #172033;

    font-size: 16px;

    font-weight: 750;

    margin-bottom: 4px;

}


.section-note {

    color: #8a94a4;

    font-size: 12px;

    line-height: 1.6;

}


/* =========================================================
   STATISTIK
========================================================= */

.progress-stat {

    background: #ffffff;

    border: 1px solid var(--gf-border);

    border-radius: 13px;

    padding: 15px 17px;

    height: 100%;

    transition:
        transform .18s ease,
        box-shadow .18s ease;

}


.progress-stat:hover {

    transform: translateY(-2px);

    box-shadow:
        0 7px 20px
        rgba(20, 43, 76, .07);

}


.progress-stat-label {

    color: #8a94a4;

    font-size: 10px;

    font-weight: 750;

    text-transform: uppercase;

    letter-spacing: .05em;

}


.progress-stat-value {

    color: #172033;

    font-size: 21px;

    font-weight: 800;

    margin-top: 3px;

}


.progress-stat-sub {

    color: #9aa4b2;

    font-size: 10px;

    margin-top: 2px;

}


/* =========================================================
   TABLE
========================================================= */

.table-wrap-custom {

    width: 100%;

    overflow-x: auto;

    border: 1px solid #e5e9ef;

    border-radius: 12px;

}


.table-custom {

    margin-bottom: 0;

    min-width: 700px;

}


.table-custom thead th {

    border-top: 0;

    border-bottom:
        1px solid #e3e8ef;

    color: #7d8796;

    font-size: 10px;

    font-weight: 750;

    text-transform: uppercase;

    letter-spacing: .05em;

    padding: 12px 14px;

    background: #fafbfd;

    white-space: nowrap;

}


.table-custom tbody td {

    border-bottom:
        1px solid #edf0f4;

    color: #384457;

    font-size: 12px;

    padding: 13px 14px;

    vertical-align: middle;

}


.table-custom tbody tr:last-child td {

    border-bottom: 0;

}


.table-custom tbody tr:hover td {

    background: #fafbfd;

}


/* =========================================================
   WORKER NAME
========================================================= */

.worker-name {

    color: #172033;

    font-size: 13px;

    font-weight: 700;

}


.worker-sub {

    color: #8a94a4;

    font-size: 10px;

    margin-top: 3px;

}


/* =========================================================
   YEAR VALUE
========================================================= */

.year-value {

    display: inline-flex;

    align-items: center;

    justify-content: center;

    min-width: 58px;

    padding: 6px 10px;

    border-radius: 8px;

    background: #f5f7fb;

    color: #172033;

    font-weight: 750;

    font-size: 12px;

}


.year-value-2025 {

    background: #f1f4f8;

    color: #596579;

}


.year-value-2026 {

    background: #eaf2ff;

    color: #123f7a;

}


/* =========================================================
   STATUS BADGE
========================================================= */

.badge-status {

    display: inline-flex;

    align-items: center;

    justify-content: center;

    gap: 5px;

    padding: 5px 10px;

    border-radius: 7px;

    font-size: 10px;

    font-weight: 750;

    white-space: nowrap;

}


.status-good {

    background: #e9f8f0;

    color: #198754;

}


.status-bad {

    background: #ffecee;

    color: #dc3545;

}


.status-mid {

    background: #f1f3f5;

    color: #596579;

}


/* =========================================================
   DELTA
========================================================= */

.delta-up {

    color: #198754;

}


.delta-down {

    color: #dc3545;

}


.delta-same {

    color: #6c757d;

}


.delta-value {

    font-weight: 800;

    font-size: 12px;

}


/* =========================================================
   CHART
========================================================= */

.chart-container {

    position: relative;

    height: 300px;

    width: 100%;

}


/* =========================================================
   EMPTY
========================================================= */

.empty-state {

    padding: 45px 20px;

    text-align: center;

    color: #788396;

}


.empty-state-icon {

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

    font-size: 22px;

}


/* =========================================================
   RESPONSIVE
========================================================= */

@media (max-width: 768px) {

    .skill-hero {

        padding: 22px 20px;

        min-height: 145px;

        border-radius: 15px;

    }


    .skill-hero-title {

        font-size: 23px;

    }


    .skill-hero-description {

        font-size: 12px;

        max-width: 90%;

    }


    .skill-hero-circle-1 {

        right: -20px;

        top: -65px;

    }


    .dashboard-heading {

        margin-bottom: 18px;

    }


    .cardx {

        padding: 17px;

    }


    .chart-container {

        height: 250px;

    }

}

</style>


<!-- =======================================================
     BLUE HERO HEADER
======================================================= -->

<div class="skill-hero">

    <div
        class="skill-hero-circle skill-hero-circle-1"
    ></div>

    <div
        class="skill-hero-circle skill-hero-circle-2"
    ></div>

    <div
        class="skill-hero-circle skill-hero-circle-3"
    ></div>


    <div class="skill-hero-content">

        <div class="skill-hero-eyebrow">

            <i class="bi bi-graph-up-arrow"></i>

            GARUDAFOOD • TEKNIK

        </div>


        <h1 class="skill-hero-title">

            Perkembangan Skill

        </h1>


        <p class="skill-hero-description">

            Analisis komparatif kenaikan atau penurunan
            rata-rata kompetensi pekerja dari tahun ke tahun.

        </p>

    </div>

</div>


<!-- =======================================================
     RINGKASAN
======================================================= -->

<div class="row g-3 mb-3">


    <!-- TOTAL PEKERJA -->

    <div class="col-md-3 col-6">

        <div class="progress-stat">

            <div class="progress-stat-label">

                Pekerja Aktif

            </div>


            <div class="progress-stat-value">

                <?= number_format(
                    $totalWorkers
                ) ?>

            </div>


            <div class="progress-stat-sub">

                Data pekerja aktif

            </div>

        </div>

    </div>


    <!-- MENINGKAT -->

    <div class="col-md-3 col-6">

        <div class="progress-stat">

            <div class="progress-stat-label">

                Meningkat

            </div>


            <div
                class="
                    progress-stat-value
                    text-success
                "
            >

                <?= number_format(
                    $totalIncrease
                ) ?>

            </div>


            <div class="progress-stat-sub">

                Dibandingkan 2025

            </div>

        </div>

    </div>


    <!-- MENURUN -->

    <div class="col-md-3 col-6">

        <div class="progress-stat">

            <div class="progress-stat-label">

                Menurun

            </div>


            <div
                class="
                    progress-stat-value
                    text-danger
                "
            >

                <?= number_format(
                    $totalDecrease
                ) ?>

            </div>


            <div class="progress-stat-sub">

                Dibandingkan 2025

            </div>

        </div>

    </div>


    <!-- TETAP -->

    <div class="col-md-3 col-6">

        <div class="progress-stat">

            <div class="progress-stat-label">

                Tetap

            </div>


            <div class="progress-stat-value">

                <?= number_format(
                    $totalSame
                ) ?>

            </div>


            <div class="progress-stat-sub">

                Nilai tidak berubah

            </div>

        </div>

    </div>

</div>


<!-- =======================================================
     INFORMASI
======================================================= -->

<div class="cardx">

    <div class="card-title-custom">

        <i
            class="
                bi
                bi-bar-chart-line-fill
                me-1
                text-primary
            "
        ></i>

        Perbandingan 2025 → 2026

    </div>


    <div class="section-note">

        Perubahan dihitung dari rata-rata seluruh
        kompetensi yang memiliki penilaian pada
        masing-masing tahun.

    </div>

</div>


<!-- =======================================================
     TABEL PERBANDINGAN PEKERJA
======================================================= -->

<div class="cardx">


    <div
        class="
            d-flex
            justify-content-between
            align-items-center
            flex-wrap
            gap-2
            mb-3
        "
    >

        <div>

            <div class="card-title-custom mb-1">

                Perkembangan Kompetensi Pekerja

            </div>


            <div class="section-note">

                Pekerja dengan peningkatan terbesar
                ditampilkan paling atas.

            </div>

        </div>


        <!-- LEGEND -->

        <div
            class="
                d-flex
                align-items-center
                gap-2
                flex-wrap
            "
        >

            <span
                class="
                    badge-status
                    status-good
                "
            >

                <i class="bi bi-arrow-up"></i>

                Meningkat

            </span>


            <span
                class="
                    badge-status
                    status-bad
                "
            >

                <i class="bi bi-arrow-down"></i>

                Menurun

            </span>


            <span
                class="
                    badge-status
                    status-mid
                "
            >

                <i class="bi bi-dash"></i>

                Tetap

            </span>

        </div>

    </div>


    <div class="table-wrap-custom">

        <table
            class="
                table
                table-custom
            "
        >

            <thead>

                <tr>

                    <th>

                        Pekerja

                    </th>


                    <th class="text-center">

                        2025

                    </th>


                    <th class="text-center">

                        2026

                    </th>


                    <th class="text-center">

                        Perubahan

                    </th>


                    <th class="text-center">

                        Status

                    </th>

                </tr>

            </thead>


            <tbody>


                <?php if (
                    $rows &&
                    $rows->num_rows > 0
                ): ?>


                    <?php while (
                        $r =
                        $rows->fetch_assoc()
                    ): ?>


                        <?php

                        /* =============================
                           CEK DATA 2025
                        ============================= */

                        $has25 =
                            $r['y25'] !== null &&
                            $r['y25'] !== '';


                        /* =============================
                           CEK DATA 2026
                        ============================= */

                        $has26 =
                            $r['y26'] !== null &&
                            $r['y26'] !== '';


                        /* =============================
                           HITUNG PERUBAHAN
                        ============================= */

                        if (
                            $has25 &&
                            $has26
                        ) {

                            $delta =
                                (float)$r['y26']
                                -
                                (float)$r['y25'];

                        } else {

                            $delta = null;

                        }


                        /* =============================
                           STATUS
                        ============================= */

                        if (
                            $delta === null
                        ) {

                            $statusClass =
                                'status-mid';

                            $statusText =
                                'Belum Lengkap';

                            $statusIcon =
                                'bi-dash-circle';

                        } elseif (
                            $delta > 0
                        ) {

                            $statusClass =
                                'status-good';

                            $statusText =
                                'Meningkat';

                            $statusIcon =
                                'bi-arrow-up-circle-fill';

                        } elseif (
                            $delta < 0
                        ) {

                            $statusClass =
                                'status-bad';

                            $statusText =
                                'Menurun';

                            $statusIcon =
                                'bi-arrow-down-circle-fill';

                        } else {

                            $statusClass =
                                'status-mid';

                            $statusText =
                                'Tetap';

                            $statusIcon =
                                'bi-dash-circle-fill';

                        }


                        /* =============================
                           DELTA CLASS
                        ============================= */

                        if (
                            $delta === null
                        ) {

                            $deltaClass =
                                'delta-same';

                        } elseif (
                            $delta > 0
                        ) {

                            $deltaClass =
                                'delta-up';

                        } elseif (
                            $delta < 0
                        ) {

                            $deltaClass =
                                'delta-down';

                        } else {

                            $deltaClass =
                                'delta-same';

                        }

                        ?>


                        <tr>


                            <!-- =========================
                                 PEKERJA
                            ========================== -->

                            <td>

                                <div
                                    class="worker-name"
                                >

                                    <?= e(
                                        $r['nama']
                                    ) ?>

                                </div>


                                <div
                                    class="worker-sub"
                                >

                                    <i
                                        class="
                                            bi
                                            bi-person
                                            me-1
                                        "
                                    ></i>

                                    Perbandingan
                                    assessment

                                </div>

                            </td>


                            <!-- =========================
                                 2025
                            ========================== -->

                            <td class="text-center">

                                <?php if (
                                    $has25
                                ): ?>

                                    <span
                                        class="
                                            year-value
                                            year-value-2025
                                        "
                                    >

                                        <?= number_format(
                                            (float)$r['y25'],
                                            2
                                        ) ?>

                                    </span>

                                <?php else: ?>

                                    <span
                                        class="
                                            text-muted
                                            fw-semibold
                                        "
                                    >

                                        -

                                    </span>

                                <?php endif; ?>

                            </td>


                            <!-- =========================
                                 2026
                            ========================== -->

                            <td class="text-center">

                                <?php if (
                                    $has26
                                ): ?>

                                    <span
                                        class="
                                            year-value
                                            year-value-2026
                                        "
                                    >

                                        <?= number_format(
                                            (float)$r['y26'],
                                            2
                                        ) ?>

                                    </span>

                                <?php else: ?>

                                    <span
                                        class="
                                            text-muted
                                            fw-semibold
                                        "
                                    >

                                        -

                                    </span>

                                <?php endif; ?>

                            </td>


                            <!-- =========================
                                 PERUBAHAN
                            ========================== -->

                            <td
                                class="
                                    text-center
                                    delta-value
                                    <?= $deltaClass ?>
                                "
                            >

                                <?php if (
                                    $delta !== null
                                ): ?>

                                    <?php if (
                                        $delta > 0
                                    ): ?>

                                        <i
                                            class="
                                                bi
                                                bi-arrow-up
                                                me-1
                                            "
                                        ></i>

                                    <?php elseif (
                                        $delta < 0
                                    ): ?>

                                        <i
                                            class="
                                                bi
                                                bi-arrow-down
                                                me-1
                                            "
                                        ></i>

                                    <?php else: ?>

                                        <i
                                            class="
                                                bi
                                                bi-dash
                                                me-1
                                            "
                                        ></i>

                                    <?php endif; ?>


                                    <?= $delta >= 0
                                        ? '+'
                                        : ''
                                    ?><?= number_format(
                                        $delta,
                                        2
                                    ) ?>

                                <?php else: ?>

                                    -

                                <?php endif; ?>

                            </td>


                            <!-- =========================
                                 STATUS
                            ========================== -->

                            <td class="text-center">

                                <span
                                    class="
                                        badge-status
                                        <?= $statusClass ?>
                                    "
                                >

                                    <i
                                        class="
                                            bi
                                            <?= $statusIcon ?>
                                        "
                                    ></i>

                                    <?= e(
                                        $statusText
                                    ) ?>

                                </span>

                            </td>


                        </tr>


                    <?php endwhile; ?>


                <?php else: ?>


                    <tr>

                        <td
                            colspan="5"
                        >

                            <div
                                class="empty-state"
                            >

                                <div
                                    class="
                                        empty-state-icon
                                    "
                                >

                                    <i
                                        class="
                                            bi
                                            bi-graph-up
                                        "
                                    ></i>

                                </div>


                                <strong>

                                    Belum ada data perkembangan

                                </strong>


                                <div class="mt-1">

                                    Belum tersedia data
                                    assessment pekerja.

                                </div>

                            </div>

                        </td>

                    </tr>


                <?php endif; ?>


            </tbody>

        </table>

    </div>

</div>


<!-- =======================================================
     GRAFIK TREN
======================================================= -->

<div class="cardx">


    <div
        class="
            d-flex
            justify-content-between
            align-items-center
            flex-wrap
            gap-2
            mb-3
        "
    >

        <div>

            <div class="card-title-custom">

                <i
                    class="
                        bi
                        bi-graph-up-arrow
                        me-1
                        text-primary
                    "
                ></i>

                Tren Rata-rata Skill Keseluruhan

            </div>


            <div class="section-note">

                Perkembangan rata-rata nilai skill
                berdasarkan tahun assessment.

            </div>

        </div>


        <div
            class="
                badge-status
                status-mid
            "
        >

            Skala 0 – 5

        </div>

    </div>


    <?php if (
        !empty($labels)
    ): ?>


        <div
            class="chart-container"
        >

            <canvas
                id="trend"
            ></canvas>

        </div>


    <?php else: ?>


        <div
            class="empty-state"
        >

            <div class="empty-state-icon">

                <i
                    class="
                        bi
                        bi-bar-chart-line
                    "
                ></i>

            </div>


            <strong>

                Belum ada data grafik

            </strong>


            <div class="mt-1">

                Belum ada data penilaian
                untuk ditampilkan pada grafik.

            </div>

        </div>


    <?php endif; ?>


</div>


<!-- =======================================================
     CHART.JS
======================================================= -->

<?php if (
    !empty($labels)
): ?>

<script>

document.addEventListener(
    "DOMContentLoaded",
    function () {

        const canvas =
            document.getElementById(
                'trend'
            );


        if (!canvas) {

            return;

        }


        /* =================================================
           CEK CHART.JS
        ================================================= */

        if (
            typeof Chart === 'undefined'
        ) {

            console.warn(
                'Chart.js belum tersedia.'
            );

            return;

        }


        const ctx =
            canvas.getContext('2d');


        /* =================================================
           DATA PHP
        ================================================= */

        const labels =
            <?= json_encode(
                $labels,
                JSON_UNESCAPED_UNICODE
            ) ?>;


        const values =
            <?= json_encode(
                $vals
            ) ?>;


        /* =================================================
           GRADIENT
        ================================================= */

        const gradient =
            ctx.createLinearGradient(
                0,
                0,
                0,
                300
            );


        gradient.addColorStop(
            0,
            'rgba(18, 63, 122, 0.18)'
        );


        gradient.addColorStop(
            1,
            'rgba(18, 63, 122, 0.01)'
        );


        /* =================================================
           CHART
        ================================================= */

        new Chart(
            ctx,
            {

                type: 'line',


                data: {

                    labels: labels,


                    datasets: [

                        {

                            label:
                                'Rata-rata Nilai Skill',


                            data:
                                values,


                            borderColor:
                                '#123f7a',


                            backgroundColor:
                                gradient,


                            borderWidth:
                                3,


                            tension:
                                0.35,


                            fill:
                                true,


                            pointBackgroundColor:
                                '#123f7a',


                            pointBorderColor:
                                '#ffffff',


                            pointBorderWidth:
                                2,


                            pointRadius:
                                5,


                            pointHoverRadius:
                                7

                        }

                    ]

                },


                options: {

                    responsive:
                        true,


                    maintainAspectRatio:
                        false,


                    interaction: {

                        intersect:
                            false,

                        mode:
                            'index'

                    },


                    scales: {

                        y: {

                            min:
                                0,

                            max:
                                5,


                            ticks: {

                                stepSize:
                                    1,

                                font: {

                                    size:
                                        11

                                },

                                color:
                                    '#788396'

                            },


                            grid: {

                                color:
                                    '#edf0f4'

                            }

                        },


                        x: {

                            grid: {

                                display:
                                    false

                            },


                            ticks: {

                                font: {

                                    size:
                                        11

                                },

                                color:
                                    '#788396'

                            }

                        }

                    },


                    plugins: {

                        legend: {

                            display:
                                false

                        },


                        tooltip: {

                            backgroundColor:
                                '#12213a',

                            titleColor:
                                '#ffffff',

                            bodyColor:
                                '#ffffff',

                            padding:
                                12,

                            cornerRadius:
                                9,


                            callbacks: {

                                label:
                                    function (
                                        context
                                    ) {

                                        return (
                                            ' Rata-rata: ' +
                                            Number(
                                                context.raw
                                            ).toFixed(2)
                                        );

                                    }

                            }

                        }

                    }

                }

            }
        );

    }

);

</script>

<?php endif; ?>


<?php

require __DIR__ . '/../partials/footer.php';

?>