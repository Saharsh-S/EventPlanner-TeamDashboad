<?php
// ============================================================
//  includes/header.php  -  Shared page header / navbar
// ============================================================
if (!isset($pageTitle)) $pageTitle = 'CSS Dashboard';
$user = currentUser();
$isExec = isExec();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> - CSS Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="my_style.css">
    <link rel="stylesheet" href="app_style.css">
</head>
<body>

<header id="app-header">
    <a href="events.php" class="header-brand">
        <img src="imgs/Interconnected unity in blue and black.png" alt="CSS Logo" id="Teamlogo">
        <div class="brand-labels">
            <span class="brand-name">CSS Dashboard</span>
            <span class="brand-sub">McMaster CS Society</span>
        </div>
    </a>

    <nav id="app-nav">
        <a href="events.php"   class="navlink <?= basename($_SERVER['PHP_SELF']) === 'events.php'    ? 'active' : '' ?>">Events</a>
        <a href="calendar.php" class="navlink <?= basename($_SERVER['PHP_SELF']) === 'calendar.php'  ? 'active' : '' ?>">Calendar</a>
        <?php if ($isExec): ?>
        <a href="dashboard.php" class="navlink <?= basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : '' ?>">Dashboard</a>
        <a href="event_form.php" class="navlink nav-add <?= basename($_SERVER['PHP_SELF']) === 'event_form.php' ? 'active' : '' ?>">+ New Event</a>
        <?php endif; ?>
        <div class="nav-user">
            <span class="nav-username"><?= htmlspecialchars($user['name']) ?></span>
            <span class="nav-role <?= $isExec ? 'exec' : 'member' ?>"><?= $isExec ? 'Executive' : 'Member' ?></span>
        </div>
        <a href="logout.php" class="logout-btn">Log Out</a>
    </nav>
</header>

<main class="app-main">