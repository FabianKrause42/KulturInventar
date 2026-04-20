<?php
declare(strict_types=1);

require_once __DIR__ . '/src/auth/session.php';
require_login();
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <?php require_once __DIR__ . '/src/helpers/head_meta.php'; ?>
    <title>KulturInventar</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/styles.css">
</head>
<body>

    <main>

        <!-- ── Suche ─────────────────────────────────── -->
        <section class="search-section">
            <div class="search-row">
                <div class="search-field-wrap">
                    <span class="search-field-icon" aria-hidden="true">
                        <img src="<?= BASE_URL ?>/assets/img/icons/search.png" width="20" height="20" alt="">
                    </span>
                    <input
                        type="search"
                        id="q"
                        class="search-input"
                        placeholder="Suchen"
                        autocomplete="off"
                        autofocus
                    >
                </div>
                <button
                    type="button"
                    class="btn-scan"
                    id="btn-scan-suche"
                    aria-label="QR-Code scannen"
                >
                    <img src="<?= BASE_URL ?>/assets/img/icons/qr-code.png" width="30" height="30" alt="">
                </button>
            </div>
        </section>

        <!-- ── Trefferliste ──────────────────────────── -->
        <section class="result-section">
            <p class="result-label" id="result-label"></p>
            <ul class="result-list" id="result-list"></ul>
            <div id="scroll-sentinel"></div>
        </section>

        <!-- ── Neuer Artikel ─────────────────────────── -->
        <div class="new-item-wrap">
            <a href="<?= BASE_URL ?>/artikel_neu.php" class="btn">Neuen Artikel anlegen</a>
        </div>

    </main>

    <script>
    var BASE_URL = '<?= BASE_URL ?>';
    (function () {
        var STORAGE_KEY = 'kulturinventar_suche';
        var input     = document.getElementById('q');
        var list      = document.getElementById('result-list');
        var label     = document.getElementById('result-label');
        var sentinel  = document.getElementById('scroll-sentinel');

        var currentQuery = '';
        var offset       = 0;
        var total        = 0;
        var loading      = false;
        var debounceTimer;

        // ── Debounced Input ──────────────────────────
        input.addEventListener('input', function () {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(function () {
                var q = input.value.trim();
                if (q === currentQuery) return;
                currentQuery = q;
                sessionStorage.setItem(STORAGE_KEY, q);
                reset();
                if (q.length >= 3) {
                    loadMore();
                } else {
                    label.textContent = '';
                    sessionStorage.removeItem(STORAGE_KEY);
                }
            }, 300);
        });

        // ── Reset bei neuer Suche ────────────────────
        function reset() {
            offset = 0;
            total  = 0;
            list.innerHTML = '';
            label.textContent = '';
        }

        // ── Nächste Seite laden ──────────────────────
        function loadMore() {
            if (loading) return;
            loading = true;

            var url = BASE_URL + '/api/search.php?q=' + encodeURIComponent(currentQuery) + '&offset=' + offset;

            fetch(url)
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    total = data.total;
                    renderResults(data.results);
                    offset += data.results.length;

                    if (offset === 0 || data.total === 0) {
                        label.textContent = 'Keine Treffer für „' + currentQuery + '"';
                    } else {
                        label.textContent = total + ' Ergebnis' + (total === 1 ? '' : 'se');
                    }
                })
                .catch(function () {
                    label.textContent = 'Fehler beim Laden.';
                })
                .finally(function () {
                    loading = false;
                });
        }

        // ── Zeilen rendern ───────────────────────────
        function renderResults(results) {
            results.forEach(function (item) {
                var li = document.createElement('li');
                li.innerHTML =
                    '<a href="' + BASE_URL + '/artikel.php?id=' + item.id + '" class="result-row">' +
                        '<img class="result-thumb" src="' + escHtml(item.thumb) + '"' +
                            ' alt="' + escHtml(item.bezeichnung) + '"' +
                            ' loading="lazy" width="100" height="95">' +
                        '<div class="result-info">' +
                            '<span class="result-bezeichnung">' + escHtml(item.bezeichnung) + '</span>' +
                            (item.standort
                                ? '<span class="result-standort">Standort: ' + escHtml(item.standort) + '</span>'
                                : '') +
                        '</div>' +
                    '</a>';
                list.appendChild(li);
            });
        }

        // ── Infinite Scroll ──────────────────────────
        var observer = new IntersectionObserver(function (entries) {
            if (entries[0].isIntersecting && currentQuery.length >= 3 && offset < total) {
                loadMore();
            }
        }, { rootMargin: '200px' });

        observer.observe(sentinel);

        // ── HTML escapen ─────────────────────────────
        function escHtml(str) {
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;');
        }

        // ── Gespeicherte Suche wiederherstellen ──────
        var savedQuery = sessionStorage.getItem(STORAGE_KEY);
        if (savedQuery && savedQuery.length >= 3) {
            input.value  = savedQuery;
            currentQuery = savedQuery;
            loadMore();
        }
    }());
    </script>

    <script>
    document.getElementById('btn-scan-suche').addEventListener('click', function () {
        window.location.href = BASE_URL + '/scanner.php?context=suche';
    });
    </script>

</body>
</html>
