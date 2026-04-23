<?php


require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

requireLogin();

$user   = currentUser();
$isExec = isExec();
$pdo    = getDB();

// ── Flash message ─────────────────────────────────────────
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

// ── Month / year from GET (validated) ────────────────────
$year  = isset($_GET['year'])  ? (int)$_GET['year']  : (int)date('Y');
$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');

if ($month < 1)  { $month = 12; $year--; }
if ($month > 12) { $month = 1;  $year++; }

// ── Active view: calendar | year | list ──────────────────
$validViews  = ['calendar', 'year', 'list'];
$activeView  = in_array($_GET['view'] ?? '', $validViews, true)
               ? $_GET['view']
               : 'calendar';

// ── Calendar math ─────────────────────────────────────────
$firstDay    = mktime(0, 0, 0, $month, 1, $year);
$daysInMonth = (int)date('t', $firstDay);
$startDow    = (int)date('N', $firstDay) % 7; // Sun=0 … Sat=6

// ── Events this month (for calendar + day modal) ──────────
$uid  = (int)$user['id'];
$stmt = $pdo->prepare(
    'SELECT e.*,
            COUNT(r.rsvp_id) AS rsvp_count,
            MAX(CASE WHEN r.user_id = ? THEN 1 ELSE 0 END) AS user_rsvpd
     FROM Events e
     LEFT JOIN RSVPs r ON r.event_id = e.event_id
     WHERE YEAR(e.date) = ? AND MONTH(e.date) = ?
     GROUP BY e.event_id
     ORDER BY e.date ASC, e.time ASC'
);
$stmt->execute([$uid, $year, $month]);
$monthEvents = $stmt->fetchAll();

// Index by calendar day
$byDay = [];
foreach ($monthEvents as $ev) {
    $byDay[(int)date('j', strtotime($ev['date']))][] = $ev;
}

// ── Upcoming events for list view ─────────────────────────
$listStmt = $pdo->prepare(
    'SELECT e.*,
            COUNT(r.rsvp_id) AS rsvp_count,
            MAX(CASE WHEN r.user_id = ? THEN 1 ELSE 0 END) AS user_rsvpd
     FROM Events e
     LEFT JOIN RSVPs r ON r.event_id = e.event_id
     WHERE e.date >= CURDATE()
     GROUP BY e.event_id
     ORDER BY e.date ASC, e.time ASC
     LIMIT 30'
);
$listStmt->execute([$uid]);
$upcomingEvents = $listStmt->fetchAll();

// ── Event counts per month for year view ─────────────────
$yearStmt = $pdo->prepare(
    'SELECT MONTH(date) AS m, COUNT(*) AS cnt
     FROM Events
     WHERE YEAR(date) = ?
     GROUP BY MONTH(date)'
);
$yearStmt->execute([$year]);
$yearCounts = array_fill(1, 12, 0);
foreach ($yearStmt->fetchAll() as $row) {
    $yearCounts[(int)$row['m']] = (int)$row['cnt'];
}

// ── Exec-only RSVP heatmap (per calendar day) ─────────────
$heatmap = [];
if ($isExec) {
    $hStmt = $pdo->prepare(
        'SELECT DAY(e.date) AS day, SUM(sub.cnt) AS total
         FROM Events e
         JOIN (SELECT event_id, COUNT(*) AS cnt FROM RSVPs GROUP BY event_id) sub
              ON sub.event_id = e.event_id
         WHERE YEAR(e.date) = ? AND MONTH(e.date) = ?
         GROUP BY DAY(e.date)'
    );
    $hStmt->execute([$year, $month]);
    foreach ($hStmt->fetchAll() as $row) {
        $heatmap[(int)$row['day']] = (int)$row['total'];
    }
}

// ── Navigation values ─────────────────────────────────────
$monthName = date('F', $firstDay);
$prevMonth = $month === 1  ? 12  : $month - 1;
$prevYear  = $month === 1  ? $year - 1 : $year;
$nextMonth = $month === 12 ? 1   : $month + 1;
$nextYear  = $month === 12 ? $year + 1 : $year;

// ── Year range for dropdown (DB range ±1) ─────────────────
$rangeRow = $pdo->query(
    'SELECT MIN(YEAR(date)) AS mn, MAX(YEAR(date)) AS mx FROM Events'
)->fetch();
$minYear = min((int)($rangeRow['mn'] ?? date('Y')), (int)date('Y') - 1);
$maxYear = max((int)($rangeRow['mx'] ?? date('Y')), (int)date('Y') + 2);

// ── Category colours ──────────────────────────────────────
$catColors = [
    'academic'    => '#60b8e0',
    'networking'  => '#a78bfa',
    'social'      => '#6dbe6d',
    'workshop'    => '#f5a623',
    'competition' => '#e06060',
];

$monthAbbr = ['Jan','Feb','Mar','Apr','May','Jun',
               'Jul','Aug','Sep','Oct','Nov','Dec'];

$pageTitle = 'Calendar';
include __DIR__ . '/includes/header.php';
?>

<style>
/* ── View toggle ───────────────────────────────────────── */
.cal-view-toggle { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }

/* ── Year selector (inline dropdown) ──────────────────── */
.year-select {
    background: #141414; border: 1px solid #2a2a2a;
    border-radius: 6px; color: #aaa;
    font-family: 'Montserrat', sans-serif; font-size: 0.78rem;
    font-weight: 700; padding: 5px 10px; cursor: pointer;
    outline: none; transition: border-color 0.2s, color 0.2s;
    appearance: auto;
}
.year-select:hover,
.year-select:focus { border-color: #60b8e0; color: #e8e8e8; }
.year-select option { background: #141414; }

/* ── Calendar nav centre ───────────────────────────────── */
.cal-nav-center {
    display: flex; flex-direction: column;
    align-items: center; gap: 6px;
}

/* ── Year view grid ────────────────────────────────────── */
.year-view-header {
    display: flex; align-items: center;
    justify-content: space-between;
    margin-bottom: 24px; gap: 12px;
}
.year-big-title {
    font-size: 2rem; font-weight: 700;
    color: #fff; letter-spacing: -0.03em; margin: 0;
}
.year-title-wrap {
    display: flex; flex-direction: column;
    align-items: center; gap: 8px;
}
.year-mini-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 12px;
}
@media (max-width: 900px) { .year-mini-grid { grid-template-columns: repeat(3,1fr); } }
@media (max-width: 600px) { .year-mini-grid { grid-template-columns: repeat(2,1fr); } }

/* Mini month card */
.mini-month {
    background: #111; border: 1px solid #1a1a1a;
    border-radius: 10px; padding: 14px;
    text-decoration: none; color: inherit;
    display: flex; flex-direction: column; gap: 10px;
    transition: border-color 0.2s, background 0.2s, transform 0.15s;
}
.mini-month:hover { border-color: #2e2e2e; background: #141414; transform: translateY(-1px); }
.mini-month--active {
    border-color: rgba(96,184,224,0.5) !important;
    background: rgba(96,184,224,0.05) !important;
}

.mini-month-header {
    display: flex; align-items: center; justify-content: space-between;
}
.mini-month-name {
    font-size: 0.75rem; font-weight: 700; color: #ccc;
    letter-spacing: 0.04em; text-transform: uppercase;
}
.mini-event-badge {
    font-size: 0.6rem; font-weight: 700;
    background: rgba(96,184,224,0.15); color: #60b8e0;
    border: 1px solid rgba(96,184,224,0.3);
    border-radius: 4px; padding: 1px 6px; letter-spacing: 0.04em;
}

/* Tiny calendar inside year card */
.mini-cal {
    display: grid; grid-template-columns: repeat(7, 1fr); gap: 1px;
}
.mini-dow {
    font-size: 0.48rem; font-weight: 700; color: #2a2a2a;
    text-align: center; padding: 2px 0;
}
.mini-day, .mini-empty {
    font-size: 0.52rem; color: #3a3a3a;
    text-align: center; padding: 2px 0; border-radius: 2px; line-height: 1.5;
}
.mini-today {
    background: #60b8e0; color: #0d0d0d;
    font-weight: 700; border-radius: 2px;
}

/* ── List view ─────────────────────────────────────────── */
.list-section-title {
    font-size: 0.82rem; font-weight: 700; color: #555;
    letter-spacing: 0.07em; text-transform: uppercase; margin-bottom: 14px;
}
</style>

<!-- ── Page header ──────────────────────────────────────── -->
<div class="page-header">
    <h1>Event Calendar</h1>
    <div class="cal-view-toggle">
        <button class="cal-toggle-btn <?= $activeView === 'calendar' ? 'active' : '' ?>"
                data-view="calendar">Calendar</button>
        <button class="cal-toggle-btn <?= $activeView === 'year'     ? 'active' : '' ?>"
                data-view="year">Year View</button>
        <button class="cal-toggle-btn <?= $activeView === 'list'     ? 'active' : '' ?>"
                data-view="list">List View</button>
        <?php if ($isExec): ?>
            <a href="event_form.php" class="btn-add-event" style="margin-left:8px;">+ New Event</a>
        <?php endif; ?>
    </div>
</div>

<?php if ($flash): ?>
    <div class="flash flash-<?= htmlspecialchars($flash['type']) ?>">
        <?= htmlspecialchars($flash['msg']) ?>
    </div>
<?php endif; ?>


<!-- ══════════════════════════════════════════════════════
     VIEW: CALENDAR (month grid)
════════════════════════════════════════════════════════ -->
<div id="view-calendar" style="display:<?= $activeView === 'calendar' ? 'block' : 'none' ?>;">

    <!-- Month nav with inline year picker -->
    <div class="cal-nav">
        <a href="calendar.php?month=<?= $prevMonth ?>&year=<?= $prevYear ?>&view=calendar"
           class="cal-nav-btn">
            &larr; <?= $monthAbbr[$prevMonth - 1] ?>
        </a>

        <div class="cal-nav-center">
            <h2 class="cal-month-title"><?= $monthName ?></h2>
            <form method="GET" action="calendar.php">
                <input type="hidden" name="month" value="<?= $month ?>">
                <input type="hidden" name="view"  value="calendar">
                <select name="year" class="year-select" onchange="this.form.submit()">
                    <?php for ($y = $minYear; $y <= $maxYear; $y++): ?>
                        <option value="<?= $y ?>" <?= $y === $year ? 'selected' : '' ?>><?= $y ?></option>
                    <?php endfor; ?>
                </select>
            </form>
        </div>

        <a href="calendar.php?month=<?= $nextMonth ?>&year=<?= $nextYear ?>&view=calendar"
           class="cal-nav-btn">
            <?= $monthAbbr[$nextMonth - 1] ?> &rarr;
        </a>
    </div>

    <?php if ($isExec): ?>
    <div class="cal-exec-bar">
        <span class="cal-exec-label">RSVP Heatmap Active</span>
        <div class="cal-legend">
            <?php foreach ($catColors as $cat => $color): ?>
                <span class="leg-dot" style="background:<?= $color ?>"></span>
                <span class="leg-label"><?= ucfirst($cat) ?></span>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Grid -->
    <div class="cal-grid">
        <?php foreach (['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $dow): ?>
            <div class="cal-dow"><?= $dow ?></div>
        <?php endforeach; ?>

        <!-- Empty leading cells -->
        <?php for ($i = 0; $i < $startDow; $i++): ?>
            <div class="cal-cell cal-cell--empty"></div>
        <?php endfor; ?>

        <!-- Day cells -->
        <?php
        $todayD = (int)date('j');
        $todayM = (int)date('n');
        $todayY = (int)date('Y');

        for ($d = 1; $d <= $daysInMonth; $d++):
            $isToday    = ($d === $todayD && $month === $todayM && $year === $todayY);
            $dayEvents  = $byDay[$d] ?? [];
            $heatVal    = $isExec ? ($heatmap[$d] ?? 0) : 0;
            $heatOpacity = $heatVal > 0 ? min(0.45, $heatVal / 60) : 0;
        ?>
        <div class="cal-cell
                    <?= $isToday        ? 'cal-cell--today'      : '' ?>
                    <?= !empty($dayEvents) ? 'cal-cell--has-events' : '' ?>"
             style="<?= $heatOpacity > 0 ? "background:rgba(96,184,224,{$heatOpacity})" : '' ?>"
             data-day="<?= $d ?>"
             data-label="<?= $monthName ?> <?= $d ?>, <?= $year ?>"
        >
            <div class="cal-day-num"><?= $d ?></div>
            <?php if ($isExec && $heatVal > 0): ?>
                <div class="cal-heat-count"><?= $heatVal ?> RSVPs</div>
            <?php endif; ?>
            <div class="cal-day-events">
                <?php foreach (array_slice($dayEvents, 0, 3) as $ev):
                    $color = $catColors[$ev['category']] ?? '#888';
                ?>
                <div class="cal-event-pill"
                     style="border-left:3px solid <?= $color ?>"
                     title="<?= htmlspecialchars($ev['title']) ?> - <?= date('g:i A', strtotime($ev['time'])) ?>">
                    <?= htmlspecialchars(mb_strimwidth($ev['title'], 0, 22, '…')) ?>
                </div>
                <?php endforeach; ?>
                <?php if (count($dayEvents) > 3): ?>
                    <div class="cal-event-more">+<?= count($dayEvents) - 3 ?> more</div>
                <?php endif; ?>
            </div>
        </div>
        <?php endfor; ?>
    </div><!-- .cal-grid -->

</div><!-- #view-calendar -->


<!-- ══════════════════════════════════════════════════════
     VIEW: YEAR (mini month cards)
════════════════════════════════════════════════════════ -->
<div id="view-year" style="display:<?= $activeView === 'year' ? 'block' : 'none' ?>;">

    <div class="year-view-header">
        <a href="calendar.php?month=<?= $month ?>&year=<?= $year - 1 ?>&view=year"
           class="cal-nav-btn">&larr; <?= $year - 1 ?></a>

        <div class="year-title-wrap">
            <h2 class="year-big-title"><?= $year ?></h2>
            <form method="GET" action="calendar.php">
                <input type="hidden" name="month" value="<?= $month ?>">
                <input type="hidden" name="view"  value="year">
                <select name="year" class="year-select" onchange="this.form.submit()">
                    <?php for ($y = $minYear; $y <= $maxYear; $y++): ?>
                        <option value="<?= $y ?>" <?= $y === $year ? 'selected' : '' ?>><?= $y ?></option>
                    <?php endfor; ?>
                </select>
            </form>
        </div>

        <a href="calendar.php?month=<?= $month ?>&year=<?= $year + 1 ?>&view=year"
           class="cal-nav-btn"><?= $year + 1 ?> &rarr;</a>
    </div>

    <div class="year-mini-grid">
        <?php for ($m = 1; $m <= 12; $m++):
            $mFirst    = mktime(0, 0, 0, $m, 1, $year);
            $mDays     = (int)date('t', $mFirst);
            $mStartDow = (int)date('N', $mFirst) % 7; // Sun=0
            $mCount    = $yearCounts[$m];
            $isActive  = ($m === $month);
        ?>
        <a href="calendar.php?month=<?= $m ?>&year=<?= $year ?>&view=calendar"
           class="mini-month <?= $isActive ? 'mini-month--active' : '' ?>">

            <div class="mini-month-header">
                <span class="mini-month-name"><?= $monthAbbr[$m - 1] ?></span>
                <?php if ($mCount > 0): ?>
                    <span class="mini-event-badge"><?= $mCount ?></span>
                <?php endif; ?>
            </div>

            <div class="mini-cal">
                <?php foreach (['S','M','T','W','T','F','S'] as $dh): ?>
                    <div class="mini-dow"><?= $dh ?></div>
                <?php endforeach; ?>

                <?php for ($i = 0; $i < $mStartDow; $i++): ?>
                    <div class="mini-empty"></div>
                <?php endfor; ?>

                <?php for ($d = 1; $d <= $mDays; $d++):
                    $isTd = ($d === $todayD && $m === $todayM && $year === $todayY);
                ?>
                    <div class="mini-day <?= $isTd ? 'mini-today' : '' ?>"><?= $d ?></div>
                <?php endfor; ?>
            </div>

        </a>
        <?php endfor; ?>
    </div>

</div><!-- #view-year -->


<!-- ══════════════════════════════════════════════════════
     VIEW: LIST (upcoming events)
════════════════════════════════════════════════════════ -->
<div id="view-list" style="display:<?= $activeView === 'list' ? 'block' : 'none' ?>;">

    <h2 class="list-section-title">Upcoming Events</h2>

    <?php if (empty($upcomingEvents)): ?>
        <div class="empty-state">No upcoming events found.</div>
    <?php else: ?>
        <div class="list-events">
            <?php foreach ($upcomingEvents as $ev):
                $color = $catColors[$ev['category']] ?? '#888';
            ?>
            <div class="list-event-row" style="border-left:4px solid <?= $color ?>">

                <div class="list-event-date">
                    <div class="list-month"><?= date('M', strtotime($ev['date'])) ?></div>
                    <div class="list-day"><?= date('j', strtotime($ev['date'])) ?></div>
                </div>

                <div class="list-event-info">
                    <div class="list-event-title"><?= htmlspecialchars($ev['title']) ?></div>
                    <div class="list-event-meta">
                        <?= date('g:i A', strtotime($ev['time'])) ?>
                        &middot; <?= htmlspecialchars($ev['location']) ?>
                        &middot; Capacity <?= (int)$ev['capacity'] ?>
                    </div>
                    <?php if (!empty(trim((string)$ev['description']))): ?>
                    <div class="list-event-desc">
                        <?= htmlspecialchars(mb_strimwidth($ev['description'], 0, 140, '…')) ?>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="list-event-actions">
                    <div class="list-rsvp-count">
                        <span class="count-num" id="lcount-<?= (int)$ev['event_id'] ?>">
                            <?= (int)$ev['rsvp_count'] ?>
                        </span>
                        <span class="count-label">interested</span>
                    </div>
                    <button
                        class="rsvp-btn <?= $ev['user_rsvpd'] ? 'rsvpd' : '' ?>"
                        data-event-id="<?= (int)$ev['event_id'] ?>"
                        data-list="1"
                    ><?= $ev['user_rsvpd'] ? 'Interested &#10003;' : 'RSVP' ?></button>
                    <?php if ($isExec): ?>
                        <a href="event_form.php?id=<?= (int)$ev['event_id'] ?>"
                           class="btn-edit" style="font-size:0.7rem;padding:6px 12px;">Edit</a>
                    <?php endif; ?>
                </div>

            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

</div><!-- #view-list -->


<!-- ══════════════════════════════════════════════════════
     DAY DETAIL MODAL
════════════════════════════════════════════════════════ -->
<div class="modal-overlay" id="day-modal">
    <div class="modal-box modal-box--wide">
        <div class="modal-day-header">
            <h3 id="day-modal-title">-</h3>
            <button class="modal-close-x" id="day-modal-close">&#x2715;</button>
        </div>
        <div id="day-modal-body" class="day-modal-body"></div>
        <?php if ($isExec): ?>
        <div class="modal-day-footer">
            <a id="day-new-event-link" href="event_form.php" class="btn-add-event"
               style="font-size:0.72rem;padding:8px 16px;">+ Schedule Event This Day</a>
        </div>
        <?php endif; ?>
    </div>
</div>


<script>
/**
 * Inline calendar script for calendar.php
 *
 * Responsibilities:
 *   - View switching (calendar / year / list) without page reload
 *   - Day modal: open on cell click, populate with events from PHP data
 *   - RSVP toggle: AJAX POST to rsvp.php, updates button + counts
 *
 * Standards: const/let only, addEventListener (no onclick attrs),
 * wrapped in DOMContentLoaded, clear separation from PHP.
 */

// ── PHP → JS data bridge ──────────────────────────────────
const IS_EXEC      = <?= $isExec ? 'true' : 'false' ?>;
const CAL_YEAR     = <?= (int)$year ?>;
const CAL_MONTH    = <?= (int)$month ?>;
const CAL_MONTH_PAD = String(<?= (int)$month ?>).padStart(2, '0');
const DAY_EVENTS   = <?= json_encode($byDay,     JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
const CAT_COLORS   = <?= json_encode($catColors, JSON_HEX_TAG) ?>;

document.addEventListener('DOMContentLoaded', function () {

    // ── View toggle buttons ────────────────────────────────
    const toggleBtns = document.querySelectorAll('.cal-toggle-btn[data-view]');
    toggleBtns.forEach(function (btn) {
        btn.addEventListener('click', function () {
            switchView(btn.dataset.view);
        });
    });

    // ── Calendar day cell clicks ───────────────────────────
    const calGrid = document.getElementById('view-calendar');
    if (calGrid) {
        calGrid.addEventListener('click', function (e) {
            const cell = e.target.closest('.cal-cell:not(.cal-cell--empty)');
            if (!cell) return;
            const day   = parseInt(cell.dataset.day,   10);
            const label = cell.dataset.label || String(day);
            openDayModal(day, label);
        });
    }

    // ── Day modal close - X button ─────────────────────────
    const closeBtn = document.getElementById('day-modal-close');
    if (closeBtn) {
        closeBtn.addEventListener('click', closeDayModal);
    }

    // ── Day modal close - overlay click ───────────────────
    const dayModal = document.getElementById('day-modal');
    if (dayModal) {
        dayModal.addEventListener('click', function (e) {
            if (e.target === dayModal) closeDayModal();
        });
    }

    // ── RSVP buttons (event delegation: modal + list view) ─
    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.rsvp-btn');
        if (!btn) return;
        const eventId = parseInt(btn.dataset.eventId, 10);
        if (!eventId) return;
        const inList  = btn.dataset.list === '1';
        toggleRSVP(btn, eventId, inList);
    });

});

/**
 * Switch the visible view panel and update toggle button styles.
 *
 * @param {string} view - One of 'calendar', 'year', 'list'.
 */
function switchView(view) {
    const views = ['calendar', 'year', 'list'];
    views.forEach(function (v) {
        const panel = document.getElementById('view-' + v);
        if (panel) panel.style.display = (v === view) ? 'block' : 'none';
    });

    document.querySelectorAll('.cal-toggle-btn[data-view]').forEach(function (btn) {
        btn.classList.toggle('active', btn.dataset.view === view);
    });
}

/**
 * Open the day detail modal and populate it with events for that day.
 *
 * @param {number} day   - Calendar day number (1–31).
 * @param {string} label - Human-readable date label for the modal title.
 */
function openDayModal(day, label) {
    document.getElementById('day-modal-title').textContent = label;

    const events = DAY_EVENTS[day] || [];
    const body   = document.getElementById('day-modal-body');

    if (events.length === 0) {
        body.innerHTML = '<p class="day-modal-empty">No events scheduled for this day.</p>';
    } else {
        body.innerHTML = events.map(function (ev) {
            const color = CAT_COLORS[ev.category] || '#888';
            const rsvpd = ev.user_rsvpd == 1;
            const desc  = ev.description
                ? '<div class="day-event-desc">'
                  + escHtml(ev.description.substring(0, 160))
                  + (ev.description.length > 160 ? '…' : '')
                  + '</div>'
                : '';
            const editBtn = IS_EXEC
                ? '<a href="event_form.php?id=' + ev.event_id
                  + '" class="btn-edit" style="font-size:.7rem;padding:6px 12px;'
                  + 'text-decoration:none;">Edit</a>'
                : '';

            return '<div class="day-event-card" style="border-left:4px solid ' + color + '">'
                + '<div class="day-event-top">'
                + '<span class="day-event-cat" style="color:' + color + '">' + cap(ev.category) + '</span>'
                + '<span class="day-event-time">' + fmtTime(ev.time) + '</span>'
                + '</div>'
                + '<div class="day-event-title">' + escHtml(ev.title) + '</div>'
                + '<div class="day-event-meta">' + escHtml(ev.location)
                + ' &middot; Capacity ' + ev.capacity + '</div>'
                + desc
                + '<div class="day-event-footer">'
                + '<span class="day-rsvp-count"><span id="mc-' + ev.event_id + '">'
                + ev.rsvp_count + '</span> interested</span>'
                + '<button class="rsvp-btn ' + (rsvpd ? 'rsvpd' : '') + '"'
                + ' data-event-id="' + ev.event_id + '"'
                + '>' + (rsvpd ? 'Interested &#10003;' : 'RSVP') + '</button>'
                + editBtn
                + '</div>'
                + '</div>';
        }).join('');
    }

    // Update exec "new event" link with pre-filled date
    const newLink = document.getElementById('day-new-event-link');
    if (newLink) {
        const dayPad = String(day).padStart(2, '0');
        newLink.href = 'event_form.php?prefill_date='
                     + CAL_YEAR + '-' + CAL_MONTH_PAD + '-' + dayPad;
    }

    document.getElementById('day-modal').classList.add('open');
}

/**
 * Close the day detail modal.
 */
function closeDayModal() {
    document.getElementById('day-modal').classList.remove('open');
}

/**
 * Toggle RSVP status for an event via AJAX.
 *
 * @param {HTMLElement} btn     - The RSVP button element.
 * @param {number}      eventId - The event's database ID.
 * @param {boolean}     inList  - Whether the button is in the list view.
 */
function toggleRSVP(btn, eventId, inList) {
    btn.disabled = true;

    fetch('rsvp.php', {
        method:  'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body:    'event_id=' + eventId
    })
    .then(function (r) { return r.json(); })
    .then(function (data) {
        if (data.error) {
            showToast(data.error, 'error');
            btn.disabled = false;
            return;
        }

        // Update the clicked button
        btn.innerHTML = data.rsvpd ? 'Interested &#10003;' : 'RSVP';
        btn.className = 'rsvp-btn' + (data.rsvpd ? ' rsvpd' : '');

        // Update modal count
        const mc = document.getElementById('mc-' + eventId);
        if (mc) mc.textContent = data.count;

        // Update list-view count
        const lc = document.getElementById('lcount-' + eventId);
        if (lc) lc.textContent = data.count;

        // Keep DAY_EVENTS in sync so re-opening modal reflects change
        for (const day in DAY_EVENTS) {
            DAY_EVENTS[day].forEach(function (ev) {
                if (ev.event_id == eventId) {
                    ev.user_rsvpd = data.rsvpd ? 1 : 0;
                    ev.rsvp_count = data.count;
                }
            });
        }

        btn.disabled = false;
    })
    .catch(function () {
        showToast('Something went wrong. Please try again.', 'error');
        btn.disabled = false;
    });
}

// ── Utility helpers ───────────────────────────────────────
/**
 * Capitalise first letter of a string.
 * @param {string} s
 * @returns {string}
 */
function cap(s) {
    return String(s).charAt(0).toUpperCase() + String(s).slice(1);
}

/**
 * Escape HTML special characters.
 * @param {string} s
 * @returns {string}
 */
function escHtml(s) {
    return String(s)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

/**
 * Format a HH:MM:SS time string to "h:mm AM/PM".
 * @param {string} t
 * @returns {string}
 */
function fmtTime(t) {
    const parts = t.split(':').map(Number);
    const h     = parts[0];
    const m     = parts[1];
    const ampm  = h >= 12 ? 'PM' : 'AM';
    return ((h % 12) || 12) + ':' + String(m).padStart(2, '0') + ' ' + ampm;
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>