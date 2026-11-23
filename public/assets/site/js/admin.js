(function () {
    'use strict';

    var shell = document.querySelector('[data-admin-shell]');
    var openButton = document.querySelector('[data-sidebar-open]');
    var closeButton = document.querySelector('[data-sidebar-close]');
    var backdrop = document.querySelector('[data-sidebar-backdrop]');

    if (!shell || !openButton) return;

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

    window.addEventListener('resize', function () {
        if (window.innerWidth > 1080 && shell.classList.contains('is-sidebar-open')) {
            setSidebar(false, false);
        }
    });

    // Konfirmasi aksi destruktif/perubahan status tanpa atribut inline.
    document.addEventListener('submit', function (event) {
        var form = event.target;
        var message = form.getAttribute && form.getAttribute('data-confirm');

        if (!message && event.submitter && event.submitter.getAttribute) {
            message = event.submitter.getAttribute('data-confirm');
        }

        if (!message && document.activeElement && form.contains(document.activeElement)) {
            message = document.activeElement.getAttribute('data-confirm');
        }

        if (message && !window.confirm(message)) {
            event.preventDefault();
        }
    });
})();
