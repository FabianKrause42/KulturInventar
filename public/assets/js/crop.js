/**
 * KulturInventar – Einfacher Bild-Crop
 * Aufruf: zeigeCropOverlay(file, function(croppedFile, previewUrl) { ... })
 * Der Nutzer positioniert und zoomt das Bild; "Verwenden" speichert den
 * sichtbaren Ausschnitt als JPEG-Blob und ruft den Callback auf.
 */
(function (win) {
    'use strict';

    win.zeigeCropOverlay = function (file, onDone) {
        var url = URL.createObjectURL(file);

        /* ── Overlay ── */
        var overlay = document.createElement('div');
        overlay.style.cssText =
            'position:fixed;inset:0;z-index:10000;background:#000;' +
            'display:flex;flex-direction:column;touch-action:none;';

        /* ── Viewport (Bild-Bereich) ── */
        var vp = document.createElement('div');
        vp.style.cssText = 'flex:1;position:relative;overflow:hidden;';

        /* ── Bild ── */
        var img = new Image();
        img.draggable = false;
        img.style.cssText = 'position:absolute;transform-origin:0 0;will-change:transform;max-width:none;';

        /* ── State ── */
        var tx = 0, ty = 0, sc = 1, minSc = 0.5;
        var dragging = false, startX = 0, startY = 0;
        var pinchDist0 = 0, pinchSc0 = 1;

        function applyTransform() {
            img.style.transform = 'translate(' + tx + 'px,' + ty + 'px) scale(' + sc + ')';
        }

        img.onload = function () {
            var vw = vp.clientWidth, vh = vp.clientHeight;
            sc = Math.max(vw / img.naturalWidth, vh / img.naturalHeight);
            minSc = sc * 0.5;
            tx = (vw - img.naturalWidth  * sc) / 2;
            ty = (vh - img.naturalHeight * sc) / 2;
            applyTransform();
        };

        /* ── Touch ── */
        vp.addEventListener('touchstart', function (e) {
            e.preventDefault();
            if (e.touches.length === 1) {
                dragging = true;
                startX = e.touches[0].clientX - tx;
                startY = e.touches[0].clientY - ty;
            } else if (e.touches.length === 2) {
                dragging = false;
                pinchDist0 = Math.hypot(
                    e.touches[1].clientX - e.touches[0].clientX,
                    e.touches[1].clientY - e.touches[0].clientY);
                pinchSc0 = sc;
            }
        }, { passive: false });

        vp.addEventListener('touchmove', function (e) {
            e.preventDefault();
            if (dragging && e.touches.length === 1) {
                tx = e.touches[0].clientX - startX;
                ty = e.touches[0].clientY - startY;
                applyTransform();
            } else if (e.touches.length === 2) {
                var dist = Math.hypot(
                    e.touches[1].clientX - e.touches[0].clientX,
                    e.touches[1].clientY - e.touches[0].clientY);
                sc = Math.max(minSc, pinchSc0 * (dist / pinchDist0));
                applyTransform();
            }
        }, { passive: false });

        vp.addEventListener('touchend', function () { dragging = false; });

        /* ── Maus (Desktop-Test) ── */
        vp.addEventListener('mousedown', function (e) {
            dragging = true; startX = e.clientX - tx; startY = e.clientY - ty;
        });
        function onMouseMove(e) {
            if (!dragging) return;
            tx = e.clientX - startX; ty = e.clientY - startY; applyTransform();
        }
        function onMouseUp() { dragging = false; }
        document.addEventListener('mousemove', onMouseMove);
        document.addEventListener('mouseup',   onMouseUp);

        vp.addEventListener('wheel', function (e) {
            e.preventDefault();
            sc = Math.max(minSc, sc * (e.deltaY < 0 ? 1.1 : 0.9));
            applyTransform();
        }, { passive: false });

        vp.appendChild(img);
        img.src = url;

        /* ── Hinweis-Text ── */
        var hint = document.createElement('div');
        hint.textContent = 'Verschieben & Zoomen, dann „Verwenden" tippen';
        hint.style.cssText =
            'position:absolute;bottom:8px;left:0;right:0;text-align:center;' +
            'font-size:0.78rem;color:rgba(255,255,255,0.6);pointer-events:none;';
        vp.appendChild(hint);

        /* ── Buttons ── */
        var btnRow = document.createElement('div');
        btnRow.style.cssText =
            'width:100%;padding:1rem;box-sizing:border-box;' +
            'display:flex;gap:0.6rem;background:#111;flex-shrink:0;';

        function makeBtn(text, color) {
            var b = document.createElement('button');
            b.textContent = text;
            b.style.cssText =
                'flex:1;height:48px;background:' + color + ';' +
                'border:2px solid #1b1b1b;border-radius:8px;' +
                'font-size:1.1rem;font-family:inherit;cursor:pointer;';
            return b;
        }

        var btnAbbrechen = makeBtn('Abbrechen', '#ffa9a9');
        var btnVerwenden = makeBtn('Verwenden ✓', '#a9ffac');

        function cleanup() {
            document.removeEventListener('mousemove', onMouseMove);
            document.removeEventListener('mouseup',   onMouseUp);
            URL.revokeObjectURL(url);
            if (overlay.parentNode) overlay.parentNode.removeChild(overlay);
        }

        btnAbbrechen.addEventListener('click', cleanup);

        btnVerwenden.addEventListener('click', function () {
            var vw  = vp.clientWidth, vh = vp.clientHeight;
            var dpr = window.devicePixelRatio || 1;
            var canvas = document.createElement('canvas');
            canvas.width  = vw * dpr;
            canvas.height = vh * dpr;
            var ctx = canvas.getContext('2d');
            ctx.scale(dpr, dpr);
            ctx.drawImage(img, tx, ty, img.naturalWidth * sc, img.naturalHeight * sc);
            canvas.toBlob(function (blob) {
                var croppedFile = new File([blob], 'bild.jpg', { type: 'image/jpeg' });
                var previewUrl  = URL.createObjectURL(blob);
                cleanup();
                onDone(croppedFile, previewUrl);
            }, 'image/jpeg', 0.90);
        });

        btnRow.appendChild(btnAbbrechen);
        btnRow.appendChild(btnVerwenden);
        overlay.appendChild(vp);
        overlay.appendChild(btnRow);
        document.body.appendChild(overlay);
    };

}(window));
