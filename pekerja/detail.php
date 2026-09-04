<?php

$page_title = 'Detail Pekerja';

/* =========================================================
   HEADER / DATABASE CONNECTION
========================================================= */
/*
 * header.php pada project ini menyediakan koneksi database
 * melalui variable $conn serta helper aplikasi.
 *
 * Harus dipanggil SEBELUM semua query database dan SEBELUM
 * proses POST agar $conn sudah tersedia.
 */
ob_start();
require __DIR__ . '/../partials/header.php';

/* Pastikan koneksi benar-benar tersedia. */
if (!isset($conn) || !($conn instanceof mysqli)) {
    die('Koneksi database tidak tersedia. Pastikan partials/header.php menyediakan $conn.');
}


/* =========================================================
   PARAMETER
========================================================= */

$id = (int) ($_GET['id'] ?? 0);

if ($id <= 0) {
    header('Location: index.php');
    exit;
}


/* =========================================================
   HELPER REDIRECT
========================================================= */

function redirectDetail($id, $type, $message = '', $focusSkill = 0)
{
    $url = 'detail.php?id=' . (int) $id . '&' . $type . '=1';

    if ($message !== '') {
        $url .= '&msg=' . urlencode($message);
    }

    /*
     * Setelah POST berhasil, kembalikan user ke baris skill yang sama.
     * Hash dipakai sebagai anchor sehingga browser tidak hanya mengandalkan
     * posisi scroll pixel yang bisa berubah setelah halaman di-render ulang.
     */
    if ((int) $focusSkill > 0) {
        $url .= '#skill-row-' . (int) $focusSkill;
    }

    header('Location: ' . $url);
    exit;
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


        /* CEK DUPLIKAT */

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
                'Gagal memeriksa data assessment.'
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


        /* INSERT */

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
                'Gagal menyiapkan penyimpanan assessment.'
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
                'Nilai assessment berhasil ditambahkan.',
                $id_skill
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
       EDIT NILAI TERBARU PER SKILL
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


        /* CEK ASSESSMENT */

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
                'Gagal memeriksa assessment.'
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


        /* UPDATE */

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
                'Gagal menyiapkan update assessment.'
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
                'Nilai assessment berhasil diperbarui.',
                (int) $assessment['id_skill']
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
       HAPUS NILAI TERBARU PER SKILL
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


        /*
         * Pastikan data assessment memang milik
         * pekerja yang sedang dibuka.
         */

        $stmt = $conn->prepare("
            SELECT
                id,
                id_skill,
                tahun,
                nilai
            FROM penilaian_skill
            WHERE id = ?
              AND id_pekerja = ?
            LIMIT 1
        ");


        if (!$stmt) {
            redirectDetail(
                $id,
                'error',
                'Gagal memeriksa data assessment.'
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


        /*
         * Pastikan ID yang dihapus adalah nilai TERBARU
         * dari skill tersebut.
         */

        $stmt = $conn->prepare("
            SELECT
                id
            FROM penilaian_skill
            WHERE id_pekerja = ?
              AND id_skill = ?
            ORDER BY tahun DESC, id DESC
            LIMIT 1
        ");


        if (!$stmt) {
            redirectDetail(
                $id,
                'error',
                'Gagal memeriksa nilai terbaru skill.'
            );
        }


        $id_skill_check = (int) $assessment['id_skill'];


        $stmt->bind_param(
            'ii',
            $id,
            $id_skill_check
        );

        $stmt->execute();

        $latest_check = $stmt->get_result()->fetch_assoc();

        $stmt->close();


        if (
            !$latest_check ||
            (int) $latest_check['id'] !== $id_penilaian
        ) {

            redirectDetail(
                $id,
                'error',
                'Hanya nilai terbaru dari skill yang dapat dihapus.'
            );
        }


        /*
         * HAPUS DATA
         */

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
                'Gagal menyiapkan proses penghapusan.'
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

                redirectDetail(
                    $id,
                    'deleted',
                    'Nilai assessment berhasil dihapus.',
                    $id_skill_check
                );

            }


            redirectDetail(
                $id,
                'error',
                'Nilai assessment tidak berhasil dihapus.'
            );
        }


        $error = $stmt->error;

        $stmt->close();


        redirectDetail(
            $id,
            'error',
            'Gagal menghapus nilai assessment: ' . $error
        );
    }


    /* =====================================================
       EDIT HASIL TRAINING
    ===================================================== */

    if ($action === 'edit_training') {

        $id_peserta = (int) ($_POST['id_peserta'] ?? 0);

        $hasil = trim(
            $_POST['hasil_training'] ?? ''
        );


        if ($id_peserta <= 0) {
            redirectDetail(
                $id,
                'error',
                'Data training tidak valid.'
            );
        }


        /* CARI KOLOM HASIL TRAINING */

        $training_columns = [];

        $columns_result = $conn->query("
            SHOW COLUMNS FROM training_peserta
        ");


        if ($columns_result) {

            while ($column = $columns_result->fetch_assoc()) {

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


        /* CEK PESERTA */

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
                'Gagal memeriksa data peserta training.'
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
                'Gagal menyiapkan update training.'
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
   HEADER
========================================================= */




/* =========================================================
   PESAN
========================================================= */

$success_message = '';
$error_message   = '';


if (isset($_GET['saved'])) {

    $success_message =
        $_GET['msg']
        ??
        'Nilai assessment berhasil ditambahkan.';
}


if (isset($_GET['updated'])) {

    $success_message =
        $_GET['msg']
        ??
        'Nilai assessment berhasil diperbarui.';
}


if (isset($_GET['deleted'])) {

    $success_message =
        $_GET['msg']
        ??
        'Nilai assessment berhasil dihapus.';
}


if (isset($_GET['training_updated'])) {

    $success_message =
        $_GET['msg']
        ??
        'Hasil training berhasil diperbarui.';
}


if (isset($_GET['error'])) {

    $error_message =
        $_GET['msg']
        ??
        'Terjadi kesalahan.';
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


$stmt->bind_param(
    'i',
    $id
);

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
   SEMUA RIWAYAT ASSESSMENT
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


$stmt->bind_param(
    'i',
    $id
);

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

/* Tahun lama di kiri, tahun terbaru selalu di kanan. */
sort($years, SORT_NUMERIC);


/* =========================================================
   MATRIX NILAI
========================================================= */

$history_matrix = [];


foreach ($assessment_data as $row) {

    $skill_id = (int) $row['id_skill'];

    $tahun = (int) $row['tahun'];


    if (!isset($history_matrix[$skill_id])) {

        $history_matrix[$skill_id] = [];
    }


    if (!isset($history_matrix[$skill_id][$tahun])) {

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
        &&
        !empty($history_matrix[$skill_id])
    ) {

        $skill_years =
            array_keys(
                $history_matrix[$skill_id]
            );

        rsort($skill_years);

        $latest_year =
            $skill_years[0];

        $latest_skill[$skill_id] =
            $history_matrix[$skill_id][$latest_year];
    }
}


/* =========================================================
   TAHUN TERBARU
========================================================= */

$tahun_terbaru = !empty($years)
    ? (int) end($years)
    : (int) date('Y');


$tahun_baru = $tahun_terbaru + 1;


/* =========================================================
   STATISTIK
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
        $latest_skill[$skill_id]
        ?? null;

    $target = 3;


    if (
        $latest
        &&
        $latest['nilai'] !== null
    ) {

        $actual =
            (float) $latest['nilai'];

        $gap =
            max(
                0,
                $target - $actual
            );


        if ($gap >= 2) {

            $status =
                'Perlu Training';

            $status_class =
                'status-bad';

            $total_training++;

        } elseif ($gap > 0) {

            $status =
                'Perlu Peningkatan';

            $status_class =
                'status-warning';

            $total_peningkatan++;

        } else {

            $status =
                'Kompeten';

            $status_class =
                'status-good';

            $total_kompeten++;
        }


        $total_skill++;

        $total_actual += $actual;

        $total_target += $target;

    } else {

        $actual = null;

        $gap = null;

        $status =
            'Belum Dinilai';

        $status_class =
            'status-neutral';
    }


    $skill_mapping[] = [
        'id_skill' =>
            $skill_id,

        'nama_skill' =>
            $skill['nama_skill'],

        'latest' =>
            $latest,

        'actual' =>
            $actual,

        'target' =>
            $target,

        'gap' =>
            $gap,

        'status' =>
            $status,

        'status_class' =>
            $status_class
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

    $overall_status =
        'Perlu Training';

    $overall_class =
        'status-bad';

} elseif ($total_peningkatan > 0) {

    $overall_status =
        'Perlu Peningkatan';

    $overall_class =
        'status-warning';

} elseif ($total_skill > 0) {

    $overall_status =
        'Kompeten';

    $overall_class =
        'status-good';

} else {

    $overall_status =
        'Belum Dinilai';

    $overall_class =
        'status-neutral';
}


/* =========================================================
   DATA GRAFIK PER SKILL
========================================================= */

$chart_data = [];


foreach ($skills as $skill) {

    $skill_id =
        (int) $skill['id'];

    $labels = [];
    $values = [];
    $dates = [];
    $assessors = [];


    if (
        isset($history_matrix[$skill_id])
        &&
        !empty($history_matrix[$skill_id])
    ) {

        $skill_years =
            array_keys(
                $history_matrix[$skill_id]
            );

        sort($skill_years);


        foreach ($skill_years as $tahun) {

            $data =
                $history_matrix[$skill_id][$tahun];


            $labels[] =
                (string) $tahun;


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
        'nama' =>
            $skill['nama_skill'],

        'labels' =>
            $labels,

        'values' =>
            $values,

        'dates' =>
            $dates,

        'assessors' =>
            $assessors
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

        $training_data[] =
            $row;
    }


    $stmt_training->close();
}


/* =========================================================
   DETEKSI KOLOM NILAI TRAINING
========================================================= */

$training_result_column = null;

$training_columns = [];


$columns_result = $conn->query("
    SHOW COLUMNS FROM training_peserta
");


if ($columns_result) {

    while ($column = $columns_result->fetch_assoc()) {

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
   DETAIL PEKERJA
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


/* HAPUS */

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


/* DETAIL GRAFIK */

.btn-skill-detail {
    background: #f3f5f8;
    border: 1px solid #dfe4eb;
    color: #475467;
}

.btn-skill-detail:hover {
    background: #e9edf3;
    color: #123f7a;
}


/* TAMBAH */

.btn-skill-add {
    background: #fff;
    border: 1px dashed #b8c5d6;
    color: #123f7a;
}

.btn-skill-add:hover {
    background: #eef5ff;
    border-color: #123f7a;
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
   DETAIL GRAFIK SKILL
========================================================= */

.chart-detail-box {
    height: 300px;
    position: relative;
    padding: 4px 2px 0;
}

.chart-info {
    background: linear-gradient(135deg, #f7faff, #eef5ff);
    border: 1px solid #dbe7f8;
    border-radius: 12px;
    padding: 12px 14px;
    font-size: 12px;
}

.chart-info .skill-name-detail {
    color: #123f7a;
    font-weight: 750;
}

.chart-target-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 30px;
    padding: 4px 8px;
    border-radius: 7px;
    background: #ffecee;
    color: #dc3545;
    font-weight: 750;
}

.detail-stat-grid {
    display: grid;
    grid-template-columns: repeat(5, minmax(0, 1fr));
    gap: 10px;
    margin-bottom: 16px;
}

.detail-stat-card {
    border: 1px solid #e7ebf1;
    border-radius: 12px;
    padding: 12px;
    min-height: 92px;
}

.detail-stat-card.latest {
    background: #eef6ff;
}

.detail-stat-card.target {
    background: #fff1f3;
}

.detail-stat-card.development {
    background: #edf9f3;
}

.detail-stat-card.count {
    background: #f4f0ff;
}

.detail-stat-card.average {
    background: #fff8e9;
}

.detail-stat-label {
    color: #788396;
    font-size: 10px;
    font-weight: 650;
    margin-bottom: 5px;
}

.detail-stat-value {
    color: #172033;
    font-size: 21px;
    line-height: 1.1;
    font-weight: 800;
}

.detail-stat-card.development .detail-stat-value {
    color: #198754;
}

.detail-stat-meta {
    margin-top: 7px;
    color: #788396;
    font-size: 10px;
    line-height: 1.35;
}

.detail-stat-meta i {
    margin-right: 4px;
}

.detail-chart-legend {
    display: flex;
    justify-content: center;
    align-items: center;
    flex-wrap: wrap;
    gap: 16px;
    margin: 2px 0 8px;
    font-size: 11px;
    color: #667184;
}

.legend-line {
    width: 30px;
    height: 3px;
    display: inline-block;
    vertical-align: middle;
    margin-right: 5px;
    border-radius: 3px;
    background: #2f9be8;
}

.legend-target {
    height: 0;
    border-top: 2px dashed #ff7d98;
    background: transparent;
}

.detail-development-note {
    margin-top: 10px;
    padding: 9px 12px;
    border-radius: 9px;
    background: #f7f9fc;
    border: 1px solid #edf0f4;
    color: #667184;
    font-size: 10px;
    text-align: center;
}

.detail-development-note strong {
    color: #123f7a;
}

.training-result {
    font-weight: 600;
    color: #123b72;
}



/* =========================================================
   DETAIL PAGE - VISUAL REFRESH
========================================================= */

.detail-page {
    color: #172033;
}

.detail-topbar {
    min-height: 106px;
    padding: 22px 24px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
    background: #fff;
    border: 1px solid #edf0f5;
    border-radius: 18px;
    box-shadow: 0 8px 26px rgba(25, 45, 75, .06);
}

.detail-topbar-left {
    display: flex;
    align-items: center;
    gap: 18px;
    min-width: 0;
}

.detail-back-btn {
    width: 50px;
    height: 50px;
    flex: 0 0 50px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 12px;
    border: 1px solid #e4e9f1;
    background: #f8fafc;
    color: #667085;
    text-decoration: none;
    font-size: 20px;
    transition: .2s ease;
}

.detail-back-btn:hover {
    background: #eef5ff;
    border-color: #cfe0f7;
    color: #123f7a;
    transform: translateX(-1px);
}

.detail-page-title {
    margin: 0;
    color: #10213d;
    font-size: 25px;
    line-height: 1.15;
    font-weight: 800;
    letter-spacing: -.4px;
}

.detail-page-subtitle {
    margin-top: 6px;
    color: #7a879c;
    font-size: 14px;
    line-height: 1.4;
}

.detail-topbar-actions {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
    justify-content: flex-end;
}

.detail-btn {
    min-height: 44px;
    padding: 0 17px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 9px;
    border-radius: 10px;
    text-decoration: none;
    font-size: 14px;
    font-weight: 700;
    border: 1px solid transparent;
    transition: .2s ease;
    white-space: nowrap;
}

.detail-btn i {
    font-size: 16px;
}

.detail-btn-danger {
    background: #e62f45;
    color: #fff;
    box-shadow: 0 5px 12px rgba(230, 47, 69, .12);
}

.detail-btn-danger:hover {
    background: #cf2539;
    color: #fff;
    transform: translateY(-1px);
}

.detail-btn-warning {
    background: #f9b800;
    color: #111827;
    box-shadow: 0 5px 12px rgba(249, 184, 0, .12);
}

.detail-btn-warning:hover {
    background: #e6a900;
    color: #111827;
    transform: translateY(-1px);
}

/* PROFILE */

.profile-card {
    padding: 27px 25px 24px;
    background: #fff;
    border: 1px solid #edf0f5;
    border-radius: 18px;
    box-shadow: 0 8px 26px rgba(25, 45, 75, .05);
}

.profile-main {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 24px;
}

.profile-left {
    display: flex;
    align-items: center;
    gap: 28px;
    min-width: 0;
}

.profile-avatar {
    width: 94px;
    height: 94px;
    border-radius: 14px;
    background: #0d57bd;
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    flex: 0 0 94px;
    font-size: 43px;
    font-weight: 700;
    box-shadow: inset 0 -8px 20px rgba(0, 0, 0, .08);
}

.profile-label {
    color: #8190a6;
    font-size: 13px;
    line-height: 1.2;
    font-weight: 700;
    letter-spacing: .1px;
    text-transform: uppercase;
}

.profile-name {
    margin: 10px 0 7px;
    color: #10213d;
    font-size: 29px;
    line-height: 1.1;
    font-weight: 800;
    letter-spacing: -.6px;
}

.profile-department {
    color: #66758e;
    font-size: 15px;
    font-weight: 500;
}

.profile-status {
    text-align: right;
    min-width: 155px;
}

.profile-status .badge-status {
    margin-top: 14px;
    padding: 8px 15px;
    border-radius: 999px;
    font-size: 13px;
}

.profile-divider {
    height: 1px;
    margin: 27px 0 25px;
    background: #e7ebf1;
}

.profile-info-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 30px;
}

.profile-info-label {
    margin-bottom: 9px;
    color: #8190a6;
    font-size: 13px;
}

.profile-info-value {
    color: #172033;
    font-size: 14px;
    font-weight: 750;
}

.profile-info-item .badge-status {
    padding: 5px 10px;
    border-radius: 999px;
    font-size: 12px;
}

/* STAT CARDS */

.detail-stat-row {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 15px;
}

.detail-stat-item {
    min-height: 128px;
    padding: 22px 20px;
    display: flex;
    align-items: center;
    gap: 17px;
    border: 1px solid #edf0f5;
    border-radius: 17px;
    background: #fff;
    box-shadow: 0 8px 24px rgba(25, 45, 75, .05);
}

.detail-stat-icon {
    width: 58px;
    height: 58px;
    flex: 0 0 58px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    font-size: 25px;
}

.icon-blue {
    background: #eaf2ff;
    color: #0d67d7;
}

.icon-green {
    background: #e4f8ec;
    color: #16864d;
}

.icon-orange {
    background: #fff5dd;
    color: #f0a500;
}

.icon-red {
    background: #ffe8ec;
    color: #e33b50;
}

.detail-stat-content {
    min-width: 0;
}

.detail-stat-title {
    color: #78869b;
    font-size: 13px;
    font-weight: 600;
    margin-bottom: 5px;
}

.detail-stat-number {
    color: #172033;
    font-size: 27px;
    line-height: 1.05;
    font-weight: 800;
}

.detail-stat-number span {
    color: #68758b;
    font-size: 17px;
    font-weight: 600;
}

.number-green {
    color: #16864d;
}

.number-orange {
    color: #f0a500;
}

.number-red {
    color: #e33b50;
}

.detail-stat-description {
    margin-top: 7px;
    color: #78869b;
    font-size: 12px;
}

/* MAPPING */

.detail-page .cardx {
    border-radius: 18px;
    border: 1px solid #edf0f5;
    box-shadow: 0 8px 26px rgba(25, 45, 75, .05);
}

.detail-page .card-title {
    color: #10213d;
    font-size: 18px;
    font-weight: 800;
}

.detail-page .section-note {
    color: #8190a6;
    font-size: 13px;
}

.detail-page .table {
    border-collapse: separate;
    border-spacing: 0;
}

.detail-page .table thead th {
    padding: 13px 15px;
    background: #fbfcfe;
    color: #7b8799;
    border-top: 1px solid #edf0f5;
    border-bottom: 1px solid #edf0f5;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
}

.detail-page .table thead th:first-child {
    border-left: 1px solid #edf0f5;
    border-top-left-radius: 10px;
}

.detail-page .table thead th:last-child {
    border-right: 1px solid #edf0f5;
    border-top-right-radius: 10px;
}

.detail-page .table tbody td {
    padding: 15px;
    color: #24324a;
    border-bottom: 1px solid #edf0f5;
    font-size: 13px;
    background: #fff;
}

.detail-page .table tbody tr:last-child td:first-child {
    border-bottom-left-radius: 10px;
}

.detail-page .table tbody tr:last-child td:last-child {
    border-bottom-right-radius: 10px;
}

.detail-page .table-hover tbody tr:hover td {
    background: #fbfdff;
}

.mapping-value {
    color: #172033;
    font-size: 15px;
    font-weight: 800;
}

.skill-mapping-more {
    padding-top: 14px;
}

.btn-skill-more {
    border: 0;
    background: transparent;
    color: #0969da;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 5px 10px;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
}

.btn-skill-more:hover {
    color: #064da2;
}

.btn-skill-more i {
    font-size: 15px;
    transition: transform .2s ease;
}

.btn-skill-more.expanded i {
    transform: rotate(180deg);
}

/* STATUS */

.detail-page .status-neutral,
.detail-page .status-good,
.detail-page .status-warning,
.detail-page .status-bad {
    border-radius: 999px;
    padding: 6px 12px;
    font-size: 11px;
    font-weight: 700;
}

/* =========================================================
   RESPONSIVE DETAIL
========================================================= */

@media (max-width: 1100px) {
    .detail-stat-row {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 768px) {
    .detail-topbar {
        padding: 18px;
        align-items: flex-start;
        flex-direction: column;
    }

    .detail-topbar-actions {
        width: 100%;
        justify-content: flex-start;
    }

    .detail-btn {
        flex: 1 1 auto;
    }

    .profile-main {
        align-items: flex-start;
        flex-direction: column;
    }

    .profile-status {
        text-align: left;
        min-width: 0;
    }

    .profile-status .badge-status {
        margin-top: 8px;
    }

    .profile-info-grid {
        grid-template-columns: 1fr;
        gap: 18px;
    }

    .profile-left {
        gap: 18px;
    }

    .profile-avatar {
        width: 76px;
        height: 76px;
        flex-basis: 76px;
        font-size: 34px;
    }

    .profile-name {
        font-size: 24px;
    }

    .detail-stat-row {
        grid-template-columns: 1fr;
    }

    .detail-stat-item {
        min-height: 108px;
    }
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
        height: 250px;
    }

    .detail-stat-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .detail-stat-card:last-child {
        grid-column: 1 / -1;
    }
}

</style>


<div class="detail-page">


<!-- =======================================================
     ALERT
======================================================= -->

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

<div class="detail-topbar cardx mb-4">

    <div class="detail-topbar-left">

        <a href="index.php" class="detail-back-btn" title="Kembali">
            <i class="bi bi-arrow-left"></i>
        </a>

        <div>
            <h1 class="detail-page-title">Detail Pekerja</h1>
            <div class="detail-page-subtitle">
                Informasi detail pekerja, kompetensi, dan riwayat penilaian
            </div>
        </div>

    </div>

    <div class="detail-topbar-actions">

        <a
            href="download_pdf.php?id=<?= (int) $id ?>"
            class="detail-btn detail-btn-danger"
        >
            <i class="bi bi-file-earmark-pdf"></i>
            <span>Download PDF</span>
        </a>

        <a
            href="../penilaian/index.php?pekerja=<?= (int) $id ?>"
            class="detail-btn detail-btn-warning"
        >
            <i class="bi bi-pencil-square"></i>
            <span>Update Penilaian</span>
        </a>

    </div>

</div>


<!-- =======================================================
     PROFILE
======================================================= -->

<div class="cardx profile-card mb-4">

    <div class="profile-main">

        <div class="profile-left">

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

            <div class="profile-identity">

                <div class="profile-label">PROFIL PEKERJA</div>

                <h2 class="profile-name">
                    <?= e($pekerja['nama'] ?? '-') ?>
                </h2>

                <div class="profile-department">
                    <?= e(
                        $pekerja['departemen']
                        ?: 'Departemen belum ditentukan'
                    ) ?>
                </div>

            </div>

        </div>

        <div class="profile-status">

            <div class="profile-label">STATUS KOMPETENSI</div>

            <span class="badge-status <?= e($overall_class) ?>">
                <?= e($overall_status) ?>
            </span>

        </div>

    </div>

    <div class="profile-divider"></div>

    <div class="profile-info-grid">

        <div class="profile-info-item">
            <div class="profile-info-label">No. Reg / ID</div>
            <div class="profile-info-value">
                <?= (int) $pekerja['id'] ?>
            </div>
        </div>

        <div class="profile-info-item">
            <div class="profile-info-label">Departemen</div>
            <div class="profile-info-value">
                <?= e($pekerja['departemen'] ?: '-') ?>
            </div>
        </div>

        <div class="profile-info-item">
            <div class="profile-info-label">Status Pekerja</div>
            <div>
                <?php if (($pekerja['status'] ?? '') === 'Aktif'): ?>
                    <span class="badge-status status-good">Aktif</span>
                <?php else: ?>
                    <span class="badge-status status-bad">Nonaktif</span>
                <?php endif; ?>
            </div>
        </div>

    </div>

</div>


<!-- =======================================================
     STATISTIK
======================================================= -->

<div class="detail-stat-row mb-4">

    <div class="cardx detail-stat-item">
        <div class="detail-stat-icon icon-blue">
            <i class="bi bi-graph-up-arrow"></i>
        </div>
        <div class="detail-stat-content">
            <div class="detail-stat-title">Rata-rata Skill</div>
            <div class="detail-stat-number">
                <?= number_format($rata_rata, 2) ?>
                <span>/ 5</span>
            </div>
            <div class="detail-stat-description">
                Target <?= number_format($rata_target, 2) ?>
            </div>
        </div>
    </div>

    <div class="cardx detail-stat-item">
        <div class="detail-stat-icon icon-green">
            <i class="bi bi-check-circle-fill"></i>
        </div>
        <div class="detail-stat-content">
            <div class="detail-stat-title">Skill Kompeten</div>
            <div class="detail-stat-number number-green">
                <?= number_format($total_kompeten) ?>
            </div>
            <div class="detail-stat-description">
                memenuhi target
            </div>
        </div>
    </div>

    <div class="cardx detail-stat-item">
        <div class="detail-stat-icon icon-orange">
            <i class="bi bi-graph-up-arrow"></i>
        </div>
        <div class="detail-stat-content">
            <div class="detail-stat-title">Perlu Peningkatan</div>
            <div class="detail-stat-number number-orange">
                <?= number_format($total_peningkatan) ?>
            </div>
            <div class="detail-stat-description">
                masih ada gap
            </div>
        </div>
    </div>

    <div class="cardx detail-stat-item">
        <div class="detail-stat-icon icon-red">
            <i class="bi bi-mortarboard-fill"></i>
        </div>
        <div class="detail-stat-content">
            <div class="detail-stat-title">Perlu Training</div>
            <div class="detail-stat-number number-red">
                <?= number_format($total_training) ?>
            </div>
            <div class="detail-stat-description">
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

                    <tr class="<?= $no > 5 ? 'skill-extra-row d-none' : '' ?>">

                        <td>
                            <?= $no++ ?>
                        </td>


                        <td>

                            <div class="fw-semibold">

                                <?= e(
                                    $item['nama_skill']
                                ) ?>

                            </div>


                            <?php if ($item['latest']): ?>

                                <div class="small text-muted">

                                    <?= e(
                                        $item['latest']['tahun']
                                    ) ?>

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
                                        : '-'
                                    ?>

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

                            <span
                                class="badge-status <?= e(
                                    $item['status_class']
                                ) ?>"
                            >

                                <?= e(
                                    $item['status']
                                ) ?>

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

    <?php if (count($skill_mapping) > 5): ?>
        <div class="skill-mapping-more text-center">
            <button
                type="button"
                class="btn-skill-more"
                id="skillMappingMoreBtn"
                onclick="toggleSkillMapping()"
            >
                <span id="skillMappingMoreText">Lihat semua skill</span>
                <i class="bi bi-chevron-down" id="skillMappingMoreIcon"></i>
            </button>
        </div>
    <?php endif; ?>

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

                Edit dan hapus hanya pada nilai terbaru setiap skill.

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
                            width="150"
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


                    <tr id="skill-row-<?= $skill_id ?>">


                        <!-- SKILL -->

                        <td>

                            <div class="fw-semibold">

                                <?= e(
                                    $skill['nama_skill']
                                ) ?>

                            </div>

                        </td>


                        <!-- TAHUN -->

                        <?php foreach ($years as $tahun): ?>

                            <?php

                            $history =
                                $history_matrix[
                                    $skill_id
                                ][$tahun]
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
                                                        $history[
                                                            'tanggal_penilaian'
                                                        ]
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


                                <!-- GRAFIK: SELALU PALING KIRI -->

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


                                <?php if ($latest): ?>


                                    <!-- EDIT -->

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
                                                    $latest['tanggal_penilaian'] ?? ''
                                                ),
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>,
                                            <?= htmlspecialchars(
                                                json_encode(
                                                    $latest['assessor'] ?? ''
                                                ),
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>,
                                            <?= htmlspecialchars(
                                                json_encode(
                                                    $latest['catatan'] ?? ''
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


                                    <!-- HAPUS -->

                                    <form
                                        method="POST"
                                        class="d-inline"
                                        onsubmit="return confirmDeleteSkill(
                                            <?= htmlspecialchars(
                                                json_encode(
                                                    $skill['nama_skill']
                                                ),
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>,
                                            <?= (int) $latest['tahun'] ?>,
                                            <?= (float) $latest['nilai'] ?>
                                        );"
                                    >

                                        <input
                                            type="hidden"
                                            name="action"
                                            value="delete_skill"
                                        >

                                        <input
                                            type="hidden"
                                            name="id_penilaian"
                                            value="<?= (int) $latest['id'] ?>"
                                        >

                                        <button
                                            type="submit"
                                            class="btn-skill-action btn-skill-delete"
                                            title="Hapus nilai terbaru"
                                        >

                                            <i class="bi bi-trash"></i>

                                        </button>

                                    </form>


                                <?php else: ?>


                                    <!-- TAMBAH -->

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


                    if (
                        $status_training
                        === 'Selesai'
                    ) {

                        $training_class =
                            'status-good';

                    } elseif (
                        $status_training
                        === 'Dibatalkan'
                    ) {

                        $training_class =
                            'status-bad';

                    } elseif (
                        $status_training
                        === 'Sedang Berlangsung'
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
                                ]
                                ?? ''
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

                            <?= e(
                                $tgl_mulai
                            ) ?>


                            <?php if (
                                $tgl_selesai !== '-'
                            ): ?>

                                <span class="text-muted">
                                    s/d
                                </span>

                                <?= e(
                                    $tgl_selesai
                                ) ?>

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
                                                    $training['nama_training']
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

                            <span
                                class="badge-status <?= e(
                                    $training_class
                                ) ?>"
                            >

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
                            value=""
                        >

                        <datalist id="skillList">

                            <?php foreach ($skills as $skill): ?>

                                <option
                                    value="<?= e($skill['nama_skill']) ?>"
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
     MODAL EDIT NILAI TERBARU
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

                            Tahun tidak diubah karena ini merupakan
                            nilai terbaru skill.

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

                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">

                        <div>

                            <span class="text-muted">
                                Skill:
                            </span>

                            <strong
                                id="detailSkillName"
                                class="skill-name-detail"
                            >
                                -
                            </strong>

                        </div>


                        <div class="d-flex align-items-center gap-2">

                            <span class="text-muted">
                                Target:
                            </span>

                            <span
                                id="detailSkillTarget"
                                class="chart-target-badge"
                            >
                                3
                            </span>

                        </div>

                    </div>

                </div>


                <div class="detail-stat-grid">


                    <div class="detail-stat-card latest">

                        <div class="detail-stat-label">
                            Nilai Terbaru
                        </div>

                        <div
                            class="detail-stat-value"
                            id="detailLatestValue"
                        >
                            -
                        </div>

                        <div
                            class="detail-stat-meta"
                            id="detailLatestMeta"
                        >

                            <i class="bi bi-star"></i>

                            <span>
                                Tahun -
                            </span>

                        </div>

                    </div>


                    <div class="detail-stat-card target">

                        <div class="detail-stat-label">
                            Target
                        </div>

                        <div
                            class="detail-stat-value"
                            id="detailTargetValue"
                        >
                            3
                        </div>

                        <div class="detail-stat-meta">

                            <i class="bi bi-bullseye"></i>

                            Minimal nilai

                        </div>

                    </div>


                    <div class="detail-stat-card development">

                        <div class="detail-stat-label">
                            Perkembangan
                        </div>

                        <div
                            class="detail-stat-value"
                            id="detailDevelopmentValue"
                        >
                            -
                        </div>

                        <div
                            class="detail-stat-meta"
                            id="detailDevelopmentMeta"
                        >

                            <i class="bi bi-graph-up-arrow"></i>

                            Belum ada perkembangan

                        </div>

                    </div>


                    <div class="detail-stat-card count">

                        <div class="detail-stat-label">
                            Jumlah Penilaian
                        </div>

                        <div
                            class="detail-stat-value"
                            id="detailCountValue"
                        >
                            0
                        </div>

                        <div class="detail-stat-meta">

                            <i class="bi bi-calendar3"></i>

                            <span>
                                Riwayat tahun
                            </span>

                        </div>

                    </div>


                    <div class="detail-stat-card average">

                        <div class="detail-stat-label">
                            Rata-rata
                        </div>

                        <div
                            class="detail-stat-value"
                            id="detailAverageValue"
                        >
                            -
                        </div>

                        <div class="detail-stat-meta">

                            <i class="bi bi-bar-chart"></i>

                            Semua tahun

                        </div>

                    </div>


                </div>


                <div class="detail-chart-legend">

                    <span>
                        <span class="legend-line"></span>
                        Nilai Aktual
                    </span>

                    <span>
                        <span class="legend-line legend-target"></span>
                        Target (Minimum)
                    </span>

                </div>


                <div class="chart-detail-box">

                    <canvas id="skillDetailChart"></canvas>

                </div>


                <div
                    id="detailDevelopmentNote"
                    class="detail-development-note d-none"
                ></div>


                <div
                    id="detailSkillEmpty"
                    class="text-center text-muted py-4 d-none"
                >

                    <i class="bi bi-bar-chart fs-3 d-block mb-2"></i>

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

                        Nilai akan diperbarui langsung pada
                        data peserta training.

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


<!-- =======================================================
     CHART.JS
======================================================= -->

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>


<script>

/* =========================================================
   DATA GRAFIK
========================================================= */

const skillChartData =
    <?= json_encode(
        $chart_data,
        JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES
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
   KONFIRMASI HAPUS
========================================================= */

function confirmDeleteSkill(
    skillName,
    tahun,
    nilai
)
{
    const nama =
        skillName || 'skill ini';

    const tahunText =
        tahun || '-';

    const nilaiText =
        Number(nilai).toFixed(0);

    return confirm(
        'Yakin ingin menghapus nilai assessment terbaru?\n\n' +
        'Skill: ' + nama + '\n' +
        'Tahun: ' + tahunText + '\n' +
        'Nilai: ' + nilaiText + ' / 5\n\n' +
        'Data yang dihapus tidak dapat dikembalikan.'
    );
}


/* =========================================================
   TAMBAH NILAI
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
        .forEach(function (option) {

            const skillName =
                option.value
                    .trim()
                    .toLowerCase();


            if (skillName === keyword) {

                foundId =
                    option.dataset.id || '';
            }

        });


    addSkillId.value =
        foundId;
}


if (addSkillSearch) {

    addSkillSearch.addEventListener(
        'input',
        function () {

            setSelectedSkillId();

        }
    );


    addSkillSearch.addEventListener(
        'change',
        function () {

            setSelectedSkillId();

        }
    );
}


function openAddSkill(
    skillId = '',
    tahun = <?= (int) $tahun_baru ?>,
    skillName = ''
)
{

    const skillSearch =
        document.getElementById(
            'add_skill_search'
        );


    const skillHidden =
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


    if (skillHidden) {

        skillHidden.value =
            skillId || '';

    }


    if (skillSearch) {

        skillSearch.value =
            skillName || '';

        setSelectedSkillId();

    }


    if (tahunInput) {

        tahunInput.value =
            tahun ||
            <?= (int) $tahun_baru ?>;

    }


    if (title) {

        title.textContent =
            skillName
                ? 'Tambah nilai: ' + skillName
                : 'Tambahkan nilai assessment.';
    }


    const modal =
        getModal(
            'modalAddSkill'
        );


    if (modal) {

        modal.show();

    }

}


/* =========================================================
   EDIT NILAI TERBARU
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

        idInput.value =
            id;
    }


    if (tahunInput) {

        tahunInput.value =
            tahun;
    }


    if (nilaiInput) {

        nilaiInput.value =
            nilai;
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
                ? 'Edit nilai: ' + skillName
                : 'Edit nilai terbaru.';
    }


    const modal =
        getModal(
            'modalEditSkill'
        );


    if (modal) {

        modal.show();

    }

}


/* =========================================================
   DETAIL GRAFIK SKILL
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


    const latestValue =
        document.getElementById(
            'detailLatestValue'
        );


    const latestMeta =
        document.getElementById(
            'detailLatestMeta'
        );


    const targetValue =
        document.getElementById(
            'detailTargetValue'
        );


    const targetBadge =
        document.getElementById(
            'detailSkillTarget'
        );


    const developmentValue =
        document.getElementById(
            'detailDevelopmentValue'
        );


    const developmentMeta =
        document.getElementById(
            'detailDevelopmentMeta'
        );


    const countValue =
        document.getElementById(
            'detailCountValue'
        );


    const averageValue =
        document.getElementById(
            'detailAverageValue'
        );


    const developmentNote =
        document.getElementById(
            'detailDevelopmentNote'
        );


    const TARGET = 3;


    if (title) {
        title.textContent =
            'Detail Penilaian Skill';
    }


    if (name) {
        name.textContent =
            skillName || '-';
    }


    if (targetValue) {
        targetValue.textContent =
            TARGET;
    }


    if (targetBadge) {
        targetBadge.textContent =
            TARGET;
    }


    if (skillDetailChart) {

        skillDetailChart.destroy();

        skillDetailChart = null;
    }


    const history =
        data &&
        Array.isArray(data.labels) &&
        Array.isArray(data.values)

            ? data.labels
                .map(function (year, index) {

                    return {

                        tahun:
                            String(year),

                        nilai:
                            data.values[index] === null ||
                            data.values[index] === undefined

                                ? null

                                : Number(
                                    data.values[index]
                                )
                    };

                })
                .filter(function (item) {

                    return (
                        item.nilai !== null &&
                        !Number.isNaN(item.nilai)
                    );

                })

            : [];


    if (countValue) {

        countValue.textContent =
            history.length;
    }


    if (history.length > 0) {

        const first =
            history[0];


        const latest =
            history[
                history.length - 1
            ];


        const sum =
            history.reduce(
                function (
                    total,
                    item
                ) {

                    return (
                        total +
                        item.nilai
                    );

                },
                0
            );


        const average =
            sum / history.length;


        /*
         * PERKEMBANGAN
         * nilai terbaru - nilai pertama
         */

        const development =
            latest.nilai -
            first.nilai;


        if (latestValue) {

            latestValue.textContent =
                latest.nilai.toFixed(0);
        }


        if (latestMeta) {

            latestMeta.innerHTML =
                '<i class="bi bi-star-fill"></i>' +
                'Tahun ' +
                latest.tahun;
        }


        if (averageValue) {

            averageValue.textContent =
                average.toFixed(2);
        }


        if (developmentValue) {

            if (development > 0) {

                developmentValue.textContent =
                    '+' +
                    development.toFixed(0);

            } else if (development < 0) {

                developmentValue.textContent =
                    development.toFixed(0);

            } else {

                developmentValue.textContent =
                    '0';
            }
        }


        if (developmentMeta) {

            let icon =
                'bi bi-dash-circle';

            let text =
                'Tetap';


            if (development > 0) {

                icon =
                    'bi bi-arrow-up-circle';

                text =
                    'Meningkat';

            } else if (development < 0) {

                icon =
                    'bi bi-arrow-down-circle';

                text =
                    'Menurun';
            }


            developmentMeta.innerHTML =
                '<i class="' +
                icon +
                '"></i>' +
                text;
        }


        if (developmentNote) {

            let description;


            if (history.length === 1) {

                description =
                    'Baru ada 1 penilaian pada tahun <strong>' +
                    first.tahun +
                    '</strong>, sehingga perkembangan belum dapat dibandingkan.';

            } else if (development > 0) {

                description =
                    'Perkembangan dari <strong>' +
                    first.nilai.toFixed(0) +
                    '</strong> (' +
                    first.tahun +
                    ') menjadi <strong>' +
                    latest.nilai.toFixed(0) +
                    '</strong> (' +
                    latest.tahun +
                    ') — meningkat <strong>+' +
                    development.toFixed(0) +
                    '</strong> poin.';

            } else if (development < 0) {

                description =
                    'Perkembangan dari <strong>' +
                    first.nilai.toFixed(0) +
                    '</strong> (' +
                    first.tahun +
                    ') menjadi <strong>' +
                    latest.nilai.toFixed(0) +
                    '</strong> (' +
                    latest.tahun +
                    ') — menurun <strong>' +
                    development.toFixed(0) +
                    '</strong> poin.';

            } else {

                description =
                    'Perkembangan dari <strong>' +
                    first.nilai.toFixed(0) +
                    '</strong> (' +
                    first.tahun +
                    ') menjadi <strong>' +
                    latest.nilai.toFixed(0) +
                    '</strong> (' +
                    latest.tahun +
                    ') — tetap.';
            }


            developmentNote.innerHTML =
                description;


            developmentNote.classList.remove(
                'd-none'
            );
        }


    } else {


        if (latestValue) {

            latestValue.textContent =
                '-';
        }


        if (latestMeta) {

            latestMeta.innerHTML =
                '<i class="bi bi-star"></i>Tahun -';
        }


        if (developmentValue) {

            developmentValue.textContent =
                '-';
        }


        if (developmentMeta) {

            developmentMeta.innerHTML =
                '<i class="bi bi-dash-circle"></i>Belum ada perkembangan';
        }


        if (averageValue) {

            averageValue.textContent =
                '-';
        }


        if (developmentNote) {

            developmentNote.classList.add(
                'd-none'
            );
        }
    }


    if (
        !canvas ||
        history.length === 0
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


        const labels =
            history.map(
                function (item) {

                    return item.tahun;
                }
            );


        const values =
            history.map(
                function (item) {

                    return item.nilai;
                }
            );


        skillDetailChart =
            new Chart(
                canvas,
                {

                    type: 'line',


                    data: {

                        labels: labels,


                        datasets: [

                            {

                                label:
                                    'Nilai Aktual',

                                data:
                                    values,

                                borderColor:
                                    '#2f9be8',

                                backgroundColor:
                                    'rgba(47,155,232,0.10)',

                                borderWidth:
                                    3,

                                pointRadius:
                                    5,

                                pointHoverRadius:
                                    7,

                                pointBorderWidth:
                                    2,

                                tension:
                                    0.25,

                                fill:
                                    true

                            },


                            {

                                label:
                                    'Target (Minimum)',

                                data:
                                    labels.map(
                                        function () {
                                            return TARGET;
                                        }
                                    ),

                                borderColor:
                                    '#ff7d98',

                                borderWidth:
                                    2,

                                borderDash:
                                    [7, 6],

                                pointRadius:
                                    0,

                                pointHoverRadius:
                                    0,

                                tension:
                                    0,

                                fill:
                                    false

                            }

                        ]

                    },


                    options: {

                        responsive:
                            true,

                        maintainAspectRatio:
                            false,

                        interaction: {

                            intersect:
                                false,

                            mode:
                                'index'
                        },


                        scales: {

                            y: {

                                min:
                                    0,

                                max:
                                    5,

                                ticks: {

                                    stepSize:
                                        1,

                                    precision:
                                        0
                                },

                                title: {

                                    display:
                                        true,

                                    text:
                                        'Nilai'
                                },

                                grid: {

                                    color:
                                        '#e9edf3'
                                }

                            },


                            x: {

                                title: {

                                    display:
                                        true,

                                    text:
                                        'Tahun'
                                },

                                grid: {

                                    display:
                                        false
                                }

                            }

                        },


                        plugins: {

                            legend: {

                                display:
                                    false
                            },


                            tooltip: {

                                callbacks: {

                                    title:
                                        function (
                                            context
                                        ) {

                                            return (
                                                'Tahun ' +
                                                context[0].label
                                            );

                                        },


                                    label:
                                        function (
                                            context
                                        ) {

                                            return (
                                                context.dataset.label +
                                                ': ' +
                                                Number(
                                                    context.parsed.y
                                                ).toFixed(0) +
                                                ' / 5'
                                            );

                                        }

                                }

                            }

                        }

                    }

                }
            );
    }


    const modal =
        getModal(
            'modalSkillDetail'
        );


    if (modal) {

        modal.show();
    }

}



/* =========================================================
   TOGGLE SKILL MAPPING
========================================================= */

function toggleSkillMapping()
{
    const rows = document.querySelectorAll(
        '.skill-extra-row'
    );

    const button = document.getElementById(
        'skillMappingMoreBtn'
    );

    const text = document.getElementById(
        'skillMappingMoreText'
    );

    if (!rows.length || !button || !text) {
        return;
    }

    const expanded =
        button.classList.contains('expanded');

    rows.forEach(function (row) {
        row.classList.toggle(
            'd-none',
            expanded
        );
    });

    button.classList.toggle(
        'expanded',
        !expanded
    );

    text.textContent =
        expanded
            ? 'Lihat semua skill'
            : 'Sembunyikan skill';
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


    const modal =
        getModal(
            'modalEditTraining'
        );


    if (modal) {

        modal.show();
    }

}


/* =========================================================
   FIX CHART SAAT MODAL DIBUKA
========================================================= */

const detailModal =
    document.getElementById(
        'modalSkillDetail'
    );


if (detailModal) {

    detailModal.addEventListener(
        'shown.bs.modal',
        function () {

            if (skillDetailChart) {

                skillDetailChart.resize();
            }

        }
    );
}

</script>


<?php

require __DIR__ . '/../partials/footer.php';

?>

<script>
/* =========================================================
   KEMBALI KE SKILL TERAKHIR SETELAH POST / REDIRECT
========================================================= */
(function () {
    if (!window.history || !('scrollRestoration' in window.history)) return;

    window.history.scrollRestoration = 'manual';

    function restoreSkillPosition() {
        var hash = window.location.hash || '';

        if (!hash || hash.indexOf('#skill-row-') !== 0) {
            return;
        }

        var target = document.getElementById(hash.substring(1));

        if (!target) return;

        /*
         * Dua frame diperlukan karena tinggi halaman dapat berubah
         * setelah Bootstrap/modal/chart selesai dirender.
         */
        requestAnimationFrame(function () {
            requestAnimationFrame(function () {
                target.scrollIntoView({
                    behavior: 'auto',
                    block: 'center'
                });

                /* Pastikan browser tidak mengembalikan ke posisi scroll lama. */
                setTimeout(function () {
                    target.scrollIntoView({
                        behavior: 'auto',
                        block: 'center'
                    });
                }, 100);
            });
        });
    }

    window.addEventListener('load', restoreSkillPosition);
    document.addEventListener('DOMContentLoaded', restoreSkillPosition);
})();
</script>
