/* global window, document, fetch */
(function () {
    'use strict';

    /**
     * Tag-Eingabefeld initialisieren.
     *
     * @param {HTMLInputElement} inputEl  Das Textfeld
     * @param {string}           baseUrl  BASE_URL der App
     */
    window.initTagInput = function (inputEl, baseUrl) {
        var input = inputEl;

        // Dropdown-Liste direkt nach dem Input einfügen
        var dropdown = document.createElement('ul');
        dropdown.className = 'tag-dropdown';
        input.parentNode.appendChild(dropdown);

        var debounceTimer;
        var suggestions = [];
        var activeIndex = -1;

        /* ── Hilfsfunktionen ─────────────────────────────────── */

        // Wort nach dem letzten Komma (gerade getippt)
        function getCurrentWord() {
            var val       = input.value;
            var lastComma = val.lastIndexOf(',');
            return val.slice(lastComma + 1).trim();
        }

        // Anzahl bereits bestätigter Tags (vor dem letzten Komma)
        function getConfirmedCount() {
            var val       = input.value;
            var lastComma = val.lastIndexOf(',');
            if (lastComma < 0) return 0;
            return val.slice(0, lastComma)
                .split(',')
                .filter(function (t) { return t.trim() !== ''; })
                .length;
        }

        // Aktuelles Wort als Tag bestätigen (fügt ", " an)
        function confirmWord(word) {
            word = word.trim();
            if (!word) return;
            var val       = input.value;
            var lastComma = val.lastIndexOf(',');
            var before    = lastComma >= 0 ? val.slice(0, lastComma).trimEnd() : '';
            input.value   = (before ? before + ', ' : '') + word + ', ';
            hideDropdown();
        }

        function hideDropdown() {
            dropdown.style.display = 'none';
            dropdown.innerHTML     = '';
            suggestions  = [];
            activeIndex  = -1;
        }

        function updateActive() {
            Array.from(dropdown.children).forEach(function (li, i) {
                li.classList.toggle('active', i === activeIndex);
            });
        }

        function showDropdown(items) {
            if (!items.length) { hideDropdown(); return; }
            suggestions = items;
            activeIndex = -1;
            dropdown.innerHTML = '';
            items.forEach(function (name) {
                var li = document.createElement('li');
                li.textContent = name;
                li.addEventListener('mousedown', function (e) {
                    e.preventDefault(); // blur verhindern
                    confirmWord(name);
                });
                dropdown.appendChild(li);
            });
            dropdown.style.display = 'block';
        }

        /* ── Tastatursteuerung ───────────────────────────────── */

        input.addEventListener('keydown', function (e) {
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                activeIndex = Math.min(activeIndex + 1, suggestions.length - 1);
                updateActive();
                return;
            }
            if (e.key === 'ArrowUp') {
                e.preventDefault();
                activeIndex = Math.max(activeIndex - 1, -1);
                updateActive();
                return;
            }
            if (e.key === 'Escape') {
                hideDropdown();
                return;
            }
            // Backspace: letzten bestätigten Tag komplett löschen
            if (e.key === 'Backspace') {
                var currentWord = getCurrentWord();
                // Nur eingreifen wenn kein Zeichen mehr vor dem Cursor im aktuellen Wort steht
                if (currentWord === '') {
                    var val = input.value.replace(/,\s*$/, '').trimEnd();
                    var lastComma = val.lastIndexOf(',');
                    if (lastComma >= 0) {
                        e.preventDefault();
                        input.value = val.slice(0, lastComma).trimEnd();
                        // Trailing ", " wieder anhängen damit nächstes Wort korrekt positioniert ist
                        if (input.value !== '') input.value += ', ';
                        hideDropdown();
                    }
                }
                return;
            }
            if (e.key === ' ') {
                var wordSpace = getCurrentWord();
                if (!wordSpace) return;
                if (getConfirmedCount() >= 3) { e.preventDefault(); return; }
                e.preventDefault();
                confirmWord(activeIndex >= 0 ? suggestions[activeIndex] : wordSpace);
                return;
            }
            if (e.key === 'Enter') {
                e.preventDefault();
                var wordEnter = getCurrentWord();
                if (wordEnter && getConfirmedCount() < 3) {
                    confirmWord(activeIndex >= 0 ? suggestions[activeIndex] : wordEnter);
                }
                // Abschließen: trailing ", " entfernen, Keyboard schließen
                input.value = input.value.replace(/,\s*$/, '').trim();
                hideDropdown();
                input.blur();
            }
        });

        /* ── Autocomplete bei Eingabe ────────────────────────── */

        input.addEventListener('input', function () {
            var word = getCurrentWord();
            if (word.length < 2 || getConfirmedCount() >= 3) {
                hideDropdown();
                return;
            }
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(function () {
                fetch(baseUrl + '/api/tags.php?q=' + encodeURIComponent(word))
                    .then(function (r) { return r.json(); })
                    .then(function (d)  { showDropdown(d.tags || []); })
                    .catch(hideDropdown);
            }, 200);
        });

        /* ── Aufräumen beim Verlassen ────────────────────────── */

        input.addEventListener('blur', function () {
            // Kurze Verzögerung damit mousedown auf Dropdown noch feuern kann
            setTimeout(function () {
                hideDropdown();
                input.value = input.value.replace(/,\s*$/, '').trim();
            }, 160);
        });
    };
}());
