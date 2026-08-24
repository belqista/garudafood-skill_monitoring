<?php

/* =========================================================
   1. KONEKSI
========================================================= */
require_once __DIR__ . '/../config/database.php';

if (!function_exists('e')) {
    function e($v) {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}


/* =========================================================
   2. PROSES CRUD DULU
   Semua header()/redirect harus dilakukan di sini
========================================================= */

$success = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';


    /* =====================================================
       TAMBAH / EDIT
    ===================================================== */

    if ($action === 'save') {

        $id         = (int)($_POST['id'] ?? 0);
        $no_reg     = trim($_POST['no_reg'] ?? '');
        $nama       = trim($_POST['nama'] ?? '');
        $departemen = trim($_POST['departemen'] ?? '');
        $keterangan = trim($_POST['keterangan'] ?? '');
        $status     = $_POST['status'] ?? 'Aktif';

        if ($no_reg === '') {
            $error = 'No. Reg / ID wajib diisi.';
        }

        elseif ($nama === '') {
            $error = 'Nama pekerja wajib diisi.';
        }

        elseif ($departemen === '') {
            $error = 'Departemen wajib diisi.';
        }

        elseif (!in_array($status, ['Aktif', 'Nonaktif'], true)) {
            $error = 'Status tidak valid.';
        }


        /* =================================================
           CEK NO REG DUPLIKAT
        ================================================= */

        if ($error === '') {

            if ($id > 0) {

                $cek = $conn->prepare("
                    SELECT id
                    FROM pekerja
                    WHERE no_reg = ?
                    AND id != ?
                    LIMIT 1
                ");

                $cek->bind_param(
                    'si',
                    $no_reg,
                    $id
                );

            } else {

                $cek = $conn->prepare("
                    SELECT id
                    FROM pekerja
                    WHERE no_reg = ?
                    LIMIT 1
                ");

                $cek->bind_param(
                    's',
                    $no_reg
                );
            }

            $cek->execute();

            $result = $cek->get_result();

            if ($result->num_rows > 0) {
                $error = 'No. Reg / ID tersebut sudah digunakan.';
            }

            $cek->close();
        }


        /* =================================================
           SIMPAN
        ================================================= */

        if ($error === '') {

            if ($id > 0) {

                $stmt = $conn->prepare("
                    UPDATE pekerja
                    SET
                        no_reg = ?,
                        nama = ?,
                        departemen = ?,
                        keterangan = ?,
                        status = ?
                    WHERE id = ?
                ");

                $stmt->bind_param(
                    'sssssi',
                    $no_reg,
                    $nama,
                    $departemen,
                    $keterangan,
                    $status,
                    $id
                );

                if ($stmt->execute()) {

                    header(
                        'Location: index.php?success=' .
                        urlencode('Data pekerja berhasil diperbarui.')
                    );

                    exit;
                }

                $error = 'Data pekerja gagal diperbarui.';

            } else {

                $stmt = $conn->prepare("
                    INSERT INTO pekerja
                    (
                        no_reg,
                        nama,
                        departemen,
                        keterangan,
                        status
                    )
                    VALUES (?, ?, ?, ?, ?)
                ");

                $stmt->bind_param(
                    'sssss',
                    $no_reg,
                    $nama,
                    $departemen,
                    $keterangan,
                    $status
                );

                if ($stmt->execute()) {

                    header(
                        'Location: index.php?success=' .
                        urlencode('Data pekerja berhasil ditambahkan.')
                    );

                    exit;
                }

                $error = 'Data pekerja gagal ditambahkan.';
            }

            if (isset($stmt)) {
                $stmt->close();
            }
        }
    }


    /* =====================================================
       HAPUS
    ===================================================== */

    elseif ($action === 'delete') {

        $id = (int)($_POST['id'] ?? 0);

        if ($id <= 0) {
            $error = 'ID pekerja tidak valid.';
        } else {

            $conn->begin_transaction();

            try {

                /* Hapus peserta training */
                $stmt = $conn->prepare("
                    DELETE FROM training_peserta
                    WHERE id_pekerja = ?
                ");

                if ($stmt) {
                    $stmt->bind_param('i', $id);
                    $stmt->execute();
                    $stmt->close();
                }


                /* Hapus penilaian */
                $stmt = $conn->prepare("
                    DELETE FROM penilaian_skill
                    WHERE id_pekerja = ?
                ");

                if ($stmt) {
                    $stmt->bind_param('i', $id);
                    $stmt->execute();
                    $stmt->close();
                }


                /* Hapus pekerja */
                $stmt = $conn->prepare("
                    DELETE FROM pekerja
                    WHERE id = ?
                ");

                $stmt->bind_param('i', $id);

                if (!$stmt->execute()) {
                    throw new Exception(
                        'Gagal menghapus data pekerja.'
                    );
                }

                $stmt->close();

                $conn->commit();

                header(
                    'Location: index.php?success=' .
                    urlencode('Data pekerja berhasil dihapus.')
                );

                exit;

            } catch (Throwable $e) {

                $conn->rollback();

                $error =
                    'Data pekerja gagal dihapus: ' .
                    $e->getMessage();
            }
        }
    }
}


/* =========================================================
   3. SETELAH CRUD BARU LOAD HEADER
========================================================= */

$page_title = 'Data Pekerja';

require __DIR__ . '/../partials/header.php';


/* =========================================================
   4. PESAN
========================================================= */

if (isset($_GET['success'])) {
    $success = trim($_GET['success']);
}


/* =========================================================
   5. FILTER
========================================================= */

$keyword     = trim($_GET['q'] ?? '');
$departemen  = trim($_GET['departemen'] ?? '');
$status      = $_GET['status'] ?? '';


$where  = [];
$params = [];
$types  = '';


if ($keyword !== '') {

    $where[] = "
        (
            no_reg LIKE ?
            OR nama LIKE ?
            OR departemen LIKE ?
            OR keterangan LIKE ?
        )
    ";

    $like = '%' . $keyword . '%';

    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;

    $types .= 'ssss';
}


if ($departemen !== '') {

    $where[] = 'departemen = ?';

    $params[] = $departemen;

    $types .= 's';
}


if (
    $status === 'Aktif' ||
    $status === 'Nonaktif'
) {

    $where[] = 'status = ?';

    $params[] = $status;

    $types .= 's';
}


/* =========================================================
   6. QUERY DATA
========================================================= */

$sql = "
    SELECT
        id,
        no_reg,
        nama,
        departemen,
        keterangan,
        status
    FROM pekerja
";

if (!empty($where)) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}

$sql .= "
    ORDER BY
        CASE
            WHEN status = 'Aktif' THEN 1
            ELSE 2
        END,
        nama ASC
";


$stmt = $conn->prepare($sql);

if (!empty($params)) {
    $stmt->bind_param(
        $types,
        ...$params
    );
}

$stmt->execute();

$data = $stmt->get_result();


/* =========================================================
   7. DEPARTEMEN
========================================================= */

$departemen_list = [];

$q = $conn->query("
    SELECT DISTINCT departemen
    FROM pekerja
    WHERE departemen IS NOT NULL
    AND departemen != ''
    ORDER BY departemen
");

if ($q) {

    while ($r = $q->fetch_assoc()) {
        $departemen_list[] = $r['departemen'];
    }
}


/* =========================================================
   8. STATISTIK
========================================================= */

$stat = $conn->query("
    SELECT
        COUNT(*) AS total,
        SUM(status = 'Aktif') AS aktif,
        SUM(status = 'Nonaktif') AS nonaktif
    FROM pekerja
");

$stat_data = $stat
    ? $stat->fetch_assoc()
    : [];

$total     = (int)($stat_data['total'] ?? 0);
$total_aktif = (int)($stat_data['aktif'] ?? 0);
$total_nonaktif = (int)($stat_data['nonaktif'] ?? 0);

?>

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

    <div class="col-md-4">

        <div class="cardx h-100">

            <div class="d-flex align-items-center gap-3">

                <div class="stat-icon">
                    <i class="bi bi-people-fill"></i>
                </div>

                <div>

                    <div class="section-note">
                        Total Pekerja
                    </div>

                    <div class="fs-3 fw-bold">
                        <?= number_format($total) ?>
                    </div>

                </div>

            </div>

        </div>

    </div>


    <div class="col-md-4">

        <div class="cardx h-100">

            <div class="d-flex align-items-center gap-3">

                <div
                    class="stat-icon"
                    style="
                        background:#e9f8f0;
                        color:#198754;
                    "
                >
                    <i class="bi bi-person-check-fill"></i>
                </div>

                <div>

                    <div class="section-note">
                        Pekerja Aktif
                    </div>

                    <div class="fs-3 fw-bold">
                        <?= number_format($total_aktif) ?>
                    </div>

                </div>

            </div>

        </div>

    </div>


    <div class="col-md-4">

        <div class="cardx h-100">

            <div class="d-flex align-items-center gap-3">

                <div
                    class="stat-icon"
                    style="
                        background:#fff0f0;
                        color:#dc3545;
                    "
                >
                    <i class="bi bi-person-dash-fill"></i>
                </div>

                <div>

                    <div class="section-note">
                        Pekerja Nonaktif
                    </div>

                    <div class="fs-3 fw-bold">
                        <?= number_format($total_nonaktif) ?>
                    </div>

                </div>

            </div>

        </div>

    </div>

</div>


<!-- =======================================================
     DATA PEKERJA
======================================================= -->

<div class="cardx mb-4">

    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">

        <div>

            <div class="card-title">
                Data Pekerja
            </div>

            <div class="section-note">
                No. Reg / ID diisi secara manual.
            </div>

        </div>


        <button
            type="button"
            class="btn btn-primary"
            data-bs-toggle="modal"
            data-bs-target="#modalPekerja"
            onclick="prepareAdd()"
        >

            <i class="bi bi-person-plus me-1"></i>

            Tambah Pekerja

        </button>

    </div>


    <!-- FILTER -->

    <form
        method="get"
        class="row g-3 mt-2"
    >

        <div class="col-lg-4">

            <label class="form-label">
                Cari
            </label>

            <input
                type="text"
                name="q"
                class="form-control"
                value="<?= e($keyword) ?>"
                placeholder="No. Reg, nama, departemen..."
            >

        </div>


        <div class="col-lg-3">

            <label class="form-label">
                Departemen
            </label>

            <select
                name="departemen"
                class="form-select"
            >

                <option value="">
                    Semua Departemen
                </option>

                <?php foreach ($departemen_list as $d): ?>

                <option
                    value="<?= e($d) ?>"
                    <?= $departemen === $d ? 'selected' : '' ?>
                >
                    <?= e($d) ?>
                </option>

                <?php endforeach; ?>

            </select>

        </div>


        <div class="col-lg-2">

            <label class="form-label">
                Status
            </label>

            <select
                name="status"
                class="form-select"
            >

                <option value="">
                    Semua
                </option>

                <option
                    value="Aktif"
                    <?= $status === 'Aktif' ? 'selected' : '' ?>
                >
                    Aktif
                </option>

                <option
                    value="Nonaktif"
                    <?= $status === 'Nonaktif' ? 'selected' : '' ?>
                >
                    Nonaktif
                </option>

            </select>

        </div>


        <div class="col-lg-3 d-flex align-items-end gap-2">

            <button class="btn btn-primary">
                <i class="bi bi-search me-1"></i>
                Cari
            </button>

            <a
                href="index.php"
                class="btn btn-light border"
            >
                Reset
            </a>

        </div>

    </form>

</div>


<!-- =======================================================
     TABLE
======================================================= -->

<div class="cardx">

    <div class="d-flex justify-content-between align-items-center mb-3">

        <div>

            <div class="card-title">
                Daftar Pekerja
            </div>

            <div class="section-note">
                No. Reg / ID diinput manual.
            </div>

        </div>

        <span class="badge-status status-good">

            <?= $data->num_rows ?> Pekerja

        </span>

    </div>


    <div class="table-responsive">

        <table class="table table-hover align-middle">

            <thead>

                <tr>

                    <th>No.</th>

                    <th>No. Reg / ID</th>

                    <th>Nama</th>

                    <th>Departemen</th>

                    <th>Keterangan</th>

                    <th>Status</th>

                    <th class="text-end">
                        Aksi
                    </th>

                </tr>

            </thead>


            <tbody>

            <?php if ($data->num_rows > 0): ?>

                <?php $no = 1; ?>

                <?php while ($r = $data->fetch_assoc()): ?>

                <tr>

                    <td>
                        <?= $no++ ?>
                    </td>


                    <td>

                        <span class="fw-semibold">
                            <?= e($r['no_reg']) ?>
                        </span>

                    </td>


                    <td>

                        <a
                            href="detail.php?id=<?= (int)$r['id'] ?>"
                            class="worker-name text-decoration-none fw-semibold"
                        >

                            <?= e($r['nama']) ?>

                        </a>

                    </td>


                    <td>
                        <?= e($r['departemen']) ?>
                    </td>


                    <td>
                        <?= e($r['keterangan'] ?: '-') ?>
                    </td>


                    <td>

                        <?php if ($r['status'] === 'Aktif'): ?>

                            <span class="badge-status status-good">
                                Aktif
                            </span>

                        <?php else: ?>

                            <span class="badge-status status-bad">
                                Nonaktif
                            </span>

                        <?php endif; ?>

                    </td>


                    <td class="text-end">

                        <a
                            href="detail.php?id=<?= (int)$r['id'] ?>"
                            class="btn btn-sm btn-outline-primary"
                            title="Detail"
                        >
                            <i class="bi bi-eye"></i>
                        </a>


                        <button
                            type="button"
                            class="btn btn-sm btn-outline-secondary"
                            data-bs-toggle="modal"
                            data-bs-target="#modalPekerja"
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
                            onsubmit="return confirmDelete('<?= e($r['nama']) ?>')"
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
                                class="btn btn-sm btn-outline-danger"
                            >
                                <i class="bi bi-trash"></i>
                            </button>

                        </form>

                    </td>

                </tr>

                <?php endwhile; ?>

            <?php else: ?>

                <tr>

                    <td
                        colspan="7"
                        class="text-center py-5 text-muted"
                    >

                        <i class="bi bi-person-x fs-1 d-block mb-2"></i>

                        Data pekerja tidak ditemukan.

                    </td>

                </tr>

            <?php endif; ?>

            </tbody>

        </table>

    </div>

</div>


<!-- =======================================================
     MODAL
======================================================= -->

<div
    class="modal fade"
    id="modalPekerja"
    tabindex="-1"
>

    <div class="modal-dialog modal-dialog-centered">

        <div class="modal-content">

            <form method="post">

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
                            class="modal-title"
                            id="modalTitle"
                        >
                            Tambah Pekerja
                        </h5>

                        <small class="text-muted">
                            No. Reg / ID diisi manual.
                        </small>

                    </div>

                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="modal"
                    ></button>

                </div>


                <div class="modal-body">

                    <div class="mb-3">

                        <label class="form-label">
                            No. Reg / ID
                            <span class="text-danger">*</span>
                        </label>

                        <input
                            type="text"
                            name="no_reg"
                            id="form_no_reg"
                            class="form-control"
                            required
                            maxlength="50"
                            placeholder="Contoh: REG-001"
                        >

                    </div>


                    <div class="mb-3">

                        <label class="form-label">
                            Nama Pekerja
                            <span class="text-danger">*</span>
                        </label>

                        <input
                            type="text"
                            name="nama"
                            id="form_nama"
                            class="form-control"
                            required
                            maxlength="150"
                        >

                    </div>


                    <div class="mb-3">

                        <label class="form-label">
                            Departemen
                            <span class="text-danger">*</span>
                        </label>

                        <input
                            type="text"
                            name="departemen"
                            id="form_departemen"
                            class="form-control"
                            required
                        >

                    </div>


                    <div class="mb-3">

                        <label class="form-label">
                            Keterangan
                        </label>

                        <textarea
                            name="keterangan"
                            id="form_keterangan"
                            class="form-control"
                            rows="3"
                            placeholder="Keterangan tambahan..."
                        ></textarea>

                    </div>


                    <div>

                        <label class="form-label">
                            Status
                        </label>

                        <select
                            name="status"
                            id="form_status"
                            class="form-select"
                        >

                            <option value="Aktif">
                                Aktif
                            </option>

                            <option value="Nonaktif">
                                Nonaktif
                            </option>

                        </select>

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

                        Simpan

                    </button>

                </div>

            </form>

        </div>

    </div>

</div>


<script>

function prepareAdd() {

    document.getElementById('modalTitle').innerText =
        'Tambah Pekerja';

    document.getElementById('form_id').value =
        '0';

    document.getElementById('form_no_reg').value =
        '';

    document.getElementById('form_nama').value =
        '';

    document.getElementById('form_departemen').value =
        '';

    document.getElementById('form_keterangan').value =
        '';

    document.getElementById('form_status').value =
        'Aktif';
}


function prepareEdit(data) {

    document.getElementById('modalTitle').innerText =
        'Edit Data Pekerja';

    document.getElementById('form_id').value =
        data.id || 0;

    document.getElementById('form_no_reg').value =
        data.no_reg || '';

    document.getElementById('form_nama').value =
        data.nama || '';

    document.getElementById('form_departemen').value =
        data.departemen || '';

    document.getElementById('form_keterangan').value =
        data.keterangan || '';

    document.getElementById('form_status').value =
        data.status || 'Aktif';
}


function confirmDelete(nama) {

    return confirm(
        'Hapus pekerja "' +
        nama +
        '"?\n\n' +
        'Data penilaian skill dan peserta training yang terkait juga akan dihapus.'
    );
}

</script>


<?php
require __DIR__ . '/../partials/footer.php';
?>