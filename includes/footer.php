<?php
// ============================================================
//  includes/footer.php  -  Shared page footer
// ============================================================
?>
</main>

<div id="toast" aria-live="polite"></div>

<script>
// ── Global toast helper ──────────────────────────────────
function showToast(msg, type = '') {
    const t = document.getElementById('toast');
    t.textContent = msg;
    t.className = 'show ' + type;
    clearTimeout(t._timer);
    t._timer = setTimeout(() => { t.className = ''; }, 3000);
}

// ── Auto-dismiss flash messages ──────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    const flash = document.querySelector('.flash');
    if (flash) {
        setTimeout(() => flash.style.opacity = '0', 3000);
        setTimeout(() => flash.remove(), 3400);
    }
});
</script>
</body>
</html>
