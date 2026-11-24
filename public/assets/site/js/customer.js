(function () {
    'use strict';

    // ---- Sidebar ----
    var shell = document.querySelector('[data-customer-shell]');
    var openButton = document.querySelector('[data-sidebar-open]');
    var closeButton = document.querySelector('[data-sidebar-close]');
    var backdrop = document.querySelector('[data-sidebar-backdrop]');

    if (shell && openButton) {
        function setSidebar(open, restoreFocus) {
            shell.classList.toggle('is-sidebar-open', open);
            openButton.setAttribute('aria-expanded', open ? 'true' : 'false');
            if (backdrop) backdrop.hidden = !open;
            document.body.style.overflow = open ? 'hidden' : '';
            if (restoreFocus) {
                if (open && closeButton) closeButton.focus();
                if (!open) openButton.focus();
            }
        }
        openButton.addEventListener('click', function () { setSidebar(true, true); });
        if (closeButton) closeButton.addEventListener('click', function () { setSidebar(false, true); });
        if (backdrop) backdrop.addEventListener('click', function () { setSidebar(false, true); });
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && shell.classList.contains('is-sidebar-open')) {
                setSidebar(false, true);
            }
        });
    }

    // ---- SweetAlert helpers ----
    function hasSwal() { return typeof Swal !== 'undefined' && Swal && typeof Swal.fire === 'function'; }

    function toast(icon, title, opts) {
        if (!hasSwal()) return Promise.resolve();
        return Swal.fire(Object.assign({
            toast: true,
            position: 'top-end',
            icon: icon,
            title: title,
            showConfirmButton: false,
            timer: icon === 'error' ? 4500 : 2600,
            timerProgressBar: true,
        }, opts || {}));
    }

    function alertModal(icon, title, text, opts) {
        if (!hasSwal()) {
            window.alert((title ? title + '\n' : '') + (text || ''));
            return Promise.resolve({ isConfirmed: true });
        }
        return Swal.fire(Object.assign({
            icon: icon,
            title: title || '',
            text: text || '',
            confirmButtonText: 'OK',
            confirmButtonColor: '#B5935A',
        }, opts || {}));
    }

    function confirmDialog(opts) {
        opts = opts || {};
        if (!hasSwal()) {
            return Promise.resolve({ isConfirmed: window.confirm(opts.text || opts.title || 'Lanjutkan?') });
        }
        return Swal.fire(Object.assign({
            icon: opts.icon || 'question',
            title: opts.title || 'Konfirmasi',
            text: opts.text || '',
            showCancelButton: true,
            confirmButtonText: opts.confirmButtonText || 'Ya, lanjutkan',
            cancelButtonText: opts.cancelButtonText || 'Batal',
            confirmButtonColor: opts.confirmButtonColor || '#B5935A',
            cancelButtonColor: '#6b7280',
            reverseButtons: true,
            focusCancel: true,
        }, opts));
    }

    window.InviteUI = {
        hasSwal: hasSwal,
        toast: toast,
        alert: alertModal,
        confirm: confirmDialog,
        success: function (msg, opts) { return toast('success', msg, opts); },
        error: function (msg, opts) { return toast('error', msg, opts); },
        info: function (msg, opts) { return toast('info', msg, opts); },
    };

    // ---- Session flash to SweetAlert ----
    document.addEventListener('DOMContentLoaded', function () {
        if (!hasSwal()) return;
        [['[data-flash-success]', 'success', 2800], ['[data-flash-error]', 'error', 4500], ['[data-flash-warning]', 'warning', 4000], ['[data-flash-info]', 'info', 3200]].forEach(function (cfg) {
            var el = document.querySelector(cfg[0]);
            if (!el) return;
            var msg = el.getAttribute(cfg[0].slice(1, -1)) || el.textContent.trim();
            if (msg) { toast(cfg[1], msg, { timer: cfg[2] }); el.style.display = 'none'; }
        });
    });

    // ---- Image compression ----
    var DEFAULT_MAX_DIM = 1600;
    var DEFAULT_QUALITY = 0.82;
    var COMPRESS_THRESHOLD = 900 * 1024;

    function loadImageBitmap(file) {
        if (typeof createImageBitmap === 'function') {
            return createImageBitmap(file, { imageOrientation: 'from-image' }).catch(function () {
                return createImageBitmap(file);
            });
        }
        return new Promise(function (resolve, reject) {
            var img = new Image();
            var url = URL.createObjectURL(file);
            img.onload = function () { URL.revokeObjectURL(url); resolve(img); };
            img.onerror = function (e) { URL.revokeObjectURL(url); reject(e); };
            img.src = url;
        });
    }

    function canvasToBlob(canvas, type, quality) {
        return new Promise(function (resolve) {
            canvas.toBlob(function (blob) { resolve(blob); }, type, quality);
        });
    }

    async function compressImage(file, opts) {
        opts = opts || {};
        var maxDim = Math.max(1, parseInt(opts.maxDimension, 10) || DEFAULT_MAX_DIM);
        var quality = typeof opts.quality === 'number' ? opts.quality : DEFAULT_QUALITY;
        if (!file || !/^image\/(jpeg|png|webp)$/i.test(file.type)) return file;

        var bitmap;
        try { bitmap = await loadImageBitmap(file); } catch (e) { return file; }
        var w = bitmap.width || bitmap.naturalWidth;
        var h = bitmap.height || bitmap.naturalHeight;
        if (!w || !h) { if (bitmap.close) bitmap.close(); return file; }

        var needsResize = w > maxDim || h > maxDim;
        var needsCompress = file.size > COMPRESS_THRESHOLD;
        if (!needsResize && !needsCompress) { if (bitmap.close) bitmap.close(); return file; }

        var scale = needsResize ? Math.min(maxDim / w, maxDim / h, 1) : 1;
        var tw = Math.max(1, Math.round(w * scale));
        var th = Math.max(1, Math.round(h * scale));

        var canvas = document.createElement('canvas');
        canvas.width = tw;
        canvas.height = th;
        var ctx = canvas.getContext('2d');
        if (!ctx) { if (bitmap.close) bitmap.close(); return file; }

        var outType = file.type;
        if (outType !== 'image/png' && outType !== 'image/webp') outType = 'image/jpeg';
        if (outType === 'image/jpeg') {
            ctx.fillStyle = '#FFFFFF';
            ctx.fillRect(0, 0, tw, th);
        }
        ctx.drawImage(bitmap, 0, 0, tw, th);
        if (bitmap.close) bitmap.close();

        var blob = await canvasToBlob(canvas, outType, outType === 'image/png' ? undefined : quality);
        if (!blob || (blob.size >= file.size && !needsResize)) return file;

        var name = file.name || ('image.' + (outType === 'image/png' ? 'png' : outType === 'image/webp' ? 'webp' : 'jpg'));
        if (outType === 'image/jpeg' && !/\.jpe?g$/i.test(name)) name = name.replace(/\.[^.]+$/, '') + '.jpg';
        if (outType === 'image/webp' && !/\.webp$/i.test(name)) name = name.replace(/\.[^.]+$/, '') + '.webp';

        try { return new File([blob], name, { type: outType, lastModified: Date.now() }); }
        catch (e) { blob.name = name; blob.lastModified = Date.now(); return blob; }
    }

    async function compressFileInput(input, opts) {
        var files = Array.from(input.files || []);
        if (!files.length) return { files: [], changed: false };
        var changed = false;
        var out = [];
        for (var i = 0; i < files.length; i++) {
            var original = files[i];
            var compressed = await compressImage(original, opts);
            if (compressed !== original) changed = true;
            out.push(compressed);
        }
        if (changed) {
            try {
                var dt = new DataTransfer();
                out.forEach(function (f) { dt.items.add(f); });
                input.files = dt.files;
            } catch (e) { /* fallback: caller can use returned files */ }
        }
        return { files: out, changed: changed, input: input };
    }

    window.InviteCompress = {
        compressImage: compressImage,
        compressFileInput: compressFileInput,
        maxDimension: DEFAULT_MAX_DIM,
    };

    // ---- Generic SweetAlert confirm for forms ----
    document.addEventListener('submit', function (event) {
        var form = event.target;
        if (!form || !form.matches || !form.matches('form[data-swal-confirm]')) return;
        if (form.dataset.swalConfirmed === '1') return;
        event.preventDefault();
        var text = form.getAttribute('data-swal-confirm') || 'Lanjutkan tindakan ini?';
        var title = form.getAttribute('data-swal-title') || 'Konfirmasi';
        var confirmText = form.getAttribute('data-swal-confirm-text') || 'Ya, lanjutkan';
        var icon = form.getAttribute('data-swal-icon') || 'question';
        var danger = form.getAttribute('data-swal-danger') === '1';
        var doSubmit = function () {
            form.dataset.swalConfirmed = '1';
            var btn = form.querySelector('button[type="submit"], button:not([type])');
            if (btn) { btn.disabled = true; btn.classList.add('is-loading'); }
            if (hasSwal()) {
                Swal.fire({ title: 'Memproses...', allowOutsideClick: false, allowEscapeKey: false, didOpen: function () { Swal.showLoading(); } });
            }
            form.submit();
        };
        if (hasSwal()) {
            confirmDialog({
                icon: icon,
                title: title,
                text: text,
                confirmButtonText: confirmText,
                confirmButtonColor: danger ? '#dc2626' : '#B5935A',
            }).then(function (r) { if (r.isConfirmed) doSubmit(); });
        } else {
            if (window.confirm(text)) doSubmit();
        }
    }, true);

    // ---- Generic loading state for non-confirm forms with data-swal-loading ----
    document.addEventListener('submit', function (event) {
        var form = event.target;
        if (!form || !form.matches || !form.matches('form[data-swal-loading]')) return;
        if (form.dataset.swalLoadingShown === '1') return;
        form.dataset.swalLoadingShown = '1';
        var btn = form.querySelector('button[type="submit"], button:not([type])');
        if (btn) { btn.disabled = true; btn.classList.add('is-loading'); }
        if (hasSwal()) {
            var title = form.getAttribute('data-swal-loading') || 'Memproses...';
            Swal.fire({ title: title, text: 'Mohon tunggu sebentar.', allowOutsideClick: false, allowEscapeKey: false, didOpen: function () { Swal.showLoading(); } });
        }
    }, true);
})();