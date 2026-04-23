<?php


require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

requireLogin();   // redirects to login.php if not authenticated

$user   = currentUser();
$isExec = isExec();
$pdo    = getDB();

// Flash message passed from redirect
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

// Sort: date (default) | alpha | time
$validSorts = ['date', 'alpha', 'time'];
$sort       = in_array($_GET['sort'] ?? '', $validSorts, true)
              ? $_GET['sort']
              : 'date';

$orderSQL = match($sort) {
    'alpha' => 'e.title ASC,  e.date ASC, e.time ASC',
    'time'  => 'e.time ASC,   e.date ASC',
    default => 'e.date ASC,   e.time ASC',
};

// Category filter
$validCats = ['academic', 'networking', 'social', 'workshop', 'competition'];
$catFilter  = in_array($_GET['cat'] ?? '', $validCats, true)
              ? $_GET['cat']
              : 'all';

// Build parameterised query
$uid = (int)$user['id'];

if ($catFilter === 'all') {
    $sql    = "SELECT e.*,
                      COUNT(r.rsvp_id) AS rsvp_count,
                      MAX(CASE WHEN r.user_id = ? THEN 1 ELSE 0 END) AS user_rsvpd
               FROM Events e
               LEFT JOIN RSVPs r ON r.event_id = e.event_id
               GROUP BY e.event_id
               ORDER BY {$orderSQL}";
    $params = [$uid];
} else {
    $sql    = "SELECT e.*,
                      COUNT(r.rsvp_id) AS rsvp_count,
                      MAX(CASE WHEN r.user_id = ? THEN 1 ELSE 0 END) AS user_rsvpd
               FROM Events e
               LEFT JOIN RSVPs r ON r.event_id = e.event_id
               WHERE e.category = ?
               GROUP BY e.event_id
               ORDER BY {$orderSQL}";
    $params = [$uid, $catFilter];
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$events = $stmt->fetchAll();

// Current user's total RSVP count (for badge)
$rsvpCountStmt = $pdo->prepare('SELECT COUNT(*) FROM RSVPs WHERE user_id = ?');
$rsvpCountStmt->execute([$uid]);
$myRsvpCount = (int)$rsvpCountStmt->fetchColumn();

$pageTitle = 'Events';
include __DIR__ . '/includes/header.php';
?>

<style>
/* Controls row */
.controls-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 24px;
    flex-wrap: wrap;
}
.controls-left  { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
.controls-right { display: flex; align-items: center; gap: 10px; }

/* Sort buttons */
.sort-bar   { display: flex; align-items: center; gap: 6px; }
.sort-label {
    font-size: 0.63rem; font-weight: 700;
    letter-spacing: 0.07em; text-transform: uppercase; color: #444;
}
.sort-btn {
    background: transparent; border: 1px solid #222; color: #555;
    font-family: 'Montserrat', sans-serif; font-size: 0.68rem;
    font-weight: 700; letter-spacing: 0.05em; text-transform: uppercase;
    padding: 6px 13px; border-radius: 5px; cursor: pointer;
    text-decoration: none; display: inline-block;
    transition: border-color 0.2s, color 0.2s, background 0.2s;
}
.sort-btn:hover  { border-color: #444; color: #ccc; }
.sort-btn.active {
    border-color: #60b8e0; color: #60b8e0;
    background: rgba(96,184,224,0.08);
}

/* Result count */
.result-count {
    font-size: 0.68rem; font-weight: 700;
    color: #444; letter-spacing: 0.05em; text-transform: uppercase;
}

/* My RSVPs badge */
.my-rsvp-badge {
    display: inline-flex; align-items: center; gap: 5px;
    font-size: 0.68rem; font-weight: 700;
    letter-spacing: 0.05em; text-transform: uppercase;
    color: #60b8e0; background: rgba(96,184,224,0.1);
    border: 1px solid rgba(96,184,224,0.25);
    border-radius: 5px; padding: 5px 12px;
}

/* Group dividers - fixed to remove blank spaces */
.group-divider {
    font-size: 0.62rem;
    font-weight: 700;
    letter-spacing: 0.09em;
    text-transform: uppercase;
    color: #383838;
    padding: 14px 0 6px;
    border-top: 1px solid #1a1a1a;
    margin-top: 6px;
    grid-column: 1 / -1;
}
.group-divider:first-of-type {
    border-top: none;
    padding-top: 0;
    margin-top: 0;
}

/* Fix blank description gaps */
.event-desc:empty {
    display: none;
}
.event-desc {
    margin: 0;
}

/* Events grid */
#events-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(290px, 1fr));
    gap: 16px;
}

.event-card {
    background: #141414;
    border: 1px solid #1e1e1e;
    border-radius: 12px;
    padding: 22px;
    display: flex;
    flex-direction: column;
    gap: 10px;
    transition: border-color 0.2s, transform 0.2s;
}
.event-card:hover { border-color: #2a2a2a; transform: translateY(-2px); }

.event-category {
    font-size: 0.66rem;
    font-weight: 700;
    letter-spacing: 0.09em;
    text-transform: uppercase;
    color: #60b8e0;
    background: rgba(96,184,224,0.1);
    border: 1px solid rgba(96,184,224,0.2);
    border-radius: 4px;
    padding: 3px 8px;
    width: fit-content;
}
.event-card h3 {
    font-size: 1rem;
    font-weight: 700;
    color: #fff;
    line-height: 1.3;
    margin: 0;
}
.event-desc {
    font-size: 0.83rem;
    color: #666;
    line-height: 1.65;
    flex: 1;
}
.event-meta {
    display: flex;
    flex-direction: column;
    gap: 4px;
}
.event-meta span {
    font-size: 0.78rem;
    color: #555;
    display: flex;
    align-items: center;
    gap: 7px;
}
.event-meta span::before {
    content: '';
    width: 4px;
    height: 4px;
    border-radius: 50%;
    background: #2a2a2a;
    flex-shrink: 0;
}
.event-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-top: 4px;
    flex-wrap: wrap;
    gap: 8px;
}
.rsvp-btn {
    background: transparent;
    border: 1px solid #333;
    color: #888;
    font-family: 'Montserrat', sans-serif;
    font-size: 0.72rem;
    font-weight: 700;
    letter-spacing: 0.05em;
    text-transform: uppercase;
    padding: 7px 14px;
    border-radius: 6px;
    cursor: pointer;
    transition: border-color 0.2s, color 0.2s, background 0.2s;
}
.rsvp-btn:hover { border-color: #60b8e0; color: #60b8e0; }
.rsvp-btn.rsvpd { border-color: #6dbe6d; color: #6dbe6d; background: rgba(109,190,109,0.08); }
.rsvp-btn:disabled { opacity: 0.5; cursor: not-allowed; }
.rsvp-count {
    font-size: 0.75rem;
    color: #555;
    display: flex;
    align-items: center;
    gap: 5px;
}
.count-num { color: #60b8e0; font-weight: 700; }
.exec-actions {
    display: flex;
    gap: 8px;
    margin-top: 2px;
}
.btn-edit {
    flex: 1;
    background: transparent;
    border: 1px solid #2a2a2a;
    color: #666;
    font-family: 'Montserrat', sans-serif;
    font-size: 0.68rem;
    font-weight: 700;
    letter-spacing: 0.05em;
    text-transform: uppercase;
    padding: 7px 10px;
    border-radius: 5px;
    cursor: pointer;
    text-decoration: none;
    text-align: center;
    transition: border-color 0.2s, color 0.2s;
}
.btn-edit:hover { border-color: #60b8e0; color: #60b8e0; }
.btn-delete {
    flex: 1;
    background: transparent;
    border: 1px solid #2a2a2a;
    color: #666;
    font-family: 'Montserrat', sans-serif;
    font-size: 0.68rem;
    font-weight: 700;
    letter-spacing: 0.05em;
    text-transform: uppercase;
    padding: 7px 10px;
    border-radius: 5px;
    cursor: pointer;
    transition: border-color 0.2s, color 0.2s;
}
.btn-delete:hover { border-color: #e06060; color: #e06060; }
.empty-state {
    text-align: center;
    padding: 70px 20px;
    color: #444;
    font-size: 0.9rem;
}
.empty-state a { color: #60b8e0; text-decoration: none; }
</style>

<!-- Page header -->
<div class="page-header">
    <h1>Upcoming Events</h1>
    <?php if ($isExec): ?>
        <a href="event_form.php" class="btn-add-event">+ New Event</a>
    <?php endif; ?>
</div>

<!-- Controls: filter + sort + badge -->
<div class="controls-row">
    <div class="controls-left">
        <div class="filter-wrap" style="margin-bottom:0;">
            <select id="category-filter" class="filter-select">
                <option value="all"         <?= $catFilter === 'all'         ? 'selected' : '' ?>>All Categories</option>
                <option value="academic"    <?= $catFilter === 'academic'    ? 'selected' : '' ?>>Academic</option>
                <option value="networking"  <?= $catFilter === 'networking'  ? 'selected' : '' ?>>Networking</option>
                <option value="social"      <?= $catFilter === 'social'      ? 'selected' : '' ?>>Social</option>
                <option value="workshop"    <?= $catFilter === 'workshop'    ? 'selected' : '' ?>>Workshop</option>
                <option value="competition" <?= $catFilter === 'competition' ? 'selected' : '' ?>>Competition</option>
            </select>
        </div>

        <div class="sort-bar">
            <span class="sort-label">Sort</span>
            <a href="events.php?sort=date&cat=<?= htmlspecialchars($catFilter) ?>"
               class="sort-btn <?= $sort === 'date'  ? 'active' : '' ?>">Date</a>
            <a href="events.php?sort=alpha&cat=<?= htmlspecialchars($catFilter) ?>"
               class="sort-btn <?= $sort === 'alpha' ? 'active' : '' ?>">A-Z</a>
            <a href="events.php?sort=time&cat=<?= htmlspecialchars($catFilter) ?>"
               class="sort-btn <?= $sort === 'time'  ? 'active' : '' ?>">Time</a>
        </div>

        <span class="result-count">
            <?= count($events) ?> event<?= count($events) !== 1 ? 's' : '' ?>
        </span>
    </div>

    <div class="controls-right">
        <span class="my-rsvp-badge">
            &#10003; <span id="my-rsvp-num"><?= $myRsvpCount ?></span>
            RSVP<?= $myRsvpCount !== 1 ? 's' : '' ?>
        </span>
    </div>
</div>

<!-- Flash message -->
<?php if ($flash): ?>
    <div class="flash flash-<?= htmlspecialchars($flash['type']) ?>">
        <?= htmlspecialchars($flash['msg']) ?>
    </div>
<?php endif; ?>

<!-- Events grid -->
<?php if (empty($events)): ?>
    <div class="empty-state">
        No events<?= $catFilter !== 'all' ? ' in this category' : '' ?> yet.
        <?php if ($isExec): ?>
            <a href="event_form.php">Create the first one &rarr;</a>
        <?php endif; ?>
    </div>
<?php else: ?>

    <div id="events-grid">

        <?php
        $lastGroup = null;
        $firstEvent = true;

        foreach ($events as $ev):
            // Determine group label for current sort mode
            if ($sort === 'date') {
                $group = date('F Y', strtotime($ev['date']));
            } elseif ($sort === 'alpha') {
                $first = strtoupper(substr($ev['title'], 0, 1));
                $group = ctype_alpha($first) ? $first : '#';
            } else {
                $hour  = (int)date('H', strtotime($ev['time']));
                if      ($hour < 12) $group = 'Morning (before noon)';
                elseif  ($hour < 17) $group = 'Afternoon (12 - 5 PM)';
                elseif  ($hour < 20) $group = 'Evening (5 - 8 PM)';
                else                 $group = 'Night (after 8 PM)';
            }

            // Only show group divider if this is a NEW group and NOT the first event
            if ($group !== $lastGroup):
                $lastGroup = $group;
                // Skip printing divider for first group
                if (!$firstEvent):
        ?>
            <div class="group-divider"><?= htmlspecialchars($group) ?></div>
        <?php 
                endif;
                $firstEvent = false;
            endif; 
        ?>

            <!-- Event card -->
            <div class="event-card" data-category="<?= htmlspecialchars($ev['category']) ?>">

                <div class="event-category"><?= ucfirst(htmlspecialchars($ev['category'])) ?></div>

                <h3><?= htmlspecialchars($ev['title']) ?></h3>

                <?php if (!empty(trim((string)$ev['description']))): ?>
                    <p class="event-desc"><?= htmlspecialchars($ev['description']) ?></p>
                <?php endif; ?>

                <div class="event-meta">
                    <span><?= date('D, M j, Y', strtotime($ev['date'])) ?></span>
                    <span><?= date('g:i A', strtotime($ev['time'])) ?></span>
                    <span><?= htmlspecialchars($ev['location']) ?></span>
                    <span>Capacity: <?= (int)$ev['capacity'] ?></span>
                </div>

                <div class="event-footer">
                    <button
                        class="rsvp-btn <?= $ev['user_rsvpd'] ? 'rsvpd' : '' ?>"
                        data-event-id="<?= (int)$ev['event_id'] ?>"
                    >
                        <?= $ev['user_rsvpd'] ? 'Interested &#10003;' : 'RSVP' ?>
                    </button>
                    <div class="rsvp-count">
                        <span class="count-num" id="count-<?= (int)$ev['event_id'] ?>">
                            <?= (int)$ev['rsvp_count'] ?>
                        </span>
                        interested
                    </div>
                </div>

                <?php if ($isExec): ?>
                <div class="exec-actions">
                    <a href="event_form.php?id=<?= (int)$ev['event_id'] ?>" class="btn-edit">Edit</a>
                    <button
                        class="btn-delete"
                        data-id="<?= (int)$ev['event_id'] ?>"
                        data-title="<?= htmlspecialchars($ev['title'], ENT_QUOTES) ?>"
                    >Delete</button>
                </div>
                <?php endif; ?>

            </div>

        <?php endforeach; ?>

    </div>

<?php endif; ?>

<!-- Delete confirmation modal (exec only) -->
<?php if ($isExec): ?>
<div class="modal-overlay" id="delete-modal">
    <div class="modal-box">
        <h3>Delete Event?</h3>
        <p id="delete-modal-msg"></p>
        <div class="modal-actions">
            <button class="btn-modal-cancel" id="modal-cancel-btn">Cancel</button>
            <form method="POST" action="event_delete.php" style="flex:1;">
                <input type="hidden" name="event_id" id="delete-event-id" value="">
                <button type="submit" class="btn-modal-confirm" style="width:100%;">Delete</button>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
/**
 * Inline event script for events.php
 *
 * Responsibilities:
 *   - Category filter: navigate on select change (preserves sort param)
 *   - RSVP toggle: AJAX POST to rsvp.php, update button + counts
 *   - Delete modal: open/close, populate with event title + id
 *
 * Standards: const/let only, addEventListener (no onclick attrs),
 * wrapped in DOMContentLoaded, clear separation from PHP logic.
 */
document.addEventListener('DOMContentLoaded', function () {

    // Category filter -> navigate preserving sort
    const catSelect = document.getElementById('category-filter');
    if (catSelect) {
        catSelect.addEventListener('change', function () {
            const sort = new URLSearchParams(window.location.search).get('sort') || 'date';
            window.location.href = 'events.php?cat=' + encodeURIComponent(this.value) + '&sort=' + sort;
        });
    }

    // RSVP buttons (event delegation on grid)
    const grid = document.getElementById('events-grid');
    if (grid) {
        grid.addEventListener('click', function (e) {
            const btn = e.target.closest('.rsvp-btn');
            if (!btn) return;
            const eventId = parseInt(btn.dataset.eventId, 10);
            if (!eventId) return;
            toggleRSVP(btn, eventId);
        });
    }

    // Delete modal
    const deleteModal = document.getElementById('delete-modal');
    if (deleteModal) {
        document.addEventListener('click', function (e) {
            const btn = e.target.closest('.btn-delete');
            if (!btn) return;
            const id    = btn.dataset.id;
            const title = btn.dataset.title;
            document.getElementById('delete-event-id').value   = id;
            document.getElementById('delete-modal-msg').textContent =
                'Delete "' + title + '"? This will also remove all RSVPs and team stats.';
            deleteModal.classList.add('open');
        });

        const cancelBtn = document.getElementById('modal-cancel-btn');
        if (cancelBtn) {
            cancelBtn.addEventListener('click', function () {
                deleteModal.classList.remove('open');
            });
        }

        deleteModal.addEventListener('click', function (e) {
            if (e.target === deleteModal) deleteModal.classList.remove('open');
        });
    }

});

/**
 * Toggle RSVP for an event via AJAX.
 *
 * @param {HTMLElement} btn     - The RSVP button that was clicked.
 * @param {number}      eventId - Database ID of the event.
 */
function toggleRSVP(btn, eventId) {
    btn.disabled = true;

    fetch('rsvp.php', {
        method:  'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body:    'event_id=' + eventId
    })
    .then(function (response) { return response.json(); })
    .then(function (data) {
        if (data.error) {
            showToast(data.error, 'error');
            btn.disabled = false;
            return;
        }

        btn.innerHTML = data.rsvpd ? 'Interested &#10003;' : 'RSVP';
        btn.className = 'rsvp-btn' + (data.rsvpd ? ' rsvpd' : '');

        const countEl = document.getElementById('count-' + eventId);
        if (countEl) countEl.textContent = data.count;

        const numEl = document.getElementById('my-rsvp-num');
        if (numEl) {
            let current = parseInt(numEl.textContent, 10) || 0;
            current     = data.rsvpd ? current + 1 : Math.max(0, current - 1);
            numEl.textContent = current;
        }

        btn.disabled = false;
    })
    .catch(function () {
        showToast('Something went wrong. Please try again.', 'error');
        btn.disabled = false;
    });
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>