<?php
/* =========================================================
   GARUDAFOOD SKILL MONITORING
   PENILAIAN SKILL
========================================================= */


/* =========================================================
   PAGE TITLE
========================================================= */

$page_title = 'Penilaian Skill';


/* =========================================================
   PROSES SIMPAN / UPDATE PENILAIAN
   DILAKUKAN SEBELUM HEADER.HTML
========================================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $id_pekerja =
        (int) ($_POST['id_pekerja'] ?? 0);

    $id_skill =
        (int) ($_POST['id_skill'] ?? 0);

    $tahun =
        (int) ($_POST['tahun'] ?? date('Y'));

    $nilai =
        (int) ($_POST['nilai'] ?? 1);

    $nilai = max(
        1,
        min(
            5,
            $nilai
        )
    );

    $tanggal_penilaian =
        trim(
            $_POST['tanggal_penilaian']
            ?? ''
        );

    if ($tanggal_penilaian === '') {
        $tanggal_penilaian = date('Y-m-d');
    }

    $assessor =
        trim(
            $_POST['assessor']
            ?? ''
        );

    $catatan =
        trim(
            $_POST['catatan']
            ?? ''
        );


    /* =====================================================
       VALIDASI DASAR
    ===================================================== */

    if (
        $id_pekerja <= 0 ||
        $id_skill <= 0 ||
        $tahun < 2020 ||
        $tahun > 2100
    ) {

        header(
            'Location: index.php?error=invalid'
        );

        exit;
    }


    /* =====================================================
       PASTIKAN PEKERJA AKTIF
    ===================================================== */

    $checkWorker = $conn->prepare("
        SELECT id
        FROM pekerja
        WHERE id = ?
          AND status = 'Aktif'
        LIMIT 1
    ");

    if (!$checkWorker) {

        die(
            'Gagal memeriksa pekerja: ' .
            htmlspecialchars(
                $conn->error,
                ENT_QUOTES,
                'UTF-8'
            )
        );
    }

    $checkWorker->bind_param(
        'i',
        $id_pekerja
    );

    $checkWorker->execute();

    $workerResult =
        $checkWorker->get_result();

    if (
        !$workerResult ||
        $workerResult->num_rows <= 0
    ) {

        $checkWorker->close();

        header(
            'Location: index.php?error=worker'
        );

        exit;
    }

    $checkWorker->close();


    /* =====================================================
       PASTIKAN SKILL AKTIF
    ===================================================== */

    $checkSkill = $conn->prepare("
        SELECT id
        FROM skill
        WHERE id = ?
          AND status = 'Aktif'
        LIMIT 1
    ");

    if (!$checkSkill) {

        die(
            'Gagal memeriksa skill: ' .
            htmlspecialchars(
                $conn->error,
                ENT_QUOTES,
                'UTF-8'
            )
        );
    }

    $checkSkill->bind_param(
        'i',
        $id_skill
    );

    $checkSkill->execute();

    $skillResult =
        $checkSkill->get_result();

    if (
        !$skillResult ||
        $skillResult->num_rows <= 0
    ) {

        $checkSkill->close();

        header(
            'Location: index.php?error=skill'
        );

        exit;
    }

    $checkSkill->close();


    /* =====================================================
       SIMPAN / UPDATE
       
       Kombinasi:
       id_pekerja + id_skill + tahun
       
       Jika sudah ada:
       UPDATE nilai terbaru.
       
       Tahun sebelumnya tetap tersimpan.
    ===================================================== */

    $sql = "

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

        (?, ?, ?, ?, ?, ?, ?)

        ON DUPLICATE KEY UPDATE

            nilai =
                VALUES(nilai),

            tanggal_penilaian =
                VALUES(tanggal_penilaian),

            assessor =
                VALUES(assessor),

            catatan =
                VALUES(catatan)

    ";


    $st =
        $conn->prepare($sql);


    if (!$st) {

        die(
            'Gagal menyiapkan penyimpanan penilaian: ' .
            htmlspecialchars(
                $conn->error,
                ENT_QUOTES,
                'UTF-8'
            )
        );
    }


    $st->bind_param(
        'iiiisss',
        $id_pekerja,
        $id_skill,
        $tahun,
        $nilai,
        $tanggal_penilaian,
        $assessor,
        $catatan
    );


    if (!$st->execute()) {

        die(
            'Gagal menyimpan penilaian: ' .
            htmlspecialchars(
                $st->error,
                ENT_QUOTES,
                'UTF-8'
            )
        );
    }


    $st->close();


    header(
        'Location: index.php?saved=1'
    );

    exit;
}


/* =========================================================
   HEADER
========================================================= */

require __DIR__ . '/../partials/header.php';


/* =========================================================
   DATA PEKERJA
========================================================= */

$people = $conn->query("

    SELECT

        p.id,
        p.no_reg,
        p.nama,
        p.departemen,
        p.keterangan

    FROM pekerja p

    WHERE
        p.status = 'Aktif'

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


/* =========================================================
   RIWAYAT PENILAIAN TERBARU
========================================================= */

$recent = $conn->query("

    SELECT

        ps.*,

        p.nama,
        p.no_reg,
        p.departemen,

        s.nama_skill

    FROM penilaian_skill ps

    INNER JOIN pekerja p
        ON p.id = ps.id_pekerja

    INNER JOIN skill s
        ON s.id = ps.id_skill

    ORDER BY

        ps.tanggal_penilaian DESC,
        ps.id DESC

    LIMIT 15

");


/* =========================================================
   ERROR MESSAGE
========================================================= */

$error_message = '';

if (
    isset($_GET['error'])
) {

    switch (
        $_GET['error']
    ) {

        case 'invalid':

            $error_message =
                'Data penilaian yang dikirim tidak valid.';

            break;


        case 'worker':

            $error_message =
                'Pekerja yang dipilih tidak ditemukan atau sudah tidak aktif.';

            break;


        case 'skill':

            $error_message =
                'Skill yang dipilih tidak ditemukan atau sudah tidak aktif.';

            break;


        default:

            $error_message =
                'Terjadi kesalahan pada data penilaian.';

            break;
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
   PAGE HERO / BLUE CARD
========================================================= */

.page-hero {

    position: relative;

    overflow: hidden;

    background:
        linear-gradient(
            135deg,
            #174b8d 0%,
            #123f7a 48%,
            #0c346d 100%
        );

    border-radius: 17px;

    min-height: 141px;

    padding: 25px 30px;

    margin-bottom: 25px;

    box-shadow:
        0 10px 28px
        rgba(18, 63, 122, .16);

    color: #ffffff;

}


/* =========================================================
   HERO CONTENT
========================================================= */

.page-hero-content {

    position: relative;

    z-index: 3;

    max-width: 850px;

}


.page-hero-eyebrow {

    display: inline-flex;

    align-items: center;

    gap: 7px;

    color: rgba(255,255,255,.95);

    font-size: 12px;

    font-weight: 700;

    text-transform: uppercase;

    letter-spacing: .07em;

    margin-bottom: 7px;

}


.page-hero-title {

    margin: 0;

    color: #ffffff;

    font-size: 28px;

    line-height: 1.2;

    font-weight: 800;

}


.page-hero-description {

    margin-top: 8px;

    color: rgba(255,255,255,.82);

    font-size: 13px;

    line-height: 1.6;

}


/* =========================================================
   HERO DECORATION
========================================================= */

.page-hero-circle-1 {

    position: absolute;

    width: 155px;

    height: 155px;

    border-radius: 50%;

    right: 65px;

    top: -75px;

    background:
        rgba(255,255,255,.035);

}


.page-hero-circle-2 {

    position: absolute;

    width: 125px;

    height: 125px;

    border-radius: 50%;

    right: -20px;

    bottom: -60px;

    background:
        rgba(255,255,255,.055);

}


.page-hero-circle-3 {

    position: absolute;

    width: 80px;

    height: 80px;

    border-radius: 50%;

    right: 145px;

    bottom: -35px;

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

    margin-bottom: 18px;

    display: flex;

    align-items: center;

    gap: 8px;

}


.card-title-custom i {

    color: var(--gf-blue);

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

    min-height: 43px;

}


.form-control:focus,
.form-select:focus {

    border-color:
        var(--gf-blue);

    box-shadow:
        0 0 0 3px
        rgba(18, 63, 122, 0.12);

}


/* =========================================================
   SEARCH SELECT
========================================================= */

.search-select {

    position: relative;

    width: 100%;

}


.search-select-input {

    width: 100%;

    background: #fff;

    cursor: text;

    padding-right: 42px;

}


.search-select-input::placeholder {

    color: #9aa4b2;

}


.search-select-dropdown {

    position: absolute;

    top: calc(100% + 5px);

    left: 0;

    right: 0;

    z-index: 9999;

    background: #fff;

    border: 1px solid #dbe2ef;

    border-radius: 10px;

    box-shadow:
        0 12px 30px
        rgba(20, 43, 76, .14);

    max-height: 250px;

    overflow-y: auto;

    display: none;

}


.search-select.open
.search-select-dropdown {

    display: block;

}


.search-option {

    padding: 10px 13px;

    cursor: pointer;

    border-bottom:
        1px solid #f0f2f5;

    transition:
        .15s ease;

}


.search-option:last-child {

    border-bottom: 0;

}


.search-option:hover {

    background:
        #f3f7fd;

}


.search-option-name {

    font-size: 13px;

    font-weight: 600;

    color: #172033;

}


.search-option-meta {

    margin-top: 3px;

    font-size: 10px;

    color: #7d8796;

}


.search-option-empty {

    padding: 14px;

    text-align: center;

    color: #8a94a4;

    font-size: 12px;

}


.search-clear {

    position: absolute;

    right: 10px;

    top: 50%;

    transform:
        translateY(-50%);

    width: 25px;

    height: 25px;

    border: 0;

    background: transparent;

    color: #8a94a4;

    display: none;

    align-items: center;

    justify-content: center;

    cursor: pointer;

    border-radius: 50%;

    z-index: 2;

}


.search-clear:hover {

    background:
        #f1f3f6;

    color:
        #4a5568;

}


.search-select.has-value
.search-clear {

    display: flex;

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
   SECONDARY BUTTON
========================================================= */

.btn-light {

    border-color:
        #dbe2ef !important;

    color:
        #536174 !important;

    background:
        #ffffff !important;

    border-radius:
        9px;

    font-size:
        13px;

}


.btn-light:hover {

    background:
        #f7f9fc !important;

}


/* =========================================================
   TABLE
========================================================= */

.table-custom {

    margin-bottom: 0;

    min-width: 900px;

}


.table-responsive {

    border-radius: 11px;

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

    padding: 12px 10px;

    background:
        #fafbfd;

    white-space: nowrap;

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

    background:
        #fafbfd;

}


.table-custom tbody tr:last-child td {

    border-bottom: 0;

}


/* =========================================================
   BADGE NILAI
========================================================= */

.score-badge {

    display: inline-flex;

    align-items: center;

    justify-content: center;

    width: 30px;

    height: 30px;

    border-radius: 8px;

    font-weight: 750;

    font-size: 12px;

}


.score-1 {

    background:
        #ffecee;

    color:
        #dc3545;

}


.score-2 {

    background:
        #fff4d8;

    color:
        #b77900;

}


.score-3 {

    background:
        #eaf2ff;

    color:
        #123f7a;

}


.score-4 {

    background:
        #e9f8f0;

    color:
        #198754;

}


.score-5 {

    background:
        #d1e7dd;

    color:
        #0f5132;

}


/* =========================================================
   WORKER BADGE
========================================================= */

.worker-id {

    display: inline-flex;

    align-items: center;

    padding: 4px 7px;

    border-radius: 6px;

    background:
        #f4f6f9;

    border:
        1px solid #e2e6ec;

    color:
        #596579;

    font-size: 10px;

    font-weight: 700;

}


/* =========================================================
   INFO BOX
========================================================= */

.assessment-info {

    display: flex;

    align-items: flex-start;

    gap: 10px;

    background:
        #f3f7fd;

    border:
        1px solid #dce8f8;

    border-radius: 10px;

    padding: 11px 13px;

    margin-bottom: 20px;

    color:
        #536b8c;

    font-size: 11px;

    line-height: 1.5;

}


.assessment-info i {

    color:
        var(--gf-blue);

    font-size: 15px;

    margin-top: 1px;

}


/* =========================================================
   FORM ACTION
========================================================= */

.form-action {

    border-top:
        1px solid #edf0f4;

    padding-top:
        18px;

}


/* =========================================================
   EMPTY TABLE
========================================================= */

.empty-history {

    text-align: center;

    padding: 40px 20px !important;

    color: #8a94a4 !important;

}


.empty-history i {

    font-size: 30px;

    display: block;

    margin-bottom: 10px;

    color: #b2bbc8;

}


/* =========================================================
   RESPONSIVE
========================================================= */

@media (max-width: 768px) {

    .page-hero {

        min-height: 145px;

        padding: 22px 20px;

        border-radius: 15px;

    }


    .page-hero-title {

        font-size: 23px;

    }


    .page-hero-description {

        font-size: 12px;

        max-width: 90%;

    }


    .page-hero-circle-1 {

        right: -35px;

    }


    .page-hero-circle-3 {

        display: none;

    }


    .cardx {

        padding: 17px;

        border-radius: 14px;

    }


    .dashboard-title {

        font-size: 23px;

    }


    .search-select-dropdown {

        max-height: 220px;

    }

}


/* =========================================================
   SMALL MOBILE
========================================================= */

@media (max-width: 480px) {

    .page-hero {

        padding: 20px 18px;

    }


    .page-hero-eyebrow {

        font-size: 10px;

    }


    .page-hero-title {

        font-size: 21px;

    }


    .page-hero-description {

        font-size: 11px;

        max-width: 95%;

    }


    .cardx {

        padding: 15px;

    }

}

</style>


<!-- =======================================================
     HEADER / BLUE HERO CARD
======================================================= -->

<div class="page-hero">

    <!-- DECORATION -->

    <div
        class="page-hero-circle-1"
    ></div>

    <div
        class="page-hero-circle-2"
    ></div>

    <div
        class="page-hero-circle-3"
    ></div>


    <!-- CONTENT -->

    <div class="page-hero-content">

        <div class="page-hero-eyebrow">

            <i
                class="bi bi-award-fill"
            ></i>

            GARUDAFOOD • TEKNIK

        </div>


        <h1 class="page-hero-title">

            Penilaian Skill

        </h1>


        <div class="page-hero-description">

            Lakukan input atau pembaruan nilai kompetensi
            pekerja secara berkala berdasarkan hasil assessment.

        </div>

    </div>

</div>


<!-- =======================================================
     SUCCESS
======================================================= -->

<?php if (isset($_GET['saved'])): ?>

    <div
        class="
            alert
            alert-success
            alert-dismissible
            fade
            show
            border-0
            shadow-sm
            mb-4
        "
        role="alert"
        style="
            border-radius:12px;
            background:#e9f8f0;
            color:#0f5132;
        "
    >

        <i
            class="bi bi-check-circle-fill me-2"
        ></i>

        Nilai skill berhasil diperbarui.
        History tahun sebelumnya tetap tersimpan.

        <button
            type="button"
            class="btn-close"
            data-bs-dismiss="alert"
            aria-label="Close"
        ></button>

    </div>

<?php endif; ?>


<!-- =======================================================
     ERROR
======================================================= -->

<?php if ($error_message !== ''): ?>

    <div
        class="
            alert
            alert-danger
            alert-dismissible
            fade
            show
            border-0
            shadow-sm
            mb-4
        "
        role="alert"
        style="
            border-radius:12px;
        "
    >

        <i
            class="
                bi
                bi-exclamation-triangle-fill
                me-2
            "
        ></i>

        <?= e($error_message) ?>

        <button
            type="button"
            class="btn-close"
            data-bs-dismiss="alert"
            aria-label="Close"
        ></button>

    </div>

<?php endif; ?>


<!-- =======================================================
     FORM INPUT / UPDATE
======================================================= -->

<div class="cardx">

    <div class="card-title-custom">

        <i
            class="
                bi
                bi-pencil-square
            "
        ></i>

        Update Nilai Skill Terbaru

    </div>


    <div class="assessment-info">

        <i
            class="
                bi
                bi-info-circle-fill
            "
        ></i>

        <div>

            Pilih pekerja dan skill yang akan dinilai.
            Jika kombinasi pekerja, skill, dan tahun sudah
            memiliki data, nilai tersebut akan diperbarui.
            Data dari tahun sebelumnya tetap tersimpan sebagai history.

        </div>

    </div>


    <form
        method="post"
        class="row g-3"
        id="penilaianForm"
    >


        <!-- =================================================
             PEKERJA
        ================================================== -->

        <div class="col-md-5">

            <label class="form-label">

                Pekerja

            </label>


            <div
                class="search-select"
                id="workerSearch"
            >

                <input
                    type="hidden"
                    name="id_pekerja"
                    id="id_pekerja"
                    value=""
                >


                <input
                    type="text"
                    id="workerSearchInput"
                    class="form-control search-select-input"
                    placeholder="Ketik nama pekerja..."
                    autocomplete="off"
                >


                <button
                    type="button"
                    class="search-clear"
                    id="workerClear"
                    title="Hapus pilihan"
                >

                    <i
                        class="bi bi-x"
                    ></i>

                </button>


                <div
                    class="search-select-dropdown"
                    id="workerDropdown"
                >

                    <?php if (
                        $people &&
                        $people->num_rows > 0
                    ): ?>

                        <?php while (
                            $p =
                                $people->fetch_assoc()
                        ): ?>

                            <div
                                class="
                                    search-option
                                    worker-option
                                "
                                data-id="<?= (int)$p['id'] ?>"
                                data-name="<?= e($p['nama']) ?>"
                                data-search="<?= e(
                                    strtolower(
                                        $p['nama'] . ' ' .
                                        ($p['no_reg'] ?? '') . ' ' .
                                        ($p['departemen'] ?? '')
                                    )
                                ) ?>"
                            >

                                <div
                                    class="
                                        search-option-name
                                    "
                                >

                                    <?= e(
                                        $p['nama']
                                    ) ?>

                                </div>


                                <div
                                    class="
                                        search-option-meta
                                    "
                                >

                                    <?php if (
                                        !empty(
                                            $p['no_reg']
                                        )
                                    ): ?>

                                        No. Reg:

                                        <?= e(
                                            $p['no_reg']
                                        ) ?>

                                    <?php endif; ?>


                                    <?php if (
                                        !empty(
                                            $p['departemen']
                                        )
                                    ): ?>

                                        &nbsp; • &nbsp;

                                        <?= e(
                                            $p['departemen']
                                        ) ?>

                                    <?php endif; ?>

                                </div>

                            </div>

                        <?php endwhile; ?>

                    <?php else: ?>

                        <div
                            class="
                                search-option-empty
                            "
                        >

                            Tidak ada pekerja aktif.

                        </div>

                    <?php endif; ?>

                </div>

            </div>

        </div>


        <!-- =================================================
             SKILL
        ================================================== -->

        <div class="col-md-5">

            <label class="form-label">

                Skill

            </label>


            <div
                class="search-select"
                id="skillSearch"
            >

                <input
                    type="hidden"
                    name="id_skill"
                    id="id_skill"
                    value=""
                >


                <input
                    type="text"
                    id="skillSearchInput"
                    class="form-control search-select-input"
                    placeholder="Ketik nama skill..."
                    autocomplete="off"
                >


                <button
                    type="button"
                    class="search-clear"
                    id="skillClear"
                    title="Hapus pilihan"
                >

                    <i
                        class="bi bi-x"
                    ></i>

                </button>


                <div
                    class="search-select-dropdown"
                    id="skillDropdown"
                >

                    <?php if (
                        $skills &&
                        $skills->num_rows > 0
                    ): ?>

                        <?php while (
                            $s =
                                $skills->fetch_assoc()
                        ): ?>

                            <div
                                class="
                                    search-option
                                    skill-option
                                "
                                data-id="<?= (int)$s['id'] ?>"
                                data-name="<?= e(
                                    $s['nama_skill']
                                ) ?>"
                                data-search="<?= e(
                                    strtolower(
                                        $s['nama_skill']
                                    )
                                ) ?>"
                            >

                                <div
                                    class="
                                        search-option-name
                                    "
                                >

                                    <?= e(
                                        $s['nama_skill']
                                    ) ?>

                                </div>

                            </div>

                        <?php endwhile; ?>

                    <?php else: ?>

                        <div
                            class="
                                search-option-empty
                            "
                        >

                            Tidak ada skill aktif.

                        </div>

                    <?php endif; ?>

                </div>

            </div>

        </div>


        <!-- =================================================
             TAHUN
        ================================================== -->

        <div class="col-md-2">

            <label class="form-label">

                Tahun

            </label>


            <input
                name="tahun"
                type="number"
                min="2020"
                max="2100"
                value="<?= date('Y') ?>"
                class="form-control"
                required
            >

        </div>


        <!-- =================================================
             NILAI
        ================================================== -->

        <div class="col-md-2">

            <label class="form-label">

                Nilai (1-5)

            </label>


            <select
                name="nilai"
                class="form-select"
                required
            >

                <?php for (
                    $i = 1;
                    $i <= 5;
                    $i++
                ): ?>

                    <option
                        value="<?= $i ?>"
                    >

                        <?= $i ?>

                    </option>

                <?php endfor; ?>

            </select>

        </div>


        <!-- =================================================
             TANGGAL
        ================================================== -->

        <div class="col-md-3">

            <label class="form-label">

                Tanggal Penilaian

            </label>


            <input
                name="tanggal_penilaian"
                type="date"
                value="<?= date('Y-m-d') ?>"
                class="form-control"
                required
            >

        </div>


        <!-- =================================================
             ASSESSOR
        ================================================== -->

        <div class="col-md-4">

            <label class="form-label">

                Assessor

            </label>


            <input
                name="assessor"
                class="form-control"
                placeholder="Nama Supervisor / Assessor"
            >

        </div>


        <!-- =================================================
             CATATAN
        ================================================== -->

        <div class="col-md-5">

            <label class="form-label">

                Catatan

            </label>


            <input
                name="catatan"
                class="form-control"
                placeholder="Catatan atau evaluasi assessment..."
            >

        </div>


        <!-- =================================================
             BUTTON
        ================================================== -->

        <div class="col-12">

            <div
                class="
                    form-action
                    mt-2
                "
            >

                <button
                    type="submit"
                    class="btn btn-primary"
                >

                    <i
                        class="
                            bi
                            bi-save
                            me-1
                        "
                    ></i>

                    Simpan Penilaian

                </button>

            </div>

        </div>


    </form>

</div>


<!-- =======================================================
     RIWAYAT PENILAIAN
======================================================= -->

<div class="cardx">

    <div class="card-title-custom">

        <i
            class="
                bi
                bi-clock-history
            "
        ></i>

        Riwayat Penilaian Terbaru

    </div>


    <div class="table-responsive">

        <table
            class="
                table
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

                <?php if (
                    $recent &&
                    $recent->num_rows > 0
                ): ?>


                    <?php while (
                        $r =
                        $recent->fetch_assoc()
                    ): ?>


                        <tr>

                            <!-- TANGGAL -->

                            <td>

                                <?= e(
                                    $r['tanggal_penilaian']
                                    ?: '-'
                                ) ?>

                            </td>


                            <!-- PEKERJA -->

                            <td>

                                <div
                                    class="
                                        fw-semibold
                                        text-dark
                                    "
                                >

                                    <?= e(
                                        $r['nama']
                                    ) ?>

                                </div>


                                <div
                                    class="
                                        mt-1
                                        d-flex
                                        align-items-center
                                        gap-2
                                        flex-wrap
                                    "
                                >

                                    <?php if (
                                        !empty(
                                            $r['no_reg']
                                        )
                                    ): ?>

                                        <span
                                            class="worker-id"
                                        >

                                            No. Reg:

                                            <?= e(
                                                $r['no_reg']
                                            ) ?>

                                        </span>

                                    <?php endif; ?>


                                    <?php if (
                                        !empty(
                                            $r['departemen']
                                        )
                                    ): ?>

                                        <small
                                            class="
                                                text-muted
                                            "
                                        >

                                            <?= e(
                                                $r['departemen']
                                            ) ?>

                                        </small>

                                    <?php endif; ?>

                                </div>

                            </td>


                            <!-- SKILL -->

                            <td>

                                <?= e(
                                    $r['nama_skill']
                                ) ?>

                            </td>


                            <!-- TAHUN -->

                            <td>

                                <?= (int)
                                    $r['tahun']
                                ?>

                            </td>


                            <!-- NILAI -->

                            <td>

                                <?php

                                $nilaiHistory =
                                    (int)
                                    $r['nilai'];

                                $nilaiHistory =
                                    max(
                                        1,
                                        min(
                                            5,
                                            $nilaiHistory
                                        )
                                    );

                                ?>


                                <span
                                    class="
                                        score-badge
                                        score-<?= $nilaiHistory ?>
                                    "
                                >

                                    <?= $nilaiHistory ?>

                                </span>

                            </td>


                            <!-- ASSESSOR -->

                            <td>

                                <?= e(
                                    $r['assessor']
                                    ?: '-'
                                ) ?>

                            </td>


                            <!-- CATATAN -->

                            <td>

                                <span
                                    class="text-muted"
                                >

                                    <?= e(
                                        $r['catatan']
                                        ?: '-'
                                    ) ?>

                                </span>

                            </td>


                        </tr>


                    <?php endwhile; ?>


                <?php else: ?>


                    <tr>

                        <td
                            colspan="7"
                            class="
                                empty-history
                            "
                        >

                            <i
                                class="
                                    bi
                                    bi-inbox
                                "
                            ></i>

                            Belum ada riwayat
                            penilaian skill.

                        </td>

                    </tr>


                <?php endif; ?>

            </tbody>

        </table>

    </div>

</div>


<script>

/* =========================================================
   SEARCHABLE SELECT
========================================================= */

document.addEventListener(
    'DOMContentLoaded',
    function () {


        function setupSearchSelect(config) {

            const wrapper =
                document.getElementById(
                    config.wrapper
                );


            const input =
                document.getElementById(
                    config.input
                );


            const hidden =
                document.getElementById(
                    config.hidden
                );


            const dropdown =
                document.getElementById(
                    config.dropdown
                );


            const clearButton =
                document.getElementById(
                    config.clear
                );


            if (
                !wrapper ||
                !input ||
                !hidden ||
                !dropdown
            ) {

                return;

            }


            const options =
                Array.from(
                    dropdown.querySelectorAll(
                        config.optionClass
                    )
                );


            /* =================================================
               OPEN
            ================================================= */

            function openDropdown() {

                wrapper.classList.add(
                    'open'
                );

                filterOptions();

            }


            /* =================================================
               CLOSE
            ================================================= */

            function closeDropdown() {

                wrapper.classList.remove(
                    'open'
                );

            }


            /* =================================================
               FILTER
            ================================================= */

            function filterOptions() {

                const keyword =
                    input.value
                        .toLowerCase()
                        .trim();


                let visibleCount = 0;


                options.forEach(
                    function (option) {

                        const searchText =
                            (
                                option.dataset.search
                                || ''
                            ).toLowerCase();


                        if (
                            keyword === '' ||
                            searchText.includes(
                                keyword
                            )
                        ) {

                            option.style.display =
                                '';

                            visibleCount++;

                        } else {

                            option.style.display =
                                'none';

                        }

                    }
                );


                let emptyMessage =
                    dropdown.querySelector(
                        '.search-no-result'
                    );


                if (
                    visibleCount === 0 &&
                    options.length > 0
                ) {

                    if (!emptyMessage) {

                        emptyMessage =
                            document.createElement(
                                'div'
                            );

                        emptyMessage.className =
                            'search-option-empty search-no-result';

                        emptyMessage.textContent =
                            'Data tidak ditemukan.';

                        dropdown.appendChild(
                            emptyMessage
                        );

                    }


                    emptyMessage.style.display =
                        '';

                } else if (
                    emptyMessage
                ) {

                    emptyMessage.style.display =
                        'none';

                }

            }


            /* =================================================
               PILIH DATA
            ================================================= */

            options.forEach(
                function (option) {

                    option.addEventListener(
                        'click',
                        function () {

                            const id =
                                option.dataset.id;

                            const name =
                                option.dataset.name;


                            hidden.value =
                                id;

                            input.value =
                                name;


                            wrapper.classList.add(
                                'has-value'
                            );


                            closeDropdown();

                        }
                    );

                }
            );


            /* =================================================
               KETIK
            ================================================= */

            input.addEventListener(
                'input',
                function () {

                    hidden.value =
                        '';

                    wrapper.classList.remove(
                        'has-value'
                    );


                    openDropdown();

                }
            );


            /* =================================================
               FOCUS
            ================================================= */

            input.addEventListener(
                'focus',
                function () {

                    openDropdown();

                }
            );


            /* =================================================
               CLEAR
            ================================================= */

            if (clearButton) {

                clearButton.addEventListener(
                    'click',
                    function () {

                        input.value =
                            '';

                        hidden.value =
                            '';

                        wrapper.classList.remove(
                            'has-value'
                        );


                        openDropdown();

                        input.focus();

                    }
                );

            }


            /* =================================================
               CLICK OUTSIDE
            ================================================= */

            document.addEventListener(
                'click',
                function (event) {

                    if (
                        !wrapper.contains(
                            event.target
                        )
                    ) {

                        closeDropdown();

                    }

                }
            );

        }


        /* =====================================================
           PEKERJA
        ===================================================== */

        setupSearchSelect({

            wrapper:
                'workerSearch',

            input:
                'workerSearchInput',

            hidden:
                'id_pekerja',

            dropdown:
                'workerDropdown',

            clear:
                'workerClear',

            optionClass:
                '.worker-option'

        });


        /* =====================================================
           SKILL
        ===================================================== */

        setupSearchSelect({

            wrapper:
                'skillSearch',

            input:
                'skillSearchInput',

            hidden:
                'id_skill',

            dropdown:
                'skillDropdown',

            clear:
                'skillClear',

            optionClass:
                '.skill-option'

        });


        /* =====================================================
           VALIDASI FORM
        ===================================================== */

        const form =
            document.getElementById(
                'penilaianForm'
            );


        if (form) {

            form.addEventListener(
                'submit',
                function (event) {

                    const workerId =
                        document.getElementById(
                            'id_pekerja'
                        ).value;


                    const skillId =
                        document.getElementById(
                            'id_skill'
                        ).value;


                    /* =========================================
                       VALIDASI PEKERJA
                    ========================================= */

                    if (
                        !workerId ||
                        parseInt(
                            workerId,
                            10
                        ) <= 0
                    ) {

                        event.preventDefault();


                        alert(
                            'Silakan pilih pekerja terlebih dahulu.'
                        );


                        document
                            .getElementById(
                                'workerSearchInput'
                            )
                            .focus();


                        return;

                    }


                    /* =========================================
                       VALIDASI SKILL
                    ========================================= */

                    if (
                        !skillId ||
                        parseInt(
                            skillId,
                            10
                        ) <= 0
                    ) {

                        event.preventDefault();


                        alert(
                            'Silakan pilih skill terlebih dahulu.'
                        );


                        document
                            .getElementById(
                                'skillSearchInput'
                            )
                            .focus();


                        return;

                    }

                }
            );

        }

    }
);

</script>


<?php

require __DIR__ . '/../partials/footer.php';

?>