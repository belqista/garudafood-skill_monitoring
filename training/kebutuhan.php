<?php
/* =========================================================
   GARUDAFOOD SKILL MONITORING
   KEBUTUHAN TRAINING

   FITUR:
   - Nilai terendah
   - Rata-rata nilai SEMUA skill
   - Filter tahun
   - Filter nilai
   - Filter skill / kompetensi
   - Filter departemen
   - Filter keterangan
   - Filter pekerja
   - Download Excel
   - Download PDF

   LOGIKA:
   - Pekerja masuk jika memiliki minimal 1 skill <= 2.5
   - Rata-rata dihitung dari SEMUA skill pada tahun terpilih
   - Filter nilai / skill hanya menentukan hasil yang ditampilkan
   - Filter tidak mengubah perhitungan rata-rata keseluruhan

   STRUKTUR PEKERJA:
   - id
   - no_reg
   - nama
   - departemen
   - keterangan
   - status

   TIDAK MENGGUNAKAN:
   - id_jabatan
   - tabel jabatan
   - NIK
========================================================= */

$page_title = 'Kebutuhan Training';

require __DIR__ . '/../partials/header.php';


/* =========================================================
   KONFIGURASI
========================================================= */

$batas_nilai = 2.5;


/* =========================================================
   HELPER ESCAPE
========================================================= */

if (!function_exists('e')) {

    function e($value)
    {
        return htmlspecialchars(
            (string) $value,
            ENT_QUOTES,
            'UTF-8'
        );
    }
}


/* =========================================================
   PARAMETER FILTER
========================================================= */

/* Tahun */
$filter_tahun = isset($_GET['tahun'])
    ? (int) $_GET['tahun']
    : 0;


/* Nilai */
$filter_nilai = isset($_GET['nilai'])
    ? trim((string) $_GET['nilai'])
    : '';


/* Skill */
$filter_skill = isset($_GET['skill'])
    ? (int) $_GET['skill']
    : 0;


/* Departemen */
$filter_departemen = isset($_GET['departemen'])
    ? trim((string) $_GET['departemen'])
    : '';


/* Keterangan */
$filter_keterangan = isset($_GET['keterangan'])
    ? trim((string) $_GET['keterangan'])
    : '';


/* Pekerja */
$filter_pekerja = isset($_GET['pekerja'])
    ? (int) $_GET['pekerja']
    : 0;


/* =========================================================
   TAHUN ASSESSMENT TERBARU
========================================================= */

$latestYear = 0;

$qYear = $conn->query("
    SELECT MAX(tahun) AS tahun
    FROM penilaian_skill
    WHERE tahun IS NOT NULL
      AND tahun > 0
");

if ($qYear) {

    $yearData = $qYear->fetch_assoc();

    $latestYear = (int) (
        $yearData['tahun'] ?? 0
    );
}


/* =========================================================
   JIKA TAHUN TIDAK DIPILIH
   GUNAKAN TAHUN TERBARU
========================================================= */

if ($filter_tahun <= 0) {

    $filter_tahun = $latestYear;
}


/* =========================================================
   DATA TAHUN
========================================================= */

$years = [];

$qYears = $conn->query("
    SELECT DISTINCT tahun
    FROM penilaian_skill
    WHERE tahun IS NOT NULL
      AND tahun > 0
    ORDER BY tahun DESC
");

if ($qYears) {

    while ($yr = $qYears->fetch_assoc()) {

        $years[] = (int) $yr['tahun'];
    }
}


/* =========================================================
   DATA SKILL UNTUK FILTER
========================================================= */

$skillFilterData = [];

$qSkillFilter = $conn->query("
    SELECT
        id,
        nama_skill
    FROM skill
    WHERE status = 'Aktif'
    ORDER BY nama_skill ASC
");

if ($qSkillFilter) {

    while ($sf = $qSkillFilter->fetch_assoc()) {

        $skillFilterData[] = $sf;
    }
}


/* =========================================================
   DATA DEPARTEMEN UNTUK FILTER
========================================================= */

$departments = [];

$qDept = $conn->query("
    SELECT DISTINCT departemen
    FROM pekerja
    WHERE status = 'Aktif'
      AND departemen IS NOT NULL
      AND TRIM(departemen) <> ''
    ORDER BY departemen ASC
");

if ($qDept) {

    while ($d = $qDept->fetch_assoc()) {

        $departments[] = $d['departemen'];
    }
}


/* =========================================================
   DATA PEKERJA UNTUK FILTER
========================================================= */

$workerFilter = [];

$qWorkerFilter = $conn->query("
    SELECT
        id,
        no_reg,
        nama,
        departemen
    FROM pekerja
    WHERE status = 'Aktif'
    ORDER BY nama ASC
");

if ($qWorkerFilter) {

    while ($wf = $qWorkerFilter->fetch_assoc()) {

        $workerFilter[] = $wf;
    }
}


/* =========================================================
   DATA TRAINING
========================================================= */

$data_training = [];


/*
   PENTING:

   Query TIDAK memfilter nilai dan skill di SQL.

   Alasannya:
   Rata-rata harus tetap dihitung dari SEMUA skill
   pekerja pada tahun tersebut.

   Filter nilai dan skill akan diterapkan SETELAH
   semua data berhasil dikelompokkan.
*/

if ($filter_tahun > 0) {

    $sql = "
        SELECT
            p.id,
            p.no_reg,
            p.nama,
            p.departemen,
            p.keterangan,
            p.status,

            ps.id AS id_penilaian,
            ps.id_skill,
            ps.nilai,
            ps.tahun,

            s.nama_skill

        FROM pekerja p

        INNER JOIN penilaian_skill ps
            ON ps.id_pekerja = p.id

        INNER JOIN skill s
            ON s.id = ps.id_skill

        WHERE
            p.status = 'Aktif'
            AND s.status = 'Aktif'
            AND ps.tahun = ?
            AND ps.nilai IS NOT NULL
    ";


    $params = [
        $filter_tahun
    ];

    $types = 'i';


    /* =====================================================
       FILTER PEKERJA
    ===================================================== */

    if ($filter_pekerja > 0) {

        $sql .= "
            AND p.id = ?
        ";

        $params[] =
            $filter_pekerja;

        $types .= 'i';
    }


    /* =====================================================
       FILTER DEPARTEMEN
    ===================================================== */

    if ($filter_departemen !== '') {

        $sql .= "
            AND p.departemen = ?
        ";

        $params[] =
            $filter_departemen;

        $types .= 's';
    }


    /* =====================================================
       FILTER KETERANGAN
    ===================================================== */

    if ($filter_keterangan !== '') {

        $sql .= "
            AND p.keterangan LIKE ?
        ";

        $params[] =
            '%' .
            $filter_keterangan .
            '%';

        $types .= 's';
    }


    $sql .= "
        ORDER BY
            p.nama ASC,
            s.nama_skill ASC
    ";


    $stmt = $conn->prepare($sql);


    if (!$stmt) {

        die(
            'Gagal memproses data kebutuhan training: ' .
            e($conn->error)
        );
    }


    /* =====================================================
       BIND PARAMETER DINAMIS
    ===================================================== */

    $bindValues = [];

    $bindValues[] = &$types;

    foreach ($params as $key => $value) {

        $bindValues[] = &$params[$key];
    }


    call_user_func_array(
        [$stmt, 'bind_param'],
        $bindValues
    );


    if (!$stmt->execute()) {

        die(
            'Gagal mengambil data kebutuhan training: ' .
            e($stmt->error)
        );
    }


    $result = $stmt->get_result();


    /* =====================================================
       GROUP DATA PER PEKERJA
    ===================================================== */

    while ($row = $result->fetch_assoc()) {

        $id_pekerja =
            (int) $row['id'];


        /* =================================================
           BUAT DATA PEKERJA
        ================================================= */

        if (!isset(
            $data_training[$id_pekerja]
        )) {

            $data_training[$id_pekerja] = [

                'id' =>
                    $id_pekerja,

                'no_reg' =>
                    $row['no_reg'] ?? '',

                'nama' =>
                    $row['nama'] ?? '',

                'departemen' =>
                    $row['departemen'] ?? '',

                'keterangan' =>
                    $row['keterangan'] ?? '',

                'status' =>
                    $row['status'] ?? '',

                /* SEMUA NILAI */
                'all_scores' =>
                    [],

                /* SEMUA SKILL GAP */
                'all_gap_skills' =>
                    [],

                /* SKILL GAP HASIL FILTER */
                'skills' =>
                    [],

                /* NILAI TERENDAH SEMUA SKILL */
                'nilai_min' =>
                    null,

                /* RATA-RATA SEMUA SKILL */
                'nilai_rata' =>
                    null,

                /* TOTAL ASSESSMENT */
                'jumlah_assessment' =>
                    0,

                /* TOTAL GAP SEMUA SKILL */
                'jumlah_gap' =>
                    0
            ];
        }


        /* =================================================
           NILAI
        ================================================= */

        $rawNilai =
            $row['nilai'];


        if (
            $rawNilai === null
            ||
            $rawNilai === ''
        ) {

            continue;
        }


        $nilai =
            (float) $rawNilai;


        /*
           Batasi nilai 1 - 5
        */

        $nilai =
            max(
                1,
                min(
                    5,
                    $nilai
                )
            );


        /* =================================================
           SIMPAN SEMUA NILAI
        ================================================= */

        $data_training[$id_pekerja]['all_scores'][] =
            $nilai;


        $data_training[$id_pekerja]['jumlah_assessment']++;


        /* =================================================
           NILAI TERENDAH
        ================================================= */

        if (
            $data_training[$id_pekerja]['nilai_min'] === null
            ||
            $nilai <
            $data_training[$id_pekerja]['nilai_min']
        ) {

            $data_training[$id_pekerja]['nilai_min'] =
                $nilai;
        }


        /* =================================================
           SIMPAN SKILL GAP
        ================================================= */

        if ($nilai <= $batas_nilai) {

            $gapSkill = [

                'id_skill' =>
                    (int) (
                        $row['id_skill'] ?? 0
                    ),

                'nama_skill' =>
                    $row['nama_skill'] ?? '',

                'nilai' =>
                    $nilai
            ];


            $data_training[$id_pekerja]['all_gap_skills'][] =
                $gapSkill;


            $data_training[$id_pekerja]['jumlah_gap']++;
        }
    }


    $stmt->close();


    /* =====================================================
       HITUNG RATA-RATA DARI SEMUA SKILL
    ===================================================== */

    foreach (
        $data_training
        as &$worker
    ) {

        if (
            !empty(
                $worker['all_scores']
            )
        ) {

            $worker['nilai_rata'] =
                array_sum(
                    $worker['all_scores']
                )
                /
                count(
                    $worker['all_scores']
                );
        }
    }

    unset($worker);


    /* =====================================================
       TERAPKAN FILTER NILAI & SKILL
       
       FILTER INI TIDAK MENGUBAH:
       - nilai_rata
       - nilai_min
       - jumlah_assessment

       Filter hanya menentukan:
       - apakah pekerja ditampilkan
       - skill gap mana yang ditampilkan
    ===================================================== */

    foreach (
        $data_training
        as $id =>
        &$worker
    ) {

        $matchedSkills = [];


        foreach (
            $worker['all_gap_skills']
            as $gapSkill
        ) {

            $skillMatch = true;
            $nilaiMatch = true;


            /* =============================================
               FILTER SKILL
            ============================================= */

            if (
                $filter_skill > 0
                &&
                (int)$gapSkill['id_skill']
                !==
                $filter_skill
            ) {

                $skillMatch = false;
            }


            /* =============================================
               FILTER NILAI
            ============================================= */

            if ($filter_nilai === 'critical') {

                if (
                    $gapSkill['nilai'] > 1.5
                ) {

                    $nilaiMatch = false;
                }

            } elseif ($filter_nilai === 'low') {

                if (
                    $gapSkill['nilai'] <= 1.5
                    ||
                    $gapSkill['nilai'] > 2.0
                ) {

                    $nilaiMatch = false;
                }

            } elseif ($filter_nilai === 'medium') {

                if (
                    $gapSkill['nilai'] <= 2.0
                    ||
                    $gapSkill['nilai'] > 2.5
                ) {

                    $nilaiMatch = false;
                }

            } elseif ($filter_nilai === 'training') {

                if (
                    $gapSkill['nilai'] > 2.5
                ) {

                    $nilaiMatch = false;
                }
            }


            /* =============================================
               MASUK HASIL FILTER
            ============================================= */

            if (
                $skillMatch
                &&
                $nilaiMatch
            ) {

                $matchedSkills[] =
                    $gapSkill;
            }
        }


        $worker['skills'] =
            $matchedSkills;


        /*
           Kalau tidak ada skill yang cocok dengan
           filter, pekerja tidak ditampilkan.
        */

        if (
            empty(
                $worker['skills']
            )
        ) {

            unset(
                $data_training[$id]
            );
        }
    }

    unset($worker);
}


/* =========================================================
   REINDEX
========================================================= */

$data_training =
    array_values(
        $data_training
    );


/* =========================================================
   SORT PRIORITAS
========================================================= */

usort(

    $data_training,

    function (
        $a,
        $b
    ) {

        /* ================================================
           1. NILAI TERENDAH
        ================================================= */

        $nilaiA =
            $a['nilai_min'] !== null
                ? (float)$a['nilai_min']
                : 999;


        $nilaiB =
            $b['nilai_min'] !== null
                ? (float)$b['nilai_min']
                : 999;


        if (
            $nilaiA < $nilaiB
        ) {

            return -1;
        }


        if (
            $nilaiA > $nilaiB
        ) {

            return 1;
        }


        /* ================================================
           2. JUMLAH GAP
        ================================================= */

        $gapA =
            (int)(
                $a['jumlah_gap'] ?? 0
            );


        $gapB =
            (int)(
                $b['jumlah_gap'] ?? 0
            );


        if (
            $gapA > $gapB
        ) {

            return -1;
        }


        if (
            $gapA < $gapB
        ) {

            return 1;
        }


        /* ================================================
           3. RATA-RATA
        ================================================= */

        $rataA =
            $a['nilai_rata'] !== null
                ? (float)$a['nilai_rata']
                : 999;


        $rataB =
            $b['nilai_rata'] !== null
                ? (float)$b['nilai_rata']
                : 999;


        if (
            $rataA < $rataB
        ) {

            return -1;
        }


        if (
            $rataA > $rataB
        ) {

            return 1;
        }


        /* ================================================
           4. NAMA A-Z
        ================================================= */

        return strcasecmp(

            (string)(
                $a['nama'] ?? ''
            ),

            (string)(
                $b['nama'] ?? ''
            )
        );
    }
);


/* =========================================================
   STATISTIK
========================================================= */

$total_pekerja_training =
    count(
        $data_training
    );


$total_skill_gap = 0;


$total_sangat_membutuhkan =
    0;


$total_membutuhkan =
    0;


/*
   Total skill yang ditampilkan mengikuti filter.
*/

foreach (
    $data_training
    as $item
) {

    $total_skill_gap +=
        count(
            $item['skills'] ?? []
        );


    if (
        $item['nilai_min'] === null
    ) {

        continue;
    }


    $nilai =
        (float)
        $item['nilai_min'];


    if (
        $nilai <= 1.5
    ) {

        $total_sangat_membutuhkan++;

    } else {

        $total_membutuhkan++;
    }
}


/* =========================================================
   HELPER BADGE
========================================================= */

if (!function_exists('badgeKebutuhan')) {

    function badgeKebutuhan($nilai)
    {
        $nilai =
            (float)$nilai;


        if (
            $nilai <= 1.5
        ) {

            return [

                'class' =>
                    'need-critical',

                'icon' =>
                    'bi-exclamation-octagon-fill',

                'text' =>
                    'Sangat Membutuhkan Training'
            ];
        }


        if (
            $nilai <= 2.5
        ) {

            return [

                'class' =>
                    'need-warning',

                'icon' =>
                    'bi-exclamation-circle-fill',

                'text' =>
                    'Membutuhkan Training'
            ];
        }


        return [

            'class' =>
                'need-normal',

            'icon' =>
                'bi-check-circle-fill',

            'text' =>
                'Kompeten'
        ];
    }
}


/* =========================================================
   URL DOWNLOAD
========================================================= */

$queryDownload = http_build_query([

    'tahun' =>
        $filter_tahun,

    'nilai' =>
        $filter_nilai,

    'skill' =>
        $filter_skill,

    'departemen' =>
        $filter_departemen,

    'keterangan' =>
        $filter_keterangan,

    'pekerja' =>
        $filter_pekerja
]);

?>


<style>

/* =========================================================
   PAGE
========================================================= */

.training-page {
    width: 100%;
}


/* =========================================================
   HEADER
========================================================= */

.training-header {

    position: relative;

    overflow: hidden;

    background:
        linear-gradient(
            135deg,
            #123f78 0%,
            #174d8f 55%,
            #1b5ca3 100%
        );

    border-radius: 18px;

    padding: 24px 28px;

    margin-bottom: 18px;

    min-height: 150px;

    box-shadow:
        0 10px 28px
        rgba(18,63,120,.14);
}


.training-header::before {

    content: "";

    position: absolute;

    width: 190px;
    height: 190px;

    border-radius: 50%;

    background:
        rgba(255,255,255,.055);

    right: 35px;
    top: -105px;
}


.training-header::after {

    content: "";

    position: absolute;

    width: 145px;
    height: 145px;

    border-radius: 50%;

    background:
        rgba(255,255,255,.045);

    right: 145px;
    bottom: -95px;
}


.training-header > div {

    position: relative;

    z-index: 2;
}


.training-header h3 {

    margin: 0;

    color: #fff;

    font-size: 22px;

    font-weight: 700;
}


.training-header h3 i {

    color: #fff;

    font-size: 19px;
}


.training-header p {

    margin: 7px 0 0;

    color:
        rgba(255,255,255,.82);

    font-size: 13px;

    line-height: 1.6;
}


.training-header p strong {

    color: #fff;
}


/* =========================================================
   ACTION
========================================================= */

.training-header-actions {

    display: flex;

    gap: 9px;

    flex-wrap: wrap;

    align-items: center;

    position: relative;

    z-index: 3;
}


.btn-download-excel {

    background: #198754 !important;

    border: 1px solid #198754 !important;

    color: #fff !important;

    border-radius: 9px;

    font-size: 12px;

    font-weight: 700;

    padding: 9px 13px;
}


.btn-download-pdf {

    background: #dc3545 !important;

    border: 1px solid #dc3545 !important;

    color: #fff !important;

    border-radius: 9px;

    font-size: 12px;

    font-weight: 700;

    padding: 9px 13px;
}


.year-badge {

    display: inline-flex;

    align-items: center;

    gap: 6px;

    padding: 6px 11px;

    border-radius: 8px;

    background:
        rgba(255,255,255,.12);

    color: #fff;

    border:
        1px solid
        rgba(255,255,255,.22);

    font-size: 11px;

    font-weight: 700;
}


/* =========================================================
   FILTER
========================================================= */

.filter-card {

    background: #fff;

    border: 1px solid #e5ebf3;

    border-radius: 16px;

    padding: 17px;

    margin-bottom: 18px;

    box-shadow:
        0 2px 8px
        rgba(18,59,114,.025);
}


.filter-title {

    color: #123b72;

    font-size: 14px;

    font-weight: 700;

    margin-bottom: 13px;
}


.filter-label {

    color: #6d7a8d;

    font-size: 10px;

    font-weight: 700;

    text-transform: uppercase;

    letter-spacing: .04em;

    margin-bottom: 5px;
}


.filter-card .form-select,
.filter-card .form-control {

    height: 39px;

    border-radius: 8px;

    border-color: #dce4ee;

    font-size: 12px;
}


.filter-card .form-select:focus,
.filter-card .form-control:focus {

    border-color: #123f7a;

    box-shadow:
        0 0 0 .15rem
        rgba(18,63,122,.10);
}


.filter-card .btn {

    height: 39px;

    border-radius: 8px;

    font-size: 12px;

    font-weight: 700;
}


.btn-filter {

    background: #123f7a;

    border-color: #123f7a;

    color: #fff;
}


.btn-filter:hover {

    background: #092f63;

    border-color: #092f63;

    color: #fff;
}


/* =========================================================
   ACTIVE FILTER
========================================================= */

.active-filter {

    display: inline-flex;

    align-items: center;

    gap: 5px;

    background: #edf4ff;

    color: #123f7a;

    border: 1px solid #d4e4fa;

    border-radius: 20px;

    padding: 5px 9px;

    font-size: 10px;

    font-weight: 600;
}


/* =========================================================
   STAT
========================================================= */

.training-stat {

    background: #fff;

    border: 1px solid #e5ebf3;

    border-radius: 16px;

    padding: 17px 19px;

    height: 100%;

    box-shadow:
        0 2px 8px
        rgba(18,59,114,.025);
}


.training-stat-icon {

    width: 46px;

    height: 46px;

    border-radius: 12px;

    display: flex;

    align-items: center;

    justify-content: center;

    font-size: 20px;

    flex-shrink: 0;
}


.training-stat-label {

    color: #7b8798;

    font-size: 12px;

    margin-bottom: 2px;
}


.training-stat-number {

    color: #123b72;

    font-size: 23px;

    font-weight: 700;
}


/* =========================================================
   CONTAINER
========================================================= */

.training-container {

    background: #fff;

    border: 1px solid #e5ebf3;

    border-radius: 18px;

    padding: 20px;

    box-shadow:
        0 2px 8px
        rgba(18,59,114,.025);
}


.training-section-title {

    color: #123b72;

    font-size: 16px;

    font-weight: 700;
}


.training-section-note {

    color: #8994a5;

    font-size: 12px;
}


/* =========================================================
   WORKER CARD
========================================================= */

.worker-training-card {

    border: 1px solid #e2e8f1;

    border-radius: 16px;

    padding: 17px;

    background: #fff;

    transition: .2s ease;
}


.worker-training-card:hover {

    border-color: #cbd8e8;

    box-shadow:
        0 7px 22px
        rgba(18,59,114,.07);

    transform: translateY(-1px);
}


/* =========================================================
   TOP
========================================================= */

.worker-top {

    display: flex;

    align-items: flex-start;

    justify-content: space-between;

    gap: 18px;

    margin-bottom: 14px;
}


.worker-main {

    display: flex;

    align-items: flex-start;

    gap: 12px;

    min-width: 0;

    flex: 1;
}


.worker-avatar-training {

    width: 44px;

    height: 44px;

    flex: 0 0 44px;

    border-radius: 12px;

    background: #edf4ff;

    color: #123b72;

    display: flex;

    align-items: center;

    justify-content: center;

    font-weight: 800;

    font-size: 17px;
}


.worker-name-training {

    color: #123b72;

    font-size: 15px;

    font-weight: 700;

    text-decoration: none;

    display: block;

    word-break: break-word;
}


.worker-name-training:hover {

    color: #0b5ed7;
}


.worker-meta {

    color: #7b8798;

    font-size: 11px;

    margin-top: 3px;

    line-height: 1.5;
}


.worker-meta strong {

    color: #5f6f83;
}


/* =========================================================
   NILAI
========================================================= */

.worker-score-area {

    display: flex;

    align-items: flex-start;

    gap: 20px;

    flex-shrink: 0;
}


.score-box {

    text-align: right;

    min-width: 85px;
}


.score-box-label {

    color: #8a96a7;

    font-size: 10px;

    margin-bottom: 2px;
}


.score-box-value {

    font-size: 21px;

    font-weight: 800;

    line-height: 1.1;
}


.score-average {

    color: #123f7a;
}


.score-min {

    color: #dc3545;
}


.score-box-note {

    color: #9aa4b2;

    font-size: 9px;

    margin-top: 3px;
}


/* =========================================================
   GAP
========================================================= */

.gap-section {

    border-top: 1px solid #edf0f5;

    padding-top: 14px;
}


.gap-title {

    color: #66758a;

    font-size: 11px;

    font-weight: 600;

    margin-bottom: 9px;
}


.skill-list {

    display: flex;

    flex-wrap: wrap;

    gap: 7px;
}


.skill-gap {

    display: inline-flex;

    align-items: center;

    gap: 7px;

    padding: 6px 9px;

    border-radius: 8px;

    background: #fff3f3;

    border: 1px solid #ffd1d1;

    color: #b42336;

    font-size: 10px;

    font-weight: 600;

    max-width: 100%;

    word-break: break-word;
}


.skill-gap-value {

    display: inline-flex;

    align-items: center;

    justify-content: center;

    min-width: 29px;

    height: 21px;

    padding: 0 6px;

    border-radius: 6px;

    background: #e83e4d;

    color: #fff;

    font-size: 9px;

    font-weight: 800;

    flex-shrink: 0;
}


/* =========================================================
   BADGE
========================================================= */

.need-badge {

    display: inline-flex;

    align-items: center;

    gap: 5px;

    border-radius: 7px;

    padding: 5px 8px;

    font-size: 9px;

    font-weight: 700;

    margin-top: 7px;
}


.need-critical {

    background: #fff0f1;

    color: #dc3545;

    border: 1px solid #ffc9ce;
}


.need-warning {

    background: #fff7e8;

    color: #b36b00;

    border: 1px solid #ffe0a3;
}


.need-normal {

    background: #e9f8f0;

    color: #198754;

    border: 1px solid #ccebdc;
}


/* =========================================================
   FOOTER
========================================================= */

.worker-footer {

    border-top: 1px solid #edf0f5;

    margin-top: 14px;

    padding-top: 11px;

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 10px;
}


.gap-count {

    color: #7b8798;

    font-size: 10px;
}


.gap-count strong {

    color: #123b72;
}


.worker-footer .btn {

    font-size: 10px;

    border-radius: 7px;
}


/* =========================================================
   EMPTY
========================================================= */

.training-empty {

    text-align: center;

    padding: 60px 20px;
}


.training-empty-icon {

    width: 70px;

    height: 70px;

    border-radius: 50%;

    background: #eaf8f0;

    color: #198754;

    display: flex;

    align-items: center;

    justify-content: center;

    margin: 0 auto 15px;

    font-size: 30px;
}


.training-empty-title {

    color: #123b72;

    font-weight: 700;

    font-size: 16px;
}


.training-empty-text {

    color: #8a96a7;

    font-size: 12px;

    margin-top: 5px;

    line-height: 1.6;
}


/* =========================================================
   RESPONSIVE
========================================================= */

@media (max-width: 992px) {

    .worker-top {

        flex-direction: column;
    }

    .worker-score-area {

        width: 100%;

        justify-content: flex-start;
    }

    .score-box {

        text-align: left;
    }
}


@media (max-width: 768px) {

    .training-header {

        padding: 20px;

        min-height: auto;
    }


    .training-header h3 {

        font-size: 20px;
    }


    .training-header-actions {

        width: 100%;
    }


    .training-header-actions .btn {

        flex: 1;

        min-width: 130px;
    }


    .training-container {

        padding: 14px;
    }


    .worker-training-card {

        padding: 14px;
    }


    .worker-score-area {

        gap: 20px;
    }


    .score-box {

        min-width: 70px;
    }


    .score-box-value {

        font-size: 18px;
    }
}


@media (max-width: 520px) {

    .training-header {

        padding: 18px;
    }


    .training-header-actions {

        flex-direction: column;

        width: 100%;
    }


    .training-header-actions .btn {

        width: 100%;

        min-width: 100%;
    }


    .filter-card {

        padding: 13px;
    }


    .worker-score-area {

        width: 100%;

        gap: 22px;
    }


    .worker-footer {

        flex-direction: column;

        align-items: flex-start;
    }


    .worker-footer .btn {

        width: 100%;
    }


    .skill-gap {

        width: 100%;

        justify-content: space-between;
    }
}

</style>


<!-- =======================================================
     CONTENT
======================================================= -->

<div class="training-page">


    <!-- =====================================================
         HEADER
    ====================================================== -->

    <div class="training-header">

        <div
            class="
                d-flex
                justify-content-between
                align-items-center
                gap-3
                flex-wrap
            "
        >

            <div>

                <h3>

                    <i
                        class="
                            bi
                            bi-mortarboard-fill
                            me-2
                        "
                    ></i>

                    Kebutuhan Training

                </h3>


                <p>

                    Daftar pekerja dengan kompetensi
                    <strong>2,5 atau lebih rendah</strong>
                    berdasarkan assessment.

                </p>


                <?php if ($filter_tahun > 0): ?>

                    <div class="mt-3">

                        <span class="year-badge">

                            <i class="bi bi-calendar3"></i>

                            Assessment Tahun
                            <?= e($filter_tahun) ?>

                        </span>

                    </div>

                <?php endif; ?>

            </div>


            <!-- DOWNLOAD -->

            <div class="training-header-actions">

                <?php if (
                    $total_pekerja_training > 0
                ): ?>

                    <a
                        href="laporan_training.php?format=xls&<?= e($queryDownload) ?>"
                        class="
                            btn
                            btn-sm
                            btn-download-excel
                        "
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
                        href="laporan_training.php?format=pdf&<?= e($queryDownload) ?>"
                        target="_blank"
                        rel="noopener"
                        class="
                            btn
                            btn-sm
                            btn-download-pdf
                        "
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

                <?php endif; ?>

            </div>

        </div>

    </div>


    <!-- =====================================================
         FILTER
    ====================================================== -->

    <div class="filter-card">

        <div class="filter-title">

            <i
                class="
                    bi
                    bi-funnel-fill
                    me-1
                "
            ></i>

            Filter Kebutuhan Training

        </div>


        <form
            method="get"
            class="row g-2 align-items-end"
        >


            <!-- TAHUN -->

            <div class="col-xl-2 col-lg-3 col-md-4">

                <div class="filter-label">

                    Tahun Assessment

                </div>


                <select
                    name="tahun"
                    class="form-select"
                >

                    <?php foreach (
                        $years
                        as $y
                    ): ?>

                        <option
                            value="<?= (int)$y ?>"
                            <?= $filter_tahun == $y
                                ? 'selected'
                                : ''
                            ?>
                        >

                            <?= (int)$y ?>

                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <!-- NILAI -->

            <div class="col-xl-2 col-lg-3 col-md-4">

                <div class="filter-label">

                    Nilai

                </div>


                <select
                    name="nilai"
                    class="form-select"
                >

                    <option
                        value=""
                        <?= $filter_nilai === ''
                            ? 'selected'
                            : ''
                        ?>
                    >

                        Semua Nilai Training

                    </option>


                    <option
                        value="critical"
                        <?= $filter_nilai === 'critical'
                            ? 'selected'
                            : ''
                        ?>
                    >

                        ≤ 1,50 — Sangat Kritis

                    </option>


                    <option
                        value="low"
                        <?= $filter_nilai === 'low'
                            ? 'selected'
                            : ''
                        ?>
                    >

                        1,51 – 2,00

                    </option>


                    <option
                        value="medium"
                        <?= $filter_nilai === 'medium'
                            ? 'selected'
                            : ''
                        ?>
                    >

                        2,01 – 2,50

                    </option>


                    <option
                        value="training"
                        <?= $filter_nilai === 'training'
                            ? 'selected'
                            : ''
                        ?>
                    >

                        ≤ 2,50 — Semua Training

                    </option>

                </select>

            </div>


            <!-- SKILL -->

            <div class="col-xl-2 col-lg-3 col-md-4">

                <div class="filter-label">

                    Kompetensi / Skill

                </div>


                <select
                    name="skill"
                    class="form-select"
                >

                    <option value="0">

                        Semua Skill

                    </option>


                    <?php foreach (
                        $skillFilterData
                        as $sf
                    ): ?>

                        <option
                            value="<?= (int)$sf['id'] ?>"
                            <?= $filter_skill ==
                                (int)$sf['id']
                                    ? 'selected'
                                    : ''
                            ?>
                        >

                            <?= e(
                                $sf['nama_skill']
                            ) ?>

                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <!-- DEPARTEMEN -->

            <div class="col-xl-2 col-lg-3 col-md-4">

                <div class="filter-label">

                    Departemen

                </div>


                <select
                    name="departemen"
                    class="form-select"
                >

                    <option value="">

                        Semua Departemen

                    </option>


                    <?php foreach (
                        $departments
                        as $dept
                    ): ?>

                        <option
                            value="<?= e($dept) ?>"
                            <?= $filter_departemen === $dept
                                ? 'selected'
                                : ''
                            ?>
                        >

                            <?= e($dept) ?>

                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <!-- PEKERJA -->

            <div class="col-xl-2 col-lg-3 col-md-4">

                <div class="filter-label">

                    Pekerja

                </div>


                <select
                    name="pekerja"
                    class="form-select"
                >

                    <option value="0">

                        Semua Pekerja

                    </option>


                    <?php foreach (
                        $workerFilter
                        as $wf
                    ): ?>

                        <option
                            value="<?= (int)$wf['id'] ?>"
                            <?= $filter_pekerja ==
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

                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <!-- BUTTON -->

            <div class="col-xl-2 col-lg-3 col-md-4">

                <div class="filter-label">

                    &nbsp;

                </div>


                <div class="d-flex gap-2">

                    <button
                        type="submit"
                        class="
                            btn
                            btn-filter
                            flex-grow-1
                        "
                    >

                        <i
                            class="
                                bi
                                bi-search
                                me-1
                            "
                        ></i>

                        Terapkan

                    </button>


                    <a
                        href="kebutuhan.php"
                        class="
                            btn
                            btn-light
                            border
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


            <!-- KETERANGAN -->

            <div class="col-xl-6 col-lg-6 col-md-8">

                <div class="filter-label">

                    Keterangan Pekerja

                </div>


                <input
                    type="text"
                    name="keterangan"
                    class="form-control"
                    value="<?= e(
                        $filter_keterangan
                    ) ?>"
                    placeholder="Contoh: Operator, Teknisi, Senior, Junior"
                >

            </div>


        </form>


        <!-- FILTER AKTIF -->

        <?php

        $activeFilters = [];


        if ($filter_nilai !== '') {

            $nilaiLabel = [

                'critical' =>
                    'Nilai ≤ 1,50',

                'low' =>
                    'Nilai 1,51 – 2,00',

                'medium' =>
                    'Nilai 2,01 – 2,50',

                'training' =>
                    'Nilai ≤ 2,50'
            ];


            $activeFilters[] =
                $nilaiLabel[
                    $filter_nilai
                ]
                ??
                $filter_nilai;
        }


        if ($filter_skill > 0) {

            foreach (
                $skillFilterData
                as $sf
            ) {

                if (
                    (int)$sf['id']
                    ===
                    $filter_skill
                ) {

                    $activeFilters[] =
                        'Skill: ' .
                        $sf['nama_skill'];

                    break;
                }
            }
        }


        if ($filter_departemen !== '') {

            $activeFilters[] =
                'Departemen: ' .
                $filter_departemen;
        }


        if ($filter_pekerja > 0) {

            foreach (
                $workerFilter
                as $wf
            ) {

                if (
                    (int)$wf['id']
                    ===
                    $filter_pekerja
                ) {

                    $activeFilters[] =
                        'Pekerja: ' .
                        $wf['nama'];

                    break;
                }
            }
        }


        if ($filter_keterangan !== '') {

            $activeFilters[] =
                'Keterangan: ' .
                $filter_keterangan;
        }

        ?>


        <?php if (
            !empty(
                $activeFilters
            )
        ): ?>

            <div
                class="
                    d-flex
                    align-items-center
                    gap-2
                    flex-wrap
                    mt-3
                "
            >

                <small class="text-muted">

                    Filter aktif:

                </small>


                <?php foreach (
                    $activeFilters
                    as $af
                ): ?>

                    <span
                        class="active-filter"
                    >

                        <i
                            class="
                                bi
                                bi-check-circle
                            "
                        ></i>

                        <?= e($af) ?>

                    </span>

                <?php endforeach; ?>

            </div>

        <?php endif; ?>

    </div>


    <!-- =====================================================
         STATISTIK
    ====================================================== -->

    <div class="row g-3 mb-3">


        <!-- PEKERJA -->

        <div class="col-xl-4 col-md-6">

            <div class="training-stat">

                <div
                    class="
                        d-flex
                        align-items-center
                        gap-3
                    "
                >

                    <div
                        class="training-stat-icon"
                        style="
                            background:#fff0f1;
                            color:#dc3545;
                        "
                    >

                        <i
                            class="
                                bi
                                bi-person-exclamation
                            "
                        ></i>

                    </div>


                    <div>

                        <div
                            class="
                                training-stat-label
                            "
                        >

                            Pekerja Membutuhkan Training

                        </div>


                        <div
                            class="
                                training-stat-number
                            "
                        >

                            <?= number_format(
                                $total_pekerja_training
                            ) ?>

                        </div>

                    </div>

                </div>

            </div>

        </div>


        <!-- SKILL GAP -->

        <div class="col-xl-4 col-md-6">

            <div class="training-stat">

                <div
                    class="
                        d-flex
                        align-items-center
                        gap-3
                    "
                >

                    <div
                        class="training-stat-icon"
                        style="
                            background:#fff7e8;
                            color:#d97706;
                        "
                    >

                        <i
                            class="
                                bi
                                bi-award-fill
                            "
                        ></i>

                    </div>


                    <div>

                        <div
                            class="
                                training-stat-label
                            "
                        >

                            Total Skill Perlu Training

                        </div>


                        <div
                            class="
                                training-stat-number
                            "
                        >

                            <?= number_format(
                                $total_skill_gap
                            ) ?>

                        </div>

                    </div>

                </div>

            </div>

        </div>


        <!-- SANGAT BUTUH -->

        <div class="col-xl-4 col-md-6">

            <div class="training-stat">

                <div
                    class="
                        d-flex
                        align-items-center
                        gap-3
                    "
                >

                    <div
                        class="training-stat-icon"
                        style="
                            background:#fce8ea;
                            color:#b42336;
                        "
                    >

                        <i
                            class="
                                bi
                                bi-exclamation-octagon-fill
                            "
                        ></i>

                    </div>


                    <div>

                        <div
                            class="
                                training-stat-label
                            "
                        >

                            Sangat Membutuhkan Training

                        </div>


                        <div
                            class="
                                training-stat-number
                            "
                        >

                            <?= number_format(
                                $total_sangat_membutuhkan
                            ) ?>

                        </div>

                    </div>

                </div>

            </div>

        </div>

    </div>


    <!-- =====================================================
         LIST
    ====================================================== -->

    <div class="training-container">


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

                <div
                    class="
                        training-section-title
                    "
                >

                    Daftar Pekerja yang Membutuhkan Training

                </div>


                <div
                    class="
                        training-section-note
                    "
                >

                    Prioritas berdasarkan nilai terendah,
                    jumlah skill gap, kemudian rata-rata nilai.

                </div>

            </div>


            <?php if (
                $total_pekerja_training > 0
            ): ?>

                <span
                    class="
                        badge
                        bg-light
                        text-primary
                        border
                    "
                >

                    <i
                        class="
                            bi
                            bi-people-fill
                            me-1
                        "
                    ></i>

                    <?= number_format(
                        $total_pekerja_training
                    ) ?>

                    Pekerja

                </span>

            <?php endif; ?>

        </div>


        <?php if (
            !empty(
                $data_training
            )
        ): ?>


            <div
                class="
                    d-flex
                    flex-column
                    gap-3
                "
            >


                <?php foreach (
                    $data_training
                    as $worker
                ):


                    $nilai_min =
                        $worker['nilai_min'] !== null
                            ? (float)$worker['nilai_min']
                            : 0;


                    $nilai_rata =
                        $worker['nilai_rata'] !== null
                            ? (float)$worker['nilai_rata']
                            : 0;


                    $badge =
                        badgeKebutuhan(
                            $nilai_min
                        );


                    $namaWorker =
                        trim(
                            (string)(
                                $worker['nama']
                                ?? ''
                            )
                        );


                    /* INITIAL */

                    $initial = '?';


                    if (
                        $namaWorker !== ''
                    ) {

                        if (
                            function_exists(
                                'mb_substr'
                            )
                        ) {

                            $initial =
                                mb_strtoupper(
                                    mb_substr(
                                        $namaWorker,
                                        0,
                                        1,
                                        'UTF-8'
                                    ),
                                    'UTF-8'
                                );

                        } else {

                            $initial =
                                strtoupper(
                                    substr(
                                        $namaWorker,
                                        0,
                                        1
                                    )
                                );
                        }
                    }


                    $keterangan =
                        trim(
                            (string)(
                                $worker['keterangan']
                                ?? ''
                            )
                        );

                ?>


                    <!-- =================================================
                         WORKER CARD
                    ================================================== -->

                    <div
                        class="
                            worker-training-card
                        "
                    >


                        <!-- TOP -->

                        <div
                            class="worker-top"
                        >


                            <!-- WORKER -->

                            <div
                                class="worker-main"
                            >


                                <div
                                    class="
                                        worker-avatar-training
                                    "
                                >

                                    <?= e(
                                        $initial
                                    ) ?>

                                </div>


                                <div
                                    style="
                                        min-width:0;
                                    "
                                >


                                    <a
                                        href="../pekerja/detail.php?id=<?= (int)$worker['id'] ?>"
                                        class="
                                            worker-name-training
                                        "
                                    >

                                        <?= e(
                                            $worker['nama']
                                        ) ?>

                                    </a>


                                    <div
                                        class="worker-meta"
                                    >

                                        No. Reg:

                                        <strong>

                                            <?= e(
                                                $worker['no_reg']
                                                ?: '-'
                                            ) ?>

                                        </strong>


                                        <span class="mx-1">
                                            •
                                        </span>


                                        <?= e(
                                            $worker['departemen']
                                            ?: '-'
                                        ) ?>

                                    </div>


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


                                        <strong>

                                            Kebutuhan:

                                        </strong>


                                        <?= e(
                                            $keterangan !== ''
                                                ? $keterangan
                                                : 'Membutuhkan training'
                                        ) ?>

                                    </div>


                                    <div>

                                        <span
                                            class="
                                                need-badge
                                                <?= e(
                                                    $badge['class']
                                                ) ?>
                                            "
                                        >

                                            <i
                                                class="
                                                    bi
                                                    <?= e(
                                                        $badge['icon']
                                                    ) ?>
                                                "
                                            ></i>


                                            <?= e(
                                                $badge['text']
                                            ) ?>

                                        </span>

                                    </div>


                                </div>

                            </div>


                            <!-- NILAI -->

                            <div
                                class="
                                    worker-score-area
                                "
                            >


                                <!-- RATA-RATA -->

                                <div
                                    class="score-box"
                                >

                                    <div
                                        class="
                                            score-box-label
                                        "
                                    >

                                        Rata-rata Nilai

                                    </div>


                                    <div
                                        class="
                                            score-box-value
                                            score-average
                                        "
                                    >

                                        <?= number_format(
                                            $nilai_rata,
                                            2,
                                            ',',
                                            '.'
                                        ) ?>

                                    </div>


                                    <div
                                        class="
                                            score-box-note
                                        "
                                    >

                                        Semua skill

                                    </div>

                                </div>


                                <!-- TERENDAH -->

                                <div
                                    class="score-box"
                                >

                                    <div
                                        class="
                                            score-box-label
                                        "
                                    >

                                        Nilai Terendah

                                    </div>


                                    <div
                                        class="
                                            score-box-value
                                            score-min
                                        "
                                    >

                                        <?= number_format(
                                            $nilai_min,
                                            2,
                                            ',',
                                            '.'
                                        ) ?>

                                    </div>


                                    <div
                                        class="
                                            score-box-note
                                        "
                                    >

                                        Batas 2,50

                                    </div>

                                </div>


                            </div>


                        </div>


                        <!-- =================================================
                             SKILL GAP
                        ================================================== -->

                        <div
                            class="
                                gap-section
                            "
                        >


                            <div
                                class="gap-title"
                            >

                                <i
                                    class="
                                        bi
                                        bi-exclamation-triangle-fill
                                        me-1
                                    "
                                ></i>

                                Skill yang Membutuhkan Training

                            </div>


                            <div
                                class="skill-list"
                            >


                                <?php foreach (
                                    $worker['skills']
                                    as $skill
                                ):


                                    $nilai_skill =
                                        (float)(
                                            $skill['nilai']
                                            ?? 0
                                        );

                                ?>


                                    <div
                                        class="skill-gap"
                                        title="Nilai <?= e(
                                            number_format(
                                                $nilai_skill,
                                                2,
                                                ',',
                                                '.'
                                            )
                                        ) ?>"
                                    >

                                        <span>

                                            <?= e(
                                                $skill['nama_skill']
                                            ) ?>

                                        </span>


                                        <span
                                            class="
                                                skill-gap-value
                                            "
                                        >

                                            <?= number_format(
                                                $nilai_skill,
                                                2,
                                                ',',
                                                '.'
                                            ) ?>

                                        </span>

                                    </div>


                                <?php endforeach; ?>


                            </div>


                        </div>


                        <!-- =================================================
                             FOOTER
                        ================================================== -->

                        <div
                            class="
                                worker-footer
                            "
                        >


                            <div
                                class="gap-count"
                            >

                                <i
                                    class="
                                        bi
                                        bi-bar-chart-fill
                                        me-1
                                    "
                                ></i>


                                <strong>

                                    <?= number_format(
                                        count(
                                            $worker['skills'] ?? []
                                        )
                                    ) ?>

                                </strong>


                                skill ditampilkan


                                <span
                                    class="ms-2"
                                >

                                    •
                                    <?= number_format(
                                        (int)(
                                            $worker[
                                                'jumlah_assessment'
                                            ]
                                            ?? 0
                                        )
                                    ) ?>

                                    assessment

                                </span>

                            </div>


                            <a
                                href="../pekerja/detail.php?id=<?= (int)$worker['id'] ?>"
                                class="
                                    btn
                                    btn-sm
                                    btn-outline-primary
                                "
                            >

                                <i
                                    class="
                                        bi
                                        bi-eye
                                        me-1
                                    "
                                ></i>

                                Detail Pekerja

                            </a>


                        </div>


                    </div>


                <?php endforeach; ?>


            </div>


        <?php else: ?>


            <!-- =================================================
                 EMPTY
            ================================================== -->

            <div
                class="training-empty"
            >


                <div
                    class="
                        training-empty-icon
                    "
                >

                    <i
                        class="
                            bi
                            bi-check-circle-fill
                        "
                    ></i>

                </div>


                <div
                    class="
                        training-empty-title
                    "
                >

                    Tidak Ada Pekerja yang Membutuhkan Training

                </div>


                <div
                    class="
                        training-empty-text
                    "
                >

                    <?php if (
                        $filter_tahun > 0
                    ): ?>

                        Tidak ada pekerja aktif
                        dengan skill yang memenuhi
                        kriteria filter pada assessment
                        tahun

                        <strong>
                            <?= e(
                                (string)$filter_tahun
                            ) ?>
                        </strong>.

                        <?php if (
                            !empty($activeFilters)
                        ): ?>

                            <br>

                            Silakan coba ubah atau reset
                            filter yang digunakan.

                        <?php endif; ?>

                    <?php else: ?>

                        Belum ada data assessment
                        skill yang tersedia.

                    <?php endif; ?>

                </div>


            </div>


        <?php endif; ?>


    </div>


</div>


<?php

require __DIR__ . '/../partials/footer.php';

?>