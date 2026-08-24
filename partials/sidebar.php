<?php
/* =========================================================
   GARUDAFOOD SKILL MONITORING
   SIDEBAR
========================================================= */

$current_uri  = $_SERVER['REQUEST_URI'] ?? '';
$current_file = basename($_SERVER['PHP_SELF'] ?? '');


/* =========================================================
   MENU ACTIVE
========================================================= */

function menuActive($path)
{
    global $current_uri;

    return strpos($current_uri, $path) !== false
        ? 'active'
        : '';
}

?>

<aside
    class="sidebar"
    id="sidebar"
>


    <!-- =====================================================
         BRAND / LOGO GARUDAFOOD
    ====================================================== -->

    <div
        class="brand"
        style="
            flex-direction: column;
            text-align: center;
            background: transparent;
        "
    >

        <div
            class="brand-logo-wrap"
            style="
                margin-bottom: 10px;
                background: transparent;
            "
        >

            <img
                src="<?= $base ?>assets/img/logo-garudafood.png"
                alt="Garudafood"
                class="brand-logo"
                style="
                    width: 80px;
                    height: auto;
                    background: transparent;
                    filter: brightness(0) invert(1);
                "
            >

        </div>


        <div class="brand-text">

            <strong>
                GARUDAFOOD
            </strong>

            <small>
                Skill Monitoring
            </small>

        </div>

    </div>


    <!-- =====================================================
         MENU
    ====================================================== -->

    <nav class="menu">


        <!-- =================================================
             DASHBOARD
        ================================================== -->

        <a
            class="menu-link
            <?= (
                $current_uri === '/skill_monitoring/'
                ||
                $current_uri === '/skill_monitoring/index.php'
            )
                ? 'active'
                : ''
            ?>"
            href="<?= $base ?>index.php"
        >

            <i class="bi bi-grid-1x2-fill"></i>

            <span>
                Dashboard
            </span>

        </a>


        <!-- =================================================
             MASTER DATA
        ================================================== -->

        <div class="menu-title">
            MASTER DATA
        </div>


        <!-- DATA PEKERJA -->

        <a
            class="menu-link <?= menuActive('/pekerja/') ?>"
            href="<?= $base ?>pekerja/index.php"
        >

            <i class="bi bi-people-fill"></i>

            <span>
                Data Pekerja
            </span>

        </a>


        <!-- KOMPETENSI -->

        <a
            class="menu-link <?= menuActive('/skill/index.php') ?>"
            href="<?= $base ?>skill/index.php"
        >

            <i class="bi bi-award-fill"></i>

            <span>
                Kompetensi / Skill
            </span>

        </a>


        <!-- TARGET -->

        <a
            class="menu-link <?= menuActive('/skill/target.php') ?>"
            href="<?= $base ?>skill/target.php"
        >

            <i class="bi bi-bullseye"></i>

            <span>
                Target Kompetensi
            </span>

        </a>


        <!-- =================================================
     DATA & IMPORT
================================================= -->

<div class="menu-title">
    DATA & IMPORT
</div>

<a
    class="menu-link <?= menuActive('/import/import_excel.php') ?>"
    href="<?= $base ?>import/import_excel.php"
>

    <i class="bi bi-file-earmark-spreadsheet-fill"></i>

    <span>
        Import Data Excel
    </span>

</a>

        <!-- =================================================
             SKILL MONITORING
        ================================================== -->

        <div class="menu-title">
            SKILL MONITORING
        </div>


        <!-- SKILL MATRIX -->

        <a
            class="menu-link <?= menuActive('/penilaian/matrix.php') ?>"
            href="<?= $base ?>penilaian/matrix.php"
        >

            <i class="bi bi-table"></i>

            <span>
                Skill Matrix
            </span>

        </a>


        <!-- PENILAIAN -->

        <a
            class="menu-link <?= menuActive('/penilaian/index.php') ?>"
            href="<?= $base ?>penilaian/index.php"
        >

            <i class="bi bi-pencil-square"></i>

            <span>
                Penilaian Skill
            </span>

        </a>


        <!-- PERKEMBANGAN -->

        <a
            class="menu-link <?= menuActive('/penilaian/perkembangan.php') ?>"
            href="<?= $base ?>penilaian/perkembangan.php"
        >

            <i class="bi bi-graph-up-arrow"></i>

            <span>
                Perkembangan Skill
            </span>

        </a>


        <!-- =================================================
             TRAINING
        ================================================== -->

        <div class="menu-title">
            TRAINING
        </div>


        <!-- KEBUTUHAN TRAINING -->

        <a
            class="menu-link <?= menuActive('/training/kebutuhan.php') ?>"
            href="<?= $base ?>training/kebutuhan.php"
        >

            <i class="bi bi-exclamation-diamond-fill"></i>

            <span>
                Kebutuhan Training
            </span>

        </a>


        <!-- JADWAL TRAINING -->

        <a
            class="menu-link <?= menuActive('/training/index.php') ?>"
            href="<?= $base ?>training/index.php"
        >

            <i class="bi bi-calendar-event-fill"></i>

            <span>
                Jadwal Training
            </span>

        </a>


    </nav>


    <!-- =====================================================
         SIDEBAR BOTTOM
         LOGO POLITEKNIK SEMEN INDONESIA
    ====================================================== -->

    <div class="sidebar-bottom">


        <div class="sidebar-bottom-logo">

            <img
                src="<?= $base ?>assets/img/logoPOLTEKSI.png"
                alt="Politeknik Semen Indonesia"
                class="brand-logo"
                style="
                    width: 80px;
                    height: auto;
                    background: transparent;
                    filter: brightness(0) invert(1);
                "
            >

        </div>


        <div class="sidebar-bottom-info">

            <strong>
                POLITEKNIK SEMEN INDONESIA
            </strong>

            <span>
                D-3 Teknologi Informasi
            </span>

        </div>


    </div>


    <!-- =====================================================
         VERSION
    ====================================================== -->

    <div class="sidebar-footer">

        <span>
            v1.0
        </span>

        <span>
            Internal System
        </span>

    </div>


</aside>