<?php
/* =========================================================
   GARUDAFOOD SKILL MONITORING
   KEBUTUHAN TRAINING

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
   DATA TRAINING
========================================================= */

$data_training = [];

if ($latestYear > 0) {

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
            AND ps.nilai <= ?

        ORDER BY
            ps.nilai ASC,
            p.nama ASC,
            s.nama_skill ASC
    ";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {

        die(
            'Gagal memproses data kebutuhan training: ' .
            htmlspecialchars(
                $conn->error,
                ENT_QUOTES,
                'UTF-8'
            )
        );
    }

    $stmt->bind_param(
        'id',
        $latestYear,
        $batas_nilai
    );

    if (!$stmt->execute()) {

        die(
            'Gagal mengambil data kebutuhan training: ' .
            htmlspecialchars(
                $stmt->error,
                ENT_QUOTES,
                'UTF-8'
            )
        );
    }

    $result = $stmt->get_result();


    /* =====================================================
       GROUP DATA PER PEKERJA
    ===================================================== */

    while ($row = $result->fetch_assoc()) {

        $id_pekerja = (int) $row['id'];

        if (!isset($data_training[$id_pekerja])) {

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

                'skills' =>
                    [],

                'nilai_min' =>
                    null,

                'jumlah_gap' =>
                    0
            ];
        }


        /* =================================================
           NILAI
        ================================================= */

        $nilai = (float) (
            $row['nilai'] ?? 0
        );

        $nilai = max(
            1,
            min(
                5,
                $nilai
            )
        );


        /* =================================================
           MASUKKAN SKILL GAP
        ================================================= */

        $data_training[$id_pekerja]['skills'][] = [

            'id_skill' =>
                (int) (
                    $row['id_skill'] ?? 0
                ),

            'nama_skill' =>
                $row['nama_skill'] ?? '',

            'nilai' =>
                $nilai
        ];


        /* =================================================
           NILAI TERENDAH
        ================================================= */

        if (
            $data_training[$id_pekerja]['nilai_min'] === null
            ||
            $nilai < $data_training[$id_pekerja]['nilai_min']
        ) {

            $data_training[$id_pekerja]['nilai_min'] =
                $nilai;
        }


        /* =================================================
           JUMLAH SKILL GAP
        ================================================= */

        $data_training[$id_pekerja]['jumlah_gap']++;
    }

    $stmt->close();
}


/* =========================================================
   REINDEX
========================================================= */

$data_training = array_values(
    $data_training
);


/* =========================================================
   SORT PRIORITAS TRAINING

   1. Nilai terendah
   2. Jumlah skill gap terbanyak
   3. Nama pekerja A-Z
========================================================= */

usort(

    $data_training,

    function (
        $a,
        $b
    ) {

        $nilaiA =
            $a['nilai_min'] !== null
                ? (float) $a['nilai_min']
                : 999;

        $nilaiB =
            $b['nilai_min'] !== null
                ? (float) $b['nilai_min']
                : 999;


        if ($nilaiA < $nilaiB) {
            return -1;
        }

        if ($nilaiA > $nilaiB) {
            return 1;
        }


        $gapA =
            (int) (
                $a['jumlah_gap'] ?? 0
            );

        $gapB =
            (int) (
                $b['jumlah_gap'] ?? 0
            );


        if ($gapA > $gapB) {
            return -1;
        }

        if ($gapA < $gapB) {
            return 1;
        }


        return strcasecmp(

            (string) (
                $a['nama'] ?? ''
            ),

            (string) (
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

foreach (
    $data_training
    as $item
) {

    $total_skill_gap +=
        (int) (
            $item['jumlah_gap'] ?? 0
        );
}


/* =========================================================
   HITUNG LEVEL KEBUTUHAN
========================================================= */

$total_sangat_membutuhkan = 0;

$total_membutuhkan = 0;

foreach (
    $data_training
    as $item
) {

    if (
        $item['nilai_min'] === null
    ) {
        continue;
    }

    $nilai =
        (float) $item['nilai_min'];

    if ($nilai <= 1.5) {

        $total_sangat_membutuhkan++;

    } else {

        $total_membutuhkan++;
    }
}


/* =========================================================
   HELPER BADGE
========================================================= */

function badgeKebutuhan($nilai)
{
    $nilai =
        (float) $nilai;


    if ($nilai <= 1.5) {

        return [

            'class' =>
                'need-critical',

            'icon' =>
                'bi-exclamation-octagon-fill',

            'text' =>
                'Sangat Membutuhkan Training'
        ];
    }


    if ($nilai <= 2.5) {

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

?>


<style>

/* =========================================================
   PAGE
========================================================= */

.training-page {
    width: 100%;
}


/* =========================================================
   HEADER BIRU - SEPERTI DASHBOARD
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

    border: none;

    border-radius: 18px;

    padding: 24px 28px;

    margin-bottom: 18px;

    min-height: 150px;

    box-shadow:
        0 10px 28px
        rgba(
            18,
            63,
            120,
            .14
        );

}


/* Ornamen lingkaran seperti Dashboard */

.training-header::before {

    content: "";

    position: absolute;

    width: 190px;

    height: 190px;

    border-radius: 50%;

    background: rgba(
        255,
        255,
        255,
        .055
    );

    right: 35px;

    top: -105px;

    pointer-events: none;
}


.training-header::after {

    content: "";

    position: absolute;

    width: 145px;

    height: 145px;

    border-radius: 50%;

    background: rgba(
        255,
        255,
        255,
        .045
    );

    right: 145px;

    bottom: -95px;

    pointer-events: none;
}


/* Isi header harus berada di atas ornamen */

.training-header > div {

    position: relative;

    z-index: 2;
}


.training-header h3 {

    margin: 0;

    color: #ffffff;

    font-size: 22px;

    font-weight: 700;

    letter-spacing: -.2px;
}


.training-header h3 i {

    color: #ffffff;

    font-size: 19px;
}


.training-header p {

    margin: 7px 0 0;

    color: rgba(
        255,
        255,
        255,
        .82
    );

    font-size: 13px;

    line-height: 1.6;
}


.training-header p strong {

    color: #ffffff;

    font-weight: 700;
}


/* =========================================================
   HEADER ACTION
========================================================= */

.training-header-actions {

    display: flex;

    gap: 9px;

    flex-wrap: wrap;

    align-items: center;

    position: relative;

    z-index: 3;
}


/* =========================================================
   BUTTON DOWNLOAD EXCEL
========================================================= */

.training-header-actions .btn-download-excel {

    background: #198754 !important;

    border: 1px solid #198754 !important;

    color: #ffffff !important;

    border-radius: 9px;

    font-size: 12px;

    font-weight: 700;

    padding: 9px 13px;

    box-shadow:
        0 4px 10px
        rgba(
            25,
            135,
            84,
            .22
        );

    transition:
        .2s ease;
}


.training-header-actions .btn-download-excel:hover {

    background: #157347 !important;

    border-color: #157347 !important;

    color: #ffffff !important;

    transform: translateY(-1px);

    box-shadow:
        0 6px 14px
        rgba(
            25,
            135,
            84,
            .30
        );
}


/* =========================================================
   BUTTON DOWNLOAD PDF
========================================================= */

.training-header-actions .btn-download-pdf {

    background: #dc3545 !important;

    border: 1px solid #dc3545 !important;

    color: #ffffff !important;

    border-radius: 9px;

    font-size: 12px;

    font-weight: 700;

    padding: 9px 13px;

    box-shadow:
        0 4px 10px
        rgba(
            220,
            53,
            69,
            .22
        );

    transition:
        .2s ease;
}


.training-header-actions .btn-download-pdf:hover {

    background: #bb2d3b !important;

    border-color: #bb2d3b !important;

    color: #ffffff !important;

    transform: translateY(-1px);

    box-shadow:
        0 6px 14px
        rgba(
            220,
            53,
            69,
            .30
        );
}


/* =========================================================
   YEAR BADGE
========================================================= */

.year-badge {

    display: inline-flex;

    align-items: center;

    gap: 6px;

    padding: 6px 11px;

    border-radius: 8px;

    background: rgba(
        255,
        255,
        255,
        .12
    );

    color: #ffffff;

    border: 1px solid rgba(
        255,
        255,
        255,
        .22
    );

    font-size: 11px;

    font-weight: 700;

    backdrop-filter: blur(4px);
}


/* =========================================================
   STAT
========================================================= */

.training-stat {

    background: #ffffff;

    border: 1px solid #e5ebf3;

    border-radius: 16px;

    padding: 17px 19px;

    height: 100%;

    box-shadow:
        0 2px 8px
        rgba(
            18,
            59,
            114,
            .025
        );
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

    background: #ffffff;

    border: 1px solid #e5ebf3;

    border-radius: 18px;

    padding: 20px;

    box-shadow:
        0 2px 8px
        rgba(
            18,
            59,
            114,
            .025
        );
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

    padding: 18px;

    background: #ffffff;

    transition:
        box-shadow .2s ease,
        border-color .2s ease,
        transform .2s ease;
}


.worker-training-card:hover {

    border-color: #cbd8e8;

    box-shadow:
        0 7px 22px
        rgba(
            18,
            59,
            114,
            .07
        );

    transform: translateY(-1px);
}


/* =========================================================
   WORKER TOP
========================================================= */

.worker-top {

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 15px;

    margin-bottom: 15px;
}


.worker-main {

    display: flex;

    align-items: center;

    gap: 13px;

    min-width: 0;
}


.worker-avatar-training {

    width: 47px;

    height: 47px;

    flex: 0 0 47px;

    border-radius: 13px;

    background: #edf4ff;

    color: #123b72;

    display: flex;

    align-items: center;

    justify-content: center;

    font-weight: 800;

    font-size: 18px;
}


.worker-name-training {

    color: #123b72;

    font-size: 16px;

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

    font-size: 12px;

    margin-top: 3px;

    line-height: 1.5;
}


.worker-meta strong {

    color: #5f6f83;
}


/* =========================================================
   NILAI TERENDAH
========================================================= */

.worker-min {

    text-align: right;

    min-width: 110px;
}


.worker-min-label {

    color: #8a96a7;

    font-size: 11px;

    margin-bottom: 2px;
}


.worker-min-value {

    color: #dc3545;

    font-size: 24px;

    font-weight: 800;

    line-height: 1.1;
}


/* =========================================================
   GAP
========================================================= */

.gap-section {

    border-top: 1px solid #edf0f5;

    padding-top: 15px;
}


.gap-title {

    color: #66758a;

    font-size: 12px;

    font-weight: 600;

    margin-bottom: 9px;
}


/* =========================================================
   SKILL
========================================================= */

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

    font-size: 11px;

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

    color: #ffffff;

    font-size: 10px;

    font-weight: 800;

    flex-shrink: 0;
}


/* =========================================================
   NEED BADGE
========================================================= */

.need-badge {

    display: inline-flex;

    align-items: center;

    gap: 5px;

    border-radius: 7px;

    padding: 5px 8px;

    font-size: 10px;

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

    border: 1px solid #c8ead8;
}


/* =========================================================
   FOOTER CARD
========================================================= */

.worker-footer {

    border-top: 1px solid #edf0f5;

    margin-top: 15px;

    padding-top: 12px;

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 10px;
}


.gap-count {

    color: #7b8798;

    font-size: 11px;
}


.gap-count strong {

    color: #123b72;
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

        justify-content: center;
    }


    .training-container {

        padding: 14px;
    }


    .worker-training-card {

        padding: 14px;
    }


    .worker-top {

        align-items: flex-start;
    }


    .worker-min {

        min-width: 80px;
    }


    .worker-min-value {

        font-size: 20px;
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


    .worker-top {

        flex-direction: column;
    }


    .worker-main {

        width: 100%;
    }


    .worker-min {

        width: 100%;

        text-align: left;
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
         HEADER BIRU
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

                    Daftar pekerja dengan nilai kompetensi
                    <strong>2,5 atau lebih rendah</strong>
                    berdasarkan assessment terbaru.

                </p>


                <?php if ($latestYear > 0): ?>

                    <div class="mt-3">

                        <span class="year-badge">

                            <i class="bi bi-calendar3"></i>

                            Assessment Tahun
                            <?= e(
                                (string) $latestYear
                            ) ?>

                        </span>

                    </div>

                <?php endif; ?>

            </div>


            <!-- =================================================
                 DOWNLOAD
            ================================================== -->

            <div class="training-header-actions">

                <?php if ($total_pekerja_training > 0): ?>

                    <!-- EXCEL HIJAU PENUH -->

                    <a
                        href="laporan_training.php?format=xls"
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


                    <!-- PDF MERAH PENUH -->

                    <a
                        href="laporan_training.php?format=pdf"
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
         STATISTIK
    ====================================================== -->

    <div class="row g-3 mb-3">


        <!-- TOTAL PEKERJA -->

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

                        <div class="training-stat-label">

                            Pekerja Membutuhkan Training

                        </div>


                        <div class="training-stat-number">

                            <?= number_format(
                                $total_pekerja_training
                            ) ?>

                        </div>

                    </div>

                </div>

            </div>

        </div>


        <!-- TOTAL SKILL -->

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

                        <div class="training-stat-label">

                            Total Skill Perlu Training

                        </div>


                        <div class="training-stat-number">

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

                        <div class="training-stat-label">

                            Sangat Membutuhkan Training

                        </div>


                        <div class="training-stat-number">

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

                <div class="training-section-title">

                    Daftar Pekerja yang Membutuhkan Training

                </div>


                <div class="training-section-note">

                    Semakin rendah nilai,
                    semakin tinggi prioritas training.

                </div>

            </div>


            <?php if ($total_pekerja_training > 0): ?>

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


        <?php if (!empty($data_training)): ?>


            <div class="d-flex flex-column gap-3">


                <?php

                foreach (
                    $data_training
                    as $worker
                ):

                    $nilai_min =
                        $worker['nilai_min'] !== null
                            ? (float) $worker['nilai_min']
                            : 0;


                    $badge =
                        badgeKebutuhan(
                            $nilai_min
                        );


                    $namaWorker =
                        trim(
                            (string) (
                                $worker['nama'] ?? ''
                            )
                        );


                    /* =================================================
                       INISIAL
                    ================================================= */

                    $initial = '?';


                    if (
                        $namaWorker !== ''
                    ) {

                        if (
                            function_exists('mb_substr')
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

                ?>


                    <!-- =================================================
                         WORKER CARD
                    ================================================= -->

                    <div
                        class="worker-training-card"
                    >


                        <!-- =================================================
                             TOP
                        ================================================= -->

                        <div class="worker-top">


                            <div class="worker-main">


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
                                    style="min-width:0;"
                                >


                                    <!-- NAMA -->

                                    <a
                                        href="../pekerja/detail.php?id=<?= (int) $worker['id'] ?>"
                                        class="
                                            worker-name-training
                                        "
                                    >

                                        <?= e(
                                            $worker['nama']
                                        ) ?>

                                    </a>


                                    <!-- NO REG + DEPARTEMEN -->

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


                                    <!-- KETERANGAN -->

                                    <?php

                                    $keterangan =
                                        trim(
                                            (string) (
                                                $worker['keterangan']
                                                ?? ''
                                            )
                                        );

                                    ?>


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


                                    <!-- BADGE -->

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


                            <!-- =================================================
                                 NILAI TERENDAH
                            ================================================== -->

                            <div class="worker-min">

                                <div
                                    class="
                                        worker-min-label
                                    "
                                >

                                    Nilai Terendah

                                </div>


                                <div
                                    class="
                                        worker-min-value
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
                                        small
                                        text-muted
                                        mt-1
                                    "
                                >

                                    Maks. 2,50

                                </div>

                            </div>


                        </div>


                        <!-- =================================================
                             SKILL GAP
                        ================================================== -->

                        <div class="gap-section">


                            <div class="gap-title">

                                <i
                                    class="
                                        bi
                                        bi-exclamation-triangle-fill
                                        me-1
                                    "
                                ></i>

                                Skill yang Membutuhkan Training

                            </div>


                            <div class="skill-list">


                                <?php

                                foreach (
                                    $worker['skills']
                                    as $skill
                                ):

                                    $nilai_skill =
                                        (float) (
                                            $skill['nilai'] ?? 0
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
                                class="
                                    gap-count
                                "
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
                                        (int) (
                                            $worker['jumlah_gap']
                                            ?? 0
                                        )
                                    ) ?>

                                </strong>


                                skill perlu ditingkatkan

                            </div>


                            <a
                                href="../pekerja/detail.php?id=<?= (int) $worker['id'] ?>"
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

            <div class="training-empty">


                <div class="training-empty-icon">

                    <i
                        class="
                            bi
                            bi-check-circle-fill
                        "
                    ></i>

                </div>


                <div class="training-empty-title">

                    Tidak Ada Pekerja yang Membutuhkan Training

                </div>


                <div class="training-empty-text">

                    <?php if ($latestYear > 0): ?>

                        Tidak ada nilai kompetensi
                        pada atau di bawah 2,5
                        untuk assessment tahun

                        <?= e(
                            (string) $latestYear
                        ) ?>.

                    <?php else: ?>

                        Belum ada data assessment skill
                        yang tersedia.

                    <?php endif; ?>

                </div>


            </div>


        <?php endif; ?>


    </div>


</div>


<?php

require __DIR__ . '/../partials/footer.php';

?>