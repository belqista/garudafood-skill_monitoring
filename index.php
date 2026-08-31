<?php
/* =========================================================
   GARUDAFOOD SKILL MONITORING
   DASHBOARD
========================================================= */

$page_title = 'Dashboard';

require __DIR__ . '/partials/header.php';


/* =========================================================
   TAHUN ASSESSMENT TERBARU
========================================================= */

$latest_year = null;

$qLatestYear = $conn->query("
    SELECT MAX(tahun) AS tahun
    FROM penilaian_skill
");

if ($qLatestYear) {

    $rowLatestYear = $qLatestYear->fetch_assoc();

    if (!empty($rowLatestYear['tahun'])) {
        $latest_year = (int)$rowLatestYear['tahun'];
    }
}


/* =========================================================
   TOTAL PEKERJA AKTIF
========================================================= */

$workers = 0;

$q = $conn->query("
    SELECT COUNT(*) AS n
    FROM pekerja
    WHERE status = 'Aktif'
");

if ($q) {
    $workers = (int)($q->fetch_assoc()['n'] ?? 0);
}


/* =========================================================
   TOTAL SKILL AKTIF
========================================================= */

$skills = 0;

$q = $conn->query("
    SELECT COUNT(*) AS n
    FROM skill
    WHERE status = 'Aktif'
");

if ($q) {
    $skills = (int)($q->fetch_assoc()['n'] ?? 0);
}


/* =========================================================
   TRAINING AKTIF
========================================================= */

$trainings = 0;

$q = $conn->query("
    SELECT COUNT(*) AS n
    FROM training
    WHERE status IN ('Terjadwal', 'Berlangsung')
");

if ($q) {
    $trainings = (int)($q->fetch_assoc()['n'] ?? 0);
}


/* =========================================================
   FILTER SEMUA PEKERJA
========================================================= */

$filter_nilai =
    trim(
        $_GET['filter_nilai'] ?? ''
    );

$filter_kompetensi =
    trim(
        $_GET['filter_kompetensi'] ?? ''
    );

$filter_skill =
    trim(
        $_GET['filter_skill'] ?? ''
    );

$filter_departemen =
    trim(
        $_GET['filter_departemen'] ?? ''
    );

$filter_keterangan =
    trim(
        $_GET['filter_keterangan'] ?? ''
    );


/* =========================================================
   VALIDASI FILTER NILAI
========================================================= */

$allowed_filter_nilai = [

    ''      => 'Semua Nilai',
    '1'     => '≤ 1',
    '1.5'   => '≤ 1,5',
    '2'     => '≤ 2',
    '2.5'   => '≤ 2,5',
    '3'     => '≤ 3',
    '4'     => '≤ 4',
    '5'     => '≤ 5'

];


if (
    !array_key_exists(
        $filter_nilai,
        $allowed_filter_nilai
    )
) {

    $filter_nilai = '';

}


/* =========================================================
   VALIDASI FILTER KOMPETENSI
========================================================= */

$allowed_filter_kompetensi = [

    '' =>
        'Semua Kompetensi',

    'level_1' =>
        'Level 1',

    'level_2' =>
        'Level 2',

    'level_3' =>
        'Level 3',

    'level_4' =>
        'Level 4',

    'level_5' =>
        'Level 5'

];


if (
    !array_key_exists(
        $filter_kompetensi,
        $allowed_filter_kompetensi
    )
) {

    $filter_kompetensi = '';

}


/* =========================================================
   DATA UNTUK DROPDOWN FILTER
========================================================= */


/* ---------------------------------------------------------
   SKILL
--------------------------------------------------------- */

$filter_skills = [];

$qFilterSkills = $conn->query("
    SELECT
        nama_skill
    FROM skill
    WHERE
        status = 'Aktif'
    ORDER BY
        nama_skill ASC
");

if ($qFilterSkills) {

    while (
        $fr =
            $qFilterSkills->fetch_assoc()
    ) {

        $filter_skills[] =
            $fr['nama_skill'];

    }

}


/* ---------------------------------------------------------
   DEPARTEMEN
--------------------------------------------------------- */

$filter_departments = [];

$qFilterDepartments = $conn->query("
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

if ($qFilterDepartments) {

    while (
        $fr =
            $qFilterDepartments->fetch_assoc()
    ) {

        $filter_departments[] =
            $fr['departemen'];

    }

}


/* ---------------------------------------------------------
   KETERANGAN PEKERJA
--------------------------------------------------------- */

$filter_keterangan_list = [];

$qFilterKeterangan = $conn->query("
    SELECT DISTINCT
        keterangan
    FROM pekerja
    WHERE
        status = 'Aktif'
        AND keterangan IS NOT NULL
        AND TRIM(keterangan) <> ''
    ORDER BY
        keterangan ASC
");

if ($qFilterKeterangan) {

    while (
        $fr =
            $qFilterKeterangan->fetch_assoc()
    ) {

        $filter_keterangan_list[] =
            $fr['keterangan'];

    }

}


/* =========================================================
   DATA SEMUA PEKERJA + RATA-RATA NILAI SKILL

   LOGIKA:
   - Semua pekerja aktif tetap ditampilkan
   - Assessment yang digunakan adalah tahun terbaru
   - Rata-rata dihitung PER PEKERJA
   - Rata-rata berasal dari seluruh skill aktif yang dinilai
   - Pekerja tanpa assessment tetap muncul
   - Filter diterapkan setelah rata-rata worker dihitung
========================================================= */

$training_workers = 0;

$training_people = [];

$filtered_worker_count = 0;


if (
    $latest_year !== null
) {

    $latestYearSafe =
        (int)$latest_year;


    /* =====================================================
       JUMLAH PEKERJA YANG RATA-RATANYA <= 2.5
       TETAP BERDASARKAN LOGIKA DASHBOARD
    ====================================================== */

    $q = $conn->query("
        SELECT
            COUNT(*) AS n

        FROM (

            SELECT
                p.id,

                AVG(
                    CASE
                        WHEN s.id IS NOT NULL
                             AND ps.nilai IS NOT NULL
                        THEN ps.nilai
                    END
                ) AS rata_skill

            FROM pekerja p

            LEFT JOIN penilaian_skill ps
                ON ps.id_pekerja = p.id
                AND ps.tahun = {$latestYearSafe}
                AND ps.nilai IS NOT NULL

            LEFT JOIN skill s
                ON s.id = ps.id_skill
                AND s.status = 'Aktif'

            WHERE
                p.status = 'Aktif'

            GROUP BY
                p.id

            HAVING
                AVG(
                    CASE
                        WHEN s.id IS NOT NULL
                             AND ps.nilai IS NOT NULL
                        THEN ps.nilai
                    END
                ) <= 2.5

        ) x
    ");


    if ($q) {

        $training_workers =
            (int)(
                $q->fetch_assoc()['n']
                ?? 0
            );

    }


    /* =====================================================
       KONDISI FILTER EKSTERNAL

       Filter skill / kompetensi menggunakan EXISTS.

       Tujuannya:
       pekerja harus memiliki skill yang sesuai filter,
       tetapi rata-rata tetap dihitung dari SEMUA skill pekerja.
    ====================================================== */

    $extraWhere = [];


    /* -----------------------------------------------------
       SKILL
    ----------------------------------------------------- */

    if (
        $filter_skill !== ''
    ) {

        $safeSkill =
            $conn->real_escape_string(
                $filter_skill
            );


        $extraWhere[] = "

            EXISTS (

                SELECT 1

                FROM penilaian_skill fps

                INNER JOIN skill fs
                    ON fs.id = fps.id_skill
                    AND fs.status = 'Aktif'

                WHERE
                    fps.id_pekerja = p.id
                    AND fps.tahun = {$latestYearSafe}
                    AND fps.nilai IS NOT NULL
                    AND fs.nama_skill = '{$safeSkill}'

            )

        ";

    }


    /* -----------------------------------------------------
       KOMPETENSI
    ----------------------------------------------------- */

    if (
        $filter_kompetensi !== ''
    ) {

        $safeLevel =
            preg_replace(
                '/[^a-zA-Z0-9_]/',
                '',
                $filter_kompetensi
            );


        if (
            in_array(
                $safeLevel,
                [
                    'level_1',
                    'level_2',
                    'level_3',
                    'level_4',
                    'level_5'
                ],
                true
            )
        ) {

            $extraWhere[] = "

                EXISTS (

                    SELECT 1

                    FROM skill fs

                    INNER JOIN penilaian_skill fps
                        ON fps.id_skill = fs.id

                    WHERE
                        fps.id_pekerja = p.id
                        AND fps.tahun = {$latestYearSafe}
                        AND fps.nilai IS NOT NULL
                        AND fs.status = 'Aktif'
                        AND fs.{$safeLevel} IS NOT NULL
                        AND TRIM(fs.{$safeLevel}) <> ''

                )

            ";

        }

    }


    /* -----------------------------------------------------
       DEPARTEMEN
    ----------------------------------------------------- */

    if (
        $filter_departemen !== ''
    ) {

        $safeDepartemen =
            $conn->real_escape_string(
                $filter_departemen
            );


        $extraWhere[] =
            "p.departemen = '{$safeDepartemen}'";

    }


    /* -----------------------------------------------------
       KETERANGAN
    ----------------------------------------------------- */

    if (
        $filter_keterangan !== ''
    ) {

        $safeKeterangan =
            $conn->real_escape_string(
                $filter_keterangan
            );


        $extraWhere[] =
            "p.keterangan = '{$safeKeterangan}'";

    }


    $extraWhereSql = '';


    if (
        !empty($extraWhere)
    ) {

        $extraWhereSql =
            ' AND ' .
            implode(
                ' AND ',
                $extraWhere
            );

    }


    /* =====================================================
       QUERY SEMUA PEKERJA

       CATATAN:
       Filter Nilai menggunakan HAVING agar nilai yang
       dibandingkan adalah RATA-RATA SELURUH SKILL.
    ====================================================== */

    $havingFilter = '';


    if (
        $filter_nilai !== ''
    ) {

        $nilaiFilterSafe =
            (float)$filter_nilai;


        $havingFilter = "

            HAVING

                AVG(
                    CASE
                        WHEN s.id IS NOT NULL
                             AND ps.nilai IS NOT NULL
                        THEN ps.nilai
                    END
                )
                <= {$nilaiFilterSafe}

        ";

    }


    $qTrainingPeople = $conn->query("
        SELECT

            p.id,

            p.no_reg,

            p.nama,

            p.departemen,

            p.keterangan,


            /* =========================================
               RATA-RATA SEMUA SKILL PEKERJA
            ========================================== */

            ROUND(

                AVG(

                    CASE

                        WHEN
                            s.id IS NOT NULL
                            AND ps.nilai IS NOT NULL

                        THEN ps.nilai

                    END

                ),

                2

            ) AS rata_skill,


            /* =========================================
               JUMLAH SKILL YANG DINILAI
            ========================================== */

            COUNT(

                DISTINCT

                CASE

                    WHEN
                        s.id IS NOT NULL
                        AND ps.nilai IS NOT NULL

                    THEN s.id

                END

            ) AS jumlah_skill,


            /* =========================================
               JUMLAH SKILL GAP
               Batas training tetap 2.5
            ========================================== */

            SUM(

                CASE

                    WHEN
                        s.id IS NOT NULL
                        AND ps.nilai IS NOT NULL
                        AND ps.nilai <= 2.5

                    THEN 1

                    ELSE 0

                END

            ) AS jumlah_gap,


            /* =========================================
               DAFTAR SKILL YANG <= 2.5
            ========================================== */

            GROUP_CONCAT(

                DISTINCT

                CASE

                    WHEN
                        s.id IS NOT NULL
                        AND ps.nilai IS NOT NULL
                        AND ps.nilai <= 2.5

                    THEN s.nama_skill

                END

                ORDER BY
                    ps.nilai ASC,
                    s.nama_skill ASC

                SEPARATOR ', '

            ) AS skill_gap


        FROM pekerja p


        /* =============================================
           SEMUA NILAI SKILL TAHUN TERBARU
        ============================================== */

        LEFT JOIN penilaian_skill ps

            ON ps.id_pekerja = p.id

            AND ps.tahun = {$latestYearSafe}

            AND ps.nilai IS NOT NULL


        /* =============================================
           HANYA SKILL AKTIF
        ============================================== */

        LEFT JOIN skill s

            ON s.id = ps.id_skill

            AND s.status = 'Aktif'


        WHERE

            p.status = 'Aktif'

            {$extraWhereSql}


        GROUP BY

            p.id,

            p.no_reg,

            p.nama,

            p.departemen,

            p.keterangan


        {$havingFilter}


        ORDER BY

            CASE

                WHEN AVG(

                    CASE

                        WHEN
                            s.id IS NOT NULL
                            AND ps.nilai IS NOT NULL

                        THEN ps.nilai

                    END

                ) IS NULL

                THEN 1

                ELSE 0

            END ASC,


            rata_skill ASC,


            p.nama ASC

    ");


    if (
        $qTrainingPeople
    ) {

        while (
            $trainingRow =
            $qTrainingPeople->fetch_assoc()
        ) {

            $training_people[] =
                $trainingRow;

        }

    }


    $filtered_worker_count =
        count(
            $training_people
        );

}


/* =========================================================
   RATA-RATA SEMUA PEKERJA PADA ASSESSMENT TERBARU

   Setiap pekerja memiliki bobot yang sama.
========================================================= */

$avg = 0;

if (
    $latest_year !== null
) {

    $latestYearSafe =
        (int)$latest_year;


    $q = $conn->query("
        SELECT
            ROUND(
                AVG(worker_avg),
                2
            ) AS a

        FROM (

            SELECT

                p.id,

                AVG(
                    ps.nilai
                ) AS worker_avg

            FROM pekerja p

            INNER JOIN penilaian_skill ps

                ON ps.id_pekerja = p.id

                AND ps.tahun = {$latestYearSafe}

                AND ps.nilai IS NOT NULL

            INNER JOIN skill s

                ON s.id = ps.id_skill

                AND s.status = 'Aktif'

            WHERE

                p.status = 'Aktif'

            GROUP BY

                p.id

        ) workers_avg
    ");


    if ($q) {

        $avg =
            (float)(
                $q->fetch_assoc()['a']
                ?? 0
            );

    }

}


/* =========================================================
   PEKERJA UNTUK PERKEMBANGAN SKILL
   2025 VS 2026
========================================================= */

$development_people = null;

$development_sql = "
    SELECT

        p.id,

        p.nama,


        ROUND(

            AVG(

                CASE

                    WHEN
                        ps.tahun = 2025

                    THEN ps.nilai

                END

            ),

            2

        ) AS y25,


        ROUND(

            AVG(

                CASE

                    WHEN
                        ps.tahun = 2026

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
";


$development_people =
    $conn->query(
        $development_sql
    );


/* =========================================================
   DATA GRAFIK TREN RATA-RATA SKILL
========================================================= */

$chart = $conn->query("
    SELECT

        tahun,

        ROUND(
            AVG(nilai),
            2
        ) AS avg_nilai

    FROM penilaian_skill

    GROUP BY
        tahun

    ORDER BY
        tahun ASC
");


$labels = [];

$vals = [];


if ($chart) {

    while (
        $r =
        $chart->fetch_assoc()
    ) {

        $labels[] =
            $r['tahun'];

        $vals[] =
            (float)$r['avg_nilai'];

    }

}


/* =========================================================
   HITUNG STATISTIK PERKEMBANGAN
========================================================= */

$total_meningkat = 0;

$total_menurun = 0;

$total_tetap = 0;

$total_belum = 0;

$development_data = [];


if (
    $development_people
) {

    while (
        $r =
        $development_people->fetch_assoc()
    ) {

        $has25 =
            $r['y25'] !== null &&
            $r['y25'] !== '';


        $has26 =
            $r['y26'] !== null &&
            $r['y26'] !== '';


        if (
            $has25 &&
            $has26
        ) {

            $delta =
                (float)$r['y26']
                -
                (float)$r['y25'];

        } else {

            $delta =
                null;

        }


        if (
            $delta === null
        ) {

            $statusClass =
                'status-mid';

            $statusText =
                'Belum Lengkap';

            $total_belum++;

        } elseif (
            $delta > 0
        ) {

            $statusClass =
                'status-good';

            $statusText =
                'Meningkat';

            $total_meningkat++;

        } elseif (
            $delta < 0
        ) {

            $statusClass =
                'status-bad';

            $statusText =
                'Menurun';

            $total_menurun++;

        } else {

            $statusClass =
                'status-mid';

            $statusText =
                'Tetap';

            $total_tetap++;

        }


        $r['delta'] =
            $delta;


        $r['statusClass'] =
            $statusClass;


        $r['statusText'] =
            $statusText;


        $development_data[] =
            $r;

    }

}


/* =========================================================
   BATASI DATA PERKEMBANGAN
========================================================= */

$development_preview =
    array_slice(
        $development_data,
        0,
        8
    );


/* =========================================================
   TOTAL PERKEMBANGAN
========================================================= */

$total_development =
    count(
        $development_data
    );


/* =========================================================
   JUMLAH FILTER AKTIF
========================================================= */

$filter_active_count = 0;


if (
    $filter_nilai !== ''
) {
    $filter_active_count++;
}


if (
    $filter_kompetensi !== ''
) {
    $filter_active_count++;
}


if (
    $filter_skill !== ''
) {
    $filter_active_count++;
}


if (
    $filter_departemen !== ''
) {
    $filter_active_count++;
}


if (
    $filter_keterangan !== ''
) {
    $filter_active_count++;
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

    --gf-red: #dc3545;

    --gf-red-bg: #ffecee;

    --gf-orange: #b77900;

    --gf-orange-bg: #fff4d8;

}


/* =========================================================
   PAGE
========================================================= */

body {

    background:
        var(--gf-bg) !important;

    color:
        var(--gf-text);

    font-family:
        "Poppins",
        "Segoe UI",
        Arial,
        sans-serif;
}


.main-content {

    background:
        var(--gf-bg) !important;
}


/* =========================================================
   DASHBOARD HEADER
========================================================= */

.dashboard-heading {

    position: relative;

    margin-bottom: 25px;

    padding: 25px 28px;

    border-radius: 18px;

    background:
        linear-gradient(
            135deg,
            #092f63 0%,
            #123f7a 58%,
            #164b8f 100%
        );

    border:
        1px solid
        rgba(255,255,255,.08);

    box-shadow:
        0 10px 30px
        rgba(9,47,99,.18);

    overflow: hidden;
}


.dashboard-heading::before {

    content: "";

    position: absolute;

    width: 230px;

    height: 230px;

    border-radius: 50%;

    right: -80px;

    top: -130px;

    background:
        rgba(255,255,255,.055);

    pointer-events: none;
}


.dashboard-heading::after {

    content: "";

    position: absolute;

    width: 170px;

    height: 170px;

    border-radius: 50%;

    right: 70px;

    bottom: -125px;

    background:
        rgba(255,255,255,.035);

    pointer-events: none;
}


.dashboard-eyebrow {

    position: relative;

    z-index: 2;

    display: inline-flex;

    align-items: center;

    gap: 7px;

    color: #dceaff;

    font-size: 12px;

    font-weight: 700;

    text-transform: uppercase;

    letter-spacing: .08em;

    margin-bottom: 8px;
}


.dashboard-eyebrow i {

    color: #ffffff;

    font-size: 14px;
}


.dashboard-title {

    position: relative;

    z-index: 2;

    margin: 0;

    font-size: 28px;

    line-height: 1.2;

    font-weight: 750;

    color: #ffffff;
}


.dashboard-description {

    position: relative;

    z-index: 2;

    margin-top: 8px;

    color:
        rgba(255,255,255,.78);

    font-size: 13px;

    max-width: 900px;
}


/* =========================================================
   STAT CARD
========================================================= */

.stat-card {

    position: relative;

    min-height: 132px;

    padding: 22px;

    background: #ffffff;

    border:
        1px solid
        var(--gf-border);

    border-radius: 17px;

    box-shadow:
        0 5px 20px
        rgba(20,43,76,.045);

    transition:
        transform .2s ease,
        box-shadow .2s ease,
        border-color .2s ease;
}


.stat-card:hover {

    transform:
        translateY(-3px);

    border-color:
        #d7e1ef;

    box-shadow:
        0 10px 28px
        rgba(20,43,76,.10);
}


.stat-card-link {

    display: block;

    color: inherit;

    text-decoration: none;
}


.stat-card-link:hover {

    color: inherit;

    text-decoration: none;
}


.stat-label {

    color: #7a8494;

    font-size: 11px;

    font-weight: 700;

    letter-spacing: .06em;

    margin-bottom: 7px;
}


.stat-value {

    color: #172033;

    font-size: 28px;

    font-weight: 750;

    line-height: 1.1;
}


.stat-value small {

    color: #8b95a4;

    font-weight: 500;
}


.stat-icon {

    width: 46px;

    height: 46px;

    display: flex;

    align-items: center;

    justify-content: center;

    border-radius: 13px;

    background:
        var(--gf-blue-light);

    color:
        var(--gf-blue);

    font-size: 19px;
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

    padding: 21px;

    box-shadow:
        0 5px 20px
        rgba(20,43,76,.045);
}


.card-title {

    color: #172033;

    font-size: 14px;

    font-weight: 750;
}


.section-note {

    color: #8a94a4;

    font-size: 12px;
}


/* =========================================================
   TRAINING ALERT
========================================================= */

.training-alert {

    background:
        linear-gradient(
            135deg,
            #fff4f4,
            #ffffff
        );

    border:
        1px solid
        #f5d6d9;

    border-radius: 17px;

    padding: 20px;

    box-shadow:
        0 5px 20px
        rgba(150,30,45,.04);
}


.training-alert-icon {

    width: 46px;

    height: 46px;

    border-radius: 13px;

    display: flex;

    align-items: center;

    justify-content: center;

    background:
        var(--gf-red-bg);

    color:
        var(--gf-red);

    font-size: 20px;
}


/* =========================================================
   LOW SCORE / ALL WORKERS
========================================================= */

.low-score-card {

    background: #ffffff;

    border:
        1px solid
        #e1e7ef;

    border-radius: 17px;

    padding: 21px;

    box-shadow:
        0 5px 20px
        rgba(20,43,76,.045);
}


.low-score-header {

    display: flex;

    align-items: flex-start;

    justify-content: space-between;

    gap: 15px;

    flex-wrap: wrap;
}


.low-score-title {

    color:
        #123f7a;

    font-size: 14px;

    font-weight: 750;
}


.low-score-note {

    color:
        #7d8796;

    font-size: 12px;

    margin-top: 3px;

    max-width: 900px;
}


.low-score-count {

    display: inline-flex;

    align-items: center;

    gap: 5px;

    padding:
        5px 9px;

    border-radius: 7px;

    background:
        #eaf2ff;

    color:
        #123f7a;

    font-size: 10px;

    font-weight: 750;

    white-space: nowrap;
}


/* =========================================================
   FILTER
========================================================= */

.dashboard-filter {

    margin-top: 16px;

    padding: 15px;

    border:
        1px solid
        #dce6f3;

    border-radius: 13px;

    background:
        #f8faff;
}


.dashboard-filter-title {

    color:
        #123f7a;

    font-size: 11px;

    font-weight: 750;

    text-transform: uppercase;

    letter-spacing: .05em;

    margin-bottom: 11px;
}


.dashboard-filter .form-label {

    color:
        #6f7b8c;

    font-size: 9px;

    font-weight: 750;

    text-transform: uppercase;

    letter-spacing: .04em;

    margin-bottom: 4px;
}


.dashboard-filter .form-select {

    min-height: 37px;

    border:
        1px solid
        #d9e1eb;

    border-radius: 8px;

    font-size: 11px;

    color:
        #344054;
}


.dashboard-filter .form-select:focus {

    border-color:
        #123f7a;

    box-shadow:
        0 0 0 .15rem
        rgba(18,63,122,.10);
}


.filter-status {

    margin-top: 9px;

    padding-top: 9px;

    border-top:
        1px solid
        #e3eaf3;

    color:
        #7d8796;

    font-size: 10px;
}


.filter-status strong {

    color:
        #123f7a;
}


/* =========================================================
   TABLE
========================================================= */

.low-score-table {

    width: 100%;

    min-width: 1050px;

    margin:
        15px 0 0;
}


.low-score-table thead th {

    background:
        #f7f9fc;

    color:
        #7d8796;

    font-size: 9px;

    font-weight: 750;

    text-transform: uppercase;

    letter-spacing: .04em;

    padding:
        10px 8px;

    border-bottom:
        1px solid
        #dfe5ed;

    white-space: nowrap;
}


.low-score-table tbody td {

    color:
        #384457;

    font-size: 11px;

    padding:
        10px 8px;

    border-bottom:
        1px solid
        #edf0f4;

    vertical-align: middle;
}


.low-score-table tbody tr:last-child td {

    border-bottom: 0;
}


.low-score-table tbody tr:hover {

    background:
        #fafbfd;
}


.low-score-worker {

    color:
        #172033;

    font-weight: 700;

    text-decoration: none;

    display: block;
}


.low-score-worker:hover {

    color:
        #123f7a;
}


.low-score-meta {

    color:
        #8a94a4;

    font-size: 9px;

    margin-top: 2px;

    line-height: 1.5;
}


.low-score-value {

    display: inline-flex;

    align-items: center;

    justify-content: center;

    min-width: 42px;

    padding:
        5px 7px;

    border-radius: 7px;

    background:
        #eaf2ff;

    color:
        #123f7a;

    font-size: 11px;

    font-weight: 800;
}


.low-score-value.is-training {

    background:
        #ffecee;

    color:
        #dc3545;
}


.low-score-gap {

    color:
        #dc3545;

    font-size: 10px;

    font-weight: 700;
}


.low-score-skills {

    color:
        #66758a;

    font-size: 10px;

    line-height: 1.5;

    max-width: 430px;
}


.low-score-empty {

    text-align: center;

    padding:
        28px 15px;

    color:
        #8a94a4;

    font-size: 12px;
}


.low-score-empty i {

    display: block;

    font-size: 26px;

    margin-bottom: 6px;
}


/* =========================================================
   STATUS
========================================================= */

.badge-status {

    display: inline-flex;

    align-items: center;

    justify-content: center;

    padding:
        4px 7px;

    border-radius: 6px;

    font-size: 9px;

    font-weight: 700;

    white-space: nowrap;
}


.status-good {

    background:
        var(--gf-green-bg);

    color:
        var(--gf-green);
}


.status-bad {

    background:
        var(--gf-red-bg);

    color:
        var(--gf-red);
}


.status-mid {

    background:
        #f1f3f5;

    color:
        #495057;
}


/* =========================================================
   CHART
========================================================= */

.chart-wrapper {

    position: relative;

    width: 100%;

    height: 280px;
}


/* =========================================================
   DEVELOPMENT TABLE
========================================================= */

.development-table {

    margin: 0;
}


.development-table thead th {

    border-top: 0;

    border-bottom:
        1px solid
        #e3e8ef;

    color:
        #7d8796;

    font-size: 9px;

    font-weight: 750;

    text-transform: uppercase;

    letter-spacing: .04em;

    padding:
        10px 7px;

    white-space: nowrap;
}


.development-table tbody td {

    border-bottom:
        1px solid
        #edf0f4;

    color:
        #384457;

    font-size: 11px;

    padding:
        11px 7px;

    vertical-align: middle;
}


.development-table tbody tr:last-child td {

    border-bottom: 0;
}


.development-table tbody tr:hover {

    background:
        #fafbfd;
}


.development-worker {

    color:
        #16223a;

    font-weight: 650;

    text-decoration: none;

    display: block;

    max-width: 130px;

    white-space: nowrap;

    overflow: hidden;

    text-overflow: ellipsis;
}


.development-worker:hover {

    color:
        var(--gf-blue);

    text-decoration: underline;
}


/* =========================================================
   CHANGE
========================================================= */

.change-up {

    color:
        #198754;

    font-weight: 800;
}


.change-down {

    color:
        #dc3545;

    font-weight: 800;
}


.change-same {

    color:
        #6c757d;

    font-weight: 800;
}


.change-empty {

    color:
        #9aa3af;

    font-weight: 600;
}


/* =========================================================
   DEVELOPMENT SUMMARY
========================================================= */

.development-summary {

    display: flex;

    gap: 7px;

    flex-wrap: wrap;

    margin-top: 13px;
}


.development-summary-item {

    display: inline-flex;

    align-items: center;

    gap: 5px;

    padding:
        5px 8px;

    border-radius: 6px;

    font-size: 9px;

    font-weight: 700;
}


.summary-up {

    background:
        #e9f8f0;

    color:
        #198754;
}


.summary-down {

    background:
        #ffecee;

    color:
        #dc3545;
}


.summary-same {

    background:
        #f1f3f5;

    color:
        #495057;
}


/* =========================================================
   BUTTON
========================================================= */

.btn-primary {

    background:
        var(--gf-blue-dark) !important;

    border-color:
        var(--gf-blue-dark) !important;

    font-weight: 600;

    border-radius: 9px;
}


.btn-primary:hover {

    background:
        var(--gf-blue-hover) !important;

    border-color:
        var(--gf-blue-hover) !important;
}


/* =========================================================
   FLOW
========================================================= */

.system-flow {

    display: flex;

    align-items: center;

    gap: 10px;

    margin-top: 20px;

    overflow-x: auto;

    padding-bottom: 3px;
}


.flow-item {

    min-width: 145px;

    padding:
        14px 15px;

    border:
        1px solid
        #e4e9f0;

    border-radius: 12px;

    background:
        #fafbfd;

    text-align: center;
}


.flow-number {

    width: 27px;

    height: 27px;

    margin:
        0 auto 8px;

    display: flex;

    align-items: center;

    justify-content: center;

    border-radius: 50%;

    background:
        var(--gf-blue);

    color:
        #ffffff;

    font-size: 11px;

    font-weight: 700;
}


.flow-title {

    font-size: 11px;

    font-weight: 700;

    color:
        #26344b;
}


.flow-arrow {

    color:
        #a5adba;

    font-size: 17px;

    flex-shrink: 0;
}


/* =========================================================
   RESPONSIVE
========================================================= */

@media (max-width: 992px) {

    .dashboard-title {

        font-size: 24px;
    }

    .chart-wrapper {

        height: 240px;
    }

}


@media (max-width: 768px) {

    .dashboard-heading {

        padding:
            20px;

        border-radius:
            15px;

        margin-bottom:
            20px;
    }


    .dashboard-title {

        font-size:
            22px;
    }


    .dashboard-description {

        font-size:
            12px;

        line-height:
            1.6;
    }


    .stat-card {

        min-height:
            118px;

        padding:
            17px;
    }


    .stat-value {

        font-size:
            23px;
    }


    .cardx {

        padding:
            17px;

        border-radius:
            14px;
    }


    .low-score-card {

        padding:
            15px;

        border-radius:
            14px;
    }


    .dashboard-filter {

        padding:
            12px;
    }


    .low-score-table {

        min-width:
            1050px;
    }


    .low-score-table thead th,
    .low-score-table tbody td {

        padding:
            8px 7px;

        font-size:
            10px;
    }


    .low-score-skills {

        max-width:
            300px;
    }

}

</style>


<!-- =======================================================
     BOOTSTRAP ICONS
======================================================= -->

<link
    href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
    rel="stylesheet"
>


<!-- =======================================================
     CHART.JS
======================================================= -->

<script
    src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js">
</script>


<!-- =======================================================
     DASHBOARD HEADER
======================================================= -->

<div class="dashboard-heading">

    <div class="dashboard-eyebrow">

        <i class="bi bi-speedometer2"></i>

        GARUDAFOOD • TEKNIK

    </div>


    <h1 class="dashboard-title">

        Dashboard

    </h1>


    <div class="dashboard-description">

        Pantau perkembangan kompetensi pekerja dan
        identifikasi kebutuhan peningkatan skill berdasarkan
        hasil assessment.

    </div>

</div>


<!-- =======================================================
     STATISTICS
======================================================= -->

<div class="row g-3 mb-4">


    <!-- TOTAL PEKERJA -->

    <div class="col-6 col-xl-3">

        <a
            href="<?= $base ?>pekerja/index.php"
            class="stat-card-link"
        >

            <div class="stat-card">

                <div
                    class="
                        d-flex
                        justify-content-between
                        align-items-start
                    "
                >

                    <div>

                        <div class="stat-label">
                            TOTAL PEKERJA
                        </div>

                        <div class="stat-value">
                            <?= number_format($workers) ?>
                        </div>

                    </div>


                    <div class="stat-icon">

                        <i class="bi bi-people-fill"></i>

                    </div>

                </div>

            </div>

        </a>

    </div>


    <!-- RATA-RATA SKILL -->

    <div class="col-6 col-xl-3">

        <a
            href="<?= $base ?>penilaian/matrix.php"
            class="stat-card-link"
        >

            <div class="stat-card">

                <div
                    class="
                        d-flex
                        justify-content-between
                        align-items-start
                    "
                >

                    <div>

                        <div class="stat-label">
                            RATA-RATA SKILL
                        </div>

                        <div class="stat-value">

                            <?= number_format(
                                $avg,
                                2,
                                ',',
                                '.'
                            ) ?>

                            <small class="fs-6">
                                / 5
                            </small>

                        </div>

                    </div>


                    <div class="stat-icon">

                        <i class="bi bi-speedometer2"></i>

                    </div>

                </div>

            </div>

        </a>

    </div>


    <!-- BUTUH TRAINING -->

    <div class="col-6 col-xl-3">

        <a
            href="<?= $base ?>training/kebutuhan.php"
            class="stat-card-link"
        >

            <div class="stat-card">

                <div
                    class="
                        d-flex
                        justify-content-between
                        align-items-start
                    "
                >

                    <div>

                        <div class="stat-label">
                            BUTUH TRAINING
                        </div>

                        <div class="stat-value">
                            <?= number_format(
                                $training_workers
                            ) ?>
                        </div>

                    </div>


                    <div
                        class="stat-icon"
                        style="
                            background:#ffecee;
                            color:#dc3545;
                        "
                    >

                        <i
                            class="
                                bi
                                bi-exclamation-diamond-fill
                            "
                        ></i>

                    </div>

                </div>

            </div>

        </a>

    </div>


    <!-- TRAINING AKTIF -->

    <div class="col-6 col-xl-3">

        <a
            href="<?= $base ?>training/index.php"
            class="stat-card-link"
        >

            <div class="stat-card">

                <div
                    class="
                        d-flex
                        justify-content-between
                        align-items-start
                    "
                >

                    <div>

                        <div class="stat-label">
                            TRAINING AKTIF
                        </div>

                        <div class="stat-value">
                            <?= number_format(
                                $trainings
                            ) ?>
                        </div>

                    </div>


                    <div
                        class="stat-icon"
                        style="
                            background:#e9f8f0;
                            color:#198754;
                        "
                    >

                        <i
                            class="
                                bi
                                bi-mortarboard-fill
                            "
                        ></i>

                    </div>

                </div>

            </div>

        </a>

    </div>

</div>


<!-- =======================================================
     TRAINING ALERT
======================================================= -->

<div class="training-alert mb-4">

    <div
        class="
            d-flex
            align-items-center
            gap-3
            flex-wrap
        "
    >

        <div class="training-alert-icon">

            <i
                class="
                    bi
                    bi-exclamation-triangle-fill
                "
            ></i>

        </div>


        <div class="flex-grow-1">

            <div
                style="
                    color:#842029;
                    font-size:14px;
                    font-weight:750;
                "
            >

                Pekerja yang membutuhkan training

            </div>


            <div
                style="
                    color:#8a6468;
                    font-size:12px;
                    margin-top:3px;
                "
            >

                Jumlah pekerja dengan rata-rata
                nilai skill <strong>≤ 2,5</strong>.

                <?php if (
                    $latest_year !== null
                ): ?>

                    Assessment tahun
                    <strong>
                        <?= $latest_year ?>
                    </strong>.

                <?php else: ?>

                    Belum ada data assessment.

                <?php endif; ?>

            </div>

        </div>


        <a
            href="<?= $base ?>training/kebutuhan.php"
            class="btn btn-danger btn-sm"
        >

            <i
                class="
                    bi
                    bi-arrow-right-circle
                    me-1
                "
            ></i>

            Lihat Kebutuhan Training

        </a>

    </div>

</div>


<!-- =======================================================
     SEMUA PEKERJA
======================================================= -->

<div class="low-score-card mb-4">


    <!-- HEADER -->

    <div class="low-score-header">


        <div>

            <div class="low-score-title">

                <i
                    class="
                        bi
                        bi-people-fill
                        me-1
                    "
                ></i>

                Semua Pekerja

            </div>


            <div class="low-score-note">

                Menampilkan seluruh pekerja aktif beserta
                rata-rata nilai skill pada assessment terbaru.
                Pekerja dengan rata-rata ≤ 2,5 menjadi
                prioritas training.

            </div>

        </div>


        <div
            class="
                d-flex
                align-items-center
                gap-2
                flex-wrap
            "
        >

            <?php if (
                $latest_year !== null
            ): ?>

                <span
                    class="badge-status"
                    style="
                        background:#eaf2ff;
                        color:#123f7a;
                    "
                >

                    <i
                        class="
                            bi
                            bi-calendar3
                            me-1
                        "
                    ></i>

                    <?= $latest_year ?>

                </span>

            <?php endif; ?>


            <span class="low-score-count">

                <i
                    class="
                        bi
                        bi-people-fill
                    "
                ></i>

                <?= number_format(
                    $filtered_worker_count
                ) ?>

                Pekerja

            </span>

        </div>

    </div>


    <!-- ===================================================
         FILTER
    ==================================================== -->

    <form
        method="GET"
        action="<?= e(
            $_SERVER['PHP_SELF']
        ) ?>"
        class="dashboard-filter"
    >


        <div class="dashboard-filter-title">

            <i
                class="
                    bi
                    bi-funnel-fill
                    me-1
                "
            ></i>

            Filter Pekerja

        </div>


        <div class="row g-2">


            <!-- NILAI -->

            <div class="col-6 col-md-2">

                <label
                    class="form-label"
                >

                    Nilai Rata-rata

                </label>


                <select
                    name="filter_nilai"
                    class="form-select"
                >

                    <?php foreach (
                        $allowed_filter_nilai
                        as $value =>
                        $label
                    ): ?>

                        <option
                            value="<?= e($value) ?>"
                            <?= $filter_nilai ===
                                $value
                                ? 'selected'
                                : ''
                            ?>
                        >

                            <?= e($label) ?>

                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <!-- KOMPETENSI -->

            <div class="col-6 col-md-2">

                <label
                    class="form-label"
                >

                    Kompetensi

                </label>


                <select
                    name="filter_kompetensi"
                    class="form-select"
                >

                    <?php foreach (
                        $allowed_filter_kompetensi
                        as $value =>
                        $label
                    ): ?>

                        <option
                            value="<?= e($value) ?>"
                            <?= $filter_kompetensi ===
                                $value
                                ? 'selected'
                                : ''
                            ?>
                        >

                            <?= e($label) ?>

                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <!-- SKILL -->

            <div class="col-12 col-md-2">

                <label
                    class="form-label"
                >

                    Skill

                </label>


                <select
                    name="filter_skill"
                    class="form-select"
                >

                    <option value="">

                        Semua Skill

                    </option>


                    <?php foreach (
                        $filter_skills
                        as $skillName
                    ): ?>

                        <option
                            value="<?= e(
                                $skillName
                            ) ?>"
                            <?= $filter_skill ===
                                $skillName
                                ? 'selected'
                                : ''
                            ?>
                        >

                            <?= e(
                                $skillName
                            ) ?>

                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <!-- DEPARTEMEN -->

            <div class="col-12 col-md-2">

                <label
                    class="form-label"
                >

                    Departemen

                </label>


                <select
                    name="filter_departemen"
                    class="form-select"
                >

                    <option value="">

                        Semua Departemen

                    </option>


                    <?php foreach (
                        $filter_departments
                        as $department
                    ): ?>

                        <option
                            value="<?= e(
                                $department
                            ) ?>"
                            <?= $filter_departemen ===
                                $department
                                ? 'selected'
                                : ''
                            ?>
                        >

                            <?= e(
                                $department
                            ) ?>

                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <!-- KETERANGAN -->

            <div class="col-12 col-md-2">

                <label
                    class="form-label"
                >

                    Keterangan Pekerja

                </label>


                <select
                    name="filter_keterangan"
                    class="form-select"
                >

                    <option value="">

                        Semua Keterangan

                    </option>


                    <?php foreach (
                        $filter_keterangan_list
                        as $keterangan
                    ): ?>

                        <option
                            value="<?= e(
                                $keterangan
                            ) ?>"
                            <?= $filter_keterangan ===
                                $keterangan
                                ? 'selected'
                                : ''
                            ?>
                        >

                            <?= e(
                                $keterangan
                            ) ?>

                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <!-- TOMBOL -->

            <div
                class="
                    col-12
                    col-md-2
                    d-flex
                    align-items-end
                "
            >

                <div
                    class="
                        d-flex
                        gap-2
                        w-100
                    "
                >

                    <button
                        type="submit"
                        class="
                            btn
                            btn-primary
                            btn-sm
                            flex-grow-1
                        "
                    >

                        <i
                            class="
                                bi
                                bi-funnel-fill
                                me-1
                            "
                        ></i>

                        Terapkan

                    </button>


                    <a
                        href="<?= e(
                            $_SERVER['PHP_SELF']
                        ) ?>"
                        class="
                            btn
                            btn-light
                            border
                            btn-sm
                        "
                        title="Reset Filter"
                    >

                        <i
                            class="
                                bi
                                bi-arrow-counterclockwise
                            "
                        ></i>

                    </a>

                </div>

            </div>


        </div>


        <!-- STATUS FILTER -->

        <div class="filter-status">

            <?php if (
                $filter_active_count > 0
            ): ?>

                <i
                    class="
                        bi
                        bi-check-circle-fill
                        me-1
                    "
                ></i>

                <strong>
                    <?= number_format(
                        $filtered_worker_count
                    ) ?>
                </strong>

                pekerja ditemukan dengan
                <strong>
                    <?= number_format(
                        $filter_active_count
                    ) ?>
                filter aktif.

            <?php else: ?>

                <i
                    class="
                        bi
                        bi-info-circle
                        me-1
                    "
                ></i>

                Semua pekerja aktif ditampilkan.

                <strong>
                    <?= number_format(
                        $filtered_worker_count
                    ) ?>
                </strong>
                pekerja.

            <?php endif; ?>

        </div>


    </form>


    <!-- ===================================================
         TABLE
    ==================================================== -->

    <?php if (
        !empty(
            $training_people
        )
    ): ?>


        <div
            class="
                table-responsive
            "
        >

            <table
                class="
                    table
                    low-score-table
                "
            >


                <thead>

                    <tr>

                        <th>
                            Pekerja
                        </th>

                        <th
                            class="
                                text-center
                            "
                        >

                            Rata-rata

                        </th>

                        <th
                            class="
                                text-center
                            "
                        >

                            Jumlah Skill

                        </th>

                        <th
                            class="
                                text-center
                            "
                        >

                            Skill ≤ 2,5

                        </th>

                        <th>
                            Skill yang Perlu Training
                        </th>

                        <th>
                            Keterangan
                        </th>

                        <th
                            class="
                                text-center
                            "
                        >

                            Status

                        </th>

                        <th
                            class="
                                text-center
                            "
                        >

                            Detail

                        </th>

                    </tr>

                </thead>


                <tbody>


                <?php foreach (
                    $training_people
                    as $tp
                ): ?>


                    <?php

                    $rataSkill =
                        $tp['rata_skill'] !== null
                        &&
                        $tp['rata_skill'] !== ''
                            ? (float)
                                $tp['rata_skill']
                            : null;


                    $jumlahGap =
                        (int)(
                            $tp['jumlah_gap']
                            ?? 0
                        );


                    $jumlahSkill =
                        (int)(
                            $tp['jumlah_skill']
                            ?? 0
                        );


                    $keterangan =
                        trim(
                            (string)(
                                $tp[
                                    'keterangan'
                                ]
                                ?? ''
                            )
                        );


                    $isTraining =
                        $rataSkill !== null
                        &&
                        $rataSkill <= 2.5;

                    ?>


                    <tr>


                        <!-- PEKERJA -->

                        <td>

                            <a
                                href="<?= $base ?>pekerja/detail.php?id=<?= (int)$tp['id'] ?>"
                                class="low-score-worker"
                                title="<?= e(
                                    $tp['nama']
                                ) ?>"
                            >

                                <?= e(
                                    $tp['nama']
                                ) ?>

                            </a>


                            <div
                                class="
                                    low-score-meta
                                "
                            >

                                <?php

                                $meta = [];


                                if (
                                    !empty(
                                        $tp['no_reg']
                                    )
                                ) {

                                    $meta[] =
                                        'No. Reg: ' .
                                        e(
                                            $tp[
                                                'no_reg'
                                            ]
                                        );

                                }


                                if (
                                    !empty(
                                        $tp[
                                            'departemen'
                                        ]
                                    )
                                ) {

                                    $meta[] =
                                        'Departemen: ' .
                                        e(
                                            $tp[
                                                'departemen'
                                            ]
                                        );

                                }


                                if (
                                    $keterangan !== ''
                                ) {

                                    $meta[] =
                                        e(
                                            $keterangan
                                        );

                                }


                                echo implode(
                                    ' <span class="mx-1">•</span> ',
                                    $meta
                                );

                                ?>

                            </div>

                        </td>


                        <!-- RATA-RATA -->

                        <td
                            class="
                                text-center
                            "
                        >

                            <?php if (
                                $rataSkill !== null
                            ): ?>

                                <span
                                    class="
                                        low-score-value
                                        <?= $isTraining
                                            ? 'is-training'
                                            : ''
                                        ?>
                                    "
                                >

                                    <?= number_format(
                                        $rataSkill,
                                        2,
                                        ',',
                                        '.'
                                    ) ?>

                                </span>

                            <?php else: ?>

                                <span
                                    class="
                                        text-muted
                                    "
                                >
                                    -
                                </span>

                            <?php endif; ?>

                        </td>


                        <!-- JUMLAH SKILL -->

                        <td
                            class="
                                text-center
                            "
                        >

                            <?= number_format(
                                $jumlahSkill
                            ) ?>

                        </td>


                        <!-- SKILL GAP -->

                        <td
                            class="
                                text-center
                            "
                        >

                            <?php if (
                                $jumlahGap > 0
                            ): ?>

                                <span
                                    class="
                                        low-score-gap
                                    "
                                >

                                    <?= number_format(
                                        $jumlahGap
                                    ) ?>

                                    skill

                                </span>

                            <?php else: ?>

                                <span
                                    style="
                                        color:#198754;
                                        font-size:10px;
                                        font-weight:700;
                                    "
                                >

                                    0 skill

                                </span>

                            <?php endif; ?>

                        </td>


                        <!-- SKILL -->

                        <td>

                            <div
                                class="
                                    low-score-skills
                                "
                                title="<?= e(
                                    $tp[
                                        'skill_gap'
                                    ]
                                    ?? ''
                                ) ?>"
                            >

                                <?= e(
                                    $tp[
                                        'skill_gap'
                                    ]
                                    ?: '-'
                                ) ?>

                            </div>

                        </td>


                        <!-- KETERANGAN -->

                        <td>

                            <?php if (
                                $keterangan !== ''
                            ): ?>

                                <span
                                    class="
                                        badge-status
                                    "
                                    style="
                                        background:#f1f3f5;
                                        color:#495057;
                                    "
                                >

                                    <?= e(
                                        $keterangan
                                    ) ?>

                                </span>

                            <?php else: ?>

                                <span
                                    class="
                                        text-muted
                                        small
                                    "
                                >

                                    -

                                </span>

                            <?php endif; ?>

                        </td>


                        <!-- STATUS -->

                        <td
                            class="
                                text-center
                            "
                        >

                            <?php if (
                                $rataSkill === null
                            ): ?>

                                <span
                                    class="
                                        badge-status
                                        status-mid
                                    "
                                >

                                    Belum Dinilai

                                </span>

                            <?php elseif (
                                $rataSkill <= 2.5
                            ): ?>

                                <span
                                    class="
                                        badge-status
                                        status-bad
                                    "
                                >

                                    <i
                                        class="
                                            bi
                                            bi-exclamation-circle
                                            me-1
                                        "
                                    ></i>

                                    Butuh Training

                                </span>

                            <?php else: ?>

                                <span
                                    class="
                                        badge-status
                                        status-good
                                    "
                                >

                                    <i
                                        class="
                                            bi
                                            bi-check-circle
                                            me-1
                                        "
                                    ></i>

                                    Memenuhi

                                </span>

                            <?php endif; ?>

                        </td>


                        <!-- DETAIL -->

                        <td
                            class="
                                text-center
                            "
                        >

                            <a
                                href="<?= $base ?>pekerja/detail.php?id=<?= (int)$tp['id'] ?>"
                                class="
                                    btn
                                    btn-sm
                                    btn-light
                                    border
                                "
                                title="Detail Pekerja"
                            >

                                <i
                                    class="
                                        bi
                                        bi-eye
                                    "
                                ></i>

                            </a>

                        </td>


                    </tr>


                <?php endforeach; ?>


                </tbody>

            </table>

        </div>


    <?php else: ?>


        <div
            class="
                low-score-empty
            "
        >

            <i
                class="
                    bi
                    bi-search
                "
            ></i>


            Tidak ada pekerja yang
            sesuai dengan filter.


        </div>


    <?php endif; ?>


</div>


<!-- =======================================================
     CHART + PERKEMBANGAN SKILL
======================================================= -->

<div class="row g-3 mb-4">


    <!-- ===================================================
         CHART PERKEMBANGAN
    ==================================================== -->

    <div class="col-xl-7">

        <div class="cardx h-100">

            <div
                class="
                    d-flex
                    justify-content-between
                    align-items-start
                    mb-3
                "
            >

                <div>

                    <div class="card-title">

                        Perkembangan Rata-rata Skill

                    </div>


                    <div class="section-note">

                        Perkembangan hasil assessment
                        berdasarkan tahun.

                    </div>

                </div>


                <a
                    href="<?= $base ?>penilaian/perkembangan.php"
                    class="badge-status"
                    style="
                        background:#eaf2ff;
                        color:#123f7a;
                        text-decoration:none;
                    "
                >

                    <i
                        class="
                            bi
                            bi-graph-up
                            me-1
                        "
                    ></i>

                    Detail

                </a>

            </div>


            <div class="chart-wrapper">

                <?php if (
                    !empty($labels)
                ): ?>

                    <canvas
                        id="skillChart"
                    ></canvas>

                <?php else: ?>

                    <div
                        class="
                            d-flex
                            align-items-center
                            justify-content-center
                            h-100
                            text-muted
                            small
                        "
                    >

                        <div class="text-center">

                            <i
                                class="
                                    bi
                                    bi-bar-chart-line
                                    fs-2
                                    d-block
                                    mb-2
                                "
                            ></i>

                            Belum ada data
                            assessment.

                        </div>

                    </div>

                <?php endif; ?>

            </div>

        </div>

    </div>


    <!-- ===================================================
         PERKEMBANGAN SKILL PEKERJA
    ==================================================== -->

    <div class="col-xl-5">

        <div class="cardx h-100">

            <div
                class="
                    d-flex
                    justify-content-between
                    align-items-start
                    gap-2
                    mb-2
                "
            >

                <div>

                    <div class="card-title">

                        Perkembangan Skill

                    </div>


                    <div class="section-note">

                        Perbandingan rata-rata skill
                        2025 → 2026.

                    </div>

                </div>


                <a
                    href="<?= $base ?>penilaian/perkembangan.php"
                    class="
                        btn
                        btn-light
                        btn-sm
                        border
                    "
                >

                    Semua

                    <i
                        class="
                            bi
                            bi-arrow-right
                            ms-1
                        "
                    ></i>

                </a>

            </div>


            <!-- SUMMARY -->

            <div
                class="
                    development-summary
                "
            >

                <span
                    class="
                        development-summary-item
                        summary-up
                    "
                >

                    <i
                        class="
                            bi
                            bi-arrow-up
                        "
                    ></i>

                    <?= number_format(
                        $total_meningkat
                    ) ?>

                    Meningkat

                </span>


                <span
                    class="
                        development-summary-item
                        summary-down
                    "
                >

                    <i
                        class="
                            bi
                            bi-arrow-down
                        "
                    ></i>

                    <?= number_format(
                        $total_menurun
                    ) ?>

                    Menurun

                </span>


                <span
                    class="
                        development-summary-item
                        summary-same
                    "
                >

                    <i
                        class="
                            bi
                            bi-dash
                        "
                    ></i>

                    <?= number_format(
                        $total_tetap
                    ) ?>

                    Tetap

                </span>

            </div>


            <div
                class="
                    table-responsive
                    mt-2
                "
            >

                <table
                    class="
                        table
                        development-table
                    "
                >

                    <thead>

                        <tr>

                            <th>
                                Pekerja
                            </th>

                            <th
                                class="
                                    text-center
                                "
                            >
                                2025
                            </th>

                            <th
                                class="
                                    text-center
                                "
                            >
                                2026
                            </th>

                            <th
                                class="
                                    text-center
                                "
                            >
                                Perubahan
                            </th>

                            <th
                                class="
                                    text-center
                                "
                            >
                                Status
                            </th>

                        </tr>

                    </thead>


                    <tbody>


                    <?php if (
                        !empty(
                            $development_preview
                        )
                    ): ?>


                        <?php foreach (
                            $development_preview
                            as $r
                        ): ?>


                            <?php

                            $has25 =
                                $r['y25'] !== null &&
                                $r['y25'] !== '';


                            $has26 =
                                $r['y26'] !== null &&
                                $r['y26'] !== '';


                            $delta =
                                $r['delta'];


                            if (
                                $delta !== null
                            ) {

                                if (
                                    $delta > 0
                                ) {

                                    $changeClass =
                                        'change-up';

                                } elseif (
                                    $delta < 0
                                ) {

                                    $changeClass =
                                        'change-down';

                                } else {

                                    $changeClass =
                                        'change-same';

                                }

                            } else {

                                $changeClass =
                                    'change-empty';

                            }

                            ?>


                            <tr>


                                <!-- PEKERJA -->

                                <td>

                                    <a
                                        href="<?= $base ?>pekerja/detail.php?id=<?= (int)$r['id'] ?>"
                                        class="
                                            development-worker
                                        "
                                        title="<?= e(
                                            $r['nama']
                                        ) ?>"
                                    >

                                        <?= e(
                                            $r['nama']
                                        ) ?>

                                    </a>

                                </td>


                                <!-- 2025 -->

                                <td
                                    class="
                                        text-center
                                    "
                                >

                                    <?php if (
                                        $has25
                                    ): ?>

                                        <strong>

                                            <?= number_format(
                                                (float)$r['y25'],
                                                2
                                            ) ?>

                                        </strong>

                                    <?php else: ?>

                                        <span
                                            class="
                                                text-muted
                                            "
                                        >

                                            -

                                        </span>

                                    <?php endif; ?>

                                </td>


                                <!-- 2026 -->

                                <td
                                    class="
                                        text-center
                                    "
                                >

                                    <?php if (
                                        $has26
                                    ): ?>

                                        <strong>

                                            <?= number_format(
                                                (float)$r['y26'],
                                                2
                                            ) ?>

                                        </strong>

                                    <?php else: ?>

                                        <span
                                            class="
                                                text-muted
                                            "
                                        >

                                            -

                                        </span>

                                    <?php endif; ?>

                                </td>


                                <!-- PERUBAHAN -->

                                <td
                                    class="
                                        text-center
                                        <?= $changeClass ?>
                                    "
                                >

                                    <?php if (
                                        $delta !== null
                                    ): ?>

                                        <?= $delta >= 0
                                            ? '+'
                                            : ''
                                        ?>

                                        <?= number_format(
                                            $delta,
                                            2
                                        ) ?>

                                    <?php else: ?>

                                        -

                                    <?php endif; ?>

                                </td>


                                <!-- STATUS -->

                                <td
                                    class="
                                        text-center
                                    "
                                >

                                    <span
                                        class="
                                            badge-status
                                            <?= e(
                                                $r[
                                                    'statusClass'
                                                ]
                                            ) ?>
                                        "
                                    >

                                        <?= e(
                                            $r[
                                                'statusText'
                                            ]
                                        ) ?>

                                    </span>

                                </td>


                            </tr>


                        <?php endforeach; ?>


                    <?php else: ?>


                        <tr>

                            <td
                                colspan="5"
                                class="
                                    text-center
                                "
                            >

                                <div
                                    class="
                                        development-empty
                                    "
                                >

                                    <i
                                        class="
                                            bi
                                            bi-bar-chart-line
                                        "
                                    ></i>

                                    Belum ada data
                                    perkembangan skill.

                                </div>

                            </td>

                        </tr>


                    <?php endif; ?>


                    </tbody>

                </table>

            </div>


            <?php if (
                $total_development > 8
            ): ?>

                <div
                    class="
                        text-center
                        border-top
                        pt-2
                        mt-1
                    "
                >

                    <a
                        href="<?= $base ?>penilaian/perkembangan.php"
                        class="
                            text-decoration-none
                            small
                            fw-semibold
                        "
                        style="
                            color:#123f7a;
                        "
                    >

                        Lihat
                        <?= number_format(
                            $total_development
                        ) ?>
                        pekerja lainnya

                        <i
                            class="
                                bi
                                bi-arrow-right
                                ms-1
                            "
                        ></i>

                    </a>

                </div>

            <?php endif; ?>

        </div>

    </div>

</div>


<!-- =======================================================
     ALUR SISTEM
======================================================= -->

<div class="row g-3 mt-1">

    <div class="col-12">

        <div class="cardx">

            <div
                class="
                    d-flex
                    justify-content-between
                    align-items-start
                    flex-wrap
                    gap-3
                "
            >

                <div>

                    <div
                        class="
                            card-title
                            mb-1
                        "
                    >

                        Alur Sistem
                        Skill Monitoring

                    </div>


                    <div class="section-note">

                        Proses pemantauan kompetensi pekerja
                        dari assessment sampai pelatihan.

                    </div>

                </div>


                <a
                    href="<?= $base ?>penilaian/matrix.php"
                    class="
                        btn
                        btn-primary
                        btn-sm
                    "
                >

                    Buka Skill Matrix

                    <i
                        class="
                            bi
                            bi-arrow-right
                            ms-1
                        "
                    ></i>

                </a>

            </div>


            <div class="system-flow">


                <div class="flow-item">

                    <div class="flow-number">
                        1
                    </div>

                    <div class="flow-title">
                        Nilai Terbaru
                    </div>

                </div>


                <div class="flow-arrow">

                    <i
                        class="
                            bi
                            bi-chevron-right
                        "
                    ></i>

                </div>


                <div class="flow-item">

                    <div class="flow-number">
                        2
                    </div>

                    <div class="flow-title">
                        Perkembangan Skill
                    </div>

                </div>


                <div class="flow-arrow">

                    <i
                        class="
                            bi
                            bi-chevron-right
                        "
                    ></i>

                </div>


                <div class="flow-item">

                    <div class="flow-number">
                        3
                    </div>

                    <div class="flow-title">
                        Cek Nilai ≤ 2,5
                    </div>

                </div>


                <div class="flow-arrow">

                    <i
                        class="
                            bi
                            bi-chevron-right
                        "
                    ></i>

                </div>


                <div class="flow-item">

                    <div class="flow-number">
                        4
                    </div>

                    <div class="flow-title">
                        Kebutuhan Training
                    </div>

                </div>


                <div class="flow-arrow">

                    <i
                        class="
                            bi
                            bi-chevron-right
                        "
                    ></i>

                </div>


                <div class="flow-item">

                    <div class="flow-number">
                        5
                    </div>

                    <div class="flow-title">
                        Assessment Ulang
                    </div>

                </div>

            </div>

        </div>

    </div>

</div>


<!-- =======================================================
     CHART.JS
======================================================= -->

<?php if (
    !empty($labels)
): ?>

<script>

document.addEventListener(
    'DOMContentLoaded',
    function () {

        const canvas =
            document.getElementById(
                'skillChart'
            );


        if (!canvas) {
            return;
        }


        const labels =
            <?= json_encode(
                $labels,
                JSON_UNESCAPED_UNICODE
            ) ?>;


        const values =
            <?= json_encode(
                $vals
            ) ?>;


        new Chart(
            canvas,
            {

                type: 'line',


                data: {

                    labels: labels,


                    datasets: [

                        {

                            label:
                                'Rata-rata Skill',

                            data:
                                values,

                            borderColor:
                                '#123f7a',

                            backgroundColor:
                                'rgba(18,63,122,.08)',

                            borderWidth:
                                3,

                            pointBackgroundColor:
                                '#123f7a',

                            pointBorderColor:
                                '#ffffff',

                            pointBorderWidth:
                                2,

                            pointRadius:
                                4,

                            pointHoverRadius:
                                6,

                            tension:
                                .35,

                            fill:
                                true

                        }

                    ]

                },


                options: {

                    responsive:
                        true,

                    maintainAspectRatio:
                        false,


                    plugins: {

                        legend: {

                            display:
                                false

                        },


                        tooltip: {

                            backgroundColor:
                                '#092f63',

                            titleColor:
                                '#ffffff',

                            bodyColor:
                                '#ffffff',

                            padding:
                                11,

                            displayColors:
                                false,

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

                    },


                    scales: {

                        x: {

                            grid: {

                                display:
                                    false

                            },

                            border: {

                                display:
                                    false

                            },

                            ticks: {

                                color:
                                    '#8a94a4',

                                font: {

                                    size:
                                        11

                                }

                            }

                        },


                        y: {

                            min:
                                0,

                            max:
                                5,

                            ticks: {

                                stepSize:
                                    1,

                                color:
                                    '#8a94a4',

                                font: {

                                    size:
                                        11

                                }

                            },

                            grid: {

                                color:
                                    '#edf0f4'

                            },

                            border: {

                                display:
                                    false

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

require __DIR__ . '/partials/footer.php';

?>