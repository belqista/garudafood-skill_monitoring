<?php

/* =========================================================
   GARUDAFOOD SKILL MONITORING
   JADWAL TRAINING
========================================================= */

$page_title = 'Jadwal Training';

require __DIR__ . '/../partials/header.php';


/* =========================================================
   HELPER
========================================================= */

$success = '';
$error   = '';


/* =========================================================
   PESAN
========================================================= */

if (isset($_GET['saved'])) {
    $success = 'Jadwal training berhasil dibuat.';
}

if (isset($_GET['updated'])) {
    $success = 'Jadwal training berhasil diperbarui.';
}

if (isset($_GET['deleted'])) {
    $success = 'Jadwal training berhasil dihapus.';
}


/* =========================================================
   DATA SKILL
========================================================= */

$skills = [];

$qSkills = $conn->query("
    SELECT
        id,
        nama_skill
    FROM skill
    WHERE status = 'Aktif'
    ORDER BY nama_skill ASC
");

if ($qSkills) {

    while ($row = $qSkills->fetch_assoc()) {

        $skills[] = $row;

    }

}


/* =========================================================
   DATA JABATAN
========================================================= */

$jabatan = [];

$qJabatan = $conn->query("
    SELECT
        id,
        nama_jabatan
    FROM jabatan
    ORDER BY nama_jabatan ASC
");

if ($qJabatan) {

    while ($row = $qJabatan->fetch_assoc()) {

        $jabatan[] = $row;

    }

}


/* =========================================================
   POST ACTION
========================================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';


    /* =====================================================
       SIMPAN / UPDATE
    ===================================================== */

    if ($action === 'save') {

        $id = (int) ($_POST['id'] ?? 0);

        $nama_training =
            trim($_POST['nama_training'] ?? '');

        $id_skill =
            (int) ($_POST['id_skill'] ?? 0);

        $id_jabatan =
            (int) ($_POST['id_jabatan'] ?? 0);

        $trainer =
            trim($_POST['trainer'] ?? '');

        $tanggal_mulai =
            !empty($_POST['tanggal_mulai'])
                ? $_POST['tanggal_mulai']
                : null;

        $tanggal_selesai =
            !empty($_POST['tanggal_selesai'])
                ? $_POST['tanggal_selesai']
                : null;

        $lokasi =
            trim($_POST['lokasi'] ?? '');

        $status =
            $_POST['status'] ?? 'Terjadwal';

        $catatan =
            trim($_POST['catatan'] ?? '');


        /* =================================================
           VALIDASI
        ================================================= */

        if ($nama_training === '') {

            $error =
                'Nama training wajib diisi.';

        } elseif ($id_jabatan <= 0) {

            $error =
                'Jabatan target wajib dipilih.';

        } elseif (
            !in_array(
                $status,
                [
                    'Terjadwal',
                    'Berlangsung',
                    'Selesai',
                    'Terlambat',
                    'Dibatalkan'
                ],
                true
            )
        ) {

            $error =
                'Status training tidak valid.';

        } elseif (
            $tanggal_mulai !== null &&
            $tanggal_selesai !== null &&
            $tanggal_selesai < $tanggal_mulai
        ) {

            $error =
                'Tanggal selesai tidak boleh sebelum tanggal mulai.';

        }


        /* =================================================
           INSERT / UPDATE
        ================================================= */

        if ($error === '') {

            /* =============================================
               UPDATE
            ============================================= */

            if ($id > 0) {

                $stmt = $conn->prepare("
                    UPDATE training
                    SET
                        nama_training = ?,
                        id_skill = ?,
                        id_jabatan = ?,
                        trainer = ?,
                        tanggal_mulai = ?,
                        tanggal_selesai = ?,
                        lokasi = ?,
                        status = ?,
                        catatan = ?
                    WHERE id = ?
                ");


                if (!$stmt) {

                    $error =
                        'Gagal menyiapkan query update: '
                        . $conn->error;

                } else {

                    $stmt->bind_param(
                        'siissssssi',
                        $nama_training,
                        $id_skill,
                        $id_jabatan,
                        $trainer,
                        $tanggal_mulai,
                        $tanggal_selesai,
                        $lokasi,
                        $status,
                        $catatan,
                        $id
                    );


                    if ($stmt->execute()) {

                        $stmt->close();

                        header(
                            'Location: index.php?updated=1'
                        );

                        exit;

                    }


                    $error =
                        'Jadwal training gagal diperbarui: '
                        . $stmt->error;

                    $stmt->close();

                }


            }

            /* =============================================
               INSERT
            ============================================= */

            else {

                $stmt = $conn->prepare("
                    INSERT INTO training
                    (
                        nama_training,
                        id_skill,
                        id_jabatan,
                        trainer,
                        tanggal_mulai,
                        tanggal_selesai,
                        lokasi,
                        status,
                        catatan
                    )
                    VALUES
                    (
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        ?
                    )
                ");


                if (!$stmt) {

                    $error =
                        'Gagal menyiapkan query: '
                        . $conn->error;

                } else {

                    $stmt->bind_param(
                        'siissssss',
                        $nama_training,
                        $id_skill,
                        $id_jabatan,
                        $trainer,
                        $tanggal_mulai,
                        $tanggal_selesai,
                        $lokasi,
                        $status,
                        $catatan
                    );


                    if ($stmt->execute()) {

                        $stmt->close();

                        header(
                            'Location: index.php?saved=1'
                        );

                        exit;

                    }


                    $error =
                        'Jadwal training gagal dibuat: '
                        . $stmt->error;

                    $stmt->close();

                }

            }

        }

    }


    /* =====================================================
       DELETE
    ===================================================== */

    elseif ($action === 'delete') {

        $id =
            (int) ($_POST['id'] ?? 0);


        if ($id <= 0) {

            $error =
                'ID training tidak valid.';

        } else {

            $stmt = $conn->prepare("
                DELETE FROM training
                WHERE id = ?
            ");


            if (!$stmt) {

                $error =
                    'Gagal menyiapkan query hapus: '
                    . $conn->error;

            } else {

                $stmt->bind_param(
                    'i',
                    $id
                );


                if ($stmt->execute()) {

                    $stmt->close();

                    header(
                        'Location: index.php?deleted=1'
                    );

                    exit;

                }


                $error =
                    'Training gagal dihapus: '
                    . $stmt->error;

                $stmt->close();

            }

        }

    }

}


/* =========================================================
   FILTER
========================================================= */

$filter_jabatan =
    (int) ($_GET['jabatan'] ?? 0);

$filter_status =
    trim($_GET['status'] ?? '');

$keyword =
    trim($_GET['q'] ?? '');


/* =========================================================
   QUERY TRAINING
========================================================= */

$where  = [];
$params = [];
$types  = '';


/* =========================================================
   SEARCH
========================================================= */

if ($keyword !== '') {

    $where[] = "
        (
            t.nama_training LIKE ?
            OR COALESCE(t.trainer, '') LIKE ?
            OR COALESCE(t.lokasi, '') LIKE ?
            OR COALESCE(t.catatan, '') LIKE ?
            OR COALESCE(s.nama_skill, '') LIKE ?
            OR COALESCE(j.nama_jabatan, '') LIKE ?
        )
    ";


    $like =
        '%' . $keyword . '%';


    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;


    $types .=
        'ssssss';

}


/* =========================================================
   FILTER JABATAN
========================================================= */

if ($filter_jabatan > 0) {

    $where[] =
        't.id_jabatan = ?';

    $params[] =
        $filter_jabatan;

    $types .=
        'i';

}


/* =========================================================
   FILTER STATUS
========================================================= */

$valid_status = [
    'Terjadwal',
    'Berlangsung',
    'Selesai',
    'Terlambat',
    'Dibatalkan'
];


if (
    in_array(
        $filter_status,
        $valid_status,
        true
    )
) {

    $where[] =
        't.status = ?';

    $params[] =
        $filter_status;

    $types .=
        's';

}


/* =========================================================
   SQL DATA
========================================================= */

$sql = "
    SELECT
        t.*,

        s.nama_skill,

        j.nama_jabatan

    FROM training t

    LEFT JOIN skill s
        ON s.id = t.id_skill

    LEFT JOIN jabatan j
        ON j.id = t.id_jabatan
";


if (!empty($where)) {

    $sql .=
        ' WHERE ' .
        implode(
            ' AND ',
            $where
        );

}


$sql .= "

    ORDER BY

        CASE

            WHEN t.status = 'Berlangsung'
                THEN 1

            WHEN t.status = 'Terjadwal'
                THEN 2

            WHEN t.status = 'Terlambat'
                THEN 3

            WHEN t.status = 'Selesai'
                THEN 4

            WHEN t.status = 'Dibatalkan'
                THEN 5

            ELSE 6

        END,

        t.tanggal_mulai ASC,

        t.id DESC
";


$stmt =
    $conn->prepare($sql);


if (!$stmt) {

    die(
        'Query training error: '
        . e($conn->error)
    );

}


if (!empty($params)) {

    $stmt->bind_param(
        $types,
        ...$params
    );

}


if (!$stmt->execute()) {

    die(
        'Gagal mengambil data training: '
        . e($stmt->error)
    );

}


$rows =
    $stmt->get_result();


/* =========================================================
   STATISTIK
========================================================= */

$total_training = 0;
$terjadwal      = 0;
$berlangsung    = 0;
$selesai        = 0;
$terlambat      = 0;
$dibatalkan     = 0;


$qStat = $conn->query("
    SELECT

        COUNT(*) AS total,

        SUM(
            status = 'Terjadwal'
        ) AS terjadwal,

        SUM(
            status = 'Berlangsung'
        ) AS berlangsung,

        SUM(
            status = 'Selesai'
        ) AS selesai,

        SUM(
            status = 'Terlambat'
        ) AS terlambat,

        SUM(
            status = 'Dibatalkan'
        ) AS dibatalkan

    FROM training
");


if ($qStat) {

    $stat =
        $qStat->fetch_assoc();


    $total_training =
        (int) ($stat['total'] ?? 0);

    $terjadwal =
        (int) ($stat['terjadwal'] ?? 0);

    $berlangsung =
        (int) ($stat['berlangsung'] ?? 0);

    $selesai =
        (int) ($stat['selesai'] ?? 0);

    $terlambat =
        (int) ($stat['terlambat'] ?? 0);

    $dibatalkan =
        (int) ($stat['dibatalkan'] ?? 0);

}


/* =========================================================
   FUNGSI STATUS
========================================================= */

function trainingStatusClass($status)
{

    switch ($status) {

        case 'Terjadwal':
            return 'status-terjadwal';

        case 'Berlangsung':
            return 'status-berlangsung';

        case 'Selesai':
            return 'status-selesai';

        case 'Terlambat':
            return 'status-terlambat';

        case 'Dibatalkan':
            return 'status-dibatalkan';

        default:
            return 'status-default';

    }

}


function trainingStatusIcon($status)
{

    switch ($status) {

        case 'Terjadwal':
            return 'bi-clock';

        case 'Berlangsung':
            return 'bi-play-circle';

        case 'Selesai':
            return 'bi-check-circle';

        case 'Terlambat':
            return 'bi-exclamation-circle';

        case 'Dibatalkan':
            return 'bi-x-circle';

        default:
            return 'bi-info-circle';

    }

}


/* =========================================================
   FUNGSI JABATAN
========================================================= */

function isLeaderJabatan($nama)
{

    $nama =
        strtolower(
            (string) $nama
        );


    return
        strpos(
            $nama,
            'leader'
        ) !== false

        ||

        strpos(
            $nama,
            'supervisor'
        ) !== false

        ||

        strpos(
            $nama,
            'koordinator'
        ) !== false

        ||

        strpos(
            $nama,
            'kepala'
        ) !== false;

}


/* =========================================================
   DATA TABEL KE ARRAY
=========================================================

   Kita masukkan hasil query ke array supaya data edit
   bisa dikirim dengan aman menggunakan data-* attribute.

========================================================= */

$training_rows = [];

while ($row = $rows->fetch_assoc()) {

    $training_rows[] = $row;

}

$stmt->close();

?>

<style>

/* =========================================================
   PAGE
========================================================= */

.training-page-card {

    background: #ffffff;

    border: 1px solid #e7ebf1;

    border-radius: 17px;

    box-shadow:
        0 5px 20px
        rgba(20,43,76,.045);

}


/* =========================================================
   STAT
========================================================= */

.training-stat {

    padding: 20px;

    border-radius: 16px;

    background: #ffffff;

    border: 1px solid #e7ebf1;

    box-shadow:
        0 5px 20px
        rgba(20,43,76,.035);

    height: 100%;

}


.training-stat-icon {

    width: 48px;

    height: 48px;

    border-radius: 13px;

    display: flex;

    align-items: center;

    justify-content: center;

    font-size: 20px;

}


/* =========================================================
   TABLE
========================================================= */

.training-table {

    margin-bottom: 0;

}


.training-table th {

    color: #7d8796;

    font-size: 10px;

    font-weight: 700;

    text-transform: uppercase;

    letter-spacing: .05em;

    border-bottom:
        1px solid #e3e8ef;

    white-space: nowrap;

    padding:
        11px 8px;

}


.training-table td {

    color: #384457;

    font-size: 11px;

    vertical-align: middle;

    border-bottom:
        1px solid #edf0f4;

    padding:
        11px 8px;

}


.training-table tbody tr:last-child td {

    border-bottom: 0;

}


.training-table tbody tr:hover {

    background:
        #fafbfd;

}


/* =========================================================
   TRAINING NAME
========================================================= */

.training-name {

    color: #16223a;

    font-size: 11px;

    font-weight: 700;

    line-height: 1.45;

}


/* =========================================================
   JABATAN
========================================================= */

.jabatan-badge {

    display: inline-flex;

    align-items: center;

    gap: 5px;

    padding: 5px 8px;

    border-radius: 7px;

    font-size: 9px;

    font-weight: 700;

    white-space: nowrap;

    background:
        #eaf2ff;

    color:
        #123f7a;

}


.jabatan-leader {

    background:
        #fff4d8;

    color:
        #9a6700;

}


.jabatan-pelaksana {

    background:
        #eaf2ff;

    color:
        #123f7a;

}


/* =========================================================
   STATUS
========================================================= */

.status-training {

    display: inline-flex;

    align-items: center;

    gap: 5px;

    padding: 5px 8px;

    border-radius: 7px;

    font-size: 9px;

    font-weight: 700;

    white-space: nowrap;

}


.status-terjadwal {

    background:
        #eaf2ff;

    color:
        #123f7a;

}


.status-berlangsung {

    background:
        #fff4d8;

    color:
        #9a6700;

}


.status-selesai {

    background:
        #e9f8f0;

    color:
        #198754;

}


.status-terlambat {

    background:
        #ffecee;

    color:
        #dc3545;

}


.status-dibatalkan {

    background:
        #f0f1f3;

    color:
        #6c757d;

}


.status-default {

    background:
        #f0f1f3;

    color:
        #6c757d;

}


/* =========================================================
   FILTER
========================================================= */

.training-filter {

    background:
        #f8fafc;

    border:
        1px solid #e7ebf1;

    border-radius:
        12px;

    padding:
        15px;

}


/* =========================================================
   RESPONSIVE
========================================================= */

@media (max-width: 768px) {

    .training-stat {

        padding: 16px;

    }

    .training-table {

        min-width: 1050px;

    }

}

</style>


<!-- =======================================================
     ALERT
======================================================= -->

<?php if ($success !== ''): ?>

<div
    class="
        alert
        alert-success
        border-0
        shadow-sm
        d-flex
        align-items-center
    "
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
    class="
        alert
        alert-danger
        border-0
        shadow-sm
        d-flex
        align-items-center
    "
>

    <i
        class="
            bi
            bi-exclamation-triangle-fill
            me-2
        "
    ></i>

    <?= e($error) ?>

</div>

<?php endif; ?>


<!-- =======================================================
     STATISTIK
======================================================= -->

<div class="row g-3 mb-4">


    <!-- TOTAL -->

    <div class="col-xl-3 col-md-6">

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
                        background:#eaf2ff;
                        color:#123f7a;
                    "
                >

                    <i
                        class="
                            bi
                            bi-calendar-event
                        "
                    ></i>

                </div>


                <div>

                    <div
                        class="
                            small
                            text-muted
                        "
                    >
                        Total Training
                    </div>

                    <div
                        class="
                            fs-4
                            fw-bold
                        "
                    >
                        <?= number_format(
                            $total_training
                        ) ?>
                    </div>

                </div>

            </div>

        </div>

    </div>


    <!-- TERJADWAL -->

    <div class="col-xl-3 col-md-6">

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
                        background:#eaf2ff;
                        color:#123f7a;
                    "
                >

                    <i
                        class="
                            bi
                            bi-clock
                        "
                    ></i>

                </div>


                <div>

                    <div
                        class="
                            small
                            text-muted
                        "
                    >
                        Terjadwal
                    </div>

                    <div
                        class="
                            fs-4
                            fw-bold
                        "
                    >
                        <?= number_format(
                            $terjadwal
                        ) ?>
                    </div>

                </div>

            </div>

        </div>

    </div>


    <!-- BERLANGSUNG -->

    <div class="col-xl-3 col-md-6">

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
                        background:#fff4d8;
                        color:#9a6700;
                    "
                >

                    <i
                        class="
                            bi
                            bi-play-circle
                        "
                    ></i>

                </div>


                <div>

                    <div
                        class="
                            small
                            text-muted
                        "
                    >
                        Berlangsung
                    </div>

                    <div
                        class="
                            fs-4
                            fw-bold
                        "
                    >
                        <?= number_format(
                            $berlangsung
                        ) ?>
                    </div>

                </div>

            </div>

        </div>

    </div>


    <!-- SELESAI -->

    <div class="col-xl-3 col-md-6">

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
                        background:#e9f8f0;
                        color:#198754;
                    "
                >

                    <i
                        class="
                            bi
                            bi-check-circle
                        "
                    ></i>

                </div>


                <div>

                    <div
                        class="
                            small
                            text-muted
                        "
                    >
                        Selesai
                    </div>

                    <div
                        class="
                            fs-4
                            fw-bold
                        "
                    >
                        <?= number_format(
                            $selesai
                        ) ?>
                    </div>

                </div>

            </div>

        </div>

    </div>

</div>


<!-- =======================================================
     HEADER + FILTER
======================================================= -->

<div class="training-page-card mb-4 p-4">


    <!-- HEADER -->

    <div
        class="
            d-flex
            justify-content-between
            align-items-center
            flex-wrap
            gap-3
        "
    >

        <div>

            <div
                class="
                    fw-bold
                    mb-1
                "
                style="
                    color:#172033;
                    font-size:15px;
                "
            >

                <i
                    class="
                        bi
                        bi-calendar2-week
                        me-1
                    "
                ></i>

                Jadwal Training

            </div>


            <div
                style="
                    color:#8a94a4;
                    font-size:11px;
                "
            >

                Kelola jadwal training berdasarkan
                kebutuhan kompetensi dan jabatan pekerja.

            </div>

        </div>


        <button
            type="button"
            class="btn btn-primary"
            data-bs-toggle="modal"
            data-bs-target="#modalTraining"
            onclick="prepareAdd()"
        >

            <i
                class="
                    bi
                    bi-plus-lg
                    me-1
                "
            ></i>

            Tambah Training

        </button>

    </div>


    <!-- =====================================================
         FILTER
    ====================================================== -->

    <div class="training-filter mt-4">

        <form
            method="get"
            class="row g-3 align-items-end"
        >


            <!-- SEARCH -->

            <div
                class="
                    col-xl-5
                    col-lg-5
                    col-md-6
                "
            >

                <label class="form-label">

                    Cari Training

                </label>


                <div class="input-group">

                    <span
                        class="
                            input-group-text
                            bg-white
                        "
                    >

                        <i
                            class="
                                bi
                                bi-search
                            "
                        ></i>

                    </span>


                    <input
                        type="text"
                        name="q"
                        class="form-control"
                        value="<?= e($keyword) ?>"
                        placeholder="
                            Nama training, skill, trainer,
                            lokasi...
                        "
                    >

                </div>

            </div>


            <!-- JABATAN -->

            <div
                class="
                    col-xl-3
                    col-lg-3
                    col-md-6
                "
            >

                <label class="form-label">

                    Jabatan Target

                </label>


                <select
                    name="jabatan"
                    class="form-select"
                >

                    <option value="0">

                        Semua Jabatan

                    </option>


                    <?php foreach (
                        $jabatan
                        as $j
                    ): ?>

                        <option
                            value="<?= (int) $j['id'] ?>"
                            <?= (
                                $filter_jabatan
                                === (int)$j['id']
                            )
                                ? 'selected'
                                : ''
                            ?>
                        >

                            <?= e(
                                $j['nama_jabatan']
                            ) ?>

                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <!-- STATUS -->

            <div
                class="
                    col-xl-2
                    col-lg-2
                    col-md-6
                "
            >

                <label class="form-label">

                    Status

                </label>


                <select
                    name="status"
                    class="form-select"
                >

                    <option value="">

                        Semua Status

                    </option>


                    <?php foreach (
                        $valid_status
                        as $st
                    ): ?>

                        <option
                            value="<?= e($st) ?>"
                            <?= $filter_status === $st
                                ? 'selected'
                                : ''
                            ?>
                        >

                            <?= e($st) ?>

                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <!-- BUTTON -->

            <div
                class="
                    col-xl-2
                    col-lg-2
                    col-md-6
                "
            >

                <div
                    class="
                        d-flex
                        gap-2
                    "
                >

                    <button
                        type="submit"
                        class="
                            btn
                            btn-primary
                            flex-grow-1
                        "
                    >

                        <i
                            class="
                                bi
                                bi-search
                            "
                        ></i>

                        Cari

                    </button>


                    <a
                        href="index.php"
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

        </form>

    </div>

</div>


<!-- =======================================================
     TABLE
======================================================= -->

<div class="training-page-card p-4">


    <!-- TABLE HEADER -->

    <div
        class="
            d-flex
            justify-content-between
            align-items-center
            mb-3
            flex-wrap
            gap-2
        "
    >

        <div>

            <div
                class="
                    fw-bold
                    mb-1
                "
                style="
                    color:#172033;
                    font-size:14px;
                "
            >

                Daftar Jadwal Training

            </div>


            <div
                style="
                    color:#8a94a4;
                    font-size:11px;
                "
            >

                Menampilkan jadwal berdasarkan
                filter yang dipilih.

            </div>

        </div>


        <span
            class="
                status-training
                status-terjadwal
            "
        >

            <i
                class="
                    bi
                    bi-calendar-check
                "
            ></i>

            <?= number_format(
                count($training_rows)
            ) ?>

            Jadwal

        </span>

    </div>


    <!-- TABLE -->

    <div class="table-responsive">

        <table
            class="
                table
                table-hover
                training-table
            "
        >

            <thead>

                <tr>

                    <th width="45">
                        No
                    </th>

                    <th>
                        Training
                    </th>

                    <th>
                        Jabatan Target
                    </th>

                    <th>
                        Skill
                    </th>

                    <th>
                        Trainer
                    </th>

                    <th>
                        Tanggal
                    </th>

                    <th>
                        Lokasi
                    </th>

                    <th>
                        Status
                    </th>

                    <th width="95">
                        Aksi
                    </th>

                </tr>

            </thead>


            <tbody>


            <?php if (
                !empty($training_rows)
            ): ?>


                <?php

                $no = 1;

                foreach (
                    $training_rows
                    as $r
                ):

                    $jabatanNama =
                        !empty(
                            $r['nama_jabatan']
                        )
                            ? $r['nama_jabatan']
                            : 'Semua Jabatan';


                    $isLeader =
                        isLeaderJabatan(
                            $jabatanNama
                        );


                    $jabatanClass =
                        $isLeader
                            ? 'jabatan-leader'
                            : 'jabatan-pelaksana';


                    $statusClass =
                        trainingStatusClass(
                            $r['status']
                        );


                    $statusIcon =
                        trainingStatusIcon(
                            $r['status']
                        );


                    /*
                     * Data untuk tombol EDIT.
                     *
                     * Data disimpan dalam data-* attribute.
                     * Ini menghindari masalah syntax/quote pada
                     * onclick json_encode.
                     */

                    $editData = [
                        'id' =>
                            (int)$r['id'],

                        'nama_training' =>
                            $r['nama_training'] ?? '',

                        'id_skill' =>
                            (int)($r['id_skill'] ?? 0),

                        'id_jabatan' =>
                            (int)($r['id_jabatan'] ?? 0),

                        'trainer' =>
                            $r['trainer'] ?? '',

                        'tanggal_mulai' =>
                            $r['tanggal_mulai'] ?? '',

                        'tanggal_selesai' =>
                            $r['tanggal_selesai'] ?? '',

                        'lokasi' =>
                            $r['lokasi'] ?? '',

                        'status' =>
                            $r['status'] ?? 'Terjadwal',

                        'catatan' =>
                            $r['catatan'] ?? ''
                    ];


                    $editJson =
                        json_encode(
                            $editData,
                            JSON_UNESCAPED_UNICODE |
                            JSON_HEX_TAG |
                            JSON_HEX_APOS |
                            JSON_HEX_QUOT |
                            JSON_HEX_AMP
                        );

                ?>


                    <tr>


                        <!-- NO -->

                        <td>

                            <?= $no++ ?>

                        </td>


                        <!-- TRAINING -->

                        <td>

                            <div class="training-name">

                                <?= e(
                                    $r['nama_training']
                                ) ?>

                            </div>


                            <?php if (
                                !empty(
                                    $r['catatan']
                                )
                            ): ?>

                                <div
                                    class="
                                        small
                                        text-muted
                                        mt-1
                                    "
                                >

                                    <?= e(
                                        mb_strimwidth(
                                            $r['catatan'],
                                            0,
                                            65,
                                            '...'
                                        )
                                    ) ?>

                                </div>

                            <?php endif; ?>

                        </td>


                        <!-- JABATAN -->

                        <td>

                            <span
                                class="
                                    jabatan-badge
                                    <?= e(
                                        $jabatanClass
                                    ) ?>
                                "
                            >

                                <i
                                    class="
                                        bi
                                        <?= $isLeader
                                            ? 'bi-person-badge-fill'
                                            : 'bi-person-fill'
                                        ?>
                                    "
                                ></i>

                                <?= e(
                                    $jabatanNama
                                ) ?>

                            </span>

                        </td>


                        <!-- SKILL -->

                        <td>

                            <?php if (
                                !empty(
                                    $r['nama_skill']
                                )
                            ): ?>

                                <span
                                    style="
                                        color:#39465a;
                                    "
                                >

                                    <?= e(
                                        $r['nama_skill']
                                    ) ?>

                                </span>

                            <?php else: ?>

                                <span
                                    class="text-muted"
                                >

                                    Umum

                                </span>

                            <?php endif; ?>

                        </td>


                        <!-- TRAINER -->

                        <td>

                            <?= !empty(
                                $r['trainer']
                            )
                                ? e(
                                    $r['trainer']
                                )
                                : '-'
                            ?>

                        </td>


                        <!-- TANGGAL -->

                        <td>

                            <?php if (
                                !empty(
                                    $r['tanggal_mulai']
                                )
                            ): ?>

                                <div>

                                    <i
                                        class="
                                            bi
                                            bi-calendar3
                                            me-1
                                        "
                                    ></i>

                                    <?= e(
                                        date(
                                            'd/m/Y',
                                            strtotime(
                                                $r['tanggal_mulai']
                                            )
                                        )
                                    ) ?>

                                </div>

                            <?php else: ?>

                                -

                            <?php endif; ?>


                            <?php if (
                                !empty(
                                    $r['tanggal_selesai']
                                )
                            ): ?>

                                <div
                                    class="
                                        small
                                        text-muted
                                        mt-1
                                    "
                                >

                                    s/d

                                    <?= e(
                                        date(
                                            'd/m/Y',
                                            strtotime(
                                                $r['tanggal_selesai']
                                            )
                                        )
                                    ) ?>

                                </div>

                            <?php endif; ?>

                        </td>


                        <!-- LOKASI -->

                        <td>

                            <?= !empty(
                                $r['lokasi']
                            )
                                ? e(
                                    $r['lokasi']
                                )
                                : '-'
                            ?>

                        </td>


                        <!-- STATUS -->

                        <td>

                            <span
                                class="
                                    status-training
                                    <?= e(
                                        $statusClass
                                    ) ?>
                                "
                            >

                                <i
                                    class="
                                        bi
                                        <?= e(
                                            $statusIcon
                                        ) ?>
                                    "
                                ></i>

                                <?= e(
                                    $r['status']
                                ) ?>

                            </span>

                        </td>


                        <!-- AKSI -->

                        <td>

                            <div
                                class="
                                    d-flex
                                    gap-1
                                "
                            >


                                <!-- EDIT -->

                                <button
                                    type="button"
                                    class="
                                        btn
                                        btn-sm
                                        btn-outline-primary
                                    "
                                    title="Edit"
                                    data-bs-toggle="modal"
                                    data-bs-target="#modalTraining"
                                    data-training="<?= e(
                                        $editJson
                                    ) ?>"
                                    onclick="prepareEditFromButton(this)"
                                >

                                    <i
                                        class="
                                            bi
                                            bi-pencil
                                        "
                                    ></i>

                                </button>


                                <!-- DELETE -->

                                <form
                                    method="post"
                                    class="d-inline"
                                    onsubmit="
                                        return confirmDelete(
                                            this
                                        );
                                    "
                                >

                                    <input
                                        type="hidden"
                                        name="action"
                                        value="delete"
                                    >


                                    <input
                                        type="hidden"
                                        name="id"
                                        value="<?= (int)$r['id'] ?>"
                                    >


                                    <button
                                        type="submit"
                                        class="
                                            btn
                                            btn-sm
                                            btn-outline-danger
                                        "
                                        title="Hapus"
                                    >

                                        <i
                                            class="
                                                bi
                                                bi-trash
                                            "
                                        ></i>

                                    </button>

                                </form>


                            </div>

                        </td>


                    </tr>


                <?php endforeach; ?>


            <?php else: ?>


                <tr>

                    <td
                        colspan="9"
                        class="
                            text-center
                            py-5
                        "
                    >

                        <i
                            class="
                                bi
                                bi-calendar-x
                                fs-1
                                text-muted
                                d-block
                                mb-3
                            "
                        ></i>


                        <div
                            class="
                                fw-semibold
                                mb-1
                            "
                        >

                            Tidak ada jadwal training

                        </div>


                        <div
                            class="
                                small
                                text-muted
                            "
                        >

                            Belum ada data yang sesuai
                            dengan filter.

                        </div>

                    </td>

                </tr>


            <?php endif; ?>


            </tbody>

        </table>

    </div>

</div>


<!-- =======================================================
     MODAL TAMBAH / EDIT
======================================================= -->

<div
    class="
        modal
        fade
    "
    id="modalTraining"
    tabindex="-1"
    aria-hidden="true"
>

    <div
        class="
            modal-dialog
            modal-lg
            modal-dialog-centered
        "
    >

        <form
            method="post"
            class="
                modal-content
                border-0
                shadow-lg
            "
        >


            <!-- ACTION -->

            <input
                type="hidden"
                name="action"
                value="save"
            >


            <!-- ID -->

            <input
                type="hidden"
                name="id"
                id="form_id"
                value="0"
            >


            <!-- HEADER -->

            <div class="modal-header">

                <div>

                    <h5
                        class="
                            modal-title
                            fw-bold
                        "
                        id="modalTitle"
                    >

                        Tambah Jadwal Training

                    </h5>


                    <div
                        class="
                            small
                            text-muted
                        "
                    >

                        Tentukan training berdasarkan
                        jabatan dan kompetensi.

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

                <div class="row g-3">


                    <!-- NAMA TRAINING -->

                    <div class="col-md-7">

                        <label class="form-label">

                            Nama Training

                            <span
                                class="text-danger"
                            >
                                *
                            </span>

                        </label>


                        <input
                            type="text"
                            name="nama_training"
                            id="form_nama"
                            class="form-control"
                            required
                            maxlength="255"
                            placeholder="
                                Contoh:
                                Basic Electrical
                            "
                        >

                    </div>


                    <!-- JABATAN -->

                    <div class="col-md-5">

                        <label class="form-label">

                            Jabatan Target

                            <span
                                class="text-danger"
                            >
                                *
                            </span>

                        </label>


                        <select
                            name="id_jabatan"
                            id="form_jabatan"
                            class="form-select"
                            required
                        >

                            <option value="">

                                -- Pilih Jabatan --

                            </option>


                            <?php foreach (
                                $jabatan
                                as $j
                            ): ?>

                                <option
                                    value="<?= (int)$j['id'] ?>"
                                >

                                    <?= e(
                                        $j['nama_jabatan']
                                    ) ?>

                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>


                    <!-- SKILL -->

                    <div class="col-md-6">

                        <label class="form-label">

                            Skill / Kompetensi

                        </label>


                        <select
                            name="id_skill"
                            id="form_skill"
                            class="form-select"
                        >

                            <option value="0">

                                Umum

                            </option>


                            <?php foreach (
                                $skills
                                as $s
                            ): ?>

                                <option
                                    value="<?= (int)$s['id'] ?>"
                                >

                                    <?= e(
                                        $s['nama_skill']
                                    ) ?>

                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>


                    <!-- TRAINER -->

                    <div class="col-md-6">

                        <label class="form-label">

                            Trainer

                        </label>


                        <input
                            type="text"
                            name="trainer"
                            id="form_trainer"
                            class="form-control"
                            maxlength="255"
                            placeholder="
                                Nama trainer
                            "
                        >

                    </div>


                    <!-- MULAI -->

                    <div class="col-md-4">

                        <label class="form-label">

                            Tanggal Mulai

                        </label>


                        <input
                            type="date"
                            name="tanggal_mulai"
                            id="form_mulai"
                            class="form-control"
                        >

                    </div>


                    <!-- SELESAI -->

                    <div class="col-md-4">

                        <label class="form-label">

                            Tanggal Selesai

                        </label>


                        <input
                            type="date"
                            name="tanggal_selesai"
                            id="form_selesai"
                            class="form-control"
                        >

                    </div>


                    <!-- STATUS -->

                    <div class="col-md-4">

                        <label class="form-label">

                            Status

                        </label>


                        <select
                            name="status"
                            id="form_status"
                            class="form-select"
                        >

                            <?php foreach (
                                $valid_status
                                as $st
                            ): ?>

                                <option
                                    value="<?= e($st) ?>"
                                >

                                    <?= e($st) ?>

                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>


                    <!-- LOKASI -->

                    <div class="col-12">

                        <label class="form-label">

                            Lokasi

                        </label>


                        <input
                            type="text"
                            name="lokasi"
                            id="form_lokasi"
                            class="form-control"
                            maxlength="255"
                            placeholder="
                                Contoh:
                                Training Room Teknik
                            "
                        >

                    </div>


                    <!-- CATATAN -->

                    <div class="col-12">

                        <label class="form-label">

                            Catatan

                        </label>


                        <textarea
                            name="catatan"
                            id="form_catatan"
                            class="form-control"
                            rows="3"
                            placeholder="
                                Catatan training...
                            "
                        ></textarea>

                    </div>


                </div>

            </div>


            <!-- FOOTER -->

            <div class="modal-footer">

                <button
                    type="button"
                    class="
                        btn
                        btn-light
                        border
                    "
                    data-bs-dismiss="modal"
                >

                    Batal

                </button>


                <button
                    type="submit"
                    class="
                        btn
                        btn-primary
                    "
                >

                    <i
                        class="
                            bi
                            bi-save
                            me-1
                        "
                    ></i>

                    Simpan Jadwal

                </button>

            </div>


        </form>

    </div>

</div>


<!-- =======================================================
     JAVASCRIPT
======================================================= -->

<script>

/* =========================================================
   TAMBAH DATA
========================================================= */

function prepareAdd() {

    const title =
        document.getElementById(
            'modalTitle'
        );

    const id =
        document.getElementById(
            'form_id'
        );

    const nama =
        document.getElementById(
            'form_nama'
        );

    const jabatan =
        document.getElementById(
            'form_jabatan'
        );

    const skill =
        document.getElementById(
            'form_skill'
        );

    const trainer =
        document.getElementById(
            'form_trainer'
        );

    const mulai =
        document.getElementById(
            'form_mulai'
        );

    const selesai =
        document.getElementById(
            'form_selesai'
        );

    const lokasi =
        document.getElementById(
            'form_lokasi'
        );

    const status =
        document.getElementById(
            'form_status'
        );

    const catatan =
        document.getElementById(
            'form_catatan'
        );


    title.innerText =
        'Tambah Jadwal Training';


    id.value =
        '0';


    nama.value =
        '';


    jabatan.value =
        '';


    skill.value =
        '0';


    trainer.value =
        '';


    mulai.value =
        '';


    selesai.value =
        '';


    lokasi.value =
        '';


    status.value =
        'Terjadwal';


    catatan.value =
        '';

}


/* =========================================================
   EDIT DATA
========================================================= */

function prepareEditFromButton(button) {

    const raw =
        button.getAttribute(
            'data-training'
        );


    if (!raw) {

        console.error(
            'Data training tidak ditemukan.'
        );

        return;

    }


    let data;


    try {

        data =
            JSON.parse(raw);

    } catch (error) {

        console.error(
            'Data training tidak valid:',
            error
        );

        alert(
            'Data training tidak dapat dibaca.'
        );

        return;

    }


    document.getElementById(
        'modalTitle'
    ).innerText =
        'Edit Jadwal Training';


    document.getElementById(
        'form_id'
    ).value =
        data.id || 0;


    document.getElementById(
        'form_nama'
    ).value =
        data.nama_training || '';


    document.getElementById(
        'form_jabatan'
    ).value =
        data.id_jabatan || '';


    document.getElementById(
        'form_skill'
    ).value =
        data.id_skill || 0;


    document.getElementById(
        'form_trainer'
    ).value =
        data.trainer || '';


    document.getElementById(
        'form_mulai'
    ).value =
        data.tanggal_mulai || '';


    document.getElementById(
        'form_selesai'
    ).value =
        data.tanggal_selesai || '';


    document.getElementById(
        'form_lokasi'
    ).value =
        data.lokasi || '';


    document.getElementById(
        'form_status'
    ).value =
        data.status || 'Terjadwal';


    document.getElementById(
        'form_catatan'
    ).value =
        data.catatan || '';

}


/* =========================================================
   DELETE
========================================================= */

function confirmDelete(form) {

    const id =
        form.querySelector(
            'input[name="id"]'
        );


    const button =
        form.querySelector(
            'button[type="submit"]'
        );


    const row =
        button
            ? button.closest('tr')
            : null;


    let nama =
        'jadwal training';


    if (row) {

        const nameElement =
            row.querySelector(
                '.training-name'
            );


        if (nameElement) {

            nama =
                nameElement
                    .textContent
                    .trim();

        }

    }


    return confirm(
        'Apakah kamu yakin ingin menghapus jadwal "' +
        nama +
        '"?'
    );

}

</script>


<?php

require __DIR__ . '/../partials/footer.php';

?>