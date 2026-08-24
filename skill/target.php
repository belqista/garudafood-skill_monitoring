<?php
/* =========================================================
   GARUDAFOOD SKILL MONITORING
   TARGET KOMPETENSI
========================================================= */

$page_title = 'Target Kompetensi';


/* =========================================================
   KONEKSI DATABASE
   HARUS DILAKUKAN SEBELUM OUTPUT HTML
========================================================= */

require __DIR__ . '/../partials/header.php';


/* =========================================================
   PROSES SIMPAN TARGET
========================================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    foreach ($_POST['target'] ?? [] as $id => $v) {

        $id = (int) $id;

        $v = max(
            1,
            min(
                5,
                (int) $v
            )
        );

        if ($id <= 0) {
            continue;
        }

        $st = $conn->prepare("
            UPDATE target_skill
            SET target = ?
            WHERE id = ?
        ");

        if ($st) {

            $st->bind_param(
                'ii',
                $v,
                $id
            );

            $st->execute();

            $st->close();
        }
    }


    /*
     * Karena header.php sudah mengeluarkan HTML,
     * redirect header() tidak aman dilakukan di sini.
     *
     * Gunakan JavaScript redirect agar tidak muncul:
     * Cannot modify header information
     */

    echo '
    <script>
        window.location.href = "target.php?saved=1";
    </script>
    ';

    exit;
}


/* =========================================================
   DATA TARGET KOMPETENSI
========================================================= */

$rows = $conn->query("
    SELECT
        ts.id,
        ts.target,
        s.nama_skill,
        j.nama_jabatan

    FROM target_skill ts

    JOIN skill s
        ON s.id = ts.id_skill

    JOIN jabatan j
        ON j.id = ts.id_jabatan

    ORDER BY
        j.nama_jabatan,
        s.id
");

?>

<!-- =======================================================
     CUSTOM STYLE UNTUK TARGET KOMPETENSI
======================================================= -->

<style>

/* =========================================================
   GARUDAFOOD COLOR
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
   PAGE
========================================================= */

body {

    background: var(--gf-bg) !important;

    color: var(--gf-text);

    font-family:
        "Poppins",
        "Segoe UI",
        Arial,
        sans-serif;
}


.main-content {

    background: var(--gf-bg) !important;
}


/* =========================================================
   HEADER CARD BIRU GARUDAFOOD
========================================================= */

.dashboard-heading {

    position: relative;

    margin-bottom: 25px;

    padding: 25px 28px;

    background:
        linear-gradient(
            135deg,
            #092f63 0%,
            #123f7a 100%
        );

    border: 1px solid rgba(255, 255, 255, .08);

    border-radius: 18px;

    box-shadow:
        0 8px 24px rgba(9, 47, 99, .16);

    overflow: hidden;
}


/*
 * Efek cahaya halus di bagian kanan
 */

.dashboard-heading::after {

    content: "";

    position: absolute;

    width: 190px;

    height: 190px;

    right: -70px;

    top: -100px;

    background:
        rgba(255, 255, 255, .055);

    border-radius: 50%;

    pointer-events: none;
}


/* =========================================================
   EYEBROW
========================================================= */

.dashboard-eyebrow {

    position: relative;

    z-index: 1;

    display: inline-flex;

    align-items: center;

    gap: 7px;

    color: #cfe1ff;

    font-size: 11px;

    font-weight: 700;

    text-transform: uppercase;

    letter-spacing: .09em;

    margin-bottom: 8px;
}


.dashboard-eyebrow i {

    color: #e4efff;

    font-size: 14px;
}


/* =========================================================
   TITLE
========================================================= */

.dashboard-title {

    position: relative;

    z-index: 1;

    margin: 0;

    font-size: 28px;

    line-height: 1.2;

    font-weight: 750;

    color: #ffffff;
}


/* =========================================================
   DESCRIPTION
========================================================= */

.dashboard-description {

    position: relative;

    z-index: 1;

    margin-top: 8px;

    color: rgba(255, 255, 255, .72);

    font-size: 13px;

    line-height: 1.6;

    max-width: 850px;
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
        0 5px 20px rgba(20, 43, 76, .045);

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

    line-height: 1.5;
}


/* =========================================================
   TABLE CUSTOM
========================================================= */

.table-custom {

    margin-bottom: 0;
}


.table-custom thead th {

    border-top: 0;

    border-bottom: 1px solid #e3e8ef;

    color: #7d8796;

    font-size: 10px;

    font-weight: 750;

    text-transform: uppercase;

    letter-spacing: .05em;

    padding:
        12px
        14px;

    background: #fafbfd;

    vertical-align: middle;
}


.table-custom tbody td {

    border-bottom: 1px solid #edf0f4;

    color: #384457;

    font-size: 12px;

    padding:
        12px
        14px;

    vertical-align: middle;
}


.table-custom tbody tr:last-child td {

    border-bottom: 0;
}


.table-custom tbody tr:hover {

    background: #fafbfd;
}


/* =========================================================
   FORM SELECT
========================================================= */

.form-select-sm {

    border-color: #dbe2ef;

    border-radius: 7px;

    font-size: 12px;

    padding:
        6px
        10px;

    color: #2d3748;

    background-color: #ffffff;
}


.form-select-sm:focus {

    border-color: var(--gf-blue);

    box-shadow:
        0 0 0 3px rgba(18, 63, 122, .12);
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

    padding:
        10px
        20px;

    font-size: 13px;
}


.btn-primary:hover {

    background:
        var(--gf-blue-hover) !important;

    border-color:
        var(--gf-blue-hover) !important;
}


/* =========================================================
   SUCCESS ALERT
========================================================= */

.target-success-alert {

    border: 0;

    border-radius: 12px;

    background: #e9f8f0;

    color: #0f5132;

    box-shadow:
        0 5px 18px rgba(25, 135, 84, .06);

    font-size: 12px;
}


/* =========================================================
   RESPONSIVE
========================================================= */

@media (max-width: 992px) {

    .dashboard-heading {

        padding:
            22px
            24px;
    }


    .dashboard-title {

        font-size: 25px;
    }

}


@media (max-width: 768px) {

    .dashboard-heading {

        padding:
            20px;

        border-radius: 15px;

        margin-bottom: 20px;
    }


    .dashboard-eyebrow {

        font-size: 10px;
    }


    .dashboard-title {

        font-size: 22px;
    }


    .dashboard-description {

        font-size: 12px;

        line-height: 1.55;
    }


    .cardx {

        padding: 17px;

        border-radius: 14px;
    }


    .card-title-custom {

        font-size: 14px;
    }


    .section-note {

        font-size: 11px;
    }


    .table-custom thead th {

        padding:
            10px
            11px;

        white-space: nowrap;
    }


    .table-custom tbody td {

        padding:
            11px;

        font-size: 11px;
    }


    .btn-primary {

        font-size: 12px;

        padding:
            8px
            14px;
    }

}

</style>


<!-- =======================================================
     HEADER HALAMAN
======================================================= -->

<div class="dashboard-heading">

    <div class="dashboard-eyebrow">

        <i class="bi bi-sliders"></i>

        GARUDAFOOD • TEKNIK

    </div>


    <h1 class="dashboard-title">

        Target Kompetensi

    </h1>


    <div class="dashboard-description">

        Pengaturan standar target nilai kompetensi
        yang harus dicapai oleh setiap jabatan.

    </div>

</div>


<!-- =======================================================
     SUCCESS MESSAGE
======================================================= -->

<?php if (isset($_GET['saved'])): ?>

    <div
        class="
            alert
            target-success-alert
            alert-dismissible
            fade
            show
            mb-4
        "
        role="alert"
    >

        <i class="bi bi-check-circle-fill me-2"></i>

        Target kompetensi berhasil diperbarui.


        <button
            type="button"
            class="btn-close"
            data-bs-dismiss="alert"
            aria-label="Close"
        ></button>

    </div>

<?php endif; ?>


<!-- =======================================================
     FORM TARGET KOMPETENSI
======================================================= -->

<form method="post">

    <div class="cardx">


        <!-- =================================================
             CARD HEADER
        ================================================== -->

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

                    Daftar Target per Jabatan & Skill

                </div>


                <div class="section-note">

                    Nilai target dapat diubah antara
                    1 sampai 5. Gap pada matriks dihitung
                    otomatis dari target ini.

                </div>

            </div>


            <button
                type="submit"
                class="btn btn-primary"
            >

                <i class="bi bi-save me-1"></i>

                Simpan Semua Target

            </button>

        </div>


        <!-- =================================================
             TABLE
        ================================================== -->

        <div class="table-responsive">

            <table class="table table-custom mb-0">

                <thead>

                    <tr>

                        <th style="width:35%;">

                            Jabatan

                        </th>


                        <th style="width:45%;">

                            Skill / Kompetensi

                        </th>


                        <th
                            class="text-center"
                            style="width:20%;"
                        >

                            Target (1-5)

                        </th>

                    </tr>

                </thead>


                <tbody>

                <?php if (
                    $rows &&
                    $rows->num_rows > 0
                ): ?>


                    <?php while (
                        $r = $rows->fetch_assoc()
                    ): ?>

                        <tr>


                            <!-- JABATAN -->

                            <td>

                                <div
                                    class="
                                        fw-semibold
                                        text-dark
                                    "
                                >

                                    <?= e(
                                        $r['nama_jabatan']
                                    ) ?>

                                </div>

                            </td>


                            <!-- SKILL -->

                            <td>

                                <strong>

                                    <?= e(
                                        $r['nama_skill']
                                    ) ?>

                                </strong>

                            </td>


                            <!-- TARGET -->

                            <td class="text-center">

                                <select
                                    name="target[<?= (int) $r['id'] ?>]"
                                    class="
                                        form-select
                                        form-select-sm
                                        mx-auto
                                    "
                                    style="width:90px;"
                                >

                                    <?php
                                    for (
                                        $i = 1;
                                        $i <= 5;
                                        $i++
                                    ):
                                    ?>

                                        <option
                                            value="<?= $i ?>"
                                            <?= (
                                                (int) $r['target']
                                                === $i
                                            )
                                                ? 'selected'
                                                : ''
                                            ?>
                                        >

                                            <?= $i ?>

                                        </option>

                                    <?php endfor; ?>

                                </select>

                            </td>

                        </tr>

                    <?php endwhile; ?>


                <?php else: ?>

                    <tr>

                        <td
                            colspan="3"
                            class="
                                text-center
                                py-4
                                text-muted
                            "
                        >

                            Belum ada data target kompetensi.

                        </td>

                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>


        <!-- =================================================
             BOTTOM SAVE BUTTON
        ================================================== -->

        <div class="mt-4">

            <button
                type="submit"
                class="btn btn-primary"
            >

                <i class="bi bi-save me-1"></i>

                Simpan Semua Target

            </button>

        </div>

    </div>

</form>


<?php

require __DIR__ . '/../partials/footer.php';

?>