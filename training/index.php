<?php

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

    while ($s = $qSkills->fetch_assoc()) {
        $skills[] = $s;
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

    while ($j = $qJabatan->fetch_assoc()) {
        $jabatan[] = $j;
    }

}


/* =========================================================
   TAMBAH / EDIT
========================================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';


    /* =====================================================
       SIMPAN
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

        }


        /* =================================================
           INSERT / UPDATE
        ================================================= */

        if ($error === '') {

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

                        header(
                            'Location: index.php?updated=1'
                        );

                        exit;

                    } else {

                        $error =
                            'Jadwal training gagal diperbarui: '
                            . $stmt->error;
                    }

                }

            } else {

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

                        header(
                            'Location: index.php?saved=1'
                        );

                        exit;

                    } else {

                        $error =
                            'Jadwal training gagal dibuat: '
                            . $stmt->error;
                    }

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


        if ($id > 0) {

            $stmt = $conn->prepare("
                DELETE FROM training
                WHERE id = ?
            ");

            if ($stmt) {

                $stmt->bind_param(
                    'i',
                    $id
                );

                if ($stmt->execute()) {

                    header(
                        'Location: index.php?deleted=1'
                    );

                    exit;

                } else {

                    $error =
                        'Training gagal dihapus.';
                }

            } else {

                $error =
                    'Gagal menyiapkan query hapus.';
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
    $_GET['status'] ?? '';

$keyword =
    trim($_GET['q'] ?? '');


/* =========================================================
   QUERY TRAINING
========================================================= */

$where  = [];
$params = [];
$types  = '';


/* SEARCH */

if ($keyword !== '') {

    $where[] = "
        (
            t.nama_training LIKE ?
            OR COALESCE(t.trainer, '') LIKE ?
            OR COALESCE(t.lokasi, '') LIKE ?
            OR COALESCE(s.nama_skill, '') LIKE ?
            OR COALESCE(j.nama_jabatan, '') LIKE ?
        )
    ";

    $like =
        '%' . $keyword . '%';

    for ($i = 0; $i < 5; $i++) {
        $params[] = $like;
    }

    $types .= 'sssss';
}


/* FILTER JABATAN */

if ($filter_jabatan > 0) {

    $where[] =
        't.id_jabatan = ?';

    $params[] =
        $filter_jabatan;

    $types .= 'i';
}


/* FILTER STATUS */

if (
    in_array(
        $filter_status,
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

    $where[] =
        't.status = ?';

    $params[] =
        $filter_status;

    $types .= 's';
}


/* =========================================================
   SQL
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
        implode(' AND ', $where);
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

            ELSE 5
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


$stmt->execute();

$rows =
    $stmt->get_result();


/* =========================================================
   STATISTIK
========================================================= */

$total_training = 0;
$terjadwal       = 0;
$berlangsung     = 0;
$selesai         = 0;


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
        ) AS selesai

    FROM training
");


if ($qStat) {

    $st =
        $qStat->fetch_assoc();

    $total_training =
        (int) ($st['total'] ?? 0);

    $terjadwal =
        (int) ($st['terjadwal'] ?? 0);

    $berlangsung =
        (int) ($st['berlangsung'] ?? 0);

    $selesai =
        (int) ($st['selesai'] ?? 0);
}

?>

<style>

/* =========================================================
   TRAINING PAGE
========================================================= */

.training-card {
    background: #fff;
    border: 1px solid #e7ebf1;
    border-radius: 17px;
    box-shadow: 0 5px 20px rgba(20,43,76,.045);
}

.training-stat {
    padding: 20px;
    border-radius: 16px;
    background: #fff;
    border: 1px solid #e7ebf1;
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

.training-table th {
    color: #7d8796;
    font-size: 10px;
    text-transform: uppercase;
    letter-spacing: .05em;
    border-bottom: 1px solid #e3e8ef;
    white-space: nowrap;
}

.training-table td {
    font-size: 12px;
    vertical-align: middle;
    border-bottom: 1px solid #edf0f4;
}

.training-name {
    color: #16223a;
    font-weight: 700;
}

.jabatan-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 6px 10px;
    border-radius: 8px;
    font-size: 10px;
    font-weight: 700;
    background: #eaf2ff;
    color: #123f7a;
}

.jabatan-leader {
    background: #fff4d8;
    color: #9a6700;
}

.jabatan-pelaksana {
    background: #eaf2ff;
    color: #123f7a;
}

.status-training {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 6px 10px;
    border-radius: 8px;
    font-size: 10px;
    font-weight: 700;
}

.status-terjadwal {
    background: #eaf2ff;
    color: #123f7a;
}

.status-berlangsung {
    background: #fff4d8;
    color: #9a6700;
}

.status-selesai {
    background: #e9f8f0;
    color: #198754;
}

.status-terlambat {
    background: #ffecee;
    color: #dc3545;
}

.status-dibatalkan {
    background: #f0f1f3;
    color: #6c757d;
}

</style>


<!-- =======================================================
     ALERT
======================================================= -->

<?php if ($success !== ''): ?>

<div class="alert alert-success border-0 shadow-sm">
    <i class="bi bi-check-circle-fill me-2"></i>
    <?= e($success) ?>
</div>

<?php endif; ?>


<?php if ($error !== ''): ?>

<div class="alert alert-danger border-0 shadow-sm">
    <i class="bi bi-exclamation-triangle-fill me-2"></i>
    <?= e($error) ?>
</div>

<?php endif; ?>


<!-- =======================================================
     STATISTIK
======================================================= -->

<div class="row g-3 mb-4">

    <div class="col-xl-3 col-md-6">

        <div class="training-stat h-100">

            <div class="d-flex align-items-center gap-3">

                <div
                    class="training-stat-icon"
                    style="
                        background:#eaf2ff;
                        color:#123f7a;
                    "
                >

                    <i class="bi bi-calendar-event"></i>

                </div>

                <div>

                    <div class="small text-muted">
                        Total Training
                    </div>

                    <div class="fs-4 fw-bold">
                        <?= number_format($total_training) ?>
                    </div>

                </div>

            </div>

        </div>

    </div>


    <div class="col-xl-3 col-md-6">

        <div class="training-stat h-100">

            <div class="d-flex align-items-center gap-3">

                <div
                    class="training-stat-icon"
                    style="
                        background:#eaf2ff;
                        color:#123f7a;
                    "
                >

                    <i class="bi bi-clock"></i>

                </div>

                <div>

                    <div class="small text-muted">
                        Terjadwal
                    </div>

                    <div class="fs-4 fw-bold">
                        <?= number_format($terjadwal) ?>
                    </div>

                </div>

            </div>

        </div>

    </div>


    <div class="col-xl-3 col-md-6">

        <div class="training-stat h-100">

            <div class="d-flex align-items-center gap-3">

                <div
                    class="training-stat-icon"
                    style="
                        background:#fff4d8;
                        color:#9a6700;
                    "
                >

                    <i class="bi bi-play-circle"></i>

                </div>

                <div>

                    <div class="small text-muted">
                        Berlangsung
                    </div>

                    <div class="fs-4 fw-bold">
                        <?= number_format($berlangsung) ?>
                    </div>

                </div>

            </div>

        </div>

    </div>


    <div class="col-xl-3 col-md-6">

        <div class="training-stat h-100">

            <div class="d-flex align-items-center gap-3">

                <div
                    class="training-stat-icon"
                    style="
                        background:#e9f8f0;
                        color:#198754;
                    "
                >

                    <i class="bi bi-check-circle"></i>

                </div>

                <div>

                    <div class="small text-muted">
                        Selesai
                    </div>

                    <div class="fs-4 fw-bold">
                        <?= number_format($selesai) ?>
                    </div>

                </div>

            </div>

        </div>

    </div>

</div>


<!-- =======================================================
     HEADER
======================================================= -->

<div class="cardx mb-4">

    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">

        <div>

            <div class="card-title mb-1">
                Jadwal Training
            </div>

            <div class="section-note">
                Kelola training berdasarkan kebutuhan kompetensi dan jabatan pekerja.
            </div>

        </div>


        <button
            class="btn btn-primary"
            data-bs-toggle="modal"
            data-bs-target="#modalTraining"
            onclick="prepareAdd()"
        >

            <i class="bi bi-plus-lg me-1"></i>

            Tambah Training

        </button>

    </div>


    <!-- =====================================================
         FILTER
    ====================================================== -->

    <form
        method="get"
        class="row g-3 mt-2 align-items-end"
    >

        <div class="col-xl-4 col-lg-4 col-md-6">

            <label class="form-label">
                Cari Training
            </label>

            <div class="input-group">

                <span class="input-group-text bg-white">
                    <i class="bi bi-search"></i>
                </span>

                <input
                    type="text"
                    name="q"
                    class="form-control"
                    value="<?= e($keyword) ?>"
                    placeholder="Nama training, skill, trainer..."
                >

            </div>

        </div>


        <div class="col-xl-3 col-lg-3 col-md-6">

            <label class="form-label">
                Jabatan
            </label>

            <select
                name="jabatan"
                class="form-select"
            >

                <option value="0">
                    Semua Jabatan
                </option>

                <?php foreach ($jabatan as $j): ?>

                    <option
                        value="<?= (int) $j['id'] ?>"
                        <?= $filter_jabatan == $j['id']
                            ? 'selected'
                            : ''
                        ?>
                    >

                        <?= e($j['nama_jabatan']) ?>

                    </option>

                <?php endforeach; ?>

            </select>

        </div>


        <div class="col-xl-3 col-lg-3 col-md-6">

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

                <option
                    value="Terjadwal"
                    <?= $filter_status === 'Terjadwal'
                        ? 'selected'
                        : ''
                    ?>
                >
                    Terjadwal
                </option>

                <option
                    value="Berlangsung"
                    <?= $filter_status === 'Berlangsung'
                        ? 'selected'
                        : ''
                    ?>
                >
                    Berlangsung
                </option>

                <option
                    value="Selesai"
                    <?= $filter_status === 'Selesai'
                        ? 'selected'
                        : ''
                    ?>
                >
                    Selesai
                </option>

                <option
                    value="Terlambat"
                    <?= $filter_status === 'Terlambat'
                        ? 'selected'
                        : ''
                    ?>
                >
                    Terlambat
                </option>

                <option
                    value="Dibatalkan"
                    <?= $filter_status === 'Dibatalkan'
                        ? 'selected'
                        : ''
                    ?>
                >
                    Dibatalkan
                </option>

            </select>

        </div>


        <div class="col-xl-2 col-lg-2 col-md-6">

            <div class="d-flex gap-2">

                <button class="btn btn-primary">

                    <i class="bi bi-search"></i>

                </button>

                <a
                    href="index.php"
                    class="btn btn-light border"
                >

                    <i class="bi bi-arrow-counterclockwise"></i>

                </a>

            </div>

        </div>

    </form>

</div>


<!-- =======================================================
     TABLE
======================================================= -->

<div class="cardx">

    <div class="d-flex justify-content-between align-items-center mb-3">

        <div>

            <div class="card-title mb-1">
                Daftar Jadwal Training
            </div>

            <div class="section-note">
                Training dipisahkan berdasarkan jabatan target.
            </div>

        </div>

        <span class="badge-status status-good">

            <i class="bi bi-calendar-check me-1"></i>

            <?= number_format($rows->num_rows) ?> Jadwal

        </span>

    </div>


    <div class="table-responsive">

        <table class="table table-hover training-table">

            <thead>

                <tr>

                    <th width="50">
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

                    <th width="100">
                        Aksi
                    </th>

                </tr>

            </thead>


            <tbody>

            <?php if ($rows->num_rows > 0): ?>

                <?php

                $no = 1;

                while ($r = $rows->fetch_assoc()):

                    $jabatanNama =
                        $r['nama_jabatan']
                        ?: 'Semua Jabatan';


                    /*
                     * Bedakan tampilan Leader dan Pelaksana.
                     */

                    $jabatanLower =
                        strtolower(
                            $jabatanNama
                        );


                    $isLeader =
                        (
                            strpos(
                                $jabatanLower,
                                'leader'
                            ) !== false
                            ||
                            strpos(
                                $jabatanLower,
                                'supervisor'
                            ) !== false
                            ||
                            strpos(
                                $jabatanLower,
                                'koordinator'
                            ) !== false
                            ||
                            strpos(
                                $jabatanLower,
                                'kepala'
                            ) !== false
                        );


                    $jabatanClass =
                        $isLeader
                            ? 'jabatan-leader'
                            : 'jabatan-pelaksana';


                    /*
                     * Status class
                     */

                    $statusClass =
                        'status-' .
                        strtolower(
                            $r['status']
                        );

                ?>

                    <tr>

                        <td>
                            <?= $no++ ?>
                        </td>


                        <td>

                            <div class="training-name">
                                <?= e($r['nama_training']) ?>
                            </div>

                            <?php if (!empty($r['catatan'])): ?>

                                <div class="small text-muted mt-1">

                                    <?= e(
                                        mb_strimwidth(
                                            $r['catatan'],
                                            0,
                                            70,
                                            '...'
                                        )
                                    ) ?>

                                </div>

                            <?php endif; ?>

                        </td>


                        <!-- JABATAN -->

                        <td>

                            <span
                                class="jabatan-badge <?= $jabatanClass ?>"
                            >

                                <i
                                    class="bi
                                    <?= $isLeader
                                        ? 'bi-person-badge-fill'
                                        : 'bi-person-fill'
                                    ?>"
                                ></i>

                                <?= e($jabatanNama) ?>

                            </span>

                        </td>


                        <!-- SKILL -->

                        <td>

                            <?= e(
                                $r['nama_skill']
                                ?: 'Umum'
                            ) ?>

                        </td>


                        <!-- TRAINER -->

                        <td>

                            <?= e(
                                $r['trainer']
                                ?: '-'
                            ) ?>

                        </td>


                        <!-- TANGGAL -->

                        <td>

                            <?php if (!empty($r['tanggal_mulai'])): ?>

                                <div>
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


                            <?php if (!empty($r['tanggal_selesai'])): ?>

                                <div class="small text-muted">

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

                            <?= e(
                                $r['lokasi']
                                ?: '-'
                            ) ?>

                        </td>


                        <!-- STATUS -->

                        <td>

                            <span
                                class="status-training <?= $statusClass ?>"
                            >

                                <?php

                                $icons = [

                                    'Terjadwal'
                                        => 'bi-clock',

                                    'Berlangsung'
                                        => 'bi-play-circle',

                                    'Selesai'
                                        => 'bi-check-circle',

                                    'Terlambat'
                                        => 'bi-exclamation-circle',

                                    'Dibatalkan'
                                        => 'bi-x-circle'

                                ];

                                $icon =
                                    $icons[
                                        $r['status']
                                    ]
                                    ?? 'bi-info-circle';

                                ?>

                                <i
                                    class="bi <?= $icon ?>"
                                ></i>

                                <?= e(
                                    $r['status']
                                ) ?>

                            </span>

                        </td>


                        <!-- AKSI -->

                        <td>

                            <div class="d-flex gap-1">

                                <button
                                    type="button"
                                    class="btn btn-sm btn-outline-primary"
                                    title="Edit"
                                    data-bs-toggle="modal"
                                    data-bs-target="#modalTraining"
                                    onclick='prepareEdit(<?= json_encode(
                                        $r,
                                        JSON_HEX_TAG |
                                        JSON_HEX_APOS |
                                        JSON_HEX_QUOT |
                                        JSON_HEX_AMP
                                    ) ?>)'
                                >

                                    <i class="bi bi-pencil"></i>

                                </button>


                                <form
                                    method="post"
                                    class="d-inline"
                                    onsubmit="return confirmDelete('<?= e($r['nama_training']) ?>')"
                                >

                                    <input
                                        type="hidden"
                                        name="action"
                                        value="delete"
                                    >

                                    <input
                                        type="hidden"
                                        name="id"
                                        value="<?= (int) $r['id'] ?>"
                                    >

                                    <button
                                        type="submit"
                                        class="btn btn-sm btn-outline-danger"
                                        title="Hapus"
                                    >

                                        <i class="bi bi-trash"></i>

                                    </button>

                                </form>

                            </div>

                        </td>

                    </tr>

                <?php endwhile; ?>

            <?php else: ?>

                <tr>

                    <td
                        colspan="9"
                        class="text-center py-5"
                    >

                        <i
                            class="bi bi-calendar-x fs-1 text-muted d-block mb-3"
                        ></i>

                        <div class="fw-semibold">
                            Belum ada jadwal training
                        </div>

                        <div class="small text-muted">
                            Silakan tambahkan jadwal training baru.
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
    class="modal fade"
    id="modalTraining"
    tabindex="-1"
    aria-hidden="true"
>

    <div class="modal-dialog modal-lg modal-dialog-centered">

        <form
            method="post"
            class="modal-content border-0 shadow-lg"
        >

            <input
                type="hidden"
                name="action"
                value="save"
            >

            <input
                type="hidden"
                name="id"
                id="form_id"
                value="0"
            >


            <div class="modal-header">

                <div>

                    <h5
                        class="modal-title fw-bold"
                        id="modalTitle"
                    >
                        Tambah Jadwal Training
                    </h5>

                    <div class="small text-muted">
                        Tentukan training berdasarkan jabatan dan kompetensi.
                    </div>

                </div>


                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="modal"
                ></button>

            </div>


            <div class="modal-body">

                <div class="row g-3">


                    <!-- NAMA -->

                    <div class="col-md-7">

                        <label class="form-label">
                            Nama Training
                            <span class="text-danger">*</span>
                        </label>

                        <input
                            type="text"
                            name="nama_training"
                            id="form_nama"
                            class="form-control"
                            required
                            placeholder="Contoh: Basic Electrical"
                        >

                    </div>


                    <!-- JABATAN -->

                    <div class="col-md-5">

                        <label class="form-label">

                            Jabatan Target

                            <span class="text-danger">*</span>

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

                            <?php foreach ($jabatan as $j): ?>

                                <option
                                    value="<?= (int) $j['id'] ?>"
                                >

                                    <?= e(
                                        $j['nama_jabatan']
                                    ) ?>

                                </option>

                            <?php endforeach; ?>

                        </select>


                        <div class="form-text">

                            Training akan ditandai khusus
                            untuk jabatan ini.

                        </div>

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

                            <?php foreach ($skills as $s): ?>

                                <option
                                    value="<?= (int) $s['id'] ?>"
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
                            placeholder="Nama trainer"
                        >

                    </div>


                    <!-- TANGGAL MULAI -->

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


                    <!-- TANGGAL SELESAI -->

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

                            <option value="Terjadwal">
                                Terjadwal
                            </option>

                            <option value="Berlangsung">
                                Berlangsung
                            </option>

                            <option value="Selesai">
                                Selesai
                            </option>

                            <option value="Terlambat">
                                Terlambat
                            </option>

                            <option value="Dibatalkan">
                                Dibatalkan
                            </option>

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
                            placeholder="Contoh: Training Room Teknik"
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
                            placeholder="Catatan training..."
                        ></textarea>

                    </div>

                </div>

            </div>


            <div class="modal-footer">

                <button
                    type="button"
                    class="btn btn-light border"
                    data-bs-dismiss="modal"
                >
                    Batal
                </button>


                <button
                    type="submit"
                    class="btn btn-primary"
                >

                    <i class="bi bi-save me-1"></i>

                    Simpan Jadwal

                </button>

            </div>

        </form>

    </div>

</div>


<script>

/* =========================================================
   TAMBAH
========================================================= */

function prepareAdd() {

    document.getElementById('modalTitle').innerText =
        'Tambah Jadwal Training';

    document.getElementById('form_id').value =
        '0';

    document.getElementById('form_nama').value =
        '';

    document.getElementById('form_jabatan').value =
        '';

    document.getElementById('form_skill').value =
        '0';

    document.getElementById('form_trainer').value =
        '';

    document.getElementById('form_mulai').value =
        '';

    document.getElementById('form_selesai').value =
        '';

    document.getElementById('form_lokasi').value =
        '';

    document.getElementById('form_status').value =
        'Terjadwal';

    document.getElementById('form_catatan').value =
        '';
}


/* =========================================================
   EDIT
========================================================= */

function prepareEdit(data) {

    document.getElementById('modalTitle').innerText =
        'Edit Jadwal Training';

    document.getElementById('form_id').value =
        data.id || 0;

    document.getElementById('form_nama').value =
        data.nama_training || '';

    document.getElementById('form_jabatan').value =
        data.id_jabatan || '';

    document.getElementById('form_skill').value =
        data.id_skill || 0;

    document.getElementById('form_trainer').value =
        data.trainer || '';

    document.getElementById('form_mulai').value =
        data.tanggal_mulai || '';

    document.getElementById('form_selesai').value =
        data.tanggal_selesai || '';

    document.getElementById('form_lokasi').value =
        data.lokasi || '';

    document.getElementById('form_status').value =
        data.status || 'Terjadwal';

    document.getElementById('form_catatan').value =
        data.catatan || '';
}


/* =========================================================
   DELETE
========================================================= */

function confirmDelete(nama) {

    return confirm(
        'Hapus jadwal training "' +
        nama +
        '"?'
    );

}

</script>


<?php

require __DIR__ . '/../partials/footer.php';

?>