<?php
require_once __DIR__ . '/../config/database.php';
if (!isset($conn) || !($conn instanceof mysqli)) {
    die('Koneksi database tidak tersedia.');
}

$page_title = 'Jadwal Training';
function e($value)
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}


function redirectPage($url)
{
    header('Location: ' . $url);
    exit;
}

$success = '';
$error   = '';

$valid_status = [
    'Terjadwal',
    'Berlangsung',
    'Selesai',
    'Terlambat',
    'Dibatalkan'
];

function syncAutoTrainingParticipants($conn, $trainingId, $skillId)
{
    $trainingId = (int)$trainingId;
    $skillId = (int)$skillId;

    if ($trainingId <= 0 || $skillId <= 0) {
        return;
    }

    $latestYear = 0;

    $stmtYear = $conn->prepare("
        SELECT COALESCE(MAX(tahun), 0) AS tahun
        FROM penilaian_skill
        WHERE id_skill = ?
          AND nilai IS NOT NULL
    ");

    if ($stmtYear) {
        $stmtYear->bind_param('i', $skillId);

        if ($stmtYear->execute()) {
            $yearRow = $stmtYear->get_result()->fetch_assoc();
            $latestYear = (int)($yearRow['tahun'] ?? 0);
        }

        $stmtYear->close();
    }

    $stmt = $conn->prepare("
        INSERT INTO training_peserta
            (id_training, id_pekerja, sumber)
        SELECT
            ?,
            p.id,
            'otomatis'
        FROM pekerja p
        LEFT JOIN penilaian_skill ps
            ON ps.id_pekerja = p.id
           AND ps.id_skill = ?
           AND ps.tahun = ?
           AND ps.nilai IS NOT NULL
        WHERE p.status = 'Aktif'
          AND (
                ps.id IS NULL
                OR ps.nilai < 2.5
          )
          AND NOT EXISTS (
                SELECT 1
                FROM training_peserta tp
                WHERE tp.id_training = ?
                  AND tp.id_pekerja = p.id
          )
    ");

    if ($stmt) {
        $stmt->bind_param(
            'iiii',
            $trainingId,
            $skillId,
            $latestYear,
            $trainingId
        );

        $stmt->execute();
        $stmt->close();
    }
}


function saveTrainingResults($conn, $trainingId, $results)
{
    $trainingId = (int)$trainingId;

    if ($trainingId <= 0 || !is_array($results)) {
        throw new Exception('Data hasil training tidak valid.');
    }

    // Skill dan tahun penilaian mengikuti training yang sedang diedit.
    $stmtTraining = $conn->prepare("
        SELECT id_skill, YEAR(COALESCE(tanggal_mulai, CURDATE())) AS tahun
        FROM training
        WHERE id = ?
        LIMIT 1
    ");
    if (!$stmtTraining) {
        throw new Exception('Gagal membaca training: ' . $conn->error);
    }

    $stmtTraining->bind_param('i', $trainingId);
    $stmtTraining->execute();
    $training = $stmtTraining->get_result()->fetch_assoc();
    $stmtTraining->close();

    $skillId = (int)($training['id_skill'] ?? 0);
    $tahun   = (int)($training['tahun'] ?? date('Y'));

    if (!$training || $skillId <= 0) {
        throw new Exception('Training belum memiliki Skill / Kompetensi.');
    }

    $conn->begin_transaction();

    try {
        $stmtParticipant = $conn->prepare("
            SELECT id
            FROM training_peserta
            WHERE id_training = ?
              AND id_pekerja = ?
            LIMIT 1
        ");

        $stmtUpdateParticipant = $conn->prepare("
            UPDATE training_peserta
            SET hasil_training = ?
            WHERE id_training = ?
              AND id_pekerja = ?
        ");

        $stmtFindAssessment = $conn->prepare("
            SELECT id
            FROM penilaian_skill
            WHERE id_pekerja = ?
              AND id_skill = ?
              AND tahun = ?
            ORDER BY id DESC
            LIMIT 1
        ");

        $stmtUpdateAssessment = $conn->prepare("
            UPDATE penilaian_skill
            SET nilai = ?
            WHERE id = ?
        ");

        $stmtInsertAssessment = $conn->prepare("
            INSERT INTO penilaian_skill
                (id_pekerja, id_skill, nilai, tahun)
            VALUES (?, ?, ?, ?)
        ");

        if (!$stmtParticipant || !$stmtUpdateParticipant ||
            !$stmtFindAssessment || !$stmtUpdateAssessment ||
            !$stmtInsertAssessment) {
            throw new Exception('Gagal menyiapkan penyimpanan hasil training: ' . $conn->error);
        }

        foreach ($results as $workerId => $rawValue) {
            $workerId = (int)$workerId;

            if ($workerId <= 0) {
                continue;
            }

            // Pastikan pekerja memang peserta training tersebut.
            $stmtParticipant->bind_param('ii', $trainingId, $workerId);
            $stmtParticipant->execute();
            $participantExists = $stmtParticipant->get_result()->fetch_assoc();

            if (!$participantExists) {
                continue;
            }

            $value = trim((string)$rawValue);

            // Kosong = hapus hasil training dan penilaian skill pada tahun training.
            if ($value === '') {
                $stmtUpdateParticipant->bind_param('sii', $value, $trainingId, $workerId);
                $stmtUpdateParticipant->execute();

                $stmtFindAssessment->bind_param('iii', $workerId, $skillId, $tahun);
                $stmtFindAssessment->execute();
                $assessment = $stmtFindAssessment->get_result()->fetch_assoc();

                // Penilaian skill yang sudah ada tidak dihapus saat hasil dikosongkan,
                // supaya riwayat penilaian tetap aman.
                continue;
            }

            $nilai = (float)$value;

            if ($nilai < 1 || $nilai > 5 || floor($nilai) != $nilai) {
                throw new Exception('Hasil training harus berupa angka 1 sampai 5.');
            }

            // 1) Simpan hasil training ke training_peserta.
            $hasilTraining = (string)(int)$nilai;
            $stmtUpdateParticipant->bind_param(
                'sii',
                $hasilTraining,
                $trainingId,
                $workerId
            );
            $stmtUpdateParticipant->execute();

            // 2) Cari penilaian skill pada pekerja + skill + tahun.
            $stmtFindAssessment->bind_param(
                'iii',
                $workerId,
                $skillId,
                $tahun
            );
            $stmtFindAssessment->execute();
            $assessment = $stmtFindAssessment->get_result()->fetch_assoc();

            // 3) Jika ada -> UPDATE. Jika belum ada -> INSERT.
            if ($assessment) {
                $assessmentId = (int)$assessment['id'];
                $stmtUpdateAssessment->bind_param(
                    'di',
                    $nilai,
                    $assessmentId
                );
                $stmtUpdateAssessment->execute();
            } else {
                $stmtInsertAssessment->bind_param(
                    'iidi',
                    $workerId,
                    $skillId,
                    $nilai,
                    $tahun
                );
                $stmtInsertAssessment->execute();
            }
        }

        $stmtParticipant->close();
        $stmtUpdateParticipant->close();
        $stmtFindAssessment->close();
        $stmtUpdateAssessment->close();
        $stmtInsertAssessment->close();

        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        throw $e;
    }
}

function jsonResponse($data)
{
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP);
    exit;
}

if (isset($_GET['participant_data'])) {
    $trainingId = (int)$_GET['participant_data'];
    if ($trainingId <= 0) jsonResponse(['success'=>false,'message'=>'ID training tidak valid.']);

    $stmt = $conn->prepare("SELECT t.id,t.nama_training,t.id_skill,t.tanggal_mulai,s.nama_skill FROM training t LEFT JOIN skill s ON s.id=t.id_skill WHERE t.id=? LIMIT 1");
    if (!$stmt) jsonResponse(['success'=>false,'message'=>'Gagal membaca data training.']);
    $stmt->bind_param('i',$trainingId); $stmt->execute();
    $trainingData=$stmt->get_result()->fetch_assoc(); $stmt->close();
    if (!$trainingData) jsonResponse(['success'=>false,'message'=>'Data training tidak ditemukan.']);

    $skillId=(int)($trainingData['id_skill']??0);
    $latestYear = 0;
    if ($skillId > 0) {
        $stmtYear = $conn->prepare("
            SELECT MAX(tahun) AS tahun
            FROM penilaian_skill
            WHERE id_skill = ?
              AND nilai IS NOT NULL
        ");
        if ($stmtYear) {
            $stmtYear->bind_param('i', $skillId);
            $stmtYear->execute();
            $yearRow = $stmtYear->get_result()->fetch_assoc();
            $latestYear = (int)($yearRow['tahun'] ?? 0);
            $stmtYear->close();
        }
    }

    if ($skillId > 0) {
        syncAutoTrainingParticipants($conn, $trainingId, $skillId);
    }

    $participants=[];

    $stmt=$conn->prepare("SELECT tp.id,tp.id_pekerja,tp.sumber,tp.hasil_training,p.no_reg,p.nama,p.departemen,p.keterangan,
        (SELECT ROUND(AVG(ps.nilai),2) FROM penilaian_skill ps WHERE ps.id_pekerja=p.id AND ps.id_skill=? AND ps.tahun=? AND ps.nilai IS NOT NULL) AS nilai
        FROM training_peserta tp INNER JOIN pekerja p ON p.id=tp.id_pekerja WHERE tp.id_training=?
        ORDER BY CASE WHEN tp.sumber='otomatis' THEN 1 ELSE 2 END, p.nama ASC");
    if ($stmt) {
        $stmt->bind_param('iii',$skillId,$latestYear,$trainingId); $stmt->execute(); $res=$stmt->get_result();
        while($row=$res->fetch_assoc()){ $row['nilai']=$row['nilai']!==null?(float)$row['nilai']:null; $participants[]=$row; }
        $stmt->close();
    }

    $workers=[];
    $stmt=$conn->prepare("SELECT p.id,p.no_reg,p.nama,p.departemen,p.keterangan,
        (SELECT ROUND(AVG(ps.nilai),2) FROM penilaian_skill ps WHERE ps.id_pekerja=p.id AND ps.id_skill=? AND ps.tahun=? AND ps.nilai IS NOT NULL) AS nilai
        FROM pekerja p WHERE p.status='Aktif' ORDER BY p.nama ASC");
    if ($stmt) {
        $stmt->bind_param('ii',$skillId,$latestYear); $stmt->execute(); $res=$stmt->get_result();
        while($row=$res->fetch_assoc()){ $row['nilai']=$row['nilai']!==null?(float)$row['nilai']:null; $workers[]=$row; }
        $stmt->close();
    }
    jsonResponse(['success'=>true,'training'=>$trainingData,'training_year'=>(int)($trainingData['tanggal_mulai'] ? date('Y', strtotime($trainingData['tanggal_mulai'])) : date('Y')),'latest_year'=>$latestYear,'participants'=>$participants,'workers'=>$workers]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';

    if ($action === 'add_skill') {

        $nama_skill = trim(
            $_POST['nama_skill'] ?? ''
        );

        $status_skill = $_POST['status_skill'] ?? 'Aktif';


        if ($nama_skill === '') {

            $error = 'Nama skill wajib diisi.';

        } elseif (
            !in_array(
                $status_skill,
                ['Aktif', 'Nonaktif'],
                true
            )
        ) {

            $error = 'Status skill tidak valid.';

        } else {

            $stmtCheck = $conn->prepare("
                SELECT id
                FROM skill
                WHERE LOWER(TRIM(nama_skill)) = LOWER(TRIM(?))
                LIMIT 1
            ");

            if (!$stmtCheck) {

                $error =
                    'Gagal mengecek skill: ' .
                    $conn->error;

            } else {

                $stmtCheck->bind_param(
                    's',
                    $nama_skill
                );

                $stmtCheck->execute();

                $resultCheck =
                    $stmtCheck->get_result();

                $existing =
                    $resultCheck->fetch_assoc();

                $stmtCheck->close();


                if ($existing) {

                    $error =
                        'Skill "' .
                        e($nama_skill) .
                        '" sudah terdaftar.';

                } else {

                    $stmt = $conn->prepare("
                        INSERT INTO skill
                        (
                            nama_skill,
                            status
                        )
                        VALUES
                        (
                            ?,
                            ?
                        )
                    ");

                    if (!$stmt) {

                        $error =
                            'Gagal menyiapkan tambah skill: ' .
                            $conn->error;

                    } else {

                        $stmt->bind_param(
                            'ss',
                            $nama_skill,
                            $status_skill
                        );

                        if ($stmt->execute()) {

                            $newSkillId =
                                $stmt->insert_id;

                            $stmt->close();

                            redirectPage(
                                'index.php?skill_added=' .
                                $newSkillId
                            );

                        } else {

                            $error =
                                'Skill gagal ditambahkan: ' .
                                $stmt->error;

                            $stmt->close();
                        }
                    }
                }
            }
        }
    }

    elseif ($action === 'update_skill') {

        $skill_id =
            (int)($_POST['skill_id'] ?? 0);

        $nama_skill =
            trim(
                $_POST['nama_skill'] ?? ''
            );

        $status_skill =
            $_POST['status_skill'] ?? 'Aktif';


        if ($skill_id <= 0) {

            $error =
                'ID skill tidak valid.';

        } elseif ($nama_skill === '') {

            $error =
                'Nama skill wajib diisi.';

        } elseif (
            !in_array(
                $status_skill,
                ['Aktif', 'Nonaktif'],
                true
            )
        ) {

            $error =
                'Status skill tidak valid.';

        } else {


            $stmtCheck = $conn->prepare("
                SELECT id
                FROM skill
                WHERE LOWER(TRIM(nama_skill)) = LOWER(TRIM(?))
                  AND id <> ?
                LIMIT 1
            ");

            if (!$stmtCheck) {

                $error =
                    'Gagal mengecek skill: ' .
                    $conn->error;

            } else {

                $stmtCheck->bind_param(
                    'si',
                    $nama_skill,
                    $skill_id
                );

                $stmtCheck->execute();

                $existing =
                    $stmtCheck
                        ->get_result()
                        ->fetch_assoc();

                $stmtCheck->close();


                if ($existing) {

                    $error =
                        'Nama skill tersebut sudah digunakan.';

                } else {

                    $stmt = $conn->prepare("
                        UPDATE skill
                        SET
                            nama_skill = ?,
                            status = ?
                        WHERE id = ?
                    ");

                    if (!$stmt) {

                        $error =
                            'Gagal menyiapkan update skill: ' .
                            $conn->error;

                    } else {

                        $stmt->bind_param(
                            'ssi',
                            $nama_skill,
                            $status_skill,
                            $skill_id
                        );

                        if ($stmt->execute()) {

                            $stmt->close();

                            redirectPage(
                                'index.php?skill_updated=1'
                            );

                        } else {

                            $error =
                                'Skill gagal diperbarui: ' .
                                $stmt->error;

                            $stmt->close();
                        }
                    }
                }
            }
        }
    }

    elseif ($action === 'toggle_skill') {

        $skill_id =
            (int)($_POST['skill_id'] ?? 0);


        if ($skill_id <= 0) {

            $error =
                'ID skill tidak valid.';

        } else {

            $stmt = $conn->prepare("
                UPDATE skill
                SET status =
                    CASE
                        WHEN status = 'Aktif'
                        THEN 'Nonaktif'
                        ELSE 'Aktif'
                    END
                WHERE id = ?
            ");

            if (!$stmt) {

                $error =
                    'Gagal menyiapkan perubahan status: ' .
                    $conn->error;

            } else {

                $stmt->bind_param(
                    'i',
                    $skill_id
                );

                if ($stmt->execute()) {

                    $stmt->close();

                    redirectPage(
                        'index.php?skill_status=1'
                    );

                } else {

                    $error =
                        'Status skill gagal diubah: ' .
                        $stmt->error;

                    $stmt->close();
                }
            }
        }
    }


    elseif ($action === 'delete_skill') {

        $skill_id =
            (int)($_POST['skill_id'] ?? 0);


        if ($skill_id <= 0) {

            $error =
                'ID skill tidak valid.';

        } else {

            $stmtCheckTraining = $conn->prepare("
                SELECT COUNT(*) AS jumlah
                FROM training
                WHERE id_skill = ?
            ");

            $trainingUsed = 0;

            if ($stmtCheckTraining) {

                $stmtCheckTraining->bind_param(
                    'i',
                    $skill_id
                );

                $stmtCheckTraining->execute();

                $trainingResult =
                    $stmtCheckTraining
                        ->get_result()
                        ->fetch_assoc();

                $trainingUsed =
                    (int)($trainingResult['jumlah'] ?? 0);

                $stmtCheckTraining->close();
            }

            $assessmentUsed = 0;

            $checkAssessment =
                $conn->query("
                    SELECT COUNT(*) AS jumlah
                    FROM penilaian_skill
                    WHERE id_skill = " .
                    $skill_id
                );

            if ($checkAssessment) {

                $assessmentRow =
                    $checkAssessment->fetch_assoc();

                $assessmentUsed =
                    (int)($assessmentRow['jumlah'] ?? 0);
            }


            if (
                $trainingUsed > 0 ||
                $assessmentUsed > 0
            ) {

                $error =
                    'Skill tidak dapat dihapus karena sudah digunakan pada data training atau penilaian skill. Gunakan Nonaktif jika skill tidak ingin digunakan lagi.';

            } else {

                $stmt = $conn->prepare("
                    DELETE FROM skill
                    WHERE id = ?
                ");

                if (!$stmt) {

                    $error =
                        'Gagal menyiapkan hapus skill: ' .
                        $conn->error;

                } else {

                    $stmt->bind_param(
                        'i',
                        $skill_id
                    );

                    if ($stmt->execute()) {

                        $stmt->close();

                        redirectPage(
                            'index.php?skill_deleted=1'
                        );

                    } else {

                        $error =
                            'Skill gagal dihapus: ' .
                            $stmt->error;

                        $stmt->close();
                    }
                }
            }
        }
    }


    elseif ($action === 'save') {

        $id =
            (int)($_POST['id'] ?? 0);

        $nama_training =
            trim(
                $_POST['nama_training'] ?? ''
            );

        $id_skill =
            (int)($_POST['id_skill'] ?? 0);

        $trainer =
            trim(
                $_POST['trainer'] ?? ''
            );

        $tanggal_mulai =
            !empty($_POST['tanggal_mulai'])
                ? $_POST['tanggal_mulai']
                : null;

        $tanggal_selesai =
            !empty($_POST['tanggal_selesai'])
                ? $_POST['tanggal_selesai']
                : null;

        $lokasi =
            trim(
                $_POST['lokasi'] ?? ''
            );

        $status =
            $_POST['status'] ?? 'Terjadwal';

        $catatan =
            trim(
                $_POST['catatan'] ?? ''
            );


        if ($id_skill > 0) {

            $stmtSkillName =
                $conn->prepare("
                    SELECT nama_skill
                    FROM skill
                    WHERE id = ?
                      AND status = 'Aktif'
                    LIMIT 1
                ");

            if ($stmtSkillName) {

                $stmtSkillName->bind_param(
                    'i',
                    $id_skill
                );

                $stmtSkillName->execute();

                $skillRow =
                    $stmtSkillName
                        ->get_result()
                        ->fetch_assoc();

                $stmtSkillName->close();


                if ($skillRow) {

                    $nama_training =
                        trim(
                            $skillRow['nama_skill']
                        );

                } else {

                    $error =
                        'Skill yang dipilih tidak ditemukan atau sudah nonaktif.';
                }
            }
        }

        if ($error === '') {

            if ($nama_training === '') {

                $error =
                    'Nama training wajib diisi.';

            } elseif ($id_skill <= 0) {

                $error =
                    'Skill / Kompetensi wajib dipilih agar peserta otomatis dapat ditentukan berdasarkan nilai < 2,5.';
            } elseif (
                !in_array(
                    $status,
                    $valid_status,
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
        }


        if ($error === '') {

            if ($id > 0) {

                $oldSkillId = 0;
                $stmtOldTraining = $conn->prepare("SELECT id_skill FROM training WHERE id = ? LIMIT 1");
                if ($stmtOldTraining) {
                    $stmtOldTraining->bind_param('i', $id);
                    $stmtOldTraining->execute();
                    $oldTrainingRow = $stmtOldTraining->get_result()->fetch_assoc();
                    $oldSkillId = (int)($oldTrainingRow['id_skill'] ?? 0);
                    $stmtOldTraining->close();
                }

                $stmt = $conn->prepare("
                    UPDATE training
                    SET
                        nama_training = ?,
                        id_skill = ?,
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
                        'Gagal menyiapkan query update: ' .
                        $conn->error;

                } else {

                    $stmt->bind_param(
                        'sissssssi',
                        $nama_training,
                        $id_skill,
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

                        if ($oldSkillId !== $id_skill) {
                            $stmtCleanAuto = $conn->prepare("DELETE FROM training_peserta WHERE id_training = ? AND sumber = 'otomatis'");
                            if ($stmtCleanAuto) { $stmtCleanAuto->bind_param('i', $id); $stmtCleanAuto->execute(); $stmtCleanAuto->close(); }
                        }
                        syncAutoTrainingParticipants($conn, $id, $id_skill);

                        redirectPage(
                            'index.php?updated=1'
                        );

                    } else {

                        $error =
                            'Jadwal training gagal diperbarui: ' .
                            $stmt->error;

                        $stmt->close();
                    }
                }
            }

            else {

                $stmt = $conn->prepare("
                    INSERT INTO training
                    (
                        nama_training,
                        id_skill,
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
                        ?
                    )
                ");


                if (!$stmt) {

                    $error =
                        'Gagal menyiapkan query tambah training: ' .
                        $conn->error;

                } else {

                    $stmt->bind_param(
                        'sissssss',
                        $nama_training,
                        $id_skill,
                        $trainer,
                        $tanggal_mulai,
                        $tanggal_selesai,
                        $lokasi,
                        $status,
                        $catatan
                    );


                    if ($stmt->execute()) {

                        $newTrainingId = $stmt->insert_id;
                        $stmt->close();
                        syncAutoTrainingParticipants($conn, $newTrainingId, $id_skill);

                        redirectPage(
                            'index.php?saved=1'
                        );

                    } else {

                        $error =
                            'Jadwal training gagal dibuat: ' .
                            $stmt->error;

                        $stmt->close();
                    }
                }
            }
        }
    }

    elseif ($action === 'save_training_results') {
        $trainingId = (int)($_POST['training_id'] ?? 0);
        $results = $_POST['hasil_training'] ?? [];

        try {
            saveTrainingResults($conn, $trainingId, $results);
            redirectPage(
                'index.php?training_results_saved=1&participant_training=' . $trainingId
            );
        } catch (Throwable $e) {
            $error = 'Hasil training gagal disimpan: ' . $e->getMessage();
        }
    }

    elseif ($action === 'add_participant') {
        $trainingId=(int)($_POST['training_id']??0); $workerId=(int)($_POST['worker_id']??0);
        if($trainingId<=0||$workerId<=0){$error='Training atau pekerja tidak valid.';}
        else{
            $stmt=$conn->prepare("INSERT INTO training_peserta (id_training,id_pekerja,sumber) VALUES (?,?,'manual') ON DUPLICATE KEY UPDATE id=id");
            if(!$stmt){$error='Gagal menyiapkan tambah peserta: '.$conn->error;}
            else{ $stmt->bind_param('ii',$trainingId,$workerId); if($stmt->execute()){ $stmt->close(); redirectPage('index.php?participant_saved=1&participant_training='.$trainingId); } $error='Peserta gagal ditambahkan: '.$stmt->error; $stmt->close(); }
        }
    }

    elseif ($action === 'remove_participant') {
        $participantId=(int)($_POST['participant_id']??0); $trainingId=(int)($_POST['training_id']??0);
        if($participantId<=0||$trainingId<=0){$error='Data peserta tidak valid.';}
        else{
            $stmt=$conn->prepare("DELETE FROM training_peserta WHERE id=? AND id_training=?");
            if(!$stmt){$error='Gagal menyiapkan hapus peserta: '.$conn->error;}
            else{ $stmt->bind_param('ii',$participantId,$trainingId); if($stmt->execute()){ $stmt->close(); redirectPage('index.php?participant_removed=1&participant_training='.$trainingId); } $error='Peserta gagal dihapus: '.$stmt->error; $stmt->close(); }
        }
    }

    elseif ($action === 'sync_participants') {
        $trainingId=(int)($_POST['training_id']??0);
        if($trainingId<=0){$error='ID training tidak valid.';}
        else{
            $stmt=$conn->prepare("SELECT id_skill FROM training WHERE id=? LIMIT 1");
            if(!$stmt){$error='Gagal membaca training: '.$conn->error;}
            else{
                $stmt->bind_param('i',$trainingId); $stmt->execute(); $row=$stmt->get_result()->fetch_assoc(); $stmt->close();
                if(!$row){$error='Training tidak ditemukan.';}
                else{ syncAutoTrainingParticipants($conn,$trainingId,(int)$row['id_skill']); redirectPage('index.php?participant_synced=1&participant_training='.$trainingId); }
            }
        }
    }

    elseif ($action === 'delete') {

        $id =
            (int)($_POST['id'] ?? 0);


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
                    'Gagal menyiapkan query hapus: ' .
                    $conn->error;

            } else {

                $stmt->bind_param(
                    'i',
                    $id
                );


                if ($stmt->execute()) {

                    $stmt->close();

                    /* Hapus peserta milik training yang ikut terhapus. */
                    $stmtPeserta = $conn->prepare("DELETE FROM training_peserta WHERE id_training = ?");
                    if ($stmtPeserta) {
                        $stmtPeserta->bind_param('i', $id);
                        $stmtPeserta->execute();
                        $stmtPeserta->close();
                    }

                    redirectPage(
                        'index.php?deleted=1'
                    );

                } else {

                    $error =
                        'Training gagal dihapus: ' .
                        $stmt->error;

                    $stmt->close();
                }
            }
        }
    }
}

if (isset($_GET['saved'])) {

    $success =
        'Jadwal training berhasil dibuat.';
}


if (isset($_GET['updated'])) {

    $success =
        'Jadwal training berhasil diperbarui.';
}


if (isset($_GET['deleted'])) {

    $success =
        'Jadwal training berhasil dihapus.';
}

if (isset($_GET['training_results_saved'])) $success = 'Hasil training berhasil disimpan dan penilaian skill otomatis diperbarui.';
if (isset($_GET['participant_saved'])) $success = 'Peserta training berhasil ditambahkan.';
if (isset($_GET['participant_removed'])) $success = 'Peserta training berhasil dihapus.';
if (isset($_GET['participant_synced'])) $success = 'Peserta otomatis berhasil disinkronkan berdasarkan nilai < 2,5.';


if (isset($_GET['skill_added'])) {

    $success =
        'Skill baru berhasil ditambahkan.';
}


if (isset($_GET['skill_updated'])) {

    $success =
        'Skill berhasil diperbarui.';
}


if (isset($_GET['skill_status'])) {

    $success =
        'Status skill berhasil diubah.';
}


if (isset($_GET['skill_deleted'])) {

    $success =
        'Skill berhasil dihapus.';
}

$skills = [];
$qSkills = $conn->query("
    SELECT
        id,
        nama_skill,
        status
    FROM skill
    ORDER BY
        CASE
            WHEN status = 'Aktif'
            THEN 1
            ELSE 2
        END,
        nama_skill ASC
");


if ($qSkills) {

    while ($row = $qSkills->fetch_assoc()) {

        $skills[] = $row;
    }
}

$filter_status =
    trim($_GET['status'] ?? '');

$keyword =
    trim($_GET['q'] ?? '');

$where  = [];
$params = [];
$types  = '';

if ($keyword !== '') {

    $where[] = "
        (
            t.nama_training LIKE ?
            OR COALESCE(t.trainer, '') LIKE ?
            OR COALESCE(t.lokasi, '') LIKE ?
            OR COALESCE(t.catatan, '') LIKE ?
            OR COALESCE(s.nama_skill, '') LIKE ?
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


    $types .= 'sssss';
}

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

    $types .= 's';
}

$sql = "
    SELECT
        t.*,
        s.nama_skill,
        s.status AS status_skill
    FROM training t
    LEFT JOIN skill s
        ON s.id = t.id_skill
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
        'Query training error: ' .
        e($conn->error)
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
        'Gagal mengambil data training: ' .
        e($stmt->error)
    );
}


$rows =
    $stmt->get_result();


$training_rows = [];


while ($row = $rows->fetch_assoc()) {

    $training_rows[] = $row;
}


$stmt->close();

$participantCounts = [];
$qParticipantCounts = $conn->query("SELECT id_training, COUNT(*) AS jumlah FROM training_peserta GROUP BY id_training");
if ($qParticipantCounts) { while ($pc = $qParticipantCounts->fetch_assoc()) $participantCounts[(int)$pc['id_training']] = (int)$pc['jumlah']; }

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
        (int)($stat['total'] ?? 0);

    $terjadwal =
        (int)($stat['terjadwal'] ?? 0);

    $berlangsung =
        (int)($stat['berlangsung'] ?? 0);

    $selesai =
        (int)($stat['selesai'] ?? 0);

    $terlambat =
        (int)($stat['terlambat'] ?? 0);

    $dibatalkan =
        (int)($stat['dibatalkan'] ?? 0);
}

$active_skills = [];


foreach ($skills as $skillRow) {

    if (
        ($skillRow['status'] ?? '') === 'Aktif'
    ) {

        $active_skills[] =
            $skillRow;
    }
}

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

require __DIR__ . '/../partials/header.php';
if (isset($_GET['detail'])) {
    $detailId = (int)$_GET['detail'];
    $detail = null;
    $detailParticipants = [];

    if ($detailId > 0) {
        $st = $conn->prepare("SELECT t.*, s.nama_skill FROM training t LEFT JOIN skill s ON s.id=t.id_skill WHERE t.id=? LIMIT 1");
        if ($st) {
            $st->bind_param('i', $detailId);
            $st->execute();
            $detail = $st->get_result()->fetch_assoc();
            $st->close();
        }

        if ($detail) {
            $stp = $conn->prepare("SELECT tp.id, tp.id_pekerja, COALESCE(tp.sumber,'manual') AS sumber, p.no_reg, p.nama, p.departemen, p.keterangan, ps.nilai, ps.tahun FROM training_peserta tp INNER JOIN pekerja p ON p.id=tp.id_pekerja LEFT JOIN penilaian_skill ps ON ps.id_pekerja=p.id AND ps.id_skill=? AND ps.tahun=(SELECT MAX(x.tahun) FROM penilaian_skill x WHERE x.id_skill=?) WHERE tp.id_training=? ORDER BY p.nama ASC");
            if ($stp) {
                $stp->bind_param('iii', $detail['id_skill'], $detail['id_skill'], $detailId);
                $stp->execute();
                $rr = $stp->get_result();
                while ($x=$rr->fetch_assoc()) $detailParticipants[]=$x;
                $stp->close();
            }
        }
    }

    ?>
    <style>
        .detail-card{background:#fff;border:1px solid #e7ebf1;border-radius:17px;box-shadow:0 5px 20px rgba(20,43,76,.045);}
        .detail-label{font-size:10px;text-transform:uppercase;letter-spacing:.05em;font-weight:700;color:#788396;margin-bottom:4px}
        .detail-value{font-size:13px;color:#172033;font-weight:600}

        .participant-section-title{
            display:flex;
            align-items:center;
            justify-content:space-between;
            gap:16px;
            margin:4px 0 14px;
            padding:0;
        }
        .participant-section-heading{
            font-size:15px;
            font-weight:800;
            color:#092f63;
            line-height:1.25;
        }
        .participant-section-heading span{
            color:#788396;
            font-weight:700;
        }
        .participant-section-subtitle{
            font-size:10px;
            color:#788396;
            margin-top:4px;
        }
        .participant-rule-badge{
            display:inline-flex;
            align-items:center;
            white-space:nowrap;
            background:#eaf2ff;
            color:#123f7a;
            border:1px solid #dbe8fb;
            border-radius:999px;
            padding:6px 10px;
            font-size:9px;
            font-weight:700;
        }
        .department-group{
            border:1px solid #e7ebf1;
            border-radius:13px;
            overflow:hidden;
            margin-bottom:14px;
            background:#fff;
            box-shadow:0 3px 12px rgba(20,43,76,.04);
        }
        .department-group:last-child{margin-bottom:0}
        .department-group-header{
            background:#f8fafc;
            border-bottom:1px solid #e7ebf1;
            padding:11px 14px;
            display:flex;
            align-items:center;
            justify-content:space-between;
            gap:12px;
        }
        .department-group-left{
            display:flex;
            align-items:center;
            min-width:0;
            gap:10px;
        }
        .department-icon{
            width:32px;
            height:32px;
            display:inline-flex;
            align-items:center;
            justify-content:center;
            border-radius:9px;
            background:#eaf2ff;
            color:#123f7a;
            font-size:13px;
            flex:0 0 32px;
        }
        .department-group-title{
            font-size:11px;
            font-weight:800;
            color:#172033;
            line-height:1.25;
        }
        .department-group-meta{
            font-size:8px;
            color:#8a94a4;
            margin-top:3px;
        }
        .department-group-count{
            display:inline-flex;
            align-items:center;
            white-space:nowrap;
            font-size:9px;
            font-weight:800;
            color:#123f7a;
            background:#fff;
            border:1px solid #dfe8f5;
            border-radius:999px;
            padding:5px 9px;
        }
        .department-group .table{
            font-size:10px;
            margin-bottom:0;
        }
        .department-group .table thead th{
            background:#fff;
            border-bottom:1px solid #e7ebf1;
            color:#788396;
            font-size:8px;
            font-weight:800;
            text-transform:uppercase;
            letter-spacing:.04em;
            white-space:nowrap;
            padding:9px 12px;
        }
        .department-group .table tbody td{
            padding:10px 12px;
            border-color:#eef1f5;
            color:#172033;
            vertical-align:middle;
        }
        .department-group .table tbody tr:last-child td{
            border-bottom:0;
        }
        .department-group .table tbody tr:hover{
            background:#fafbfd;
        }
        @media (max-width:767.98px){
            .participant-section-title{
                align-items:flex-start;
                flex-direction:column;
            }
            .participant-rule-badge{align-self:flex-start}
            .department-group-header{padding:10px 11px}
            .department-group .table thead th,
            .department-group .table tbody td{padding:8px 9px}
        }
    </style>
    <div class="container-fluid py-4">
      <div class="detail-card p-4">
        <div class="d-flex justify-content-between align-items-start mb-4">
          <div><div class="small text-muted mb-1">DETAIL JADWAL TRAINING</div><h4 class="fw-bold mb-0"><?= $detail ? e($detail['nama_training']) : 'Training tidak ditemukan' ?></h4></div>
          <a href="index.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Kembali</a>
        </div>
        <?php if (!$detail): ?>
          <div class="alert alert-warning mb-0">Data training tidak ditemukan.</div>
        <?php else: ?>
          <div class="row g-3 mb-4">
            <div class="col-md-4"><div class="detail-label">Skill / Kompetensi</div><div class="detail-value"><?= e($detail['nama_skill'] ?: '-') ?></div></div>
              <div class="col-md-4"><div class="detail-label">Trainer</div><div class="detail-value"><?= e($detail['trainer'] ?: '-') ?></div></div>
            <div class="col-md-3"><div class="detail-label">Tanggal Mulai</div><div class="detail-value"><?= e($detail['tanggal_mulai'] ?: '-') ?></div></div>
            <div class="col-md-3"><div class="detail-label">Tanggal Selesai</div><div class="detail-value"><?= e($detail['tanggal_selesai'] ?: '-') ?></div></div>
            <div class="col-md-3"><div class="detail-label">Lokasi</div><div class="detail-value"><?= e($detail['lokasi'] ?: '-') ?></div></div>
            <div class="col-md-3"><div class="detail-label">Status</div><div class="detail-value"><?= e($detail['status'] ?: '-') ?></div></div>
            <div class="col-12"><div class="detail-label">Catatan</div><div class="detail-value fw-normal"><?= nl2br(e($detail['catatan'] ?: '-')) ?></div></div>
          </div>
          <hr>
          <div class="participant-section-title">
            <div>
              <div class="participant-section-heading">Peserta Training <span>(<?= count($detailParticipants) ?>)</span></div>
              <div class="participant-section-subtitle">Daftar peserta berdasarkan departemen</div>
            </div>
            <span class="participant-rule-badge"><i class="bi bi-graph-down-arrow me-1"></i>Otomatis &lt; 2,5</span>
          </div>
          <?php
          $detailByDept = [];
          foreach ($detailParticipants as $dp) {
              $dept = trim((string)($dp['departemen'] ?? ''));
              if ($dept === '') $dept = 'Tanpa Departemen';
              $detailByDept[$dept][] = $dp;
          }
          uksort($detailByDept, 'strnatcasecmp');
          ?>
          <?php if ($detailByDept): ?>
              <?php foreach ($detailByDept as $deptName => $deptParticipants): ?>
                  <div class="department-group">
                      <div class="department-group-header">
                          <div class="department-group-left">
                              <span class="department-icon"><i class="bi bi-building"></i></span>
                              <div>
                                  <div class="department-group-title"><?= e($deptName) ?></div>
                                  <div class="department-group-meta">Peserta training</div>
                              </div>
                          </div>
                          <span class="department-group-count"><i class="bi bi-people me-1"></i><?= count($deptParticipants) ?> peserta</span>
                      </div>
                      <div class="table-responsive">
                          <table class="table table-hover align-middle mb-0">
                              <thead><tr><th>No</th><th>Pekerja</th><th>Keterangan</th><th>Nilai</th><th>Tahun</th><th>Sumber</th></tr></thead>
                              <tbody>
                              <?php foreach ($deptParticipants as $no => $dp): ?>
                                  <tr>
                                      <td><?= $no + 1 ?></td>
                                      <td><b><?= e($dp['nama']) ?></b><div class="small text-muted">No. Reg: <?= e($dp['no_reg']) ?></div></td>
                                      <td><?= e($dp['keterangan'] ?: '-') ?></td>
                                      <td><?= $dp['nilai'] !== null ? number_format((float)$dp['nilai'],2,',','.') : '-' ?></td>
                                      <td><?= e($dp['tahun'] ?: '-') ?></td>
                                      <td><span class="badge <?= ($dp['sumber']==='otomatis')?'bg-success':'bg-primary' ?>"><?= ucfirst(e($dp['sumber'])) ?></span></td>
                                  </tr>
                              <?php endforeach; ?>
                              </tbody>
                          </table>
                      </div>
                  </div>
              <?php endforeach; ?>
          <?php else: ?>
              <div class="text-center py-5 text-muted"><i class="bi bi-people fs-2 d-block mb-2"></i>Belum ada peserta training.</div>
          <?php endif; ?>
        <?php endif; ?>
      </div>
    </div>
    <?php
    exit;
}

?>

<style>

.training-page-card {

    background:#ffffff;

    border:1px solid #e7ebf1;

    border-radius:17px;

    box-shadow:
        0 5px 20px
        rgba(20,43,76,.045);
}

.training-stat {

    padding:20px;

    border-radius:16px;

    background:#ffffff;

    border:1px solid #e7ebf1;

    box-shadow:
        0 5px 20px
        rgba(20,43,76,.035);

    height:100%;
}


.training-stat-icon {

    width:48px;

    height:48px;

    border-radius:13px;

    display:flex;

    align-items:center;

    justify-content:center;

    font-size:20px;
}

.training-table {

    margin-bottom:0;
}


.training-table th {

    color:#7d8796;

    font-size:10px;

    font-weight:700;

    text-transform:uppercase;

    letter-spacing:.05em;

    border-bottom:
        1px solid #e3e8ef;

    white-space:nowrap;

    padding:11px 8px;
}


.training-table td {

    color:#384457;

    font-size:11px;

    vertical-align:middle;

    border-bottom:
        1px solid #edf0f4;

    padding:11px 8px;
}


.training-table tbody tr:last-child td {

    border-bottom:0;
}


.training-table tbody tr:hover {

    background:#fafbfd;
}

.training-name {

    color:#16223a;

    font-size:11px;

    font-weight:700;

    line-height:1.45;
}

.status-training {

    display:inline-flex;

    align-items:center;

    gap:5px;

    padding:5px 8px;

    border-radius:7px;

    font-size:9px;

    font-weight:700;

    white-space:nowrap;
}


.status-terjadwal {

    background:#eaf2ff;

    color:#123f7a;
}


.status-berlangsung {

    background:#fff4d8;

    color:#9a6700;
}


.status-selesai {

    background:#e9f8f0;

    color:#198754;
}


.status-terlambat {

    background:#ffecee;

    color:#dc3545;
}


.status-dibatalkan {

    background:#f0f1f3;

    color:#6c757d;
}


.status-default {

    background:#f0f1f3;

    color:#6c757d;
}

.training-filter {

    background:#f8fafc;

    border:1px solid #e7ebf1;

    border-radius:12px;

    padding:15px;
}

.skill-manager-table th {

    color:#7d8796;

    font-size:10px;

    font-weight:700;

    text-transform:uppercase;

    letter-spacing:.05em;

    white-space:nowrap;
}


.skill-manager-table td {

    font-size:11px;

    vertical-align:middle;
}


.skill-status {

    display:inline-flex;

    align-items:center;

    gap:5px;

    padding:5px 8px;

    border-radius:7px;

    font-size:9px;

    font-weight:700;
}


.skill-status-active {

    background:#e9f8f0;

    color:#198754;
}


.skill-status-inactive {

    background:#f0f1f3;

    color:#6c757d;
}

.skill-select-wrapper {

    position:relative;
}


.skill-select-actions {

    display:flex;

    gap:6px;

    margin-top:7px;
}


.skill-help {

    color:#8a94a4;

    font-size:10px;

    margin-top:5px;
}

.auto-training {

    background:#f8fafc !important;

    cursor:not-allowed;
}


 .participant-source{display:inline-flex;align-items:center;gap:4px;padding:4px 7px;border-radius:7px;font-size:9px;font-weight:700;white-space:nowrap}.participant-source-auto{background:#eaf2ff;color:#123f7a}.participant-source-manual{background:#e9f8f0;color:#198754}.participant-value-low{color:#dc3545;font-weight:800}.participant-value-normal{color:#536174;font-weight:700}
.participant-section-title{display:flex;align-items:center;justify-content:space-between;gap:16px;margin-bottom:14px;padding:2px 0}
.participant-section-heading{font-size:15px;font-weight:800;color:#092f63;line-height:1.25}
.participant-section-heading span{color:#788396;font-weight:700}
.participant-section-subtitle{font-size:10px;color:#788396;margin-top:3px}
.participant-rule-badge{display:inline-flex;align-items:center;white-space:nowrap;background:#eaf2ff;color:#123f7a;border:1px solid #dbe8fb;border-radius:999px;padding:6px 10px;font-size:9px;font-weight:700}
.department-group{border:1px solid #e7ebf1;border-radius:12px;overflow:hidden;margin-bottom:12px;background:#fff;box-shadow:0 2px 8px rgba(23,32,51,.035)}
.department-group:last-child{margin-bottom:0}
.department-group-header{background:#fff;border-bottom:1px solid #eef1f5;padding:11px 14px;display:flex;align-items:center;justify-content:space-between;gap:12px}
.department-group-left{display:flex;align-items:center;min-width:0;gap:9px}
.department-icon{width:30px;height:30px;display:inline-flex;align-items:center;justify-content:center;border-radius:8px;background:#eaf2ff;color:#123f7a;font-size:13px;flex:0 0 30px}
.department-group-title{font-size:11px;font-weight:800;color:#172033;line-height:1.2}
.department-group-meta{font-size:8px;color:#788396;margin-top:3px}
.department-group-count{display:inline-flex;align-items:center;white-space:nowrap;font-size:9px;font-weight:700;color:#123f7a;background:#f5f8fd;border:1px solid #dfe8f5;border-radius:999px;padding:5px 9px}
.department-group .table{font-size:10px}
.department-group .table thead th{background:#fbfcfe;border-bottom:1px solid #e7ebf1;color:#788396;font-size:8px;font-weight:800;text-transform:uppercase;letter-spacing:.04em;padding:9px 12px}
.department-group .table tbody td{padding:9px 12px;border-color:#eef1f5;color:#172033}
.department-group .table tbody tr:last-child td{border-bottom:0}
@media (max-width:767.98px){.participant-section-title{align-items:flex-start;flex-direction:column}.participant-rule-badge{align-self:flex-start}.department-group-header{padding:10px}.department-group .table thead th,.department-group .table tbody td{padding:8px 9px}}

@media (max-width:768px) {

    .training-stat {

        padding:16px;
    }


    .training-table {

        min-width:1050px;
    }

}

</style>

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

<div class="row g-3 mb-4">

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

<div class="training-page-card mb-4 p-4">

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

                Kelola jadwal training berdasarkan kebutuhan kompetensi pekerja.

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

    <div class="training-filter mt-4">

        <form
            method="get"
            class="row g-3 align-items-end"
        >

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
                        $valid_status as $st
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

<div class="training-page-card p-4">

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
                    $training_rows as $r
                ):
$statusClass =
                        trainingStatusClass(
                            $r['status']
                        );


                    $statusIcon =
                        trainingStatusIcon(
                            $r['status']
                        );


                    $editData = [

                        'id' =>
                            (int)$r['id'],

                        'nama_training' =>
                            $r['nama_training'] ?? '',

                        'id_skill' =>
                            (int)(
                                $r['id_skill'] ?? 0
                            ),

                        'trainer' =>
                            $r['trainer'] ?? '',

                        'tanggal_mulai' =>
                            $r['tanggal_mulai'] ?? '',

                        'tanggal_selesai' =>
                            $r['tanggal_selesai'] ?? '',

                        'lokasi' =>
                            $r['lokasi'] ?? '',

                        'status' =>
                            $r['status'] ??
                            'Terjadwal',

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


                            <div class="small mt-1" style="color:#6f7b8d;font-size:9px;"><i class="bi bi-people me-1"></i><?= number_format($participantCounts[(int)$r['id']] ?? 0) ?> peserta</div>

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

                        </td><!-- SKILL -->

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

                        <td>

                            <div
                                class="
                                    d-flex
                                    gap-1
                                "
                            >

                                <a
                                    href="index.php?detail=<?= (int)$r['id'] ?>"
                                    class="btn btn-sm btn-outline-secondary"
                                    title="Lihat Detail"
                                >
                                    <i class="bi bi-eye"></i>
                                </a>

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
                                    onclick="
                                        prepareEditFromButton(this)
                                    "
                                >

                                    <i
                                        class="
                                            bi
                                            bi-pencil
                                        "
                                    ></i>

                                </button>

                                <form
                                    method="post"
                                    class="d-inline"
                                    onsubmit="
                                        return confirmDelete(this);
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
                        colspan="8"
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

                        Tentukan training berdasarkan kompetensi.

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
                                Pilih skill terlebih dahulu
                            "
                        >


                        <div
                            id="trainingNameHelp"
                            class="
                                skill-help
                            "
                        >

                            Nama training akan otomatis
                            mengikuti skill yang dipilih.

                        </div>

                    </div><!-- SKILL -->

                    <div class="col-md-6">

                        <label class="form-label">

                            Skill / Kompetensi

                        </label>


                        <div
                            class="
                                skill-select-wrapper
                            "
                        >

                            <select
                                name="id_skill"
                                id="form_skill"
                                class="form-select"
                            >

                                <option value="0">

                                    Umum / Tanpa Skill

                                </option>


                                <?php foreach (
                                    $active_skills as $s
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


                            <div
                                class="
                                    skill-select-actions
                                "
                            >

                                <button
                                    type="button"
                                    class="
                                        btn
                                        btn-sm
                                        btn-outline-primary
                                    "
                                    data-bs-toggle="modal"
                                    data-bs-target="#modalSkillManager"
                                    onclick="openSkillManager()"
                                >

                                    <i
                                        class="
                                            bi
                                            bi-gear
                                            me-1
                                        "
                                    ></i>

                                    Kelola Skill

                                </button>


                                <button
                                    type="button"
                                    class="
                                        btn
                                        btn-sm
                                        btn-outline-success
                                    "
                                    data-bs-toggle="modal"
                                    data-bs-target="#modalAddSkill"
                                >

                                    <i
                                        class="
                                            bi
                                            bi-plus-lg
                                            me-1
                                        "
                                    ></i>

                                    Skill Baru

                                </button>

                            </div>


                            <div
                                class="
                                    skill-help
                                "
                            >

                                Pilih skill yang sudah terdaftar.
                                Nama training akan otomatis mengikuti skill.

                            </div>

                        </div>

                    </div>

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
                                $valid_status as $st
                            ): ?>

                                <option
                                    value="<?= e($st) ?>"
                                >

                                    <?= e($st) ?>

                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>

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

<div class="modal fade" id="modalPesertaTraining" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header">
                <div><h5 class="modal-title fw-bold" id="participantModalTitle">Peserta Training</h5><div class="small text-muted" id="participantModalSubtitle">Kelola peserta training.</div></div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="participantLoading" class="text-center py-5 text-muted" style="display:none;"><div class="spinner-border spinner-border-sm me-2"></div>Memuat peserta...</div>
                <div id="participantContent" style="display:none;">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                        <div><div class="fw-semibold" style="color:#172033;font-size:13px;">Daftar Peserta</div><div class="small text-muted" id="participantRuleText">Peserta otomatis berasal dari nilai &lt; 2,5.</div></div>
                        <form method="post" class="d-inline"><input type="hidden" name="action" value="sync_participants"><input type="hidden" name="training_id" id="sync_training_id" value="0"><button type="submit" class="btn btn-sm btn-outline-primary"><i class="bi bi-arrow-repeat me-1"></i>Sinkronkan Otomatis</button></form>
                    </div>
                    <form method="post" id="trainingResultsForm" onsubmit="return prepareTrainingResultsSubmit(this);">
                        <input type="hidden" name="action" value="save_training_results">
                        <input type="hidden" name="training_id" id="results_training_id" value="0">
                        <div class="mb-4" id="participantTableBody"></div>
                        <div class="d-flex justify-content-end mb-4">
                            <button type="submit" class="btn btn-success">
                                <i class="bi bi-save me-1"></i>Simpan Hasil Training
                            </button>
                        </div>
                    </form>
                    <div class="training-filter">
                        <div class="fw-semibold mb-1" style="color:#172033;font-size:13px;">Tambah Peserta Manual</div>
                        <div class="small text-muted mb-3">Kamu tetap bisa menambahkan pekerja walaupun nilainya tidak di bawah 2,5.</div>
                        <form method="post" class="row g-2 align-items-end"><input type="hidden" name="action" value="add_participant"><input type="hidden" name="training_id" id="add_training_id" value="0"><div class="col-lg-9"><label class="form-label">Pekerja</label><select name="worker_id" id="participantWorkerSelect" class="form-select" required><option value="">-- Pilih Pekerja --</option></select></div><div class="col-lg-3"><button type="submit" class="btn btn-primary w-100"><i class="bi bi-plus-lg me-1"></i>Tambah Peserta</button></div></form>
                    </div>
                </div>
                <div id="participantError" class="alert alert-danger border-0 shadow-sm" style="display:none;"></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-light border" data-bs-dismiss="modal">Tutup</button></div>
        </div>
    </div>
</div>

<div
    class="
        modal
        fade
    "
    id="modalAddSkill"
    tabindex="-1"
    aria-hidden="true"
>

    <div
        class="
            modal-dialog
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

            <input
                type="hidden"
                name="action"
                value="add_skill"
            >


            <input
                type="hidden"
                name="status_skill"
                value="Aktif"
            >


            <div class="modal-header">

                <div>

                    <h5
                        class="
                            modal-title
                            fw-bold
                        "
                    >

                        Tambah Skill Baru

                    </h5>


                    <div
                        class="
                            small
                            text-muted
                        "
                    >

                        Skill baru akan langsung tersedia
                        untuk Jadwal Training.

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

                    Nama Skill

                    <span class="text-danger">
                        *
                    </span>

                </label>


                <input
                    type="text"
                    name="nama_skill"
                    class="form-control"
                    required
                    maxlength="255"
                    placeholder="
                        Contoh: Basic Electrical
                    "
                >


                <div
                    class="
                        skill-help
                    "
                >

                    Contoh: PLC, Basic Electrical,
                    Hydraulic, Pneumatic, Welding, dll.

                </div>

            </div>


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
                            bi-plus-lg
                            me-1
                        "
                    ></i>

                    Tambah Skill

                </button>

            </div>

        </form>

    </div>

</div>

<div
    class="
        modal
        fade
    "
    id="modalSkillManager"
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

        <div
            class="
                modal-content
                border-0
                shadow-lg
            "
        >

            <div class="modal-header">

                <div>

                    <h5
                        class="
                            modal-title
                            fw-bold
                        "
                    >

                        Kelola Skill / Kompetensi

                    </h5>


                    <div
                        class="
                            small
                            text-muted
                        "
                    >

                        Tambah, edit, aktifkan, nonaktifkan,
                        atau hapus skill.

                    </div>

                </div>


                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="modal"
                ></button>

            </div>

            <div class="modal-body">


                <div
                    class="
                        d-flex
                        justify-content-between
                        align-items-center
                        mb-3
                        gap-2
                        flex-wrap
                    "
                >

                    <div>

                        <div
                            class="
                                fw-semibold
                            "
                            style="
                                color:#172033;
                                font-size:13px;
                            "
                        >

                            Daftar Skill

                        </div>


                        <div
                            class="
                                small
                                text-muted
                            "
                        >

                            Skill aktif akan muncul
                            di form Jadwal Training.

                        </div>

                    </div>


                    <button
                        type="button"
                        class="
                            btn
                            btn-sm
                            btn-primary
                        "
                        data-bs-toggle="modal"
                        data-bs-target="#modalAddSkill"
                        onclick="
                            closeSkillManagerBeforeAdd();
                        "
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


                <div class="table-responsive">

                    <table
                        class="
                            table
                            table-hover
                            skill-manager-table
                        "
                    >

                        <thead>

                            <tr>

                                <th width="45">
                                    No
                                </th>

                                <th>
                                    Nama Skill
                                </th>

                                <th>
                                    Status
                                </th>

                                <th
                                    width="180"
                                    class="text-end"
                                >
                                    Aksi
                                </th>

                            </tr>

                        </thead>


                        <tbody>

                        <?php if (
                            !empty($skills)
                        ): ?>


                            <?php

                            $skillNo = 1;

                            foreach (
                                $skills as $skillItem
                            ):

                                $skillIsActive =
                                    (
                                        $skillItem['status'] ===
                                        'Aktif'
                                    );

                            ?>


                                <tr>

                                    <td>

                                        <?= $skillNo++ ?>

                                    </td>


                                    <td>

                                        <span
                                            class="
                                                fw-semibold
                                            "
                                            style="
                                                color:#172033;
                                            "
                                        >

                                            <?= e(
                                                $skillItem[
                                                    'nama_skill'
                                                ]
                                            ) ?>

                                        </span>

                                    </td>


                                    <td>

                                        <?php if (
                                            $skillIsActive
                                        ): ?>

                                            <span
                                                class="
                                                    skill-status
                                                    skill-status-active
                                                "
                                            >

                                                <i
                                                    class="
                                                        bi
                                                        bi-check-circle-fill
                                                    "
                                                ></i>

                                                Aktif

                                            </span>

                                        <?php else: ?>

                                            <span
                                                class="
                                                    skill-status
                                                    skill-status-inactive
                                                "
                                            >

                                                <i
                                                    class="
                                                        bi
                                                        bi-dash-circle
                                                    "
                                                ></i>

                                                Nonaktif

                                            </span>

                                        <?php endif; ?>

                                    </td>


                                    <td>

                                        <div
                                            class="
                                                d-flex
                                                justify-content-end
                                                gap-1
                                            "
                                        >

                                            <button
                                                type="button"
                                                class="
                                                    btn
                                                    btn-sm
                                                    btn-outline-primary
                                                "
                                                title="Edit Skill"
                                                onclick='
                                                    editSkill(
                                                        <?= json_encode(
                                                            $skillItem,
                                                            JSON_HEX_TAG |
                                                            JSON_HEX_APOS |
                                                            JSON_HEX_QUOT |
                                                            JSON_HEX_AMP
                                                        ) ?>
                                                    )
                                                '
                                            >

                                                <i
                                                    class="
                                                        bi
                                                        bi-pencil
                                                    "
                                                ></i>

                                            </button>


                                            <!-- TOGGLE -->

                                            <form
                                                method="post"
                                                class="d-inline"
                                            >

                                                <input
                                                    type="hidden"
                                                    name="action"
                                                    value="toggle_skill"
                                                >


                                                <input
                                                    type="hidden"
                                                    name="skill_id"
                                                    value="<?= (int)$skillItem['id'] ?>"
                                                >


                                                <button
                                                    type="submit"
                                                    class="
                                                        btn
                                                        btn-sm
                                                        btn-outline-warning
                                                    "
                                                    title="<?= $skillIsActive
                                                        ? 'Nonaktifkan'
                                                        : 'Aktifkan'
                                                    ?>"
                                                >

                                                    <i
                                                        class="
                                                            bi
                                                            <?= $skillIsActive
                                                                ? 'bi-toggle-on'
                                                                : 'bi-toggle-off'
                                                            ?>
                                                        "
                                                    ></i>

                                                </button>

                                            </form>

                                            <form
                                                method="post"
                                                class="d-inline"
                                                onsubmit="
                                                    return confirmSkillDelete(
                                                        this
                                                    );
                                                "
                                            >

                                                <input
                                                    type="hidden"
                                                    name="action"
                                                    value="delete_skill"
                                                >


                                                <input
                                                    type="hidden"
                                                    name="skill_id"
                                                    value="<?= (int)$skillItem['id'] ?>"
                                                >


                                                <input
                                                    type="hidden"
                                                    name="skill_name"
                                                    value="<?= e(
                                                        $skillItem[
                                                            'nama_skill'
                                                        ]
                                                    ) ?>"
                                                >


                                                <button
                                                    type="submit"
                                                    class="
                                                        btn
                                                        btn-sm
                                                        btn-outline-danger
                                                    "
                                                    title="Hapus Skill"
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
                                    colspan="4"
                                    class="
                                        text-center
                                        py-4
                                        text-muted
                                    "
                                >

                                    Belum ada skill.

                                </td>

                            </tr>


                        <?php endif; ?>

                        </tbody>

                    </table>

                </div>

            </div>

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

                    Tutup

                </button>

            </div>


        </div>

    </div>

</div>

<div
    class="
        modal
        fade
    "
    id="modalEditSkill"
    tabindex="-1"
    aria-hidden="true"
>

    <div
        class="
            modal-dialog
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

            <input
                type="hidden"
                name="action"
                value="update_skill"
            >


            <input
                type="hidden"
                name="skill_id"
                id="edit_skill_id"
                value="0"
            >


            <div class="modal-header">

                <div>

                    <h5
                        class="
                            modal-title
                            fw-bold
                        "
                    >

                        Edit Skill

                    </h5>


                    <div
                        class="
                            small
                            text-muted
                        "
                    >

                        Perbarui nama dan status skill.

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

                        Nama Skill

                    </label>


                    <input
                        type="text"
                        name="nama_skill"
                        id="edit_skill_name"
                        class="form-control"
                        required
                        maxlength="255"
                    >

                </div>


                <div>

                    <label class="form-label">

                        Status

                    </label>


                    <select
                        name="status_skill"
                        id="edit_skill_status"
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

                    Simpan Perubahan

                </button>

            </div>

        </form>

    </div>

</div>


<script>

const skillData = <?= json_encode(
    $active_skills,
    JSON_UNESCAPED_UNICODE |
    JSON_HEX_TAG |
    JSON_HEX_APOS |
    JSON_HEX_QUOT |
    JSON_HEX_AMP
) ?>;


function el(id)
{
    return document.getElementById(id);
}

function updateTrainingNameFromSkill(
    force = true
) {

    const skillSelect =
        el('form_skill');

    const namaInput =
        el('form_nama');

    const help =
        el('trainingNameHelp');


    if (!skillSelect || !namaInput) {
        return;
    }


    const selectedId =
        parseInt(
            skillSelect.value || '0',
            10
        );


    if (selectedId > 0) {

        const selectedSkill =
            skillData.find(
                function(skill) {

                    return (
                        parseInt(
                            skill.id,
                            10
                        ) === selectedId
                    );

                }
            );


        if (selectedSkill) {

            namaInput.value =
                selectedSkill.nama_skill;


            namaInput.classList.add(
                'auto-training'
            );

            namaInput.readOnly = true;


            if (help) {

                help.innerHTML =
                    '<i class="bi bi-check-circle-fill me-1"></i>' +
                    'Nama training otomatis mengikuti skill "' +
                    escapeHtml(
                        selectedSkill.nama_skill
                    ) +
                    '".';

                help.style.color =
                    '#198754';
            }

        }

    } else {
        namaInput.readOnly = false;

        namaInput.classList.remove(
            'auto-training'
        );


        if (help) {

            help.innerHTML =
                'Tanpa skill, nama training dapat diisi manual.';

            help.style.color =
                '#8a94a4';
        }

    }

}


function escapeHtml(value)
{

    return String(value)
        .replace(
            /&/g,
            '&amp;'
        )
        .replace(
            /</g,
            '&lt;'
        )
        .replace(
            />/g,
            '&gt;'
        )
        .replace(
            /"/g,
            '&quot;'
        )
        .replace(
            /'/g,
            '&#039;'
        );
}


document.addEventListener(
    'DOMContentLoaded',
    function()
    {

        const skillSelect =
            el('form_skill');


        if (skillSelect) {

            skillSelect.addEventListener(
                'change',
                function()
                {

                    updateTrainingNameFromSkill(
                        true
                    );

                }
            );

        }

        const urlParams =
            new URLSearchParams(
                window.location.search
            );


        const addedSkillId =
            parseInt(
                urlParams.get(
                    'skill_added'
                ) || '0',
                10
            );


        if (addedSkillId > 0) {

            /*
             * Buka modal training otomatis
             */

            const trainingModal =
                document.getElementById(
                    'modalTraining'
                );


            if (
                trainingModal &&
                typeof bootstrap !== 'undefined'
            ) {

                const modal =
                    new bootstrap.Modal(
                        trainingModal
                    );


                modal.show();


                setTimeout(
                    function()
                    {

                        const skillSelect =
                            el('form_skill');


                        if (skillSelect) {

                            skillSelect.value =
                                String(
                                    addedSkillId
                                );


                            updateTrainingNameFromSkill(
                                true
                            );
                        }

                    },
                    300
                );

            }

        }

    }
);

function prepareAdd()
{

    if (el('modalTitle')) {

        el('modalTitle').innerText =
            'Tambah Jadwal Training';

    }

    if (el('form_id')) {

        el('form_id').value =
            '0';

    }

    if (el('form_nama')) {

        el('form_nama').value =
            '';

        el('form_nama').readOnly =
            false;

        el('form_nama').classList.remove(
            'auto-training'
        );

    }
if (el('form_skill')) {

        el('form_skill').value =
            '0';

    }

    if (el('form_trainer')) {

        el('form_trainer').value =
            '';

    }

    if (el('form_mulai')) {

        el('form_mulai').value =
            '';

    }

    if (el('form_selesai')) {

        el('form_selesai').value =
            '';

    }

    if (el('form_status')) {

        el('form_status').value =
            'Terjadwal';

    }

    if (el('form_lokasi')) {

        el('form_lokasi').value =
            '';

    }

    if (el('form_catatan')) {

        el('form_catatan').value =
            '';

    }

    if (el('trainingNameHelp')) {

        el('trainingNameHelp').innerHTML =
            'Nama training akan otomatis mengikuti skill yang dipilih.';

        el('trainingNameHelp').style.color =
            '#8a94a4';
    }

}

function prepareEditFromButton(button)
{

    const raw =
        button.getAttribute(
            'data-training'
        );


    if (!raw) {

        alert(
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

    if (el('modalTitle')) {

        el('modalTitle').innerText =
            'Edit Jadwal Training';

    }

    if (el('form_id')) {

        el('form_id').value =
            data.id || 0;

    }

    if (el('form_nama')) {

        el('form_nama').value =
            data.nama_training || '';

    }
if (el('form_skill')) {

        el('form_skill').value =
            data.id_skill || 0;

    }

    if (el('form_trainer')) {

        el('form_trainer').value =
            data.trainer || '';

    }

    if (el('form_mulai')) {

        el('form_mulai').value =
            data.tanggal_mulai || '';

    }

    if (el('form_selesai')) {

        el('form_selesai').value =
            data.tanggal_selesai || '';

    }

    if (el('form_lokasi')) {

        el('form_lokasi').value =
            data.lokasi || '';

    }

    if (el('form_status')) {

        el('form_status').value =
            data.status ||
            'Terjadwal';

    }

    if (el('form_catatan')) {

        el('form_catatan').value =
            data.catatan || '';

    }
    updateTrainingNameFromSkill(
        true
    );

}

function openSkillManager()
{

}

function closeSkillManagerBeforeAdd()
{

    const manager =
        document.getElementById(
            'modalSkillManager'
        );


    if (
        manager &&
        typeof bootstrap !== 'undefined'
    ) {

        const modal =
            bootstrap.Modal.getInstance(
                manager
            );


        if (modal) {

            modal.hide();

        }

    }

}

function editSkill(skill)
{

    if (!skill) {
        return;
    }


    if (el('edit_skill_id')) {

        el('edit_skill_id').value =
            skill.id || 0;

    }


    if (el('edit_skill_name')) {

        el('edit_skill_name').value =
            skill.nama_skill || '';

    }


    if (el('edit_skill_status')) {

        el('edit_skill_status').value =
            skill.status || 'Aktif';

    }


    /*
     * Tutup manager
     */

    const manager =
        document.getElementById(
            'modalSkillManager'
        );


    if (
        manager &&
        typeof bootstrap !== 'undefined'
    ) {

        const managerInstance =
            bootstrap.Modal.getInstance(
                manager
            );


        if (managerInstance) {

            managerInstance.hide();

        }

    }


    /*
     * Buka modal edit
     */

    setTimeout(
        function()
        {

            const editModal =
                document.getElementById(
                    'modalEditSkill'
                );


            if (
                editModal &&
                typeof bootstrap !== 'undefined'
            ) {

                const modal =
                    new bootstrap.Modal(
                        editModal
                    );


                modal.show();

            }

        },
        250
    );

}

function confirmSkillDelete(form)
{

    const nameInput =
        form.querySelector(
            'input[name="skill_name"]'
        );


    const nama =
        nameInput
            ? nameInput.value
            : 'skill ini';


    return confirm(
        'Apakah kamu yakin ingin menghapus skill "' +
        nama +
        '"?\n\n' +
        'Jika skill sudah digunakan pada training atau penilaian, ' +
        'skill tidak dapat dihapus.'
    );

}

let participantModalInstance=null; let currentParticipantTrainingId=0;
function escapeHtml(value){return String(value??'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;');}
function formatParticipantValue(value){if(value===null||value===undefined||value==='')return '<span class="text-muted">Belum dinilai</span>';const n=parseFloat(value);return '<span class="'+(n<2.5?'participant-value-low':'participant-value-normal')+'">'+n.toFixed(2)+'</span>';}
function openParticipantManager(trainingId,trainingName){currentParticipantTrainingId=parseInt(trainingId||'0',10);const modalElement=document.getElementById('modalPesertaTraining');if(!modalElement||currentParticipantTrainingId<=0)return;participantModalInstance=bootstrap.Modal.getOrCreateInstance(modalElement);document.getElementById('participantModalTitle').textContent='Peserta: '+(trainingName||'Training');document.getElementById('participantLoading').style.display='block';document.getElementById('participantContent').style.display='none';document.getElementById('participantError').style.display='none';participantModalInstance.show();fetch('index.php?participant_data='+encodeURIComponent(currentParticipantTrainingId),{headers:{'X-Requested-With':'XMLHttpRequest'}}).then(r=>r.json()).then(data=>{if(!data.success)throw new Error(data.message||'Gagal memuat peserta.');document.getElementById('sync_training_id').value=currentParticipantTrainingId;document.getElementById('add_training_id').value=currentParticipantTrainingId;document.getElementById('results_training_id').value=currentParticipantTrainingId;const skillName=data.training&&data.training.nama_skill?data.training.nama_skill:'tanpa skill';document.getElementById('participantRuleText').innerHTML='Otomatis: nilai <strong>&lt; 2,5</strong> pada skill <strong>'+escapeHtml(skillName)+'</strong>'+(data.latest_year?' tahun <strong>'+escapeHtml(data.latest_year)+'</strong>.':'.');renderParticipantTable(data.participants||[]);renderWorkerOptions(data.workers||[],data.participants||[]);document.getElementById('participantLoading').style.display='none';document.getElementById('participantContent').style.display='block';}).catch(error=>{document.getElementById('participantLoading').style.display='none';const box=document.getElementById('participantError');box.textContent=error.message||'Gagal memuat peserta.';box.style.display='block';});}
function renderParticipantTable(participants){
    const container=document.getElementById('participantTableBody');
    if(!container)return;
    if(!participants.length){
        container.innerHTML='<div class="department-group"><div class="text-center py-4 text-muted"><i class="bi bi-people fs-4 d-block mb-2"></i>Belum ada peserta training.</div></div>';
        return;
    }

    const grouped={};
    participants.forEach(row=>{
        const dept=String(row.departemen||'').trim()||'Tanpa Departemen';
        if(!grouped[dept])grouped[dept]=[];
        grouped[dept].push(row);
    });

    const departments=Object.keys(grouped).sort((a,b)=>a.localeCompare(b,'id',{numeric:true,sensitivity:'base'}));
    let html='';

    departments.forEach(dept=>{
        const rows=grouped[dept];
        html+='<div class="department-group">';
        html+='<div class="department-group-header">';
        html+='<div class="department-group-left">';
        html+='<span class="department-icon"><i class="bi bi-building"></i></span>';
        html+='<div><div class="department-group-title">'+escapeHtml(dept)+'</div><div class="department-group-meta">Daftar peserta training</div></div>';
        html+='</div>';
        html+='<span class="department-group-count"><i class="bi bi-people me-1"></i>'+rows.length+' peserta</span>';
        html+='</div>';
        html+='<div class="table-responsive"><table class="table table-hover training-table mb-0"><thead><tr><th width="45">No</th><th>Pekerja</th><th>Keterangan</th><th>Nilai Skill Saat Ini</th><th>Hasil Training (1–5)</th><th>Sumber</th><th width="70" class="text-end">Aksi</th></tr></thead><tbody>';

        rows.forEach((row,index)=>{
            const auto=String(row.sumber||'')==='otomatis';
            html+='<tr>';
            html+='<td>'+(index+1)+'</td>';
            html+='<td><div class="fw-semibold" style="color:#172033;">'+escapeHtml(row.nama)+'</div><div class="small text-muted" style="font-size:9px;">No. Reg: '+escapeHtml(row.no_reg||'-')+'</div></td>';
            html+='<td>'+escapeHtml(row.keterangan||'-')+'</td>';
            html+='<td>'+formatParticipantValue(row.nilai)+'</td>';
            html+='<td><select class="form-select form-select-sm training-result-select" data-worker-id="'+parseInt(row.id_pekerja||0,10)+'" style="min-width:120px;"><option value="">-- Pilih --</option><option value="1"'+(String(row.hasil_training)==='1'?' selected':'')+'>1</option><option value="2"'+(String(row.hasil_training)==='2'?' selected':'')+'>2</option><option value="3"'+(String(row.hasil_training)==='3'?' selected':'')+'>3</option><option value="4"'+(String(row.hasil_training)==='4'?' selected':'')+'>4</option><option value="5"'+(String(row.hasil_training)==='5'?' selected':'')+'>5</option></select></td>';
            html+='<td><span class="participant-source '+(auto?'participant-source-auto':'participant-source-manual')+'"><i class="bi '+(auto?'bi-magic':'bi-person-plus')+'"></i>'+(auto?'Otomatis':'Manual')+'</span></td>';
            html+='<td class="text-end"><form method="post" class="d-inline" onsubmit="return confirm(\'Hapus pekerja ini dari peserta training?\');"><input type="hidden" name="action" value="remove_participant"><input type="hidden" name="participant_id" value="'+parseInt(row.id||0,10)+'"><input type="hidden" name="training_id" value="'+currentParticipantTrainingId+'"><button type="submit" class="btn btn-sm btn-outline-danger" title="Hapus peserta"><i class="bi bi-trash"></i></button></form></td>';
            html+='</tr>';
        });

        html+='</tbody></table></div></div>';
    });

    container.innerHTML=html;
}
function renderWorkerOptions(workers,participants){const select=document.getElementById('participantWorkerSelect');if(!select)return;const selected={};participants.forEach(r=>selected[String(r.id_pekerja)]=true);let html='<option value="">-- Pilih Pekerja --</option>';workers.forEach(w=>{if(selected[String(w.id)])return;let label=(w.nama||'Tanpa Nama')+' • No. Reg: '+(w.no_reg||'-');if(w.departemen)label+=' • '+w.departemen;if(w.keterangan)label+=' • '+w.keterangan;label+=w.nilai!==null&&w.nilai!==undefined?' • Nilai: '+parseFloat(w.nilai).toFixed(2):' • Nilai: belum dinilai';html+='<option value="'+parseInt(w.id,10)+'">'+escapeHtml(label)+'</option>';});select.innerHTML=html;}

function prepareTrainingResultsSubmit(form){
    const selects=document.querySelectorAll('#participantTableBody .training-result-select');
    form.querySelectorAll('input[name^="hasil_training["]').forEach(el=>el.remove());
    selects.forEach(select=>{
        const workerId=parseInt(select.getAttribute('data-worker-id')||'0',10);
        if(workerId>0){
            const input=document.createElement('input');
            input.type='hidden';
            input.name='hasil_training['+workerId+']';
            input.value=select.value;
            form.appendChild(input);
        }
    });
    return true;
}

function confirmDelete(form)
{

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