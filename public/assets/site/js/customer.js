(function () {
    'use strict';

    var shell = document.querySelector('[data-customer-shell]');
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
})();
