document.addEventListener('DOMContentLoaded', function () {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.forEach(function (el) {
        new bootstrap.Tooltip(el);
    });

    // ---- Dark mode toggle ----
    var themeToggle = document.getElementById('themeToggle');
    if (themeToggle) {
        var current = themeToggle.getAttribute('data-current') || 'light';
        var csrf = themeToggle.getAttribute('data-csrf') || '';
        var themeUrl = themeToggle.getAttribute('data-url') || 'theme.php';
        var applyIcon = function (theme) {
            themeToggle.innerHTML = theme === 'dark'
                ? '<i class="bi bi-sun"></i>'
                : '<i class="bi bi-moon-stars"></i>';
        };
        applyIcon(current);
        themeToggle.addEventListener('click', function () {
            var next = document.documentElement.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark';
            document.documentElement.setAttribute('data-bs-theme', next);
            applyIcon(next);
            var fd = new FormData();
            fd.append('theme', next);
            fd.append('csrf_token', csrf);
            fetch(themeUrl, { method: 'POST', body: fd })
                .then(function (r) { return r.json(); })
                .then(function (d) { if (d && d.theme) applyIcon(d.theme); })
                .catch(function () {});
        });
    }

    // ---- Auto-refresh for dashboard ----
    var autoRefresh = document.getElementById('autoRefresh');
    if (autoRefresh) {
        var seconds = parseInt(autoRefresh.getAttribute('data-seconds') || '60', 10);
        var secondsLeft = seconds;
        var indicator = document.getElementById('refreshIndicator');
        function tick() {
            secondsLeft -= 1;
            if (indicator) indicator.textContent = 'Refreshes in ' + secondsLeft + 's';
            if (secondsLeft <= 0) {
                window.location.reload();
                return;
            }
        }
        setInterval(tick, 1000);
    }

    // ---- Sortable table headers (PR list) ----
    var sortable = document.querySelectorAll('table[data-sortable] th[data-sort]');
    sortable.forEach(function (th) {
        th.classList.add('sortable');
        th.style.cursor = 'pointer';
        th.addEventListener('click', function () {
            var key = th.getAttribute('data-sort');
            var dir = th.getAttribute('data-dir') === 'asc' ? 'desc' : 'asc';
            var base = th.getAttribute('data-base') || window.location.pathname.split('/').pop();
            var params = new URLSearchParams(window.location.search);
            params.set('sort', key);
            params.set('dir', dir);
            window.location.href = base + '?' + params.toString();
        });
    });
});
