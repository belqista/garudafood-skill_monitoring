<?php

require_once __DIR__ . '/../config/database.php';


/* =========================================================
   HELPER ESCAPE
========================================================= */

if (!function_exists('e')) {

    function e($v)
    {
        return htmlspecialchars(
            (string) $v,
            ENT_QUOTES,
            'UTF-8'
        );
    }

}


/* =========================================================
   PAGE TITLE
   JANGAN TIMPA JUDUL YANG SUDAH DITENTUKAN HALAMAN
========================================================= */

if (!isset($page_title) || trim($page_title) === '') {
    $page_title = 'Dashboard';
}


/* =========================================================
   CURRENT PAGE
========================================================= */

$current = basename(
    $_SERVER['PHP_SELF'] ?? ''
);


/* =========================================================
   BASE URL
========================================================= */

$base = '/skill_monitoring/';


/* =========================================================
   CURRENT URI
========================================================= */

$current_uri = $_SERVER['REQUEST_URI'] ?? '';

?>

<!doctype html>

<html lang="id">

<head>

    <meta charset="utf-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <meta
        name="theme-color"
        content="#082f63"
    >

    <title>
        <?= e($page_title) ?> | Garudafood
    </title>


    <!-- =====================================================
         FONT
    ====================================================== -->

    <link
        rel="preconnect"
        href="https://fonts.googleapis.com"
    >

    <link
        rel="preconnect"
        href="https://fonts.googleapis.com"
        crossorigin
    >

    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap"
        rel="stylesheet"
    >


    <!-- =====================================================
         BOOTSTRAP
    ====================================================== -->

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >


    <!-- =====================================================
         BOOTSTRAP ICON
    ====================================================== -->

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css"
        rel="stylesheet"
    >


    <!-- =====================================================
         APP CSS
    ====================================================== -->

    <link
        href="<?= $base ?>assets/css/app.css"
        rel="stylesheet"
    >

</head>


<body>


<div class="app-shell">


    <?php
    /*
     * SIDEBAR TETAP MENGGUNAKAN SIDEBAR ASLI
     * TIDAK DIUBAH
     */

    include __DIR__ . '/sidebar.php';
    ?>


    <main class="main-content">


        <!-- =================================================
             TOPBAR
        ================================================== -->

        <header class="topbar">


            <button
                type="button"
                class="btn icon-btn d-lg-none"
                id="sidebarToggle"
            >

                <i class="bi bi-list"></i>

            </button>


            <div class="topbar-info">

                <h1>
                    <?= e($page_title) ?>
                </h1>

            </div>


            <div class="topbar-badge">

                <i class="bi bi-shield-check"></i>

                Skill Monitoring

            </div>


        </header>


        <!-- =================================================
             PAGE BODY
        ================================================== -->

        <section class="page-body">