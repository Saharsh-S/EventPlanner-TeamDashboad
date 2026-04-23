<?php


if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

requireExec();   // redirects members to events.php

$pdo    = getDB();
$errors = [];
$event  = null;
$editId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Pre-fill date from calendar click
$prefillDate = '';
if (isset($_GET['prefill_date'])) {
    $pd = $_GET['prefill_date'];
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $pd)) $prefillDate = $pd;
}

// Pre-fill for edit
if ($editId > 0) {
    $stmt = $pdo->prepare('SELECT * FROM Events WHERE event_id = ?');
    $stmt->execute([$editId]);
    $event = $stmt->fetch();
    if (!$event) {
        $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Event not found.'];
        header('Location: events.php');
        exit;
    }
}

// Handle POST (save)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title    = trim($_POST['title'] ?? '');
    $desc     = trim($_POST['description'] ?? '');
    $date     = trim($_POST['date'] ?? '');
    $time     = trim($_POST['time'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $capacity = (int)($_POST['capacity'] ?? 0);
    $category = trim($_POST['category'] ?? '');

    $allowed_categories = ['academic','networking','social','workshop','competition'];

    if ($title === '')                           $errors[] = 'Title is required.';
    if ($date === '')                            $errors[] = 'Date is required.';
    if ($time === '')                            $errors[] = 'Time is required.';
    if ($location === '')                        $errors[] = 'Location is required.';
    if ($capacity < 1)                           $errors[] = 'Capacity must be at least 1.';
    if ($capacity > 10000)                       $errors[] = 'Capacity cannot exceed 10,000.';
    if (!in_array($category, $allowed_categories)) $errors[] = 'Invalid category.';
    
    // Validate date format YYYY-MM-DD
    if ($date && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        $errors[] = 'Date must be in YYYY-MM-DD format.';
    }

    if (empty($errors)) {
        $postId = isset($_POST['event_id']) ? (int)$_POST['event_id'] : 0;

        if ($postId > 0) {
            // UPDATE
            $pdo->prepare(
                'UPDATE Events
                 SET title=?, description=?, date=?, time=?, location=?, capacity=?, category=?
                 WHERE event_id=?'
            )->execute([$title, $desc, $date, $time, $location, $capacity, $category, $postId]);
            $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Event updated successfully.'];
        } else {
            // INSERT
            $pdo->prepare(
                'INSERT INTO Events (title, description, date, time, location, capacity, category, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            )->execute([$title, $desc, $date, $time, $location, $capacity, $category, currentUser()['id']]);
            $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Event created successfully.'];
        }
        header('Location: events.php');
        exit;
    }

    // Re-populate $event on error so the form keeps values
    $event = compact('title','desc','date','time','location','capacity','category');
    $event['event_id'] = $postId;
    $event['description'] = $desc;
}

$isEdit    = ($event && !empty($event['event_id']));
$pageTitle = $isEdit ? 'Edit Event' : 'New Event';
include __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <h1><?= $isEdit ? 'Edit Event' : 'New Event' ?></h1>
</div>

<?php if (!empty($errors)): ?>
    <div class="flash flash-error">
        <?php foreach ($errors as $e): ?><div><?= htmlspecialchars($e) ?></div><?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="form-card">
    <form method="POST" action="event_form.php" novalidate>

        <?php if ($isEdit): ?>
            <input type="hidden" name="event_id" value="<?= (int)$event['event_id'] ?>">
        <?php endif; ?>

        <div class="form-group">
            <label for="title">Event Title <span class="req">*</span></label>
            <input type="text" id="title" name="title"
                   placeholder="e.g. Study Session: Algorithms"
                   value="<?= htmlspecialchars($event['title'] ?? '') ?>" required>
        </div>

        <div class="form-group">
            <label for="description">Description</label>
            <textarea id="description" name="description" rows="4"
                      placeholder="Describe the event..."><?= htmlspecialchars($event['description'] ?? '') ?></textarea>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="date">Date <span class="req">*</span></label>
                <input type="date" id="date" name="date"
                       value="<?= htmlspecialchars($event['date'] ?? $prefillDate) ?>" required>
            </div>
            <div class="form-group">
                <label for="time">Time <span class="req">*</span></label>
                <input type="time" id="time" name="time"
                       value="<?= htmlspecialchars(substr($event['time'] ?? '', 0, 5)) ?>" required>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="location">Location <span class="req">*</span></label>
                <input type="text" id="location" name="location"
                       placeholder="e.g. JHE 264"
                       value="<?= htmlspecialchars($event['location'] ?? '') ?>" required>
            </div>
            <div class="form-group">
                <label for="capacity">Capacity <span class="req">*</span></label>
                <input type="number" id="capacity" name="capacity" min="1" max="10000"
                       placeholder="e.g. 50"
                       value="<?= htmlspecialchars($event['capacity'] ?? '') ?>" required>
            </div>
        </div>

        <div class="form-group">
            <label for="category">Category <span class="req">*</span></label>
            <select id="category" name="category">
                <?php
                $cats = ['academic'=>'Academic','networking'=>'Networking','social'=>'Social','workshop'=>'Workshop','competition'=>'Competition'];
                foreach ($cats as $val => $label):
                    $sel = (($event['category'] ?? '') === $val) ? 'selected' : '';
                ?>
                    <option value="<?= $val ?>" <?= $sel ?>><?= $label ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-actions">
            <a href="events.php" class="btn-cancel">Cancel</a>
            <button type="submit" class="btn-submit">
                <?= $isEdit ? 'Save Changes ->' : 'Create Event ->' ?>
            </button>
        </div>

    </form>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>