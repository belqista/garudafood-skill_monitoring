<?php

require_once __DIR__ . '/../config/database.php';

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

$page_title = $page_title ?? 'Dashboard';

$current = basename(
    $_SERVER['PHP_SELF'] ?? ''
);

$base = '/skill_monitoring/';

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


    <!-- FONT -->

    <link
        rel="preconnect"
        href="https://fonts.googleapis.com"
    >

    <link
        rel="preconnect"
        href="https://fonts.gstatic.com"
        crossorigin
    >

    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap"
        rel="stylesheet"
    >


    <!-- BOOTSTRAP -->

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >


    <!-- ICON -->

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css"
        rel="stylesheet"
    >


    <!-- APP CSS -->

    <link
        href="<?= $base ?>assets/css/app.css"
        rel="stylesheet"
    >

</head>


<body>


<div class="app-shell">


    <?php include __DIR__ . '/sidebar.php'; ?>


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


        <section class="page-body">