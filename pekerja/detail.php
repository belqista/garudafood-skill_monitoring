<?php

$page_title = 'Detail Pekerja';

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
   AMBIL DATA PEKERJA
=========================================================

   PENTING:
   Tabel pekerja TIDAK memiliki:
   - id_jabatan
   - nik

   Maka tidak menggunakan JOIN jabatan.

========================================================= */

$stmt = $conn->prepare("
    SELECT
        p.*
    FROM pekerja p
    WHERE p.id = ?
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

$pekerja = $stmt
    ->get_result()
    ->fetch_assoc();

$stmt->close();


if (!$pekerja) {
    header('Location: index.php');
    exit;
}


/* =========================================================
   TAHUN ASSESSMENT TERBARU
========================================================= */

$stmt = $conn->prepare("
    SELECT
        MAX(tahun) AS tahun_terbaru
    FROM penilaian_skill
    WHERE id_pekerja = ?
");

if (!$stmt) {
    die(
        'Query tahun assessment gagal: ' .
        htmlspecialchars($conn->error)
    );
}

$stmt->bind_param(
    'i',
    $id
);

$stmt->execute();

$tmp = $stmt
    ->get_result()
    ->fetch_assoc();

$stmt->close();


$tahun_terbaru = (int) (
    $tmp['tahun_terbaru']
    ?? date('Y')
);

if ($tahun_terbaru <= 0) {
    $tahun_terbaru = (int) date('Y');
}


/* =========================================================
   AMBIL SKILL TERBARU
=========================================================

   Target default = 3

========================================================= */

$stmt = $conn->prepare("
    SELECT
        s.id AS id_skill,
        s.nama_skill,

        ps.id AS id_penilaian,
        ps.tahun,
        ps.nilai,
        ps.tanggal_penilaian,
        ps.assessor,
        ps.catatan

    FROM skill s

    LEFT JOIN (

        SELECT
            ps1.*

        FROM penilaian_skill ps1

        INNER JOIN (

            SELECT
                id_skill,

                MAX(
                    CONCAT(
                        LPAD(
                            COALESCE(tahun, 0),
                            4,
                            '0'
                        ),
                        LPAD(
                            id,
                            10,
                            '0'
                        )
                    )
                ) AS latest_key

            FROM penilaian_skill

            WHERE id_pekerja = ?

            GROUP BY id_skill

        ) latest

            ON latest.id_skill = ps1.id_skill

            AND latest.latest_key =
                CONCAT(
                    LPAD(
                        COALESCE(ps1.tahun, 0),
                        4,
                        '0'
                    ),
                    LPAD(
                        ps1.id,
                        10,
                        '0'
                    )
                )

        WHERE ps1.id_pekerja = ?

    ) ps

        ON ps.id_skill = s.id

    WHERE s.status = 'Aktif'

    ORDER BY
        s.nama_skill ASC
");

if (!$stmt) {
    die(
        'Query skill gagal: ' .
        htmlspecialchars($conn->error)
    );
}

$stmt->bind_param(
    'ii',
    $id,
    $id
);

$stmt->execute();

$skill_result = $stmt->get_result();

$skill_data = [];


/* =========================================================
   STATISTIK
========================================================= */

$total_skill = 0;

$total_kompeten = 0;

$total_peningkatan = 0;

$total_training = 0;

$total_actual = 0;

$total_target = 0;

$total_gap = 0;


/* =========================================================
   PROSES DATA SKILL
========================================================= */

while ($row = $skill_result->fetch_assoc()) {

    $actual = $row['nilai'] !== null
        ? (float) $row['nilai']
        : 0;


    /* TARGET DEFAULT */

    $target = 3;


    /* GAP */

    if ($row['nilai'] !== null) {

        $gap = max(
            0,
            $target - $actual
        );

    } else {

        $gap = 0;

    }


    /* STATUS */

    if ($row['nilai'] === null) {

        $status = 'Belum Dinilai';

        $status_class = 'status-neutral';


    } elseif ($gap >= 2) {

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


    /* STATISTIK YANG SUDAH DINILAI */

    if ($row['nilai'] !== null) {

        $total_skill++;

        $total_actual += $actual;

        $total_target += $target;

        $total_gap += $gap;

    }


    /* DATA OLAHAN */

    $row['actual'] = $actual;

    $row['target'] = $target;

    $row['gap'] = $gap;

    $row['status_label'] = $status;

    $row['status_class'] = $status_class;

    $skill_data[] = $row;
}

$stmt->close();


/* =========================================================
   RATA-RATA
========================================================= */

$rata_rata = $total_skill > 0
    ? $total_actual / $total_skill
    : 0;

$rata_target = $total_skill > 0
    ? $total_target / $total_skill
    : 0;


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
   RIWAYAT PENILAIAN
========================================================= */

$stmt = $conn->prepare("
    SELECT
        ps.*,
        s.nama_skill

    FROM penilaian_skill ps

    INNER JOIN skill s
        ON s.id = ps.id_skill

    WHERE ps.id_pekerja = ?

    ORDER BY
        ps.tahun DESC,
        ps.tanggal_penilaian DESC,
        ps.id DESC

    LIMIT 100
");

if (!$stmt) {
    die(
        'Query riwayat penilaian gagal: ' .
        htmlspecialchars($conn->error)
    );
}

$stmt->bind_param(
    'i',
    $id
);

$stmt->execute();

$history_result = $stmt->get_result();

$stmt->close();


/* =========================================================
   RIWAYAT TRAINING
========================================================= */

$training_result = null;

$training_query = "

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
";

$stmt_training = $conn->prepare(
    $training_query
);

if ($stmt_training) {

    $stmt_training->bind_param(
        'i',
        $id
    );

    $stmt_training->execute();

    $training_result =
        $stmt_training->get_result();
}


/* =========================================================
   DATA PER TAHUN
========================================================= */

$year_data = [];

$stmt = $conn->prepare("
    SELECT
        tahun,
        COUNT(*) AS jumlah_penilaian,
        ROUND(AVG(nilai), 2) AS rata_rata

    FROM penilaian_skill

    WHERE id_pekerja = ?

    GROUP BY tahun

    ORDER BY tahun DESC
");

if (!$stmt) {
    die(
        'Query ringkasan tahun gagal: ' .
        htmlspecialchars($conn->error)
    );
}

$stmt->bind_param(
    'i',
    $id
);

$stmt->execute();

$year_result = $stmt->get_result();

while ($y = $year_result->fetch_assoc()) {

    $year_data[] = $y;

}

$stmt->close();


/* =========================================================
   PRIORITAS TRAINING
========================================================= */

$training_priority = array_filter(
    $skill_data,
    function ($item) {

        return $item['nilai'] !== null
            && $item['gap'] > 0;

    }
);

usort(
    $training_priority,
    function ($a, $b) {

        return $b['gap'] <=> $a['gap'];

    }
);

$training_priority = array_slice(
    $training_priority,
    0,
    5
);

?>


<!-- =======================================================
     BREADCRUMB / BACK
======================================================= -->

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">

    <div>

        <a
            href="index.php"
            class="text-decoration-none text-muted small"
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
        Download PDF
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
     PROFILE HEADER
======================================================= -->

<div class="cardx mb-4">

    <div class="row align-items-center g-4">

        <div class="col-lg-7">

            <div class="d-flex align-items-center gap-3">

                <div
                    style="
                        width:76px;
                        height:76px;
                        border-radius:20px;
                        background:#123b72;
                        color:#fff;
                        display:flex;
                        align-items:center;
                        justify-content:center;
                        font-size:30px;
                        font-weight:700;
                        flex-shrink:0;
                    "
                >

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


                    <h2 class="fw-bold mb-1">

                        <?= e(
                            $pekerja['nama'] ?? '-'
                        ) ?>

                    </h2>


                    <div class="text-muted">

                        <?= e(
                            $pekerja['departemen']
                            ?: 'Departemen belum ditentukan'
                        ) ?>

                    </div>

                </div>

            </div>

        </div>


        <div class="col-lg-5">

            <div class="d-flex justify-content-lg-end">

                <div class="text-lg-end">

                    <div class="small text-muted mb-2">

                        STATUS KOMPETENSI

                    </div>


                    <span
                        class="badge-status <?= e($overall_class) ?> fs-6 px-3 py-2"
                    >

                        <?php if ($overall_status === 'Kompeten'): ?>

                            <i class="bi bi-check-circle me-1"></i>

                        <?php elseif ($overall_status === 'Perlu Training'): ?>

                            <i class="bi bi-exclamation-triangle me-1"></i>

                        <?php elseif ($overall_status === 'Perlu Peningkatan'): ?>

                            <i class="bi bi-graph-up-arrow me-1"></i>

                        <?php else: ?>

                            <i class="bi bi-dash-circle me-1"></i>

                        <?php endif; ?>


                        <?= e($overall_status) ?>

                    </span>

                </div>

            </div>

        </div>

    </div>


    <hr class="my-4">


    <div class="row g-3">

        <div class="col-md-4">

            <div class="small text-muted">
                No. Reg / ID
            </div>

            <div class="fw-semibold">

                <?= (int) $pekerja['id'] ?>

            </div>

        </div>


        <div class="col-md-4">

            <div class="small text-muted">
                Departemen
            </div>

            <div class="fw-semibold">

                <?= e(
                    $pekerja['departemen']
                    ?: '-'
                ) ?>

            </div>

        </div>


        <div class="col-md-4">

            <div class="small text-muted">
                Status Pekerja
            </div>

            <div>

                <?php if (
                    ($pekerja['status'] ?? '')
                    === 'Aktif'
                ): ?>

                    <span
                        class="badge-status status-good"
                    >
                        Aktif
                    </span>

                <?php else: ?>

                    <span
                        class="badge-status status-bad"
                    >
                        Nonaktif
                    </span>

                <?php endif; ?>

            </div>

        </div>

    </div>

</div>


<!-- =======================================================
     STATISTIK SKILL
======================================================= -->

<div class="row g-3 mb-4">

    <div class="col-xl-3 col-md-6">

        <div class="cardx h-100">

            <div class="small text-muted mb-2">
                Rata-rata Skill
            </div>

            <div class="d-flex align-items-end gap-2">

                <div class="display-6 fw-bold">

                    <?= number_format(
                        $rata_rata,
                        2
                    ) ?>

                </div>

                <div class="text-muted mb-2">
                    / 5
                </div>

            </div>

            <div class="small text-muted mt-2">

                Target rata-rata:

                <?= number_format(
                    $rata_target,
                    2
                ) ?>

            </div>

        </div>

    </div>


    <div class="col-xl-3 col-md-6">

        <div class="cardx h-100">

            <div class="small text-muted mb-2">
                Skill Kompeten
            </div>

            <div class="display-6 fw-bold text-success">

                <?= number_format(
                    $total_kompeten
                ) ?>

            </div>

            <div class="small text-muted">
                memenuhi target
            </div>

        </div>

    </div>


    <div class="col-xl-3 col-md-6">

        <div class="cardx h-100">

            <div class="small text-muted mb-2">
                Perlu Peningkatan
            </div>

            <div class="display-6 fw-bold text-warning">

                <?= number_format(
                    $total_peningkatan
                ) ?>

            </div>

            <div class="small text-muted">
                gap masih dapat ditingkatkan
            </div>

        </div>

    </div>


    <div class="col-xl-3 col-md-6">

        <div class="cardx h-100">

            <div class="small text-muted mb-2">
                Perlu Training
            </div>

            <div class="display-6 fw-bold text-danger">

                <?= number_format(
                    $total_training
                ) ?>

            </div>

            <div class="small text-muted">
                gap kompetensi tinggi
            </div>

        </div>

    </div>

</div>


<!-- =======================================================
     SKILL MATRIX
======================================================= -->

<div class="cardx mb-4">

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

            <div class="card-title">
                Skill Mapping
            </div>

            <div class="section-note">

                Perbandingan nilai terbaru
                dengan target kompetensi.

            </div>

        </div>


        <div class="small text-muted">

            Assessment terbaru:

            <strong>

                <?= e(
                    $tahun_terbaru
                ) ?>

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
                        Kompetensi
                    </th>

                    <th class="text-center">
                        Actual
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

            <?php if (!empty($skill_data)): ?>

                <?php $no = 1; ?>

                <?php foreach ($skill_data as $s): ?>

                    <tr>

                        <td>
                            <?= $no++ ?>
                        </td>


                        <td>

                            <div class="fw-semibold">

                                <?= e(
                                    $s['nama_skill']
                                ) ?>

                            </div>


                            <?php if (
                                !empty(
                                    $s['tanggal_penilaian']
                                )
                            ): ?>

                                <div class="small text-muted">

                                    Assessment:

                                    <?= e(
                                        date(
                                            'd-m-Y',
                                            strtotime(
                                                $s['tanggal_penilaian']
                                            )
                                        )
                                    ) ?>

                                </div>

                            <?php endif; ?>

                        </td>


                        <td class="text-center">

                            <?php if (
                                $s['nilai'] === null
                            ): ?>

                                <span class="text-muted">
                                    -
                                </span>

                            <?php else: ?>

                                <strong>

                                    <?= number_format(
                                        $s['actual'],
                                        0
                                    ) ?>

                                </strong>

                            <?php endif; ?>

                        </td>


                        <td class="text-center">

                            <strong>

                                <?= number_format(
                                    $s['target'],
                                    0
                                ) ?>

                            </strong>

                        </td>


                        <td class="text-center">

                            <?php if (
                                $s['nilai'] === null
                            ): ?>

                                <span class="text-muted">
                                    -
                                </span>

                            <?php elseif (
                                $s['gap'] > 0
                            ): ?>

                                <span class="fw-bold text-danger">

                                    <?= number_format(
                                        $s['gap'],
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
                                class="
                                    badge-status
                                    <?= e(
                                        $s['status_class']
                                    ) ?>
                                "
                            >

                                <?php if (
                                    $s['status_label']
                                    === 'Kompeten'
                                ): ?>

                                    <i
                                        class="
                                            bi
                                            bi-check-circle
                                            me-1
                                        "
                                    ></i>

                                <?php elseif (
                                    $s['status_label']
                                    === 'Perlu Training'
                                ): ?>

                                    <i
                                        class="
                                            bi
                                            bi-exclamation-triangle
                                            me-1
                                        "
                                    ></i>

                                <?php elseif (
                                    $s['status_label']
                                    === 'Perlu Peningkatan'
                                ): ?>

                                    <i
                                        class="
                                            bi
                                            bi-arrow-up-circle
                                            me-1
                                        "
                                    ></i>

                                <?php else: ?>

                                    <i
                                        class="
                                            bi
                                            bi-dash-circle
                                            me-1
                                        "
                                    ></i>

                                <?php endif; ?>


                                <?= e(
                                    $s['status_label']
                                ) ?>

                            </span>

                        </td>

                    </tr>

                <?php endforeach; ?>


            <?php else: ?>

                <tr>

                    <td
                        colspan="6"
                        class="
                            text-center
                            py-5
                            text-muted
                        "
                    >

                        <i
                            class="
                                bi
                                bi-bar-chart
                                fs-1
                                d-block
                                mb-2
                            "
                        ></i>

                        Belum ada data kompetensi.

                    </td>

                </tr>

            <?php endif; ?>

            </tbody>

        </table>

    </div>

</div>


<!-- =======================================================
     RINGKASAN TAHUN
======================================================= -->

<div class="row g-4 mb-4">


    <!-- RIWAYAT PERKEMBANGAN -->

    <div class="col-lg-5">

        <div class="cardx h-100">

            <div class="card-title mb-1">
                Riwayat Perkembangan
            </div>

            <div class="section-note mb-3">

                Rata-rata hasil assessment
                berdasarkan tahun.

            </div>


            <?php if (!empty($year_data)): ?>

                <?php foreach ($year_data as $y): ?>

                    <div
                        class="
                            d-flex
                            align-items-center
                            justify-content-between
                            border-bottom
                            py-3
                        "
                    >

                        <div>

                            <div class="fw-semibold">

                                Tahun
                                <?= e($y['tahun']) ?>

                            </div>

                            <div class="small text-muted">

                                <?= number_format(
                                    $y['jumlah_penilaian']
                                ) ?>

                                penilaian

                            </div>

                        </div>


                        <div class="text-end">

                            <div class="fw-bold fs-5">

                                <?= number_format(
                                    (float) $y['rata_rata'],
                                    2
                                ) ?>

                            </div>

                            <div class="small text-muted">
                                / 5
                            </div>

                        </div>

                    </div>

                <?php endforeach; ?>

            <?php else: ?>

                <div
                    class="
                        text-center
                        text-muted
                        py-4
                    "
                >

                    Belum ada riwayat penilaian.

                </div>

            <?php endif; ?>

        </div>

    </div>


    <!-- PRIORITAS TRAINING -->

    <div class="col-lg-7">

        <div class="cardx h-100">

            <div class="card-title mb-1">
                Prioritas Training
            </div>

            <div class="section-note mb-3">

                Skill dengan gap paling besar
                perlu diprioritaskan.

            </div>


            <?php if (!empty($training_priority)): ?>

                <?php foreach ($training_priority as $s): ?>

                    <div
                        class="
                            d-flex
                            justify-content-between
                            align-items-center
                            border-bottom
                            py-3
                        "
                    >

                        <div>

                            <div class="fw-semibold">

                                <?= e(
                                    $s['nama_skill']
                                ) ?>

                            </div>

                            <div class="small text-muted">

                                Actual

                                <?= number_format(
                                    $s['actual'],
                                    0
                                ) ?>

                                →

                                Target

                                <?= number_format(
                                    $s['target'],
                                    0
                                ) ?>

                            </div>

                        </div>


                        <div class="text-end">

                            <span
                                class="
                                    badge-status
                                    <?= e(
                                        $s['status_class']
                                    ) ?>
                                "
                            >

                                Gap

                                <?= number_format(
                                    $s['gap'],
                                    0
                                ) ?>

                            </span>

                        </div>

                    </div>

                <?php endforeach; ?>

            <?php else: ?>

                <div class="text-center py-4">

                    <div
                        class="
                            text-success
                            fs-2
                            mb-2
                        "
                    >

                        <i
                            class="
                                bi
                                bi-check-circle
                            "
                        ></i>

                    </div>


                    <div class="fw-semibold">

                        Tidak ada gap kompetensi.

                    </div>


                    <div class="small text-muted">

                        Semua skill sudah memenuhi
                        target.

                    </div>

                </div>

            <?php endif; ?>

        </div>

    </div>

</div>


<!-- =======================================================
     RIWAYAT PENILAIAN
======================================================= -->

<div class="cardx mb-4">

    <div class="card-title mb-1">

        Riwayat Penilaian Skill

    </div>


    <div class="section-note mb-3">

        History assessment pekerja.
        Nilai lama tidak dihapus ketika
        assessment terbaru ditambahkan.

    </div>


    <div class="table-responsive">

        <table
            class="
                table
                table-hover
                align-middle
            "
        >

            <thead>

                <tr>

                    <th>
                        Tahun
                    </th>

                    <th>
                        Tanggal
                    </th>

                    <th>
                        Kompetensi
                    </th>

                    <th class="text-center">
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
                $history_result
                && $history_result->num_rows > 0
            ): ?>

                <?php while (
                    $h =
                        $history_result
                        ->fetch_assoc()
                ): ?>

                    <tr>

                        <td>

                            <span
                                class="
                                    badge
                                    bg-light
                                    text-dark
                                    border
                                "
                            >

                                <?= e(
                                    $h['tahun']
                                ) ?>

                            </span>

                        </td>


                        <td>

                            <?= !empty(
                                $h['tanggal_penilaian']
                            )
                                ? e(
                                    date(
                                        'd-m-Y',
                                        strtotime(
                                            $h['tanggal_penilaian']
                                        )
                                    )
                                )
                                : '-'
                            ?>

                        </td>


                        <td>

                            <strong>

                                <?= e(
                                    $h['nama_skill']
                                ) ?>

                            </strong>

                        </td>


                        <td class="text-center">

                            <span
                                class="fw-bold"
                                style="color:#123b72;"
                            >

                                <?= number_format(
                                    (float) $h['nilai'],
                                    0
                                ) ?>

                            </span>

                            / 5

                        </td>


                        <td>

                            <?= e(
                                $h['assessor']
                                ?: '-'
                            ) ?>

                        </td>


                        <td>

                            <span
                                class="
                                    small
                                    text-muted
                                "
                            >

                                <?= e(
                                    $h['catatan']
                                    ?: '-'
                                ) ?>

                            </span>

                        </td>

                    </tr>

                <?php endwhile; ?>


            <?php else: ?>

                <tr>

                    <td
                        colspan="6"
                        class="
                            text-center
                            py-5
                            text-muted
                        "
                    >

                        Belum ada riwayat penilaian.

                    </td>

                </tr>

            <?php endif; ?>

            </tbody>

        </table>

    </div>

</div>


<!-- =======================================================
     RIWAYAT TRAINING
======================================================= -->

<div class="cardx">

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

            <div class="card-title mb-1">

                Riwayat Training

            </div>

            <div class="section-note">

                Pelatihan yang pernah diikuti pekerja.

            </div>

        </div>


        <a
            href="../training/index.php"
            class="btn btn-primary btn-sm"
        >

            <i
                class="
                    bi
                    bi-mortarboard
                    me-1
                "
            ></i>

            Kelola Training

        </a>

    </div>


    <div class="table-responsive">

        <table
            class="
                table
                table-hover
                align-middle
            "
        >

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
                        Status
                    </th>

                </tr>

            </thead>


            <tbody>

            <?php if (
                $training_result
                &&
                $training_result->num_rows > 0
            ): ?>

                <?php while (
                    $t =
                        $training_result
                        ->fetch_assoc()
                ): ?>

                    <tr>

                        <td>

                            <div class="fw-semibold">

                                <?= e(
                                    $t['nama_training']
                                ) ?>

                            </div>

                        </td>


                        <td>

                            <?php

                            $tgl_mulai =
                                !empty(
                                    $t['tanggal_mulai']
                                )
                                    ? date(
                                        'd-m-Y',
                                        strtotime(
                                            $t['tanggal_mulai']
                                        )
                                    )
                                    : '-';


                            $tgl_selesai =
                                !empty(
                                    $t['tanggal_selesai']
                                )
                                    ? date(
                                        'd-m-Y',
                                        strtotime(
                                            $t['tanggal_selesai']
                                        )
                                    )
                                    : '-';

                            ?>


                            <?= e(
                                $tgl_mulai
                            ) ?>


                            <?php if (
                                $tgl_selesai !== '-'
                            ): ?>

                                s/d

                                <?= e(
                                    $tgl_selesai
                                ) ?>

                            <?php endif; ?>

                        </td>


                        <td>

                            <?= e(
                                $t['trainer']
                                ?: '-'
                            ) ?>

                        </td>


                        <td>

                            <?= e(
                                $t['lokasi']
                                ?: '-'
                            ) ?>

                        </td>


                        <td>

                            <?php

                            $status_training =
                                $t[
                                    'status_training'
                                ]
                                ?? '';


                            if (
                                $status_training
                                === 'Selesai'
                            ) {

                                $class =
                                    'status-good';

                            } elseif (
                                $status_training
                                === 'Dibatalkan'
                            ) {

                                $class =
                                    'status-bad';

                            } elseif (
                                $status_training
                                === 'Sedang Berlangsung'
                            ) {

                                $class =
                                    'status-warning';

                            } else {

                                $class =
                                    'status-neutral';

                            }

                            ?>


                            <span
                                class="
                                    badge-status
                                    <?= e($class) ?>
                                "
                            >

                                <?= e(
                                    $status_training
                                    ?: 'Terjadwal'
                                ) ?>

                            </span>

                        </td>

                    </tr>

                <?php endwhile; ?>


            <?php else: ?>

                <tr>

                    <td
                        colspan="5"
                        class="
                            text-center
                            py-5
                            text-muted
                        "
                    >

                        <i
                            class="
                                bi
                                bi-mortarboard
                                fs-1
                                d-block
                                mb-2
                            "
                        ></i>

                        Belum ada riwayat training.

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