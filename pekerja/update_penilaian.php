<?php

/* =========================================================
   UPDATE NILAI PENILAIAN SKILL
   GARUDAFOOD SKILL MONITORING
========================================================= */

require __DIR__ . '/../koneksi.php';


/* =========================================================
   VALIDASI REQUEST
========================================================= */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    header('Location: index.php');
    exit;

}


/* =========================================================
   PARAMETER
========================================================= */

$id = (int) ($_POST['id'] ?? 0);

$id_pekerja = (int) (
    $_POST['id_pekerja']
    ?? 0
);

$nilai = isset($_POST['nilai'])
    ? (int) $_POST['nilai']
    : 0;


/* =========================================================
   VALIDASI ID
========================================================= */

if (
    $id <= 0 ||
    $id_pekerja <= 0
) {

    header(
        'Location: index.php'
    );

    exit;

}


/* =========================================================
   VALIDASI NILAI
========================================================= */

if (
    $nilai < 1 ||
    $nilai > 5
) {

    header(
        'Location: detail.php?id=' .
        $id_pekerja .
        '&error=' .
        urlencode(
            'Nilai harus berada antara 1 sampai 5.'
        )
    );

    exit;

}


/* =========================================================
   CEK DATA PENILAIAN
========================================================= */

$stmt = $conn->prepare("
    SELECT
        id,
        id_pekerja,
        id_skill,
        tahun,
        nilai

    FROM penilaian_skill

    WHERE id = ?
      AND id_pekerja = ?

    LIMIT 1
");

if (!$stmt) {

    header(
        'Location: detail.php?id=' .
        $id_pekerja .
        '&error=' .
        urlencode(
            'Query validasi gagal.'
        )
    );

    exit;

}

$stmt->bind_param(
    'ii',
    $id,
    $id_pekerja
);

$stmt->execute();

$data = $stmt
    ->get_result()
    ->fetch_assoc();

$stmt->close();


/* =========================================================
   DATA TIDAK DITEMUKAN
========================================================= */

if (!$data) {

    header(
        'Location: detail.php?id=' .
        $id_pekerja .
        '&error=' .
        urlencode(
            'Data assessment tidak ditemukan.'
        )
    );

    exit;

}


/* =========================================================
   CEK APAKAH INI ASSESSMENT TERBARU
=========================================================

   Kita hanya mengizinkan edit record terbaru
   dari skill tersebut.

========================================================= */

$stmt = $conn->prepare("
    SELECT
        id

    FROM penilaian_skill

    WHERE id_pekerja = ?
      AND id_skill = ?

    ORDER BY
        tahun DESC,
        id DESC

    LIMIT 1
");

if (!$stmt) {

    header(
        'Location: detail.php?id=' .
        $id_pekerja .
        '&error=' .
        urlencode(
            'Query assessment terbaru gagal.'
        )
    );

    exit;

}

$stmt->bind_param(
    'ii',
    $id_pekerja,
    $data['id_skill']
);

$stmt->execute();

$latest = $stmt
    ->get_result()
    ->fetch_assoc();

$stmt->close();


/* =========================================================
   BUKAN ASSESSMENT TERBARU
========================================================= */

if (
    !$latest ||
    (int) $latest['id'] !== $id
) {

    header(
        'Location: detail.php?id=' .
        $id_pekerja .
        '&error=' .
        urlencode(
            'Hanya nilai assessment terbaru yang dapat diedit.'
        )
    );

    exit;

}


/* =========================================================
   UPDATE NILAI
========================================================= */

$stmt = $conn->prepare("
    UPDATE penilaian_skill

    SET
        nilai = ?

    WHERE id = ?
      AND id_pekerja = ?

    LIMIT 1
");

if (!$stmt) {

    header(
        'Location: detail.php?id=' .
        $id_pekerja .
        '&error=' .
        urlencode(
            'Query update gagal.'
        )
    );

    exit;

}

$stmt->bind_param(
    'iii',
    $nilai,
    $id,
    $id_pekerja
);


if ($stmt->execute()) {

    $stmt->close();

    header(
        'Location: detail.php?id=' .
        $id_pekerja .
        '&updated=1'
    );

    exit;

}


$stmt->close();


/* =========================================================
   GAGAL UPDATE
========================================================= */

header(
    'Location: detail.php?id=' .
    $id_pekerja .
    '&error=' .
    urlencode(
        'Nilai assessment gagal diperbarui.'
    )
);

exit;