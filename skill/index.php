<?php
$page_title = 'Kompetensi / Skill';
require __DIR__ . '/../partials/header.php';

$skills = $conn->query("
    SELECT s.*, COUNT(DISTINCT ps.id_pekerja) AS peserta 
    FROM skill s 
    LEFT JOIN penilaian_skill ps ON ps.id_skill = s.id 
    GROUP BY s.id 
    ORDER BY s.id
");
?>

<!-- =======================================================
     CUSTOM STYLE UNTUK MASTER KOMPETENSI
======================================================= -->
<style>

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
   BACKGROUND HALAMAN
========================================================= */

body {
    background: var(--gf-bg) !important;
}


/* =========================================================
   HEADER HALAMAN
   CARD BIRU GARUDAFOOD
========================================================= */

.dashboard-heading {

    margin-bottom: 25px;

    padding: 24px 27px;

    background:
        linear-gradient(
            135deg,
            #092f63 0%,
            #123f7a 100%
        );

    border-radius: 17px;

    box-shadow:
        0 7px 24px
        rgba(9, 47, 99, .16);

    position: relative;

    overflow: hidden;
}


/* Efek dekorasi halus */

.dashboard-heading::after {

    content: "";

    position: absolute;

    width: 190px;
    height: 190px;

    right: -70px;
    top: -100px;

    background:
        rgba(255,255,255,.055);

    border-radius: 50%;

    pointer-events: none;
}


.dashboard-heading::before {

    content: "";

    position: absolute;

    width: 120px;
    height: 120px;

    right: 100px;
    bottom: -90px;

    background:
        rgba(255,255,255,.035);

    border-radius: 50%;

    pointer-events: none;
}


/* =========================================================
   EYEBROW
========================================================= */

.dashboard-eyebrow {

    display: inline-flex;

    align-items: center;

    gap: 7px;

    color: #bcd8ff;

    font-size: 12px;

    font-weight: 700;

    text-transform: uppercase;

    letter-spacing: .08em;

    margin-bottom: 8px;

    position: relative;

    z-index: 1;
}


.dashboard-eyebrow i {

    font-size: 14px;

    color: #d9e9ff;
}


/* =========================================================
   TITLE
========================================================= */

.dashboard-title {

    margin: 0;

    font-size: 28px;

    line-height: 1.2;

    font-weight: 750;

    color: #ffffff;

    position: relative;

    z-index: 1;
}


/* =========================================================
   DESCRIPTION
========================================================= */

.dashboard-description {

    margin-top: 8px;

    color: rgba(255,255,255,.76);

    font-size: 13px;

    line-height: 1.6;

    position: relative;

    z-index: 1;
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


.card-title-custom {

    color: #172033;

    font-size: 16px;

    font-weight: 750;

    margin-bottom: 4px;
}


.section-note {

    color: #8a94a4;

    font-size: 12px;
}


/* =========================================================
   TABEL CUSTOM
========================================================= */

.table-custom thead th {

    border-top: 0;

    border-bottom:
        1px solid #e3e8ef;

    color: #7d8796;

    font-size: 10px;

    font-weight: 750;

    text-transform: uppercase;

    letter-spacing: .05em;

    padding: 12px 10px;

    background: #fafbfd;

    vertical-align: middle;
}


.table-custom tbody td {

    border-bottom:
        1px solid #edf0f4;

    color: #384457;

    font-size: 12px;

    padding: 13px 10px;

    vertical-align: middle;
}


.table-custom tbody tr:hover {

    background: #fafbfd;
}


/* =========================================================
   LABEL LEVEL HEADER KECIL
========================================================= */

.level-th {

    text-align: center;

    width: 14%;
}


/* =========================================================
   RESPONSIVE
========================================================= */

@media (max-width: 768px) {

    .dashboard-heading {

        padding: 20px;

        border-radius: 14px;

        margin-bottom: 20px;
    }


    .dashboard-title {

        font-size: 23px;
    }


    .dashboard-description {

        font-size: 12px;

        line-height: 1.55;
    }


    .dashboard-eyebrow {

        font-size: 10px;
    }


    .cardx {

        padding: 17px;

        border-radius: 14px;
    }

}

</style>


<!-- =======================================================
     HEADER HALAMAN
======================================================= -->

<div class="dashboard-heading">

    <div class="dashboard-eyebrow">

        <i class="bi bi-journal-bookmark-fill"></i>

        GARUDAFOOD • TEKNIK

    </div>


    <h1 class="dashboard-title">

        Master Kompetensi

    </h1>


    <div class="dashboard-description">

        Daftar lengkap standar kompetensi dan kriteria
        level keahlian pekerja.

    </div>

</div>


<!-- =======================================================
     KETERANGAN / INFORMASI
======================================================= -->

<div class="cardx">

    <div class="card-title-custom">

        Daftar Kompetensi & Indikator Level

    </div>


    <div class="section-note">

        Data kompetensi diambil dari workbook skill leader
        dan pelaksana teknik.

    </div>

</div>


<!-- =======================================================
     TABEL MASTER KOMPETENSI
======================================================= -->

<div class="cardx">

    <div class="table-responsive">

        <table class="table table-custom mb-0">

            <thead>

                <tr>

                    <th style="width: 5%;">

                        No

                    </th>


                    <th style="width: 25%;">

                        Kompetensi

                    </th>


                    <th class="level-th">

                        Level 1

                    </th>


                    <th class="level-th">

                        Level 2

                    </th>


                    <th class="level-th">

                        Level 3

                    </th>


                    <th class="level-th">

                        Level 4

                    </th>


                    <th class="level-th">

                        Level 5

                    </th>

                </tr>

            </thead>


            <tbody>

                <?php if (
                    $skills &&
                    $skills->num_rows > 0
                ): ?>

                    <?php

                    $no = 1;

                    while (
                        $r =
                        $skills->fetch_assoc()
                    ):

                    ?>

                        <tr>

                            <td
                                class="
                                    text-center
                                    fw-semibold
                                    text-muted
                                "
                            >

                                <?= $no++ ?>

                            </td>


                            <td>

                                <div
                                    class="
                                        fw-bold
                                        text-dark
                                    "
                                    style="
                                        font-size:13px;
                                    "
                                >

                                    <?= e(
                                        $r['nama_skill']
                                    ) ?>

                                </div>


                                <?php if (
                                    isset(
                                        $r['peserta']
                                    )
                                ): ?>

                                    <small
                                        class="text-muted"
                                    >

                                        <i
                                            class="
                                                bi
                                                bi-people
                                                me-1
                                            "
                                        ></i>

                                        <?= $r['peserta'] ?>

                                        pekerja dinilai

                                    </small>

                                <?php endif; ?>

                            </td>


                            <?php for (
                                $i = 1;
                                $i <= 5;
                                $i++
                            ): ?>

                                <td>

                                    <div
                                        class="text-secondary"
                                        style="
                                            font-size:11px;
                                            line-height:1.4;
                                        "
                                    >

                                        <?= e(
                                            $r[
                                                'level_' . $i
                                            ] ?: '-'
                                        ) ?>

                                    </div>

                                </td>

                            <?php endfor; ?>


                        </tr>

                    <?php endwhile; ?>


                <?php else: ?>


                    <tr>

                        <td
                            colspan="7"
                            class="
                                text-center
                                py-4
                                text-muted
                            "
                        >

                            Belum ada data kompetensi
                            yang tersedia.

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