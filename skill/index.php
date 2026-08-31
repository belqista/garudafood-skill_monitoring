<?php

/* =========================================================
   MASTER KOMPETENSI / SKILL
   GARUDAFOOD SKILL MONITORING
========================================================= */

$page_title = 'Kompetensi / Skill';

require __DIR__ . '/../partials/header.php';


/* =========================================================
   HELPER REDIRECT
========================================================= */

function redirectSkill($params = [])
{
    $url = 'index.php';

    if (!empty($params)) {
        $url .= '?' . http_build_query($params);
    }

    header('Location: ' . $url);
    exit;
}


/* =========================================================
   PROSES TAMBAH / EDIT SKILL
========================================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';


    /* =====================================================
       TAMBAH SKILL
    ===================================================== */

    if ($action === 'tambah') {

        $nama_skill = trim(
            $_POST['nama_skill'] ?? ''
        );

        $level_1 = trim(
            $_POST['level_1'] ?? ''
        );

        $level_2 = trim(
            $_POST['level_2'] ?? ''
        );

        $level_3 = trim(
            $_POST['level_3'] ?? ''
        );

        $level_4 = trim(
            $_POST['level_4'] ?? ''
        );

        $level_5 = trim(
            $_POST['level_5'] ?? ''
        );


        /* =================================================
           VALIDASI
        ================================================= */

        if ($nama_skill === '') {

            redirectSkill([
                'error' => 'Nama skill wajib diisi.',
                'modal' => 'tambah'
            ]);

        }


        /* =================================================
           CEK NAMA SKILL
        ================================================= */

        $stmt = $conn->prepare("
            SELECT id
            FROM skill
            WHERE nama_skill = ?
            LIMIT 1
        ");

        if (!$stmt) {

            redirectSkill([
                'error' => 'Query pengecekan skill gagal.',
                'modal' => 'tambah'
            ]);

        }


        $stmt->bind_param(
            's',
            $nama_skill
        );

        $stmt->execute();

        $cek = $stmt
            ->get_result()
            ->fetch_assoc();

        $stmt->close();


        if ($cek) {

            redirectSkill([
                'error' => 'Skill tersebut sudah tersedia.',
                'modal' => 'tambah'
            ]);

        }


        /* =================================================
           INSERT SKILL
           
           STATUS TIDAK DISENTUH.
           Jika database memiliki DEFAULT,
           maka default tersebut akan digunakan.
        ================================================= */

        $stmt = $conn->prepare("
            INSERT INTO skill
            (
                nama_skill,
                level_1,
                level_2,
                level_3,
                level_4,
                level_5
            )
            VALUES
            (?, ?, ?, ?, ?, ?)
        ");

        if (!$stmt) {

            redirectSkill([
                'error' => 'Query tambah skill gagal: ' . $conn->error,
                'modal' => 'tambah'
            ]);

        }


        $stmt->bind_param(
            'ssssss',
            $nama_skill,
            $level_1,
            $level_2,
            $level_3,
            $level_4,
            $level_5
        );


        if ($stmt->execute()) {

            $stmt->close();

            redirectSkill([
                'success' => 'Skill berhasil ditambahkan.'
            ]);

        }


        $stmt->close();


        redirectSkill([
            'error' => 'Skill gagal ditambahkan.',
            'modal' => 'tambah'
        ]);

    }


    /* =====================================================
       EDIT SKILL
    ===================================================== */

    if ($action === 'edit') {

        $id = (int)(
            $_POST['id'] ?? 0
        );


        $nama_skill = trim(
            $_POST['nama_skill'] ?? ''
        );


        $level_1 = trim(
            $_POST['level_1'] ?? ''
        );

        $level_2 = trim(
            $_POST['level_2'] ?? ''
        );

        $level_3 = trim(
            $_POST['level_3'] ?? ''
        );

        $level_4 = trim(
            $_POST['level_4'] ?? ''
        );

        $level_5 = trim(
            $_POST['level_5'] ?? ''
        );


        /* =================================================
           VALIDASI ID
        ================================================= */

        if ($id <= 0) {

            redirectSkill([
                'error' => 'ID skill tidak valid.'
            ]);

        }


        /* =================================================
           VALIDASI NAMA
        ================================================= */

        if ($nama_skill === '') {

            redirectSkill([
                'error' => 'Nama skill wajib diisi.',
                'edit' => $id
            ]);

        }


        /* =================================================
           CEK NAMA DUPLIKAT
        ================================================= */

        $stmt = $conn->prepare("
            SELECT id
            FROM skill
            WHERE nama_skill = ?
              AND id <> ?
            LIMIT 1
        ");

        if (!$stmt) {

            redirectSkill([
                'error' => 'Query pengecekan skill gagal.'
            ]);

        }


        $stmt->bind_param(
            'si',
            $nama_skill,
            $id
        );

        $stmt->execute();

        $cek = $stmt
            ->get_result()
            ->fetch_assoc();

        $stmt->close();


        if ($cek) {

            redirectSkill([
                'error' => 'Nama skill tersebut sudah digunakan.',
                'edit' => $id
            ]);

        }


        /* =================================================
           UPDATE SKILL
           
           STATUS TIDAK DISENTUH.
        ================================================= */

        $stmt = $conn->prepare("
            UPDATE skill

            SET
                nama_skill = ?,
                level_1 = ?,
                level_2 = ?,
                level_3 = ?,
                level_4 = ?,
                level_5 = ?

            WHERE id = ?

            LIMIT 1
        ");

        if (!$stmt) {

            redirectSkill([
                'error' => 'Query edit skill gagal: ' . $conn->error
            ]);

        }


        $stmt->bind_param(
            'ssssssi',
            $nama_skill,
            $level_1,
            $level_2,
            $level_3,
            $level_4,
            $level_5,
            $id
        );


        if ($stmt->execute()) {

            $stmt->close();

            redirectSkill([
                'success' => 'Skill berhasil diperbarui.'
            ]);

        }


        $stmt->close();


        redirectSkill([
            'error' => 'Skill gagal diperbarui.'
        ]);

    }

}


/* =========================================================
   PESAN
========================================================= */

$success = $_GET['success'] ?? '';

$error = $_GET['error'] ?? '';

$open_modal = $_GET['modal'] ?? '';

$edit_id = (int)(
    $_GET['edit'] ?? 0
);


/* =========================================================
   DATA SKILL
========================================================= */

$skills = $conn->query("
    SELECT
        s.*,

        COUNT(
            DISTINCT ps.id_pekerja
        ) AS peserta

    FROM skill s

    LEFT JOIN penilaian_skill ps
        ON ps.id_skill = s.id

    GROUP BY
        s.id

    ORDER BY
        s.id ASC
");


/* =========================================================
   DATA EDIT
========================================================= */

$edit_skill = null;


if ($edit_id > 0) {

    $stmt = $conn->prepare("
        SELECT *
        FROM skill
        WHERE id = ?
        LIMIT 1
    ");

    if ($stmt) {

        $stmt->bind_param(
            'i',
            $edit_id
        );

        $stmt->execute();

        $edit_skill = $stmt
            ->get_result()
            ->fetch_assoc();

        $stmt->close();

    }

}

?>


<!-- =======================================================
     STYLE
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


body {

    background:
        var(--gf-bg) !important;

}


/* =========================================================
   HEADER
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


.dashboard-title {

    margin: 0;

    font-size: 28px;

    line-height: 1.2;

    font-weight: 750;

    color: #ffffff;

    position: relative;

    z-index: 1;

}


.dashboard-description {

    margin-top: 8px;

    color:
        rgba(255,255,255,.76);

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

    border:
        1px solid var(--gf-border);

    border-radius: 17px;

    padding: 24px;

    box-shadow:
        0 5px 20px
        rgba(20, 43, 76, .045);

    margin-bottom: 20px;

}


.card-title-custom {

    color: var(--gf-text);

    font-size: 16px;

    font-weight: 750;

    margin-bottom: 4px;

}


.section-note {

    color: #8a94a4;

    font-size: 12px;

}


/* =========================================================
   BUTTON
========================================================= */

.btn-gf {

    background:
        var(--gf-blue);

    border: none;

    color: #fff;

    font-weight: 600;

    border-radius: 9px;

    padding: 9px 15px;

    font-size: 12px;

}


.btn-gf:hover {

    background:
        var(--gf-blue-hover);

    color: #fff;

}


.btn-edit {

    border:
        1px solid #d6e1ef;

    background: #fff;

    color:
        var(--gf-blue);

    border-radius: 8px;

    padding: 6px 10px;

    font-size: 11px;

    font-weight: 600;

}


.btn-edit:hover {

    background:
        var(--gf-blue-light);

    color:
        var(--gf-blue-dark);

}


/* =========================================================
   TABLE
========================================================= */

.table-custom {

    margin-bottom: 0;

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


.level-th {

    text-align: center;

    width: 13%;

}


/* =========================================================
   MODAL
========================================================= */

.modal-content {

    border: none;

    border-radius: 16px;

    overflow: hidden;

    box-shadow:
        0 20px 60px
        rgba(0,0,0,.18);

}


.modal-header {

    border-bottom:
        1px solid #edf0f4;

    padding: 18px 20px;

}


.modal-title {

    color:
        var(--gf-blue-dark);

    font-size: 17px;

    font-weight: 750;

}


.modal-subtitle {

    font-size: 11px;

    color:
        var(--gf-muted);

    margin-top: 2px;

}


.modal-body {

    padding: 20px;

}


.form-label {

    font-size: 12px;

    font-weight: 700;

    color: #344054;

    margin-bottom: 6px;

}


.form-control {

    border:
        1px solid #d9e1ec;

    border-radius: 9px;

    font-size: 12px;

    padding: 9px 11px;

    color: #172033;

}


.form-control:focus {

    border-color: #7fa6d9;

    box-shadow:
        0 0 0 3px
        rgba(18,63,122,.08);

}


.level-box {

    background: #f8fafc;

    border:
        1px solid #edf0f4;

    border-radius: 12px;

    padding: 12px;

    margin-top: 5px;

}


.level-title {

    font-size: 11px;

    font-weight: 700;

    color:
        var(--gf-blue-dark);

    margin-bottom: 9px;

}


.modal-footer {

    border-top:
        1px solid #edf0f4;

    padding: 13px 20px;

}


.btn-cancel {

    border:
        1px solid #d9e1ec;

    background: #fff;

    color: #475467;

    border-radius: 9px;

    font-size: 12px;

    font-weight: 600;

    padding: 8px 14px;

}


.btn-save {

    background:
        var(--gf-blue);

    color: #fff;

    border: none;

    border-radius: 9px;

    font-size: 12px;

    font-weight: 700;

    padding: 8px 15px;

}


.btn-save:hover {

    background:
        var(--gf-blue-hover);

    color: #fff;

}


/* =========================================================
   ALERT
========================================================= */

.alert-custom {

    border: none;

    border-radius: 10px;

    font-size: 12px;

    padding: 11px 14px;

    margin-bottom: 18px;

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


    .table-custom {

        min-width: 950px;

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
     PESAN
======================================================= -->

<?php if ($success !== ''): ?>

<div
    class="alert alert-success alert-custom"
>

    <i
        class="
            bi
            bi-check-circle-fill
            me-2
        "
    ></i>

    <?= e($success) ?>

</div>

<?php endif; ?>


<?php if ($error !== ''): ?>

<div
    class="alert alert-danger alert-custom"
>

    <i
        class="
            bi
            bi-exclamation-circle-fill
            me-2
        "
    ></i>

    <?= e($error) ?>

</div>

<?php endif; ?>


<!-- =======================================================
     INFORMASI + TAMBAH
======================================================= -->

<div class="cardx">

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

            <div class="card-title-custom">

                Daftar Kompetensi & Indikator Level

            </div>


            <div class="section-note">

                Data kompetensi dan indikator level
                keahlian pekerja.

            </div>

        </div>


        <button
            type="button"
            class="btn btn-gf"
            data-bs-toggle="modal"
            data-bs-target="#modalTambahSkill"
        >

            <i
                class="
                    bi
                    bi-plus-lg
                    me-1
                "
            ></i>

            Tambah Skill

        </button>

    </div>

</div>


<!-- =======================================================
     TABEL
======================================================= -->

<div class="cardx">

    <div class="table-responsive">

        <table
            class="
                table
                table-custom
                mb-0
            "
        >

            <thead>

                <tr>

                    <th
                        style="width:5%;"
                    >

                        No

                    </th>


                    <th
                        style="width:23%;"
                    >

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


                    <th
                        class="text-center"
                        style="width:9%;"
                    >

                        Aksi

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

                            <!-- NO -->

                            <td
                                class="
                                    text-center
                                    fw-semibold
                                    text-muted
                                "
                            >

                                <?= $no++ ?>

                            </td>


                            <!-- KOMPETENSI -->

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

                                    <?= (int)(
                                        $r['peserta']
                                    ) ?>

                                    pekerja dinilai

                                </small>

                            </td>


                            <!-- LEVEL 1-5 -->

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


                            <!-- AKSI -->

                            <td
                                class="text-center"
                            >

                                <button
                                    type="button"
                                    class="btn-edit"
                                    data-bs-toggle="modal"
                                    data-bs-target="#modalEditSkill<?= (int)$r['id'] ?>"
                                >

                                    <i
                                        class="
                                            bi
                                            bi-pencil
                                        "
                                    ></i>

                                    Edit

                                </button>

                            </td>

                        </tr>

                    <?php endwhile; ?>


                <?php else: ?>

                    <tr>

                        <td
                            colspan="8"
                            class="
                                text-center
                                py-5
                                text-muted
                            "
                        >

                            <i
                                class="
                                    bi
                                    bi-journal-x
                                    d-block
                                    mb-2
                                "
                                style="
                                    font-size:25px;
                                "
                            ></i>

                            Belum ada data kompetensi
                            yang tersedia.

                        </td>

                    </tr>

                <?php endif; ?>

            </tbody>

        </table>

    </div>

</div>


<!-- =======================================================
     MODAL TAMBAH SKILL
======================================================= -->

<div
    class="modal fade"
    id="modalTambahSkill"
    tabindex="-1"
    aria-hidden="true"
>

    <div
        class="
            modal-dialog
            modal-dialog-centered
            modal-lg
        "
    >

        <div class="modal-content">

            <form
                method="POST"
                action="index.php"
            >

                <input
                    type="hidden"
                    name="action"
                    value="tambah"
                >


                <!-- HEADER -->

                <div class="modal-header">

                    <div>

                        <div class="modal-title">

                            Tambah Skill

                        </div>


                        <div class="modal-subtitle">

                            Tambahkan kompetensi baru
                            beserta indikator level.

                        </div>

                    </div>


                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="modal"
                    ></button>

                </div>


                <!-- BODY -->

                <div class="modal-body">

                    <!-- NAMA SKILL -->

                    <div class="mb-3">

                        <label class="form-label">

                            Nama Skill

                        </label>


                        <input
                            type="text"
                            name="nama_skill"
                            class="form-control"
                            placeholder="Contoh: Basic PLC"
                            required
                            autofocus
                        >

                    </div>


                    <!-- LEVEL -->

                    <div class="level-box">

                        <div class="level-title">

                            Indikator Kompetensi

                        </div>


                        <div class="row g-3">

                            <!-- LEVEL 1 -->

                            <div class="col-md-6">

                                <label class="form-label">

                                    Level 1

                                </label>


                                <textarea
                                    name="level_1"
                                    class="form-control"
                                    rows="2"
                                    placeholder="Indikator level 1"
                                ></textarea>

                            </div>


                            <!-- LEVEL 2 -->

                            <div class="col-md-6">

                                <label class="form-label">

                                    Level 2

                                </label>


                                <textarea
                                    name="level_2"
                                    class="form-control"
                                    rows="2"
                                    placeholder="Indikator level 2"
                                ></textarea>

                            </div>


                            <!-- LEVEL 3 -->

                            <div class="col-md-6">

                                <label class="form-label">

                                    Level 3

                                </label>


                                <textarea
                                    name="level_3"
                                    class="form-control"
                                    rows="2"
                                    placeholder="Indikator level 3"
                                ></textarea>

                            </div>


                            <!-- LEVEL 4 -->

                            <div class="col-md-6">

                                <label class="form-label">

                                    Level 4

                                </label>


                                <textarea
                                    name="level_4"
                                    class="form-control"
                                    rows="2"
                                    placeholder="Indikator level 4"
                                ></textarea>

                            </div>


                            <!-- LEVEL 5 -->

                            <div class="col-md-6">

                                <label class="form-label">

                                    Level 5

                                </label>


                                <textarea
                                    name="level_5"
                                    class="form-control"
                                    rows="2"
                                    placeholder="Indikator level 5"
                                ></textarea>

                            </div>

                        </div>

                    </div>

                </div>


                <!-- FOOTER -->

                <div class="modal-footer">

                    <button
                        type="button"
                        class="btn-cancel"
                        data-bs-dismiss="modal"
                    >

                        Batal

                    </button>


                    <button
                        type="submit"
                        class="btn-save"
                    >

                        <i
                            class="
                                bi
                                bi-check-lg
                                me-1
                            "
                        ></i>

                        Simpan Skill

                    </button>

                </div>

            </form>

        </div>

    </div>

</div>


<!-- =======================================================
     MODAL EDIT SKILL
======================================================= -->

<?php

$skills_edit = $conn->query("
    SELECT *
    FROM skill
    ORDER BY id ASC
");

?>


<?php if (
    $skills_edit &&
    $skills_edit->num_rows > 0
): ?>

    <?php while (
        $s_edit =
        $skills_edit->fetch_assoc()
    ): ?>

        <div
            class="modal fade"
            id="modalEditSkill<?= (int)$s_edit['id'] ?>"
            tabindex="-1"
            aria-hidden="true"
        >

            <div
                class="
                    modal-dialog
                    modal-dialog-centered
                    modal-lg
                "
            >

                <div class="modal-content">

                    <form
                        method="POST"
                        action="index.php"
                    >

                        <input
                            type="hidden"
                            name="action"
                            value="edit"
                        >


                        <input
                            type="hidden"
                            name="id"
                            value="<?= (int)$s_edit['id'] ?>"
                        >


                        <!-- HEADER -->

                        <div class="modal-header">

                            <div>

                                <div class="modal-title">

                                    Edit Skill

                                </div>


                                <div class="modal-subtitle">

                                    Perbarui nama skill
                                    dan indikator level.

                                </div>

                            </div>


                            <button
                                type="button"
                                class="btn-close"
                                data-bs-dismiss="modal"
                            ></button>

                        </div>


                        <!-- BODY -->

                        <div class="modal-body">

                            <!-- NAMA -->

                            <div class="mb-3">

                                <label class="form-label">

                                    Nama Skill

                                </label>


                                <input
                                    type="text"
                                    name="nama_skill"
                                    class="form-control"
                                    value="<?= e(
                                        $s_edit['nama_skill']
                                    ) ?>"
                                    required
                                >

                            </div>


                            <!-- LEVEL -->

                            <div class="level-box">

                                <div class="level-title">

                                    Indikator Kompetensi

                                </div>


                                <div class="row g-3">

                                    <!-- LEVEL 1 -->

                                    <div class="col-md-6">

                                        <label class="form-label">

                                            Level 1

                                        </label>


                                        <textarea
                                            name="level_1"
                                            class="form-control"
                                            rows="2"
                                            placeholder="Indikator level 1"
                                        ><?= e(
                                            $s_edit['level_1'] ?? ''
                                        ) ?></textarea>

                                    </div>


                                    <!-- LEVEL 2 -->

                                    <div class="col-md-6">

                                        <label class="form-label">

                                            Level 2

                                        </label>


                                        <textarea
                                            name="level_2"
                                            class="form-control"
                                            rows="2"
                                            placeholder="Indikator level 2"
                                        ><?= e(
                                            $s_edit['level_2'] ?? ''
                                        ) ?></textarea>

                                    </div>


                                    <!-- LEVEL 3 -->

                                    <div class="col-md-6">

                                        <label class="form-label">

                                            Level 3

                                        </label>


                                        <textarea
                                            name="level_3"
                                            class="form-control"
                                            rows="2"
                                            placeholder="Indikator level 3"
                                        ><?= e(
                                            $s_edit['level_3'] ?? ''
                                        ) ?></textarea>

                                    </div>


                                    <!-- LEVEL 4 -->

                                    <div class="col-md-6">

                                        <label class="form-label">

                                            Level 4

                                        </label>


                                        <textarea
                                            name="level_4"
                                            class="form-control"
                                            rows="2"
                                            placeholder="Indikator level 4"
                                        ><?= e(
                                            $s_edit['level_4'] ?? ''
                                        ) ?></textarea>

                                    </div>


                                    <!-- LEVEL 5 -->

                                    <div class="col-md-6">

                                        <label class="form-label">

                                            Level 5

                                        </label>


                                        <textarea
                                            name="level_5"
                                            class="form-control"
                                            rows="2"
                                            placeholder="Indikator level 5"
                                        ><?= e(
                                            $s_edit['level_5'] ?? ''
                                        ) ?></textarea>

                                    </div>

                                </div>

                            </div>

                        </div>


                        <!-- FOOTER -->

                        <div class="modal-footer">

                            <button
                                type="button"
                                class="btn-cancel"
                                data-bs-dismiss="modal"
                            >

                                Batal

                            </button>


                            <button
                                type="submit"
                                class="btn-save"
                            >

                                <i
                                    class="
                                        bi
                                        bi-check-lg
                                        me-1
                                    "
                                ></i>

                                Simpan Perubahan

                            </button>

                        </div>

                    </form>

                </div>

            </div>

        </div>

    <?php endwhile; ?>

<?php endif; ?>


<!-- =======================================================
     AUTO OPEN MODAL
======================================================= -->

<script>

document.addEventListener(
    'DOMContentLoaded',
    function () {


        /* =================================================
           MODAL TAMBAH
        ================================================= */

        <?php if (
            $open_modal === 'tambah'
        ): ?>

            const modalTambah =
                document.getElementById(
                    'modalTambahSkill'
                );


            if (modalTambah) {

                const instance =
                    new bootstrap.Modal(
                        modalTambah
                    );

                instance.show();

            }

        <?php endif; ?>


        /* =================================================
           MODAL EDIT
        ================================================= */

        <?php if (
            $edit_id > 0
        ): ?>

            const modalEdit =
                document.getElementById(
                    'modalEditSkill<?= $edit_id ?>'
                );


            if (modalEdit) {

                const instance =
                    new bootstrap.Modal(
                        modalEdit
                    );

                instance.show();

            }

        <?php endif; ?>


        /* =================================================
           ALERT AUTO HILANG
        ================================================= */

        const alerts =
            document.querySelectorAll(
                '.alert-custom'
            );


        alerts.forEach(
            function(alert) {

                setTimeout(
                    function() {

                        alert.style.transition =
                            'opacity .3s';

                        alert.style.opacity =
                            '0';


                        setTimeout(
                            function() {

                                alert.remove();

                            },
                            300
                        );

                    },
                    3500
                );

            }
        );

    }
);

</script>


<?php

require __DIR__ . '/../partials/footer.php';

?>