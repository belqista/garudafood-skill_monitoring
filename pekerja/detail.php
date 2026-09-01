<?php

$page_title = 'Detail Pekerja';

/*
|--------------------------------------------------------------------------
| HEADER / DATABASE CONNECTION
|--------------------------------------------------------------------------
| header.php menyediakan koneksi $conn dan helper aplikasi.
| Output dibuffer agar proses redirect POST tetap aman.
*/
ob_start();

require __DIR__ . '/../partials/header.php';


/* =========================================================
   PARAMETER
========================================================= */

$id = (int) ($_GET['id'] ?? 0);

if ($id <= 0) {
    header('Location: index.php');
    exit;
}


/* =========================================================
   HELPER
========================================================= */

function redirectDetail($id, $type, $message = '')
{
    $url = 'detail.php?id=' . (int) $id . '&' . $type . '=1';

    if ($message !== '') {
        $url .= '&msg=' . urlencode($message);
    }

    header('Location: ' . $url);
    exit;
}


/* =========================================================
   CEK KONEKSI
========================================================= */

if (!isset($conn) || !($conn instanceof mysqli)) {
    die(
        'Koneksi database tidak tersedia. Pastikan partials/header.php menyediakan $conn.'
    );
}


/* =========================================================
   PROSES POST
========================================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';


    /* =====================================================
       TAMBAH NILAI SKILL
    ===================================================== */

    if ($action === 'add_skill') {

        $id_pekerja = (int) ($_POST['id_pekerja'] ?? 0);
        $id_skill   = (int) ($_POST['id_skill'] ?? 0);
        $tahun      = (int) ($_POST['tahun'] ?? 0);

        $nilai_raw = trim($_POST['nilai'] ?? '');
        $tanggal   = trim($_POST['tanggal_penilaian'] ?? '');
        $assessor  = trim($_POST['assessor'] ?? '');
        $catatan   = trim($_POST['catatan'] ?? '');

        if ($id_pekerja <= 0 || $id_pekerja !== $id) {
            redirectDetail(
                $id,
                'error',
                'Data pekerja tidak valid.'
            );
        }

        if ($id_skill <= 0) {
            redirectDetail(
                $id,
                'error',
                'Skill belum dipilih.'
            );
        }

        if ($tahun < 2000 || $tahun > 2100) {
            redirectDetail(
                $id,
                'error',
                'Tahun assessment tidak valid.'
            );
        }

        if ($nilai_raw === '' || !is_numeric($nilai_raw)) {
            redirectDetail(
                $id,
                'error',
                'Nilai harus berupa angka.'
            );
        }

        $nilai = (float) $nilai_raw;

        if ($nilai < 0 || $nilai > 5) {
            redirectDetail(
                $id,
                'error',
                'Nilai harus berada antara 0 sampai 5.'
            );
        }

        if ($tanggal === '') {
            $tanggal = date('Y-m-d');
        }


        /* -------------------------------------------------
           CEK SKILL
        ------------------------------------------------- */

        $stmt = $conn->prepare("
            SELECT id
            FROM skill
            WHERE id = ?
              AND status = 'Aktif'
            LIMIT 1
        ");

        if (!$stmt) {
            redirectDetail(
                $id,
                'error',
                'Gagal memeriksa skill: ' . $conn->error
            );
        }

        $stmt->bind_param('i', $id_skill);
        $stmt->execute();

        $skill_exists = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$skill_exists) {
            redirectDetail(
                $id,
                'error',
                'Skill tidak ditemukan atau tidak aktif.'
            );
        }


        /* -------------------------------------------------
           CEK DUPLIKAT
        ------------------------------------------------- */

        $stmt = $conn->prepare("
            SELECT id
            FROM penilaian_skill
            WHERE id_pekerja = ?
              AND id_skill = ?
              AND tahun = ?
            ORDER BY id DESC
            LIMIT 1
        ");

        if (!$stmt) {
            redirectDetail(
                $id,
                'error',
                'Gagal memeriksa data assessment: ' . $conn->error
            );
        }

        $stmt->bind_param(
            'iii',
            $id_pekerja,
            $id_skill,
            $tahun
        );

        $stmt->execute();

        $cek = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($cek) {
            redirectDetail(
                $id,
                'error',
                'Nilai untuk skill dan tahun tersebut sudah ada. Silakan gunakan Edit.'
            );
        }


        /* -------------------------------------------------
           INSERT
        ------------------------------------------------- */

        $stmt = $conn->prepare("
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
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");

        if (!$stmt) {
            redirectDetail(
                $id,
                'error',
                'Gagal menyiapkan penyimpanan assessment: ' . $conn->error
            );
        }

        $stmt->bind_param(
            'iiidsss',
            $id_pekerja,
            $id_skill,
            $tahun,
            $nilai,
            $tanggal,
            $assessor,
            $catatan
        );

        if ($stmt->execute()) {

            $stmt->close();

            redirectDetail(
                $id,
                'saved',
                'Nilai assessment berhasil ditambahkan.'
            );
        }

        $error = $stmt->error;
        $stmt->close();

        redirectDetail(
            $id,
            'error',
            'Gagal menambahkan nilai: ' . $error
        );
    }


    /* =====================================================
       EDIT NILAI TERBARU
    ===================================================== */

    if ($action === 'edit_skill') {

        $id_penilaian = (int) ($_POST['id_penilaian'] ?? 0);

        $nilai_raw = trim($_POST['nilai'] ?? '');
        $tanggal   = trim($_POST['tanggal_penilaian'] ?? '');
        $assessor  = trim($_POST['assessor'] ?? '');
        $catatan   = trim($_POST['catatan'] ?? '');

        if ($id_penilaian <= 0) {
            redirectDetail(
                $id,
                'error',
                'Data assessment tidak valid.'
            );
        }

        if ($nilai_raw === '' || !is_numeric($nilai_raw)) {
            redirectDetail(
                $id,
                'error',
                'Nilai harus berupa angka.'
            );
        }

        $nilai = (float) $nilai_raw;

        if ($nilai < 0 || $nilai > 5) {
            redirectDetail(
                $id,
                'error',
                'Nilai harus berada antara 0 sampai 5.'
            );
        }

        if ($tanggal === '') {
            $tanggal = date('Y-m-d');
        }


        /* -------------------------------------------------
           PASTIKAN MILIK PEKERJA
        ------------------------------------------------- */

        $stmt = $conn->prepare("
            SELECT
                id,
                id_skill,
                tahun
            FROM penilaian_skill
            WHERE id = ?
              AND id_pekerja = ?
            LIMIT 1
        ");

        if (!$stmt) {
            redirectDetail(
                $id,
                'error',
                'Gagal memeriksa assessment: ' . $conn->error
            );
        }

        $stmt->bind_param(
            'ii',
            $id_penilaian,
            $id
        );

        $stmt->execute();

        $assessment = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$assessment) {
            redirectDetail(
                $id,
                'error',
                'Assessment tidak ditemukan.'
            );
        }


        /* -------------------------------------------------
           UPDATE
        ------------------------------------------------- */

        $stmt = $conn->prepare("
            UPDATE penilaian_skill
            SET
                nilai = ?,
                tanggal_penilaian = ?,
                assessor = ?,
                catatan = ?
            WHERE id = ?
              AND id_pekerja = ?
        ");

        if (!$stmt) {
            redirectDetail(
                $id,
                'error',
                'Gagal menyiapkan update assessment: ' . $conn->error
            );
        }

        $stmt->bind_param(
            'dsssii',
            $nilai,
            $tanggal,
            $assessor,
            $catatan,
            $id_penilaian,
            $id
        );

        if ($stmt->execute()) {

            $stmt->close();

            redirectDetail(
                $id,
                'updated',
                'Nilai assessment berhasil diperbarui.'
            );
        }

        $error = $stmt->error;
        $stmt->close();

        redirectDetail(
            $id,
            'error',
            'Gagal memperbarui nilai: ' . $error
        );
    }


    /* =====================================================
       HAPUS NILAI ASSESSMENT
    ===================================================== */

    if ($action === 'delete_skill') {

        $id_penilaian = (int) ($_POST['id_penilaian'] ?? 0);

        if ($id_penilaian <= 0) {
            redirectDetail(
                $id,
                'error',
                'Data assessment tidak valid.'
            );
        }


        /* -------------------------------------------------
           PASTIKAN ASSESSMENT MILIK PEKERJA
        ------------------------------------------------- */

        $stmt = $conn->prepare("
            SELECT
                ps.id,
                ps.id_skill,
                ps.tahun,
                ps.nilai,
                s.nama_skill
            FROM penilaian_skill ps
            LEFT JOIN skill s
                ON s.id = ps.id_skill
            WHERE ps.id = ?
              AND ps.id_pekerja = ?
            LIMIT 1
        ");

        if (!$stmt) {
            redirectDetail(
                $id,
                'error',
                'Gagal memeriksa data assessment: ' . $conn->error
            );
        }

        $stmt->bind_param(
            'ii',
            $id_penilaian,
            $id
        );

        $stmt->execute();

        $assessment = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$assessment) {
            redirectDetail(
                $id,
                'error',
                'Assessment tidak ditemukan atau bukan milik pekerja ini.'
            );
        }


        /* -------------------------------------------------
           DELETE
        ------------------------------------------------- */

        $stmt = $conn->prepare("
            DELETE FROM penilaian_skill
            WHERE id = ?
              AND id_pekerja = ?
            LIMIT 1
        ");

        if (!$stmt) {
            redirectDetail(
                $id,
                'error',
                'Gagal menyiapkan penghapusan assessment: ' . $conn->error
            );
        }

        $stmt->bind_param(
            'ii',
            $id_penilaian,
            $id
        );

        if ($stmt->execute()) {

            $affected = $stmt->affected_rows;
            $stmt->close();

            if ($affected > 0) {

                $nama_skill =
                    $assessment['nama_skill']
                    ?: 'Skill';

                $tahun_assessment =
                    (int) $assessment['tahun'];

                redirectDetail(
                    $id,
                    'deleted',
                    'Nilai ' . $nama_skill .
                    ' tahun ' . $tahun_assessment .
                    ' berhasil dihapus.'
                );
            }

            redirectDetail(
                $id,
                'error',
                'Data assessment tidak berhasil dihapus.'
            );
        }

        $error = $stmt->error;
        $stmt->close();

        redirectDetail(
            $id,
            'error',
            'Gagal menghapus nilai: ' . $error
        );
    }


    /* =====================================================
       EDIT HASIL TRAINING
    ===================================================== */

    if ($action === 'edit_training') {

        $id_peserta = (int) ($_POST['id_peserta'] ?? 0);
        $hasil      = trim($_POST['hasil_training'] ?? '');

        if ($id_peserta <= 0) {
            redirectDetail(
                $id,
                'error',
                'Data training tidak valid.'
            );
        }


        /* -------------------------------------------------
           DETEKSI KOLOM HASIL TRAINING
        ------------------------------------------------- */

        $training_columns = [];

        $columns_result = $conn->query("
            SHOW COLUMNS FROM training_peserta
        ");

        if ($columns_result) {

            while ($column = $columns_result->fetch_assoc()) {
                $training_columns[] = $column['Field'];
            }
        }

        $candidate_columns = [
            'nilai',
            'hasil',
            'nilai_training',
            'score',
            'hasil_training'
        ];

        $result_column = null;

        foreach ($candidate_columns as $candidate) {

            if (
                in_array(
                    $candidate,
                    $training_columns,
                    true
                )
            ) {
                $result_column = $candidate;
                break;
            }
        }

        if (!$result_column) {
            redirectDetail(
                $id,
                'error',
                'Kolom hasil/nilai training tidak ditemukan.'
            );
        }


        /* -------------------------------------------------
           PASTIKAN PESERTA MILIK PEKERJA
        ------------------------------------------------- */

        $stmt = $conn->prepare("
            SELECT id
            FROM training_peserta
            WHERE id = ?
              AND id_pekerja = ?
            LIMIT 1
        ");

        if (!$stmt) {
            redirectDetail(
                $id,
                'error',
                'Gagal memeriksa data peserta training: ' .
                $conn->error
            );
        }

        $stmt->bind_param(
            'ii',
            $id_peserta,
            $id
        );

        $stmt->execute();

        $cek_training =
            $stmt->get_result()->fetch_assoc();

        $stmt->close();

        if (!$cek_training) {
            redirectDetail(
                $id,
                'error',
                'Data peserta training tidak ditemukan.'
            );
        }


        /* -------------------------------------------------
           UPDATE
        ------------------------------------------------- */

        $sql = "
            UPDATE training_peserta
            SET `$result_column` = ?
            WHERE id = ?
              AND id_pekerja = ?
        ";

        $stmt = $conn->prepare($sql);

        if (!$stmt) {
            redirectDetail(
                $id,
                'error',
                'Gagal menyiapkan update training: ' .
                $conn->error
            );
        }

        $stmt->bind_param(
            'sii',
            $hasil,
            $id_peserta,
            $id
        );

        if ($stmt->execute()) {

            $stmt->close();

            redirectDetail(
                $id,
                'training_updated',
                'Hasil training berhasil diperbarui.'
            );
        }

        $error = $stmt->error;
        $stmt->close();

        redirectDetail(
            $id,
            'error',
            'Gagal memperbarui hasil training: ' . $error
        );
    }
}


/* =========================================================
   PESAN
========================================================= */

$success_message = '';
$error_message   = '';

if (isset($_GET['saved'])) {

    $success_message =
        $_GET['msg']
        ?? 'Nilai assessment berhasil ditambahkan.';
}

if (isset($_GET['updated'])) {

    $success_message =
        $_GET['msg']
        ?? 'Nilai assessment berhasil diperbarui.';
}

if (isset($_GET['deleted'])) {

    $success_message =
        $_GET['msg']
        ?? 'Nilai assessment berhasil dihapus.';
}

if (isset($_GET['training_updated'])) {

    $success_message =
        $_GET['msg']
        ?? 'Hasil training berhasil diperbarui.';
}

if (isset($_GET['error'])) {

    $error_message =
        $_GET['msg']
        ?? 'Terjadi kesalahan.';
}


/* =========================================================
   DATA PEKERJA
========================================================= */

$stmt = $conn->prepare("
    SELECT *
    FROM pekerja
    WHERE id = ?
    LIMIT 1
");

if (!$stmt) {
    die(
        'Query pekerja gagal: ' .
        htmlspecialchars($conn->error)
    );
}

$stmt->bind_param('i', $id);
$stmt->execute();

$pekerja =
    $stmt->get_result()->fetch_assoc();

$stmt->close();

if (!$pekerja) {
    header('Location: index.php');
    exit;
}


/* =========================================================
   SEMUA SKILL AKTIF
========================================================= */

$skills = [];

$result = $conn->query("
    SELECT
        id,
        nama_skill
    FROM skill
    WHERE status = 'Aktif'
    ORDER BY nama_skill ASC
");

if ($result) {

    while ($row = $result->fetch_assoc()) {
        $skills[] = $row;
    }
}


/* =========================================================
   RIWAYAT ASSESSMENT
========================================================= */

$assessment_data = [];

$stmt = $conn->prepare("
    SELECT
        ps.id,
        ps.id_pekerja,
        ps.id_skill,
        ps.tahun,
        ps.nilai,
        ps.tanggal_penilaian,
        ps.assessor,
        ps.catatan,
        s.nama_skill
    FROM penilaian_skill ps
    INNER JOIN skill s
        ON s.id = ps.id_skill
    WHERE ps.id_pekerja = ?
    ORDER BY
        ps.tahun DESC,
        ps.id DESC
");

if (!$stmt) {
    die(
        'Query assessment gagal: ' .
        htmlspecialchars($conn->error)
    );
}

$stmt->bind_param('i', $id);
$stmt->execute();

$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $assessment_data[] = $row;
}

$stmt->close();


/* =========================================================
   DAFTAR TAHUN
========================================================= */

$years = [];

foreach ($assessment_data as $row) {

    $tahun = (int) $row['tahun'];

    if ($tahun > 0) {
        $years[$tahun] = $tahun;
    }
}

$years = array_values($years);

sort($years);


/* =========================================================
   MATRIX HISTORY
========================================================= */

$history_matrix = [];

foreach ($assessment_data as $row) {

    $skill_id = (int) $row['id_skill'];
    $tahun    = (int) $row['tahun'];

    if (!isset($history_matrix[$skill_id])) {
        $history_matrix[$skill_id] = [];
    }

    /*
     * Jika ada data ganda untuk skill + tahun,
     * ambil ID paling besar / data terbaru.
     */
    if (
        !isset(
            $history_matrix[$skill_id][$tahun]
        )
    ) {

        $history_matrix[$skill_id][$tahun] = $row;
    }
}


/* =========================================================
   NILAI TERBARU PER SKILL
========================================================= */

$latest_skill = [];

foreach ($skills as $skill) {

    $skill_id = (int) $skill['id'];

    $latest_skill[$skill_id] = null;

    if (
        isset($history_matrix[$skill_id])
        && !empty($history_matrix[$skill_id])
    ) {

        $skill_years =
            array_keys(
                $history_matrix[$skill_id]
            );

        rsort($skill_years);

        $latest_skill[$skill_id] =
            $history_matrix[$skill_id][$skill_years[0]];
    }
}


/* =========================================================
   TAHUN
========================================================= */

$tahun_terbaru = !empty($years)
    ? (int) end($years)
    : (int) date('Y');

$tahun_baru = $tahun_terbaru + 1;


/* =========================================================
   STATISTIK & MAPPING
========================================================= */

$total_skill       = 0;
$total_kompeten    = 0;
$total_peningkatan = 0;
$total_training    = 0;

$total_actual = 0;
$total_target = 0;

$skill_mapping = [];

foreach ($skills as $skill) {

    $skill_id = (int) $skill['id'];

    $latest =
        $latest_skill[$skill_id] ?? null;

    /*
     * Target default = 3.
     */
    $target = 3;

    if (
        $latest
        && $latest['nilai'] !== null
    ) {

        $actual = (float) $latest['nilai'];

        $gap =
            max(
                0,
                $target - $actual
            );

        if ($gap >= 2) {

            $status = 'Perlu Training';
            $status_class = 'status-bad';

            $total_training++;

        } elseif ($gap > 0) {

            $status = 'Perlu Peningkatan';
            $status_class = 'status-warning';

            $total_peningkatan++;

        } else {

            $status = 'Kompeten';
            $status_class = 'status-good';

            $total_kompeten++;
        }

        $total_skill++;

        $total_actual += $actual;
        $total_target += $target;

    } else {

        $actual = null;
        $gap = null;

        $status = 'Belum Dinilai';
        $status_class = 'status-neutral';
    }

    $skill_mapping[] = [
        'id_skill'     => $skill_id,
        'nama_skill'   => $skill['nama_skill'],
        'latest'       => $latest,
        'actual'       => $actual,
        'target'       => $target,
        'gap'          => $gap,
        'status'       => $status,
        'status_class' => $status_class
    ];
}


/* =========================================================
   RATA-RATA
========================================================= */

$rata_rata =
    $total_skill > 0
        ? $total_actual / $total_skill
        : 0;

$rata_target =
    $total_skill > 0
        ? $total_target / $total_skill
        : 3;


/* =========================================================
   STATUS KESELURUHAN
========================================================= */

if ($total_training > 0) {

    $overall_status = 'Perlu Training';
    $overall_class = 'status-bad';

} elseif ($total_peningkatan > 0) {

    $overall_status = 'Perlu Peningkatan';
    $overall_class = 'status-warning';

} elseif ($total_skill > 0) {

    $overall_status = 'Kompeten';
    $overall_class = 'status-good';

} else {

    $overall_status = 'Belum Dinilai';
    $overall_class = 'status-neutral';
}


/* =========================================================
   DATA CHART
========================================================= */

$chart_data = [];

foreach ($skills as $skill) {

    $skill_id = (int) $skill['id'];

    $labels = [];
    $values = [];
    $dates = [];
    $assessors = [];

    if (
        isset($history_matrix[$skill_id])
        && !empty($history_matrix[$skill_id])
    ) {

        $skill_years =
            array_keys(
                $history_matrix[$skill_id]
            );

        sort($skill_years);

        foreach ($skill_years as $tahun) {

            $data =
                $history_matrix[$skill_id][$tahun];

            $labels[] = (string) $tahun;

            $values[] =
                $data['nilai'] !== null
                    ? (float) $data['nilai']
                    : null;

            $dates[] =
                !empty($data['tanggal_penilaian'])
                    ? date(
                        'd-m-Y',
                        strtotime(
                            $data['tanggal_penilaian']
                        )
                    )
                    : '-';

            $assessors[] =
                $data['assessor'] ?? '';
        }
    }

    $chart_data[$skill_id] = [
        'nama'     => $skill['nama_skill'],
        'labels'   => $labels,
        'values'   => $values,
        'dates'    => $dates,
        'assessors' => $assessors
    ];
}


/* =========================================================
   RIWAYAT TRAINING
========================================================= */

$training_data = [];

$stmt_training = $conn->prepare("
    SELECT
        tp.*,
        t.nama_training,
        t.tanggal_mulai,
        t.tanggal_selesai,
        t.trainer,
        t.lokasi,
        t.status AS status_training
    FROM training_peserta tp
    INNER JOIN training t
        ON t.id = tp.id_training
    WHERE tp.id_pekerja = ?
    ORDER BY
        t.tanggal_mulai DESC,
        t.id DESC
");

if ($stmt_training) {

    $stmt_training->bind_param(
        'i',
        $id
    );

    $stmt_training->execute();

    $training_result =
        $stmt_training->get_result();

    while (
        $row =
            $training_result->fetch_assoc()
    ) {

        $training_data[] = $row;
    }

    $stmt_training->close();
}


/* =========================================================
   DETEKSI KOLOM HASIL TRAINING
========================================================= */

$training_result_column = null;
$training_columns = [];

$columns_result = $conn->query("
    SHOW COLUMNS FROM training_peserta
");

if ($columns_result) {

    while (
        $column =
            $columns_result->fetch_assoc()
    ) {

        $training_columns[] =
            $column['Field'];
    }
}

$candidate_columns = [
    'nilai',
    'hasil',
    'nilai_training',
    'score',
    'hasil_training'
];

foreach ($candidate_columns as $candidate) {

    if (
        in_array(
            $candidate,
            $training_columns,
            true
        )
    ) {

        $training_result_column =
            $candidate;

        break;
    }
}

?>

<style>

/* =========================================================
   DETAIL PAGE
========================================================= */

.detail-page .cardx {
    border-radius: 16px;
}

.detail-page .table {
    margin-bottom: 0;
}

.detail-page .table th {
    font-size: 12px;
    color: #667085;
    font-weight: 600;
    white-space: nowrap;
}

.detail-page .table td {
    font-size: 13px;
}


/* =========================================================
   PROFILE
========================================================= */

.profile-avatar {
    width: 68px;
    height: 68px;
    border-radius: 16px;
    background: #123b72;
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 27px;
    font-weight: 700;
    flex-shrink: 0;
}


/* =========================================================
   STAT
========================================================= */

.simple-stat {
    padding: 18px;
}

.simple-stat .number {
    font-size: 28px;
    font-weight: 700;
}


/* =========================================================
   HISTORY TABLE
========================================================= */

.history-table th,
.history-table td {
    vertical-align: middle;
}

.history-table th:first-child,
.history-table td:first-child {
    min-width: 260px;
}

.history-year {
    min-width: 115px;
    text-align: center;
}

.history-score {
    font-size: 16px;
    font-weight: 700;
    color: #123b72;
}

.history-date {
    display: block;
    margin-top: 2px;
    font-size: 10px;
    color: #98a2b3;
}


/* =========================================================
   ACTION
========================================================= */

.skill-actions {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 5px;
}

.btn-skill-action {
    width: 32px;
    height: 32px;
    padding: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 7px;
    font-size: 13px;
}


/* EDIT */

.btn-skill-edit {
    background: #eef5ff;
    border: 1px solid #cfe0f7;
    color: #123f7a;
}

.btn-skill-edit:hover {
    background: #dcecff;
    color: #092f63;
}


/* DETAIL */

.btn-skill-detail {
    background: #f3f5f8;
    border: 1px solid #dfe4eb;
    color: #475467;
}

.btn-skill-detail:hover {
    background: #e9edf3;
    color: #123f7a;
}


/* ADD */

.btn-skill-add {
    background: #fff;
    border: 1px dashed #b8c5d6;
    color: #123f7a;
}

.btn-skill-add:hover {
    background: #eef5ff;
    border-color: #123f7a;
}


/* DELETE */

.btn-skill-delete {
    background: #fff1f2;
    border: 1px solid #fecdd3;
    color: #dc3545;
}

.btn-skill-delete:hover {
    background: #ffe4e6;
    border-color: #fda4af;
    color: #b42318;
}


/* =========================================================
   MAPPING
========================================================= */

.mapping-value {
    font-size: 16px;
    font-weight: 700;
    color: #123b72;
}


/* =========================================================
   MODAL
========================================================= */

.modal-content {
    border: 0;
    border-radius: 16px;
}

.modal-header {
    border-bottom: 1px solid #eef1f5;
}

.modal-footer {
    border-top: 1px solid #eef1f5;
}

.form-label {
    font-size: 13px;
    font-weight: 600;
}


/* =========================================================
   CHART
========================================================= */

.chart-detail-box {
    height: 330px;
    position: relative;
}

.chart-info {
    background: #f7f9fc;
    border: 1px solid #e7ebf1;
    border-radius: 10px;
    padding: 10px 12px;
    font-size: 12px;
}


/* =========================================================
   TRAINING
========================================================= */

.training-result {
    font-weight: 600;
    color: #123b72;
}


/* =========================================================
   DELETE CONFIRMATION
========================================================= */

.delete-warning {
    background: #fff7ed;
    border: 1px solid #fed7aa;
    border-radius: 10px;
    padding: 12px 14px;
    font-size: 13px;
}

.delete-warning i {
    color: #ea580c;
}


/* =========================================================
   MOBILE
========================================================= */

@media (max-width: 768px) {

    .profile-avatar {
        width: 58px;
        height: 58px;
        font-size: 23px;
    }

    .history-table th:first-child,
    .history-table td:first-child {
        min-width: 210px;
    }

    .chart-detail-box {
        height: 270px;
    }
}

</style>


<div class="detail-page">


<?php if ($success_message !== ''): ?>

<div class="alert alert-success alert-dismissible fade show">

    <i class="bi bi-check-circle me-2"></i>

    <?= e($success_message) ?>

    <button
        type="button"
        class="btn-close"
        data-bs-dismiss="alert"
    ></button>

</div>

<?php endif; ?>


<?php if ($error_message !== ''): ?>

<div class="alert alert-danger alert-dismissible fade show">

    <i class="bi bi-exclamation-triangle me-2"></i>

    <?= e($error_message) ?>

    <button
        type="button"
        class="btn-close"
        data-bs-dismiss="alert"
    ></button>

</div>

<?php endif; ?>


<!-- =======================================================
     TOP
======================================================= -->

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">

    <div>

        <a
            href="index.php"
            class="btn btn-light border btn-sm text-muted"
        >
            <i class="bi bi-arrow-left me-1"></i>
            Kembali ke Data Pekerja
        </a>

    </div>


    <div class="d-flex gap-2 flex-wrap">

        <a
            href="index.php"
            class="btn btn-light border btn-sm"
        >
            <i class="bi bi-people me-1"></i>
            Data Pekerja
        </a>


        <a
            href="download_pdf.php?id=<?= (int) $id ?>"
            class="btn btn-danger btn-sm"
        >
            <i class="bi bi-file-earmark-pdf me-1"></i>
            PDF
        </a>


        <a
            href="../penilaian/index.php?pekerja=<?= (int) $id ?>"
            class="btn btn-primary btn-sm"
        >
            <i class="bi bi-pencil-square me-1"></i>
            Update Penilaian
        </a>

    </div>

</div>


<!-- =======================================================
     PROFILE
======================================================= -->

<div class="cardx mb-4">

    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">

        <div class="d-flex align-items-center gap-3">

            <div class="profile-avatar">

                <?= e(
                    strtoupper(
                        substr(
                            $pekerja['nama'] ?? '?',
                            0,
                            1
                        )
                    )
                ) ?>

            </div>


            <div>

                <div class="small text-muted mb-1">
                    PROFIL PEKERJA
                </div>

                <h3 class="fw-bold mb-1">
                    <?= e($pekerja['nama'] ?? '-') ?>
                </h3>

                <div class="text-muted">

                    <?= e(
                        $pekerja['departemen']
                        ?: 'Departemen belum ditentukan'
                    ) ?>

                </div>

            </div>

        </div>


        <div class="text-end">

            <div class="small text-muted mb-2">
                STATUS KOMPETENSI
            </div>

            <span class="badge-status <?= e($overall_class) ?>">
                <?= e($overall_status) ?>
            </span>

        </div>

    </div>


    <hr class="my-4">


    <div class="row g-3">

        <div class="col-md-4">

            <div class="small text-muted">
                No. Reg / ID
            </div>

            <div class="fw-semibold">
                <?= e($pekerja['no_reg'] ?? '-') ?>
            </div>

        </div>


        <div class="col-md-4">

            <div class="small text-muted">
                Departemen
            </div>

            <div class="fw-semibold">
                <?= e($pekerja['departemen'] ?? '-') ?>
            </div>

        </div>


        <div class="col-md-4">

            <div class="small text-muted">
                Status Pekerja
            </div>

            <?php if (($pekerja['status'] ?? '') === 'Aktif'): ?>

                <span class="badge bg-success-subtle text-success">
                    Aktif
                </span>

            <?php else: ?>

                <span class="badge bg-secondary-subtle text-secondary">
                    <?= e($pekerja['status'] ?? '-') ?>
                </span>

            <?php endif; ?>

        </div>

    </div>

</div>


<!-- =======================================================
     STATISTIK
======================================================= -->

<div class="row g-3 mb-4">


    <div class="col-xl-3 col-md-6">

        <div class="cardx simple-stat h-100">

            <div class="small text-muted">
                Rata-rata Skill
            </div>

            <div class="number mt-1">

                <?= number_format($rata_rata, 2) ?>

                <small class="text-muted fs-6">
                    / 5
                </small>

            </div>

            <div class="small text-muted">
                Target <?= number_format($rata_target, 2) ?>
            </div>

        </div>

    </div>


    <div class="col-xl-3 col-md-6">

        <div class="cardx simple-stat h-100">

            <div class="small text-muted">
                Skill Kompeten
            </div>

            <div class="number text-success mt-1">
                <?= number_format($total_kompeten) ?>
            </div>

            <div class="small text-muted">
                memenuhi target
            </div>

        </div>

    </div>


    <div class="col-xl-3 col-md-6">

        <div class="cardx simple-stat h-100">

            <div class="small text-muted">
                Perlu Peningkatan
            </div>

            <div class="number text-warning mt-1">
                <?= number_format($total_peningkatan) ?>
            </div>

            <div class="small text-muted">
                masih ada gap
            </div>

        </div>

    </div>


    <div class="col-xl-3 col-md-6">

        <div class="cardx simple-stat h-100">

            <div class="small text-muted">
                Perlu Training
            </div>

            <div class="number text-danger mt-1">
                <?= number_format($total_training) ?>
            </div>

            <div class="small text-muted">
                gap tinggi
            </div>

        </div>

    </div>

</div>


<!-- =======================================================
     SKILL MAPPING
======================================================= -->

<div class="cardx mb-4">

    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">

        <div>

            <div class="card-title mb-1">
                Skill Mapping
            </div>

            <div class="section-note">
                Nilai terbaru setiap skill.
            </div>

        </div>


        <div class="small text-muted">

            Assessment terbaru:

            <strong>
                <?= e($tahun_terbaru) ?>
            </strong>

        </div>

    </div>


    <div class="table-responsive">

        <table class="table table-hover align-middle">

            <thead>

                <tr>

                    <th width="50">
                        No
                    </th>

                    <th>
                        Skill
                    </th>

                    <th class="text-center">
                        Nilai
                    </th>

                    <th class="text-center">
                        Target
                    </th>

                    <th class="text-center">
                        Gap
                    </th>

                    <th>
                        Status
                    </th>

                </tr>

            </thead>


            <tbody>

            <?php if (!empty($skill_mapping)): ?>

                <?php $no = 1; ?>

                <?php foreach ($skill_mapping as $item): ?>

                <tr>

                    <td>
                        <?= $no++ ?>
                    </td>


                    <td>

                        <div class="fw-semibold">
                            <?= e($item['nama_skill']) ?>
                        </div>


                        <?php if ($item['latest']): ?>

                        <div class="small text-muted">

                            <?= e($item['latest']['tahun']) ?>

                            ·

                            <?= !empty(
                                $item['latest']['tanggal_penilaian']
                            )
                                ? e(
                                    date(
                                        'd-m-Y',
                                        strtotime(
                                            $item['latest']['tanggal_penilaian']
                                        )
                                    )
                                )
                                : '-' ?>

                        </div>

                        <?php endif; ?>

                    </td>


                    <td class="text-center">

                        <?php if ($item['latest']): ?>

                        <span class="mapping-value">

                            <?= number_format(
                                (float) $item['actual'],
                                0
                            ) ?>

                        </span>

                        <?php else: ?>

                        <span class="text-muted">
                            -
                        </span>

                        <?php endif; ?>

                    </td>


                    <td class="text-center fw-semibold">
                        3
                    </td>


                    <td class="text-center">

                        <?php if ($item['gap'] === null): ?>

                            <span class="text-muted">
                                -
                            </span>

                        <?php elseif ($item['gap'] > 0): ?>

                            <span class="fw-bold text-danger">

                                <?= number_format(
                                    $item['gap'],
                                    0
                                ) ?>

                            </span>

                        <?php else: ?>

                            <span class="fw-bold text-success">
                                0
                            </span>

                        <?php endif; ?>

                    </td>


                    <td>

                        <span class="badge-status <?= e($item['status_class']) ?>">
                            <?= e($item['status']) ?>
                        </span>

                    </td>

                </tr>

                <?php endforeach; ?>

            <?php else: ?>

                <tr>

                    <td
                        colspan="6"
                        class="text-center py-4 text-muted"
                    >
                        Belum ada skill aktif.
                    </td>

                </tr>

            <?php endif; ?>

            </tbody>

        </table>

    </div>

</div>


<!-- =======================================================
     RIWAYAT PENILAIAN
======================================================= -->

<div class="cardx mb-4">

    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">

        <div>

            <div class="card-title mb-1">
                Riwayat Penilaian
            </div>

            <div class="section-note">

                Setiap tahun otomatis menjadi kolom.
                Edit atau hapus nilai assessment melalui tombol aksi.

            </div>

        </div>


        <button
            type="button"
            class="btn btn-primary btn-sm"
            onclick="openAddSkill()"
        >

            <i class="bi bi-plus-lg me-1"></i>

            Tambah Nilai Terbaru

        </button>

    </div>


    <?php if (!empty($years)): ?>

    <div class="table-responsive">

        <table class="table table-hover history-table">

            <thead>

                <tr>

                    <th>
                        KOMPETENSI / SKILL
                    </th>


                    <?php foreach ($years as $tahun): ?>

                    <th class="history-year">
                        <?= e($tahun) ?>
                    </th>

                    <?php endforeach; ?>


                    <th
                        class="text-center"
                        width="145"
                    >
                        AKSI
                    </th>

                </tr>

            </thead>


            <tbody>

            <?php foreach ($skills as $skill): ?>

                <?php

                $skill_id =
                    (int) $skill['id'];

                $latest =
                    $latest_skill[$skill_id]
                    ?? null;

                ?>

                <tr>


                    <!-- SKILL -->

                    <td>

                        <div class="fw-semibold">
                            <?= e($skill['nama_skill']) ?>
                        </div>

                    </td>


                    <!-- NILAI PER TAHUN -->

                    <?php foreach ($years as $tahun): ?>

                        <?php

                        $history =
                            $history_matrix[$skill_id][$tahun]
                            ?? null;

                        ?>

                        <td class="text-center">

                            <?php if ($history): ?>

                                <div class="history-score">

                                    <?= number_format(
                                        (float) $history['nilai'],
                                        0
                                    ) ?>

                                </div>


                                <?php if (
                                    !empty(
                                        $history['tanggal_penilaian']
                                    )
                                ): ?>

                                <span class="history-date">

                                    <i class="bi bi-calendar3 me-1"></i>

                                    <?= e(
                                        date(
                                            'd-m-Y',
                                            strtotime(
                                                $history['tanggal_penilaian']
                                            )
                                        )
                                    ) ?>

                                </span>

                                <?php endif; ?>

                            <?php else: ?>

                                <span class="text-muted">
                                    -
                                </span>

                            <?php endif; ?>

                        </td>

                    <?php endforeach; ?>


                    <!-- AKSI -->

                    <td>

                        <div class="skill-actions">


                            <?php if ($latest): ?>


                                <!-- EDIT NILAI TERBARU -->

                                <button
                                    type="button"
                                    class="btn-skill-action btn-skill-edit"
                                    title="Edit nilai terbaru"
                                    onclick="openEditSkill(
                                        <?= (int) $latest['id'] ?>,
                                        <?= (int) $latest['tahun'] ?>,
                                        <?= htmlspecialchars(
                                            json_encode(
                                                (float) $latest['nilai']
                                            ),
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>,
                                        <?= htmlspecialchars(
                                            json_encode(
                                                $latest['tanggal_penilaian']
                                                ?? ''
                                            ),
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>,
                                        <?= htmlspecialchars(
                                            json_encode(
                                                $latest['assessor']
                                                ?? ''
                                            ),
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>,
                                        <?= htmlspecialchars(
                                            json_encode(
                                                $latest['catatan']
                                                ?? ''
                                            ),
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>,
                                        <?= htmlspecialchars(
                                            json_encode(
                                                $skill['nama_skill']
                                            ),
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    )"
                                >

                                    <i class="bi bi-pencil"></i>

                                </button>


                            <?php else: ?>


                                <!-- TAMBAH NILAI -->

                                <button
                                    type="button"
                                    class="btn-skill-action btn-skill-add"
                                    title="Tambah nilai"
                                    onclick="openAddSkill(
                                        <?= $skill_id ?>,
                                        <?= (int) $tahun_baru ?>,
                                        <?= htmlspecialchars(
                                            json_encode(
                                                $skill['nama_skill']
                                            ),
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    )"
                                >

                                    <i class="bi bi-plus"></i>

                                </button>


                            <?php endif; ?>


                            <!-- DETAIL GRAFIK -->

                            <button
                                type="button"
                                class="btn-skill-action btn-skill-detail"
                                title="Lihat grafik penilaian"
                                onclick="openSkillDetail(
                                    <?= $skill_id ?>,
                                    <?= htmlspecialchars(
                                        json_encode(
                                            $skill['nama_skill']
                                        ),
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                )"
                            >

                                <i class="bi bi-graph-up"></i>

                            </button>


                            <?php if (!empty($latest)): ?>

                            <!-- HAPUS NILAI TERBARU -->

                            <button
                                type="button"
                                class="btn-skill-action btn-skill-delete"
                                title="Hapus nilai terbaru"
                                onclick="openDeleteSkill(
                                    <?= (int) $latest['id'] ?>,
                                    <?= htmlspecialchars(
                                        json_encode(
                                            $skill['nama_skill']
                                        ),
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>,
                                    <?= (int) $latest['tahun'] ?>,
                                    <?= htmlspecialchars(
                                        json_encode(
                                            (float) $latest['nilai']
                                        ),
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                )"
                            >

                                <i class="bi bi-trash"></i>

                            </button>

                            <?php endif; ?>


                        </div>

                    </td>

                </tr>

            <?php endforeach; ?>

            </tbody>

        </table>

    </div>


    <?php else: ?>


    <div class="text-center py-5 text-muted">

        <i class="bi bi-table fs-2 d-block mb-2"></i>

        Belum ada riwayat penilaian.

    </div>


    <?php endif; ?>

</div>


<!-- =======================================================
     RIWAYAT TRAINING
======================================================= -->

<div class="cardx">

    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">

        <div>

            <div class="card-title mb-1">
                Riwayat Training
            </div>

            <div class="section-note">
                Training yang pernah diikuti pekerja.
            </div>

        </div>


        <a
            href="../training/index.php"
            class="btn btn-primary btn-sm"
        >

            <i class="bi bi-mortarboard me-1"></i>

            Kelola Training

        </a>

    </div>


    <div class="table-responsive">

        <table class="table table-hover align-middle">

            <thead>

                <tr>

                    <th>
                        Training
                    </th>

                    <th>
                        Tanggal
                    </th>

                    <th>
                        Trainer
                    </th>

                    <th>
                        Lokasi
                    </th>

                    <th>
                        Hasil / Nilai
                    </th>

                    <th>
                        Status
                    </th>

                </tr>

            </thead>


            <tbody>

            <?php if (!empty($training_data)): ?>

                <?php foreach ($training_data as $training): ?>

                    <?php

                    $status_training =
                        $training['status_training']
                        ?? '';

                    if ($status_training === 'Selesai') {

                        $training_class =
                            'status-good';

                    } elseif (
                        $status_training === 'Dibatalkan'
                    ) {

                        $training_class =
                            'status-bad';

                    } elseif (
                        $status_training ===
                        'Sedang Berlangsung'
                    ) {

                        $training_class =
                            'status-warning';

                    } else {

                        $training_class =
                            'status-neutral';
                    }


                    $tgl_mulai =
                        !empty(
                            $training['tanggal_mulai']
                        )
                            ? date(
                                'd-m-Y',
                                strtotime(
                                    $training['tanggal_mulai']
                                )
                            )
                            : '-';


                    $tgl_selesai =
                        !empty(
                            $training['tanggal_selesai']
                        )
                            ? date(
                                'd-m-Y',
                                strtotime(
                                    $training['tanggal_selesai']
                                )
                            )
                            : '-';


                    $hasil_training =
                        $training_result_column
                            ? (
                                $training[
                                    $training_result_column
                                ] ?? ''
                            )
                            : '';

                    ?>

                    <tr>


                        <td>

                            <div class="fw-semibold">

                                <?= e(
                                    $training[
                                        'nama_training'
                                    ]
                                ) ?>

                            </div>

                        </td>


                        <td>

                            <?= e($tgl_mulai) ?>

                            <?php if (
                                $tgl_selesai !== '-'
                            ): ?>

                                <span class="text-muted">
                                    s/d
                                </span>

                                <?= e($tgl_selesai) ?>

                            <?php endif; ?>

                        </td>


                        <td>

                            <?= e(
                                $training['trainer']
                                ?: '-'
                            ) ?>

                        </td>


                        <td>

                            <?= e(
                                $training['lokasi']
                                ?: '-'
                            ) ?>

                        </td>


                        <td>

                            <?php if (
                                $training_result_column
                            ): ?>

                                <div class="d-flex align-items-center gap-2">

                                    <span class="training-result">

                                        <?= $hasil_training !== ''
                                            ? e(
                                                $hasil_training
                                            )
                                            : '-'
                                        ?>

                                    </span>


                                    <button
                                        type="button"
                                        class="btn-skill-action btn-skill-edit"
                                        title="Edit hasil training"
                                        onclick="openEditTraining(
                                            <?= (int) $training['id'] ?>,
                                            <?= htmlspecialchars(
                                                json_encode(
                                                    $hasil_training
                                                ),
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>,
                                            <?= htmlspecialchars(
                                                json_encode(
                                                    $training[
                                                        'nama_training'
                                                    ]
                                                ),
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>
                                        )"
                                    >

                                        <i class="bi bi-pencil"></i>

                                    </button>

                                </div>

                            <?php else: ?>

                                <span class="text-muted">
                                    -
                                </span>

                            <?php endif; ?>

                        </td>


                        <td>

                            <span class="badge-status <?= e($training_class) ?>">

                                <?= e(
                                    $status_training
                                    ?: 'Terjadwal'
                                ) ?>

                            </span>

                        </td>

                    </tr>

                <?php endforeach; ?>

            <?php else: ?>

                <tr>

                    <td
                        colspan="6"
                        class="text-center py-5 text-muted"
                    >

                        <i class="bi bi-mortarboard fs-2 d-block mb-2"></i>

                        Belum ada riwayat training.

                    </td>

                </tr>

            <?php endif; ?>

            </tbody>

        </table>

    </div>

</div>


</div>


<!-- =======================================================
     MODAL TAMBAH NILAI
======================================================= -->

<div
    class="modal fade"
    id="modalAddSkill"
    tabindex="-1"
    aria-hidden="true"
>

    <div class="modal-dialog modal-dialog-centered">

        <div class="modal-content">

            <form method="POST">

                <input
                    type="hidden"
                    name="action"
                    value="add_skill"
                >

                <input
                    type="hidden"
                    name="id_pekerja"
                    value="<?= (int) $id ?>"
                >


                <div class="modal-header">

                    <div>

                        <h5 class="modal-title fw-bold mb-1">
                            Tambah Nilai
                        </h5>

                        <div
                            class="small text-muted"
                            id="addSkillTitle"
                        >
                            Tambahkan nilai assessment.
                        </div>

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
                            Skill
                        </label>


                        <input
                            type="text"
                            id="add_skill_search"
                            class="form-control"
                            list="skillList"
                            placeholder="Ketik untuk mencari skill..."
                            autocomplete="off"
                            required
                        >


                        <input
                            type="hidden"
                            name="id_skill"
                            id="add_id_skill"
                        >


                        <datalist id="skillList">

                            <?php foreach ($skills as $skill): ?>

                            <option
                                value="<?= e(
                                    $skill['nama_skill']
                                ) ?>"
                                data-id="<?= (int) $skill['id'] ?>"
                            ></option>

                            <?php endforeach; ?>

                        </datalist>

                    </div>


                    <div class="row g-3">


                        <div class="col-6">

                            <label class="form-label">
                                Tahun
                            </label>

                            <input
                                type="number"
                                name="tahun"
                                id="add_tahun"
                                class="form-control"
                                min="2000"
                                max="2100"
                                value="<?= (int) $tahun_baru ?>"
                                required
                            >

                        </div>


                        <div class="col-6">

                            <label class="form-label">
                                Nilai
                            </label>

                            <input
                                type="number"
                                name="nilai"
                                class="form-control"
                                min="0"
                                max="5"
                                step="1"
                                placeholder="0 - 5"
                                required
                            >

                        </div>

                    </div>


                    <div class="mt-3">

                        <label class="form-label">
                            Tanggal Assessment
                        </label>

                        <input
                            type="date"
                            name="tanggal_penilaian"
                            class="form-control"
                            value="<?= date('Y-m-d') ?>"
                        >

                    </div>


                    <div class="mt-3">

                        <label class="form-label">
                            Assessor
                        </label>

                        <input
                            type="text"
                            name="assessor"
                            class="form-control"
                            placeholder="Nama assessor"
                        >

                    </div>


                    <div class="mt-3">

                        <label class="form-label">
                            Catatan
                        </label>

                        <textarea
                            name="catatan"
                            class="form-control"
                            rows="3"
                            placeholder="Catatan assessment (opsional)"
                        ></textarea>

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

                        <i class="bi bi-check-lg me-1"></i>

                        Simpan Nilai

                    </button>

                </div>

            </form>

        </div>

    </div>

</div>


<!-- =======================================================
     MODAL EDIT NILAI
======================================================= -->

<div
    class="modal fade"
    id="modalEditSkill"
    tabindex="-1"
    aria-hidden="true"
>

    <div class="modal-dialog modal-dialog-centered">

        <div class="modal-content">

            <form method="POST">

                <input
                    type="hidden"
                    name="action"
                    value="edit_skill"
                >

                <input
                    type="hidden"
                    name="id_penilaian"
                    id="edit_id_penilaian"
                >


                <div class="modal-header">

                    <div>

                        <h5 class="modal-title fw-bold mb-1">
                            Edit Nilai
                        </h5>

                        <div
                            class="small text-muted"
                            id="editSkillTitle"
                        >
                            Edit nilai terbaru.
                        </div>

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
                            Tahun
                        </label>

                        <input
                            type="text"
                            id="edit_tahun_display"
                            class="form-control"
                            readonly
                        >

                        <div class="small text-muted mt-1">
                            Tahun tidak diubah karena ini merupakan nilai terbaru skill.
                        </div>

                    </div>


                    <div class="mb-3">

                        <label class="form-label">
                            Nilai
                        </label>

                        <input
                            type="number"
                            name="nilai"
                            id="edit_nilai"
                            class="form-control"
                            min="0"
                            max="5"
                            step="1"
                            required
                        >

                    </div>


                    <div class="mb-3">

                        <label class="form-label">
                            Tanggal Assessment
                        </label>

                        <input
                            type="date"
                            name="tanggal_penilaian"
                            id="edit_tanggal"
                            class="form-control"
                        >

                    </div>


                    <div class="mb-3">

                        <label class="form-label">
                            Assessor
                        </label>

                        <input
                            type="text"
                            name="assessor"
                            id="edit_assessor"
                            class="form-control"
                        >

                    </div>


                    <div>

                        <label class="form-label">
                            Catatan
                        </label>

                        <textarea
                            name="catatan"
                            id="edit_catatan"
                            class="form-control"
                            rows="3"
                        ></textarea>

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

                        <i class="bi bi-check-lg me-1"></i>

                        Simpan Perubahan

                    </button>

                </div>

            </form>

        </div>

    </div>

</div>


<!-- =======================================================
     MODAL HAPUS NILAI
======================================================= -->

<div
    class="modal fade"
    id="modalDeleteSkill"
    tabindex="-1"
    aria-hidden="true"
>

    <div class="modal-dialog modal-dialog-centered">

        <div class="modal-content">

            <form method="POST">

                <input
                    type="hidden"
                    name="action"
                    value="delete_skill"
                >

                <input
                    type="hidden"
                    name="id_penilaian"
                    id="delete_id_penilaian"
                >


                <div class="modal-header">

                    <div>

                        <h5 class="modal-title fw-bold mb-1">
                            Hapus Nilai Assessment
                        </h5>

                        <div class="small text-muted">
                            Konfirmasi penghapusan data.
                        </div>

                    </div>


                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="modal"
                    ></button>

                </div>


                <div class="modal-body">

                    <div class="delete-warning">

                        <div class="d-flex gap-2">

                            <div>

                                <i class="bi bi-exclamation-triangle-fill fs-5"></i>

                            </div>


                            <div>

                                <div class="fw-semibold mb-1">
                                    Yakin ingin menghapus nilai ini?
                                </div>

                                <div class="text-muted">
                                    Data assessment yang dihapus tidak dapat dikembalikan.
                                </div>

                            </div>

                        </div>

                    </div>


                    <div class="mt-3">

                        <div class="small text-muted">
                            Skill
                        </div>

                        <div
                            class="fw-semibold"
                            id="delete_skill_name"
                        >
                            -
                        </div>

                    </div>


                    <div class="row mt-2">

                        <div class="col-6">

                            <div class="small text-muted">
                                Tahun
                            </div>

                            <div
                                class="fw-semibold"
                                id="delete_skill_year"
                            >
                                -
                            </div>

                        </div>


                        <div class="col-6">

                            <div class="small text-muted">
                                Nilai
                            </div>

                            <div
                                class="fw-semibold text-danger"
                                id="delete_skill_value"
                            >
                                -
                            </div>

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
                        class="btn btn-danger"
                    >

                        <i class="bi bi-trash me-1"></i>

                        Ya, Hapus Nilai

                    </button>

                </div>

            </form>

        </div>

    </div>

</div>


<!-- =======================================================
     MODAL DETAIL GRAFIK
======================================================= -->

<div
    class="modal fade"
    id="modalSkillDetail"
    tabindex="-1"
    aria-hidden="true"
>

    <div class="modal-dialog modal-lg modal-dialog-centered">

        <div class="modal-content">


            <div class="modal-header">

                <div>

                    <h5
                        class="modal-title fw-bold"
                        id="detailSkillTitle"
                    >
                        Detail Penilaian
                    </h5>

                    <div class="small text-muted">
                        Perkembangan nilai dari tahun ke tahun.
                    </div>

                </div>


                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="modal"
                ></button>

            </div>


            <div class="modal-body">


                <div class="chart-info mb-3">

                    <div class="d-flex justify-content-between flex-wrap gap-2">


                        <div>

                            <span class="text-muted">
                                Skill:
                            </span>

                            <strong id="detailSkillName">
                                -
                            </strong>

                        </div>


                        <div>

                            <span class="text-muted">
                                Target:
                            </span>

                            <strong>
                                3
                            </strong>

                        </div>

                    </div>

                </div>


                <div class="chart-detail-box">

                    <canvas id="skillDetailChart"></canvas>

                </div>


                <div
                    id="detailSkillEmpty"
                    class="text-center text-muted py-4 d-none"
                >
                    Belum ada riwayat penilaian untuk skill ini.
                </div>

            </div>


            <div class="modal-footer">

                <button
                    type="button"
                    class="btn btn-light border"
                    data-bs-dismiss="modal"
                >
                    Tutup
                </button>

            </div>

        </div>

    </div>

</div>


<!-- =======================================================
     MODAL EDIT TRAINING
======================================================= -->

<?php if ($training_result_column): ?>

<div
    class="modal fade"
    id="modalEditTraining"
    tabindex="-1"
    aria-hidden="true"
>

    <div class="modal-dialog modal-dialog-centered">

        <div class="modal-content">

            <form method="POST">

                <input
                    type="hidden"
                    name="action"
                    value="edit_training"
                >

                <input
                    type="hidden"
                    name="id_peserta"
                    id="training_id_peserta"
                >


                <div class="modal-header">

                    <div>

                        <h5 class="modal-title fw-bold mb-1">
                            Edit Hasil Training
                        </h5>

                        <div
                            class="small text-muted"
                            id="trainingTitle"
                        >
                            Edit hasil training.
                        </div>

                    </div>


                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="modal"
                    ></button>

                </div>


                <div class="modal-body">

                    <label class="form-label">
                        Hasil / Nilai Training
                    </label>

                    <input
                        type="text"
                        name="hasil_training"
                        id="training_hasil"
                        class="form-control"
                        placeholder="Contoh: 85 / Lulus / Kompeten"
                    >

                    <div class="small text-muted mt-2">
                        Nilai akan diperbarui langsung pada data peserta training.
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

                        <i class="bi bi-check-lg me-1"></i>

                        Simpan

                    </button>

                </div>

            </form>

        </div>

    </div>

</div>

<?php endif; ?>


<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>


<script>

/* =========================================================
   PERTAHANKAN POSISI SCROLL SETELAH POST
========================================================= */

(function () {

    const scrollStorageKey =
        'detail_pekerja_scroll_y_<?= (int) $id ?>';

    /*
     * Browser diarahkan kembali ke detail.php setelah proses
     * tambah, edit, atau hapus. Simpan posisi scroll sebelum
     * form dikirim agar setelah redirect halaman kembali ke
     * posisi yang sama.
     */
    document.addEventListener('submit', function (event) {

        const form = event.target;

        if (!(form instanceof HTMLFormElement)) {
            return;
        }

        const actionInput =
            form.querySelector('input[name="action"]');

        if (!actionInput) {
            return;
        }

        const action = actionInput.value;

        if (
            action === 'add_skill' ||
            action === 'edit_skill' ||
            action === 'delete_skill' ||
            action === 'edit_training'
        ) {
            sessionStorage.setItem(
                scrollStorageKey,
                String(window.scrollY)
            );
        }

    });


    /*
     * Kembalikan posisi setelah halaman hasil redirect selesai
     * dimuat. Dua requestAnimationFrame digunakan agar layout,
     * tabel, alert, dan elemen Bootstrap sudah selesai dirender.
     */
    window.addEventListener('load', function () {

        const savedScroll =
            sessionStorage.getItem(scrollStorageKey);

        if (savedScroll === null) {
            return;
        }

        sessionStorage.removeItem(scrollStorageKey);

        const scrollPosition =
            parseInt(savedScroll, 10);

        if (Number.isNaN(scrollPosition)) {
            return;
        }

        requestAnimationFrame(function () {

            requestAnimationFrame(function () {

                window.scrollTo({
                    top: scrollPosition,
                    left: 0,
                    behavior: 'auto'
                });

            });

        });

    });

})();


/* =========================================================
   DATA CHART
========================================================= */

const skillChartData =
    <?= json_encode(
        $chart_data,
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES
    ) ?>;


/* =========================================================
   MODAL HELPER
========================================================= */

function getModal(id)
{
    const element =
        document.getElementById(id);

    if (!element) {
        return null;
    }

    return bootstrap.Modal.getOrCreateInstance(
        element
    );
}


/* =========================================================
   SKILL SEARCH
========================================================= */

const addSkillSearch =
    document.getElementById(
        'add_skill_search'
    );

const addSkillId =
    document.getElementById(
        'add_id_skill'
    );

const skillList =
    document.getElementById(
        'skillList'
    );


function setSelectedSkillId()
{
    if (
        !addSkillSearch ||
        !addSkillId ||
        !skillList
    ) {
        return;
    }

    const keyword =
        addSkillSearch.value
            .trim()
            .toLowerCase();

    let foundId = '';

    skillList
        .querySelectorAll('option')
        .forEach(function(option) {

            if (
                option.value
                    .trim()
                    .toLowerCase()
                === keyword
            ) {

                foundId =
                    option.dataset.id || '';
            }

        });

    addSkillId.value = foundId;
}


if (addSkillSearch) {

    addSkillSearch.addEventListener(
        'input',
        setSelectedSkillId
    );

    addSkillSearch.addEventListener(
        'change',
        setSelectedSkillId
    );
}


/* =========================================================
   TAMBAH SKILL
========================================================= */

function openAddSkill(
    skillId = '',
    tahun = <?= (int) $tahun_baru ?>,
    skillName = ''
)
{
    const search =
        document.getElementById(
            'add_skill_search'
        );

    const hidden =
        document.getElementById(
            'add_id_skill'
        );

    const tahunInput =
        document.getElementById(
            'add_tahun'
        );

    const title =
        document.getElementById(
            'addSkillTitle'
        );


    if (hidden) {
        hidden.value =
            skillId || '';
    }


    if (search) {
        search.value =
            skillName || '';
    }


    if (tahunInput) {

        tahunInput.value =
            tahun ||
            <?= (int) $tahun_baru ?>;
    }


    if (title) {

        title.textContent =
            skillName
                ? 'Tambah nilai: ' +
                  skillName
                : 'Tambahkan nilai assessment.';
    }


    getModal(
        'modalAddSkill'
    )?.show();
}


/* =========================================================
   EDIT SKILL
========================================================= */

function openEditSkill(
    id,
    tahun,
    nilai,
    tanggal,
    assessor,
    catatan,
    skillName
)
{
    const idInput =
        document.getElementById(
            'edit_id_penilaian'
        );

    const tahunInput =
        document.getElementById(
            'edit_tahun_display'
        );

    const nilaiInput =
        document.getElementById(
            'edit_nilai'
        );

    const tanggalInput =
        document.getElementById(
            'edit_tanggal'
        );

    const assessorInput =
        document.getElementById(
            'edit_assessor'
        );

    const catatanInput =
        document.getElementById(
            'edit_catatan'
        );

    const title =
        document.getElementById(
            'editSkillTitle'
        );


    if (idInput) {
        idInput.value = id;
    }

    if (tahunInput) {
        tahunInput.value = tahun;
    }

    if (nilaiInput) {
        nilaiInput.value = nilai;
    }

    if (tanggalInput) {
        tanggalInput.value =
            tanggal || '';
    }

    if (assessorInput) {
        assessorInput.value =
            assessor || '';
    }

    if (catatanInput) {
        catatanInput.value =
            catatan || '';
    }


    if (title) {

        title.textContent =
            skillName
                ? 'Edit nilai: ' +
                  skillName
                : 'Edit nilai terbaru.';
    }


    getModal(
        'modalEditSkill'
    )?.show();
}


/* =========================================================
   HAPUS SKILL
========================================================= */

function openDeleteSkill(
    id,
    skillName,
    tahun,
    nilai
)
{
    const idInput =
        document.getElementById(
            'delete_id_penilaian'
        );

    const nameElement =
        document.getElementById(
            'delete_skill_name'
        );

    const yearElement =
        document.getElementById(
            'delete_skill_year'
        );

    const valueElement =
        document.getElementById(
            'delete_skill_value'
        );


    if (idInput) {
        idInput.value = id;
    }


    if (nameElement) {
        nameElement.textContent =
            skillName || '-';
    }


    if (yearElement) {
        yearElement.textContent =
            tahun || '-';
    }


    if (valueElement) {
        valueElement.textContent =
            nilai ?? '-';
    }


    getModal(
        'modalDeleteSkill'
    )?.show();
}


/* =========================================================
   CHART
========================================================= */

let skillDetailChart = null;


function openSkillDetail(
    skillId,
    skillName
)
{
    const data =
        skillChartData[skillId];

    const title =
        document.getElementById(
            'detailSkillTitle'
        );

    const name =
        document.getElementById(
            'detailSkillName'
        );

    const canvas =
        document.getElementById(
            'skillDetailChart'
        );

    const empty =
        document.getElementById(
            'detailSkillEmpty'
        );


    if (title) {
        title.textContent =
            'Detail Penilaian';
    }


    if (name) {
        name.textContent =
            skillName || '-';
    }


    if (skillDetailChart) {

        skillDetailChart.destroy();

        skillDetailChart = null;
    }


    if (
        !canvas ||
        !data ||
        !data.labels.length
    ) {

        if (canvas) {
            canvas.style.display =
                'none';
        }

        if (empty) {
            empty.classList.remove(
                'd-none'
            );
        }

    } else {

        canvas.style.display =
            'block';

        if (empty) {
            empty.classList.add(
                'd-none'
            );
        }


        skillDetailChart =
            new Chart(
                canvas,
                {

                    type: 'line',

                    data: {

                        labels:
                            data.labels,

                        datasets: [

                            {
                                label: 'Nilai',
                                data:
                                    data.values,
                                borderWidth: 2,
                                pointRadius: 5,
                                pointHoverRadius: 7,
                                tension: 0.25,
                                fill: false
                            },

                            {
                                label: 'Target',
                                data:
                                    data.labels.map(
                                        () => 3
                                    ),
                                borderWidth: 1,
                                borderDash: [
                                    6,
                                    5
                                ],
                                pointRadius: 0,
                                tension: 0,
                                fill: false
                            }

                        ]

                    },


                    options: {

                        responsive: true,
                        maintainAspectRatio: false,


                        scales: {

                            y: {

                                min: 0,
                                max: 5,

                                ticks: {
                                    stepSize: 1
                                },

                                title: {

                                    display: true,
                                    text: 'Nilai'

                                }

                            },


                            x: {

                                title: {

                                    display: true,
                                    text: 'Tahun'

                                }

                            }

                        },


                        plugins: {

                            legend: {

                                display: true,
                                position: 'top'

                            },


                            tooltip: {

                                callbacks: {

                                    title:
                                        function(context)
                                        {
                                            return (
                                                'Tahun ' +
                                                context[0].label
                                            );
                                        },


                                    label:
                                        function(context)
                                        {
                                            return (
                                                context.dataset.label +
                                                ': ' +
                                                context.parsed.y
                                            );
                                        }

                                }

                            }

                        }

                    }

                }
            );
    }


    getModal(
        'modalSkillDetail'
    )?.show();
}


/* =========================================================
   EDIT TRAINING
========================================================= */

function openEditTraining(
    idPeserta,
    hasil,
    namaTraining
)
{
    const idInput =
        document.getElementById(
            'training_id_peserta'
        );

    const hasilInput =
        document.getElementById(
            'training_hasil'
        );

    const title =
        document.getElementById(
            'trainingTitle'
        );


    if (idInput) {
        idInput.value =
            idPeserta;
    }


    if (hasilInput) {
        hasilInput.value =
            hasil || '';
    }


    if (title) {

        title.textContent =
            namaTraining
                ? 'Edit hasil: ' +
                  namaTraining
                : 'Edit hasil training.';
    }


    getModal(
        'modalEditTraining'
    )?.show();
}


/* =========================================================
   RESIZE CHART
========================================================= */

const detailModal =
    document.getElementById(
        'modalSkillDetail'
    );

if (detailModal) {

    detailModal.addEventListener(
        'shown.bs.modal',
        function() {

            if (skillDetailChart) {

                skillDetailChart.resize();
            }

        }
    );

}


/* =========================================================
   VALIDASI SKILL
========================================================= */

const addForm =
    document.querySelector(
        '#modalAddSkill form'
    );

if (addForm) {

    addForm.addEventListener(
        'submit',
        function(event) {

            setSelectedSkillId();

            if (
                !addSkillId.value
            ) {

                event.preventDefault();

                alert(
                    'Silakan pilih skill yang tersedia dari daftar.'
                );

                addSkillSearch?.focus();
            }

        }
    );

}

</script>


<?php

require __DIR__ . '/../partials/footer.php';


/*
|--------------------------------------------------------------------------
| FLUSH OUTPUT BUFFER
|--------------------------------------------------------------------------
*/

ob_end_flush();

?>