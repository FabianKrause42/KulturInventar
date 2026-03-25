/**
 * KulturInventar – Bild-Zuschnitt
 *
 * Modernes Ausschnitt-Werkzeug: Das Bild steht fest (passt sich an den
 * Bildschirm an). Ein Ausschnitt-Rahmen liegt darüber. Die vier Kanten und
 * vier Ecken sind per Finger oder Maus verschiebbar. Freies Seitenverhältnis.
 *
 * API:  window.zeigeCropOverlay(file, callback)
 *       callback(croppedFile, previewUrl)
 */
(function (win) {
    'use strict';

    /* ─── Konstanten ─────────────────────────────────────── */
    var HANDLE_SIZE   = 44;              // px Touch-Trefferbereich
    var MIN_CROP      = 40;              // px Mindestgröße
    var DIM_COLOR     = 'rgba(0,0,0,0.55)';

    /* ─── Hilfsfunktionen ────────────────────────────────── */
    function clamp(v, lo, hi) { return v < lo ? lo : v > hi ? hi : v; }

    function getEventPos(e) {
        if (e.touches && e.touches.length) {
            return { x: e.touches[0].clientX, y: e.touches[0].clientY };
        }
        return { x: e.clientX, y: e.clientY };
    }

    /* ─── Haupt-Funktion ─────────────────────────────────── */
    win.zeigeCropOverlay = function (file, onDone) {
        var objUrl = URL.createObjectURL(file);

        /* ── Fullscreen-Overlay ── */
        var overlay = document.createElement('div');
        overlay.style.cssText =
            'position:fixed;inset:0;z-index:10000;background:#111;' +
            'display:flex;flex-direction:column;touch-action:none;font-family:inherit;';

        /* ── Bild-Container ── */
        var imgWrap = document.createElement('div');
        imgWrap.style.cssText = 'flex:1;position:relative;overflow:hidden;';

        /* ── Bild (object-fit:contain = statisch, füllt den Bereich) ── */
        var img = new Image();
        img.draggable = false;
        img.style.cssText =
            'position:absolute;top:0;left:0;width:100%;height:100%;' +
            'object-fit:contain;pointer-events:none;user-select:none;';
        imgWrap.appendChild(img);

        /* ── Abdunkelungs-Canvas ── */
        var dimCvs = document.createElement('canvas');
        dimCvs.style.cssText = 'position:absolute;inset:0;pointer-events:none;';
        imgWrap.appendChild(dimCvs);

        /* ── Crop-Rahmen ── */
        var frame = document.createElement('div');
        frame.style.cssText =
            'position:absolute;box-sizing:border-box;border:2px solid #fff;' +
            'pointer-events:none;';
        imgWrap.appendChild(frame);

        /* ── Handles: Ecken (tl, tr, br, bl) + Kanten (t, r, b, l) ── */
        var HANDLES = ['tl','t','tr','r','br','b','bl','l'];
        var handles = {};

        HANDLES.forEach(function (type) {
            var h       = document.createElement('div');
            var half    = (HANDLE_SIZE / 2) + 'px';
            var isCorner = type.length === 2;

            h.dataset.handle = type;
            h.style.cssText  = 'position:absolute;box-sizing:border-box;touch-action:none;';

            if (isCorner) {
                h.style.width  = HANDLE_SIZE + 'px';
                h.style.height = HANDLE_SIZE + 'px';
            } else if (type === 't' || type === 'b') {
                h.style.height = HANDLE_SIZE + 'px';
                h.style.left   = HANDLE_SIZE + 'px';
                h.style.right  = HANDLE_SIZE + 'px';
            } else {
                h.style.width  = HANDLE_SIZE + 'px';
                h.style.top    = HANDLE_SIZE + 'px';
                h.style.bottom = HANDLE_SIZE + 'px';
            }

            // Positionierung
            if (type === 'tl' || type === 't'  || type === 'tr') h.style.top    = '-' + half;
            if (type === 'bl' || type === 'b'  || type === 'br') h.style.bottom = '-' + half;
            if (type === 'tl' || type === 'l'  || type === 'bl') h.style.left   = '-' + half;
            if (type === 'tr' || type === 'r'  || type === 'br') h.style.right  = '-' + half;

            // Cursor
            h.style.cursor = ({
                tl:'nwse-resize', t:'ns-resize', tr:'nesw-resize',
                r:'ew-resize', br:'nwse-resize', b:'ns-resize',
                bl:'nesw-resize', l:'ew-resize'
            })[type];

            // Sichtbares Element: Ecke = Quadrat, Kante = Balken
            var vis = document.createElement('div');
            if (isCorner) {
                vis.style.cssText =
                    'position:absolute;width:20px;height:20px;background:#fff;border-radius:3px;' +
                    (type.includes('t') ? 'top:'    + ((HANDLE_SIZE-20)/2) + 'px;' : 'bottom:' + ((HANDLE_SIZE-20)/2) + 'px;') +
                    (type.includes('l') ? 'left:'   + ((HANDLE_SIZE-20)/2) + 'px;' : 'right:'  + ((HANDLE_SIZE-20)/2) + 'px;');
            } else if (type === 't' || type === 'b') {
                vis.style.cssText =
                    'position:absolute;left:50%;transform:translateX(-50%);width:40px;height:4px;' +
                    'background:#fff;border-radius:2px;top:' + ((HANDLE_SIZE-4)/2) + 'px;';
            } else {
                vis.style.cssText =
                    'position:absolute;top:50%;transform:translateY(-50%);height:40px;width:4px;' +
                    'background:#fff;border-radius:2px;left:' + ((HANDLE_SIZE-4)/2) + 'px;';
            }
            h.appendChild(vis);
            frame.appendChild(h);
            handles[type] = h;
        });

        /* ── State ── */
        var cropX, cropY, cropW, cropH;
        var dispX, dispY, dispW, dispH;   // Bildposition im Container (px)

        function drawDim() {
            var ctx = dimCvs.getContext('2d');
            var W = dimCvs.width, H = dimCvs.height;
            ctx.clearRect(0, 0, W, H);
            ctx.fillStyle = DIM_COLOR;
            ctx.fillRect(0, 0, W, cropY);                             // oben
            ctx.fillRect(0, cropY + cropH, W, H - cropY - cropH);    // unten
            ctx.fillRect(0, cropY, cropX, cropH);                     // links
            ctx.fillRect(cropX + cropW, cropY, W - cropX - cropW, cropH); // rechts
        }

        function applyFrame() {
            frame.style.left   = cropX + 'px';
            frame.style.top    = cropY + 'px';
            frame.style.width  = cropW + 'px';
            frame.style.height = cropH + 'px';
            drawDim();
        }

        function initCrop() {
            var cW = imgWrap.clientWidth, cH = imgWrap.clientHeight;
            dimCvs.width  = cW;
            dimCvs.height = cH;

            // Bild-Position via object-fit:contain berechnen
            var nw = img.naturalWidth, nh = img.naturalHeight;
            var sc = Math.min(cW / nw, cH / nh);
            dispW  = nw * sc;  dispH  = nh * sc;
            dispX  = (cW - dispW) / 2;
            dispY  = (cH - dispH) / 2;

            // Anfangs-Ausschnitt: 80 % des Bildes, zentriert
            var pad = 0.10;
            cropW = Math.round(dispW * (1 - 2 * pad));
            cropH = Math.round(dispH * (1 - 2 * pad));
            cropX = Math.round(dispX + dispW * pad);
            cropY = Math.round(dispY + dispH * pad);
            applyFrame();
        }

        img.onload = function () { requestAnimationFrame(initCrop); };
        img.src = objUrl;

        /* ── Drag ── */
        var activeType  = null;
        var dragStart   = null;
        var cropSnap    = null;

        function startDrag(type, e) {
            e.preventDefault(); e.stopPropagation();
            activeType = type;
            dragStart  = getEventPos(e);
            cropSnap   = { x: cropX, y: cropY, w: cropW, h: cropH };
        }

        function onMove(e) {
            if (!activeType) return;
            e.preventDefault();
            var p  = getEventPos(e);
            var dx = p.x - dragStart.x, dy = p.y - dragStart.y;
            var s  = cropSnap;
            var nx = s.x, ny = s.y, nw = s.w, nh = s.h;
            var maxX = dispX + dispW, maxY = dispY + dispH;

            if (activeType.includes('l')) {
                var l2 = clamp(s.x + dx, dispX, s.x + s.w - MIN_CROP);
                nw = s.x + s.w - l2; nx = l2;
            }
            if (activeType.includes('r')) {
                nw = clamp(s.w + dx, MIN_CROP, maxX - s.x);
            }
            if (activeType.includes('t')) {
                var t2 = clamp(s.y + dy, dispY, s.y + s.h - MIN_CROP);
                nh = s.y + s.h - t2; ny = t2;
            }
            if (activeType.includes('b')) {
                nh = clamp(s.h + dy, MIN_CROP, maxY - s.y);
            }

            cropX = nx; cropY = ny; cropW = nw; cropH = nh;
            applyFrame();
        }

        function endDrag() { activeType = null; }

        HANDLES.forEach(function (type) {
            handles[type].addEventListener('touchstart', function (e) { startDrag(type, e); }, { passive: false });
            handles[type].addEventListener('mousedown',  function (e) { startDrag(type, e); });
        });
        document.addEventListener('touchmove',  onMove,   { passive: false });
        document.addEventListener('mousemove',  onMove);
        document.addEventListener('touchend',   endDrag);
        document.addEventListener('mouseup',    endDrag);

        /* ── Button-Leiste ── */
        var btnRow = document.createElement('div');
        btnRow.style.cssText =
            'flex-shrink:0;background:#1a1a1a;' +
            'padding:0.75rem var(--spacing, 1rem) 0.75rem;box-sizing:border-box;';

        var hint = document.createElement('div');
        hint.textContent = 'Ecken & Kanten ziehen zum Zuschneiden';
        hint.style.cssText =
            'text-align:center;font-size:0.75rem;color:rgba(255,255,255,0.45);' +
            'margin-bottom:0.55rem;';
        btnRow.appendChild(hint);

        var btnWrap = document.createElement('div');
        btnWrap.style.cssText = 'display:flex;gap:0.6rem;';
        btnRow.appendChild(btnWrap);

        function makeBtn(label, bg) {
            var b = document.createElement('button');
            b.textContent = label;
            b.style.cssText =
                'flex:1;height:48px;border:none;border-radius:8px;cursor:pointer;' +
                'font-size:1.1rem;font-weight:500;font-family:inherit;background:' + bg + ';';
            return b;
        }

        var btnAbbrechen = makeBtn('Abbrechen', '#ffa9a9');
        var btnVerwenden = makeBtn('Zuschneiden ✓', '#a9ffac');
        btnWrap.appendChild(btnAbbrechen);
        btnWrap.appendChild(btnVerwenden);

        /* ── Cleanup ── */
        function cleanup() {
            document.removeEventListener('touchmove',  onMove);
            document.removeEventListener('mousemove',  onMove);
            document.removeEventListener('touchend',   endDrag);
            document.removeEventListener('mouseup',    endDrag);
            URL.revokeObjectURL(objUrl);
            if (overlay.parentNode) overlay.parentNode.removeChild(overlay);
        }

        btnAbbrechen.addEventListener('click', cleanup);

        btnVerwenden.addEventListener('click', function () {
            // Ausschnitt-Koordinaten im Originalbild berechnen
            var sc   = dispW / img.naturalWidth;
            var srcX = (cropX - dispX) / sc;
            var srcY = (cropY - dispY) / sc;
            var srcW = cropW / sc;
            var srcH = cropH / sc;

            var canvas = document.createElement('canvas');
            canvas.width  = Math.round(srcW);
            canvas.height = Math.round(srcH);
            canvas.getContext('2d').drawImage(
                img, srcX, srcY, srcW, srcH, 0, 0, canvas.width, canvas.height
            );
            canvas.toBlob(function (blob) {
                var croppedFile = new File([blob], 'bild.jpg', { type: 'image/jpeg' });
                var previewUrl  = URL.createObjectURL(blob);
                cleanup();
                onDone(croppedFile, previewUrl);
            }, 'image/jpeg', 0.92);
        });

        overlay.appendChild(imgWrap);
        overlay.appendChild(btnRow);
        document.body.appendChild(overlay);
    };

}(window));
