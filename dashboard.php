<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

requireExec();

$pdo  = getDB();
$user = currentUser();

// ── Flash message ─────────────────────────────────────────
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

// ── STAT COUNTERS ─────────────────────────────────────────
$totalEvents = (int)$pdo->query('SELECT COUNT(*) FROM Events')->fetchColumn();

$avgRSVP = (float)$pdo->query(
    'SELECT COALESCE(AVG(cnt),0)
     FROM (SELECT COUNT(*) AS cnt FROM RSVPs GROUP BY event_id) t'
)->fetchColumn();

$totalMembers = (int)$pdo->query(
    'SELECT COALESCE(SUM(members_involved),0) FROM Team_Stats'
)->fetchColumn();

$thisMonth = (int)$pdo->query(
    'SELECT COUNT(*) FROM Events
     WHERE MONTH(date)=MONTH(CURDATE()) AND YEAR(date)=YEAR(CURDATE())'
)->fetchColumn();

// ── MONTHLY EVENT CHART ───────────────────────────────────
$monthRows = $pdo->query(
    'SELECT MONTH(date) AS month_num, COUNT(*) AS event_count
     FROM Events
     GROUP BY MONTH(date)
     ORDER BY MONTH(date)'
)->fetchAll();

$monthCounts = array_fill(1, 12, 0);
foreach ($monthRows as $row) {
    $monthCounts[(int)$row['month_num']] = (int)$row['event_count'];
}

// ── ALL EVENTS (for log + edit form selects) ──────────────
$allEvents = $pdo->query(
    'SELECT event_id, title FROM Events ORDER BY date DESC'
)->fetchAll();

// ── TEAM STATS ────────────────────────────────────────────
$statsRows = $pdo->query(
    'SELECT ts.*, e.title AS event_title
     FROM Team_Stats ts
     JOIN Events e ON e.event_id = ts.event_id
     ORDER BY ts.logged_at DESC'
)->fetchAll();

$statsByTeam = [];
foreach ($statsRows as $row) {
    $statsByTeam[$row['main_team']][] = $row;
}

// ── TEAM STRUCTURE ────────────────────────────────────────
$teamStructure = [
    'communications' => [
        'label'    => 'Communications',
        'subteams' => ['design' => 'Design', 'socialmedia' => 'Social Media'],
    ],
    'studentsupport' => [
        'label'    => 'Student Support',
        'subteams' => ['academic' => 'Academic', 'mentorship' => 'Mentorship'],
    ],
    'events'   => ['label' => 'Events Team', 'subteams' => []],
    'outreach' => ['label' => 'Outreach',    'subteams' => []],
    'webtech'  => ['label' => 'Web & Tech',  'subteams' => []],
];

$pageTitle = 'Dashboard';
include __DIR__ . '/includes/header.php';

/**
 * Sum members_involved across all rows for a team.
 * @param array $rows  Team_Stats rows for one main_team.
 * @return int
 */
function teamTotal(array $rows): int {
    return array_sum(array_column($rows, 'members_involved'));
}

/**
 * Sum members_involved for a specific sub-team.
 * @param array  $rows  Team_Stats rows for one main_team.
 * @param string $sub   Sub-team key (e.g. 'design').
 * @return int
 */
function subTeamTotal(array $rows, string $sub): int {
    $filtered = array_filter($rows, fn($r) => $r['sub_team'] === $sub);
    return array_sum(array_column($filtered, 'members_involved'));
}

/**
 * Count contribution rows for a specific sub-team.
 * @param array  $rows  Team_Stats rows for one main_team.
 * @param string $sub   Sub-team key.
 * @return int
 */
function subTeamContribCount(array $rows, string $sub): int {
    return count(array_filter($rows, fn($r) => $r['sub_team'] === $sub));
}
?>

<!-- ── Flash message ──────────────────────────────────────── -->
<?php if ($flash): ?>
    <div class="flash flash-<?= htmlspecialchars($flash['type']) ?>">
        <?= htmlspecialchars($flash['msg']) ?>
    </div>
<?php endif; ?>

<div class="page-header">
    <h1>Dashboard</h1>
</div>

<!-- ── STAT CARDS ─────────────────────────────────────────── -->
<div class="stat-cards">
    <div class="stat-card">
        <div class="stat-label">Total Events Hosted</div>
        <div class="stat-value blue"><?= $totalEvents ?></div>
        <div class="stat-sub">All time</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Avg. RSVPs / Event</div>
        <div class="stat-value green"><?= number_format($avgRSVP, 1) ?></div>
        <div class="stat-sub">Interest count</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Total Members Involved</div>
        <div class="stat-value"><?= $totalMembers ?></div>
        <div class="stat-sub">Across all teams</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Events This Month</div>
        <div class="stat-value"><?= $thisMonth ?></div>
        <div class="stat-sub"><?= date('F Y') ?></div>
    </div>
</div>

<!-- ── MONTHLY BAR CHART ──────────────────────────────────── -->
<div class="chart-section">
    <h2>Event Frequency by Month</h2>
    <div class="bar-chart" id="bar-chart">
        <?php
        $months   = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
        $maxCount = max(array_merge(array_values($monthCounts), [1]));
        foreach ($monthCounts as $m => $count):
            $pct      = $maxCount > 0 ? round(($count / $maxCount) * 100) : 0;
            $heightPx = max($pct, $count > 0 ? 8 : 0);
        ?>
        <div class="bar-col">
            <div class="bar" style="height:<?= $heightPx ?>px"
                 title="<?= $count ?> event<?= $count !== 1 ? 's' : '' ?>"></div>
            <div class="bar-label"><?= $months[$m - 1] ?></div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- ── EXEC PRODUCTIVITY ───────────────────────────────────── -->
<div class="section-header">
    <h2>Exec Productivity</h2>
</div>

<div class="productivity-grid">
<?php foreach ($teamStructure as $teamKey => $teamDef):
    $teamRows  = $statsByTeam[$teamKey] ?? [];
    $teamTotal = teamTotal($teamRows);
    $evCount   = count(array_unique(array_column($teamRows, 'event_id')));
    $contribN  = count($teamRows);

    $allTotals = array_map(
        fn($k) => teamTotal($statsByTeam[$k] ?? []),
        array_keys($teamStructure)
    );
    $maxTotal = max(array_merge($allTotals, [1]));
    $flatPct  = $maxTotal > 0 ? round(($teamTotal / $maxTotal) * 100) : 0;
?>
<div class="team-section">

    <div class="team-section-header">
        <h3><?= htmlspecialchars($teamDef['label']) ?></h3>
        <button class="toggle-log-btn"
                data-target="logform-<?= $teamKey ?>">+ Log</button>
    </div>

    <!-- Sub-team progress bars -->
    <div class="subteam-list">
    <?php if (!empty($teamDef['subteams'])): ?>
        <?php foreach ($teamDef['subteams'] as $subKey => $subLabel):
            $subMembers = subTeamTotal($teamRows, $subKey);
            $subContrib = subTeamContribCount($teamRows, $subKey);
            $subPct     = $teamTotal > 0 ? round(($subMembers / $teamTotal) * 100) : 0;
        ?>
        <div class="subteam-item">
            <div class="subteam-top">
                <span class="subteam-name"><?= htmlspecialchars($subLabel) ?></span>
                <span class="subteam-pct"><?= $subPct ?>%</span>
            </div>
            <div class="progress-bar">
                <div class="progress-fill" style="width:<?= $subPct ?>%"></div>
            </div>
            <div class="subteam-members">
                <?= $subMembers ?> member<?= $subMembers !== 1 ? 's' : '' ?>
                &middot; <?= $subContrib ?> contribution<?= $subContrib !== 1 ? 's' : '' ?>
            </div>
        </div>
        <?php endforeach; ?>
        <div class="team-total-row">
            <?= $teamTotal ?> total members
            &middot; <?= $evCount ?> event<?= $evCount !== 1 ? 's' : '' ?> supported
        </div>
    <?php else: ?>
        <div class="subteam-item">
            <div class="subteam-top">
                <span class="subteam-name">Team activity</span>
                <span class="subteam-pct"><?= $teamTotal ?> members</span>
            </div>
            <div class="progress-bar">
                <div class="progress-fill" style="width:<?= $flatPct ?>%"></div>
            </div>
            <div class="subteam-members">
                <?= $evCount ?> event<?= $evCount !== 1 ? 's' : '' ?> supported
                &middot; <?= $contribN ?> contribution<?= $contribN !== 1 ? 's' : '' ?>
            </div>
        </div>
    <?php endif; ?>
    </div>

    <!-- Recent contributions with Edit + Delete buttons -->
    <?php if (!empty($teamRows)): ?>
    <div class="contrib-list">
        <?php foreach (array_slice($teamRows, 0, 3) as $row): ?>
        <div class="contrib-item" id="contrib-<?= (int)$row['stat_id'] ?>">
            <div class="contrib-info">
                <span class="contrib-event"><?= htmlspecialchars($row['event_title']) ?></span>
                <?php if ($row['sub_team']): ?>
                    <span class="contrib-sub">
                        <?= htmlspecialchars($teamDef['subteams'][$row['sub_team']] ?? $row['sub_team']) ?>
                    </span>
                <?php endif; ?>
                <span class="contrib-note"><?= htmlspecialchars($row['contribution']) ?></span>
                <span class="contrib-members">
                    <?= (int)$row['members_involved'] ?>
                    member<?= $row['members_involved'] != 1 ? 's' : '' ?>
                </span>
            </div>
            <div class="contrib-actions">
                <button class="btn-contrib-edit"
                        data-stat-id="<?= (int)$row['stat_id'] ?>"
                        data-team="<?= htmlspecialchars($teamKey) ?>"
                        data-event-id="<?= (int)$row['event_id'] ?>"
                        data-sub-team="<?= htmlspecialchars($row['sub_team'] ?? '') ?>"
                        data-members="<?= (int)$row['members_involved'] ?>"
                        data-note="<?= htmlspecialchars($row['contribution'], ENT_QUOTES) ?>"
                >Edit</button>
                <button class="btn-contrib-delete"
                        data-stat-id="<?= (int)$row['stat_id'] ?>"
                        data-note="<?= htmlspecialchars(mb_strimwidth($row['contribution'], 0, 40, '…'), ENT_QUOTES) ?>"
                >Delete</button>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Log new contribution form -->
    <div class="log-form" id="logform-<?= $teamKey ?>">
        <form method="POST" action="log_contribution.php">
            <input type="hidden" name="main_team" value="<?= $teamKey ?>">
            <div class="form-row">
                <div class="form-group">
                    <label>Event</label>
                    <select name="event_id">
                        <?php foreach ($allEvents as $ev): ?>
                        <option value="<?= (int)$ev['event_id'] ?>">
                            <?= htmlspecialchars($ev['title']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php if (!empty($teamDef['subteams'])): ?>
                <div class="form-group">
                    <label>Sub-team</label>
                    <select name="sub_team">
                        <?php foreach ($teamDef['subteams'] as $sk => $sl): ?>
                        <option value="<?= $sk ?>"><?= htmlspecialchars($sl) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php else: ?>
                    <input type="hidden" name="sub_team" value="">
                <?php endif; ?>
                <div class="form-group" style="flex:0 0 100px;">
                    <label>Members</label>
                    <input type="number" name="members_involved"
                           min="1" placeholder="0" required>
                </div>
            </div>
            <div class="form-group">
                <label>Contribution Note</label>
                <input type="text" name="contribution"
                       placeholder="e.g. Designed event poster">
            </div>
            <div style="display:flex; gap:10px;">
                <button type="button" class="btn-cancel"
                        data-target="logform-<?= $teamKey ?>">Cancel</button>
                <button type="submit" class="btn-save-log">Save &rarr;</button>
            </div>
        </form>
    </div>

</div>
<?php endforeach; ?>
</div>

<!-- ── EDIT CONTRIBUTION MODAL ─────────────────────────────── -->
<div class="modal-overlay" id="edit-contrib-modal">
    <div class="modal-box">
        <h3>Edit Contribution</h3>
        <form method="POST" action="edit_contribution.php">
            <input type="hidden" name="stat_id" id="edit-stat-id">
            <div class="form-group" style="margin-top:14px;">
                <label>Event</label>
                <select name="event_id" id="edit-event-id">
                    <?php foreach ($allEvents as $ev): ?>
                    <option value="<?= (int)$ev['event_id'] ?>">
                        <?= htmlspecialchars($ev['title']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" id="edit-subteam-wrap">
                <label>Sub-team</label>
                <select name="sub_team" id="edit-sub-team">
                    <option value="">- none -</option>
                    <option value="design">Design</option>
                    <option value="socialmedia">Social Media</option>
                    <option value="academic">Academic</option>
                    <option value="mentorship">Mentorship</option>
                </select>
            </div>
            <div class="form-group">
                <label>Members Involved</label>
                <input type="number" name="members_involved"
                       id="edit-members" min="1" required>
            </div>
            <div class="form-group">
                <label>Contribution Note</label>
                <input type="text" name="contribution" id="edit-note">
            </div>
            <div class="modal-actions">
                <button type="button" class="btn-modal-cancel"
                        id="edit-modal-cancel">Cancel</button>
                <button type="submit" class="btn-modal-confirm">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- ── DELETE CONTRIBUTION MODAL ──────────────────────────── -->
<div class="modal-overlay" id="delete-contrib-modal">
    <div class="modal-box">
        <h3>Delete Contribution?</h3>
        <p id="delete-contrib-msg" style="font-size:0.85rem;color:#999;margin:10px 0 0;"></p>
        <div class="modal-actions">
            <button type="button" class="btn-modal-cancel"
                    id="delete-modal-cancel">Cancel</button>
            <form method="POST" action="delete_contribution.php" style="flex:1;">
                <input type="hidden" name="stat_id" id="delete-stat-id">
                <button type="submit" class="btn-modal-confirm"
                        style="width:100%;">Delete</button>
            </form>
        </div>
    </div>
</div>

<style>
/* ── Contribution row with actions ── */
.contrib-item {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 10px;
}
.contrib-info {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    flex: 1;
    align-items: center;
}
.contrib-actions {
    display: flex;
    gap: 6px;
    flex-shrink: 0;
    align-items: center;
}
.btn-contrib-edit,
.btn-contrib-delete {
    font-family: 'Montserrat', sans-serif;
    font-size: 0.62rem;
    font-weight: 700;
    letter-spacing: 0.05em;
    text-transform: uppercase;
    padding: 4px 10px;
    border-radius: 4px;
    cursor: pointer;
    border: 1px solid transparent;
    transition: opacity 0.2s, border-color 0.2s, color 0.2s;
}
.btn-contrib-edit {
    background: transparent;
    border-color: #2a2a2a;
    color: #666;
}
.btn-contrib-edit:hover { border-color: #60b8e0; color: #60b8e0; }

.btn-contrib-delete {
    background: transparent;
    border-color: #2a2a2a;
    color: #666;
}
.btn-contrib-delete:hover { border-color: #e06060; color: #e06060; }
</style>

<script>
/**
 * Dashboard inline script
 * Handles: log form toggle, edit modal, delete modal.
 * Standards: const/let, addEventListener, DOMContentLoaded.
 */
document.addEventListener('DOMContentLoaded', function () {

    // ── Toggle log form open/close ─────────────────────────
    document.querySelectorAll('.toggle-log-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const target = document.getElementById(btn.dataset.target);
            if (target) target.classList.toggle('open');
        });
    });

    // Cancel buttons inside log forms
    document.querySelectorAll('.btn-cancel[data-target]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const target = document.getElementById(btn.dataset.target);
            if (target) target.classList.remove('open');
        });
    });

    // ── Edit modal ─────────────────────────────────────────
    const editModal   = document.getElementById('edit-contrib-modal');
    const editCancel  = document.getElementById('edit-modal-cancel');

    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.btn-contrib-edit');
        if (!btn) return;
        openEditModal(btn);
    });

    if (editCancel) {
        editCancel.addEventListener('click', function () {
            editModal.classList.remove('open');
        });
    }
    if (editModal) {
        editModal.addEventListener('click', function (e) {
            if (e.target === editModal) editModal.classList.remove('open');
        });
    }

    // ── Delete modal ───────────────────────────────────────
    const deleteModal  = document.getElementById('delete-contrib-modal');
    const deleteCancel = document.getElementById('delete-modal-cancel');

    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.btn-contrib-delete');
        if (!btn) return;
        openDeleteModal(btn);
    });

    if (deleteCancel) {
        deleteCancel.addEventListener('click', function () {
            deleteModal.classList.remove('open');
        });
    }
    if (deleteModal) {
        deleteModal.addEventListener('click', function (e) {
            if (e.target === deleteModal) deleteModal.classList.remove('open');
        });
    }

});

/**
 * Populate and open the Edit Contribution modal.
 * @param {HTMLElement} btn - The edit button that was clicked.
 */
function openEditModal(btn) {
    document.getElementById('edit-stat-id').value   = btn.dataset.statId;
    document.getElementById('edit-members').value   = btn.dataset.members;
    document.getElementById('edit-note').value      = btn.dataset.note;

    // Set the event dropdown
    const eventSel = document.getElementById('edit-event-id');
    if (eventSel) eventSel.value = btn.dataset.eventId;

    // Set the sub-team dropdown
    const subSel = document.getElementById('edit-sub-team');
    if (subSel) subSel.value = btn.dataset.subTeam || '';

    // Hide sub-team row for flat teams that never have sub-teams
    const subWrap = document.getElementById('edit-subteam-wrap');
    if (subWrap) {
        const flatTeams = ['events', 'outreach', 'webtech'];
        subWrap.style.display = flatTeams.includes(btn.dataset.team) ? 'none' : '';
    }

    document.getElementById('edit-contrib-modal').classList.add('open');
}

/**
 * Populate and open the Delete Contribution modal.
 * @param {HTMLElement} btn - The delete button that was clicked.
 */
function openDeleteModal(btn) {
    document.getElementById('delete-stat-id').value       = btn.dataset.statId;
    document.getElementById('delete-contrib-msg').textContent =
        'Delete "' + btn.dataset.note + '"? This cannot be undone.';
    document.getElementById('delete-contrib-modal').classList.add('open');
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>