(function () {
    'use strict';

    function initNav() {
        var nav = document.querySelector('.site-nav');
        var toggle = document.querySelector('[data-nav-toggle]');
        var navLinks = nav ? nav.querySelector('.nav-links') : null;
        if (!nav || !toggle) return;

        function setOpen(open) {
            nav.classList.toggle('is-open', open);
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            toggle.setAttribute('aria-label', open ? 'Tutup menu navigasi' : 'Buka menu navigasi');
            document.body.classList.toggle('nav-open', open);
        }

        toggle.setAttribute('aria-expanded', 'false');
        toggle.addEventListener('click', function () {
            setOpen(!nav.classList.contains('is-open'));
        });

        nav.addEventListener('click', function (event) {
            if (event.target.closest('a') && nav.classList.contains('is-open')) {
                setOpen(false);
            }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && nav.classList.contains('is-open')) {
                setOpen(false);
                toggle.focus();
            }
        });

        window.addEventListener('resize', function () {
            if (window.innerWidth > 820 && nav.classList.contains('is-open')) {
                setOpen(false);
            }
        });
    }

    function initProfileMenus() {
        var menus = Array.prototype.slice.call(document.querySelectorAll('[data-profile-menu]'));
        if (!menus.length) return;

        function items(menu) {
            return Array.prototype.slice.call(menu.querySelectorAll('[data-profile-panel] a, [data-profile-panel] button'));
        }

        function closeMenu(menu, restoreFocus) {
            var button = menu.querySelector('[data-profile-toggle]');
            var panel = menu.querySelector('[data-profile-panel]');
            if (!button || !panel || panel.hidden) return;
            panel.hidden = true;
            menu.classList.remove('is-open');
            button.setAttribute('aria-expanded', 'false');
            if (restoreFocus) button.focus();
        }

        window.closeProfileMenus = function () {
            menus.forEach(function (menu) { closeMenu(menu, false); });
        };

        menus.forEach(function (menu) {
            var button = menu.querySelector('[data-profile-toggle]');
            var panel = menu.querySelector('[data-profile-panel]');
            if (!button || !panel) return;

            button.addEventListener('click', function (event) {
                event.stopPropagation();
                var willOpen = panel.hidden;
                window.closeProfileMenus();
                if (!willOpen) return;

                panel.hidden = false;
                menu.classList.add('is-open');
                button.setAttribute('aria-expanded', 'true');
                var links = items(menu);
                if (links.length) links[0].focus();
            });

            panel.addEventListener('click', function (event) {
                if (event.target.closest('a, button')) closeMenu(menu, false);
            });

            menu.addEventListener('keydown', function (event) {
                var links = items(menu);
                if (!links.length) return;
                var current = links.indexOf(document.activeElement);

                if (event.key === 'Escape') {
                    closeMenu(menu, true);
                    return;
                }
                if (event.key === 'ArrowDown') {
                    event.preventDefault();
                    links[(current + 1) % links.length].focus();
                }
                if (event.key === 'ArrowUp') {
                    event.preventDefault();
                    links[(current - 1 + links.length) % links.length].focus();
                }
            });
        });

        document.addEventListener('click', function (event) {
            menus.forEach(function (menu) {
                if (!menu.contains(event.target)) closeMenu(menu, false);
            });
        });
    }

    function showToast(message) {
        var toast = document.querySelector('[data-toast]');
        if (!toast) {
            toast = document.createElement('div');
            toast.className = 'toast';
            toast.setAttribute('data-toast', '');
            toast.setAttribute('role', 'status');
            toast.setAttribute('aria-live', 'polite');
            document.body.appendChild(toast);
        }
        toast.textContent = message;
        toast.classList.add('is-visible');
        window.clearTimeout(showToast.timer);
        showToast.timer = window.setTimeout(function () {
            toast.classList.remove('is-visible');
        }, 2200);
    }

    window.showToast = showToast;

    function initCopyButtons() {
        document.addEventListener('click', function (event) {
            var button = event.target.closest('[data-copy-link]');
            if (!button) return;

            var value = button.getAttribute('data-copy-link') || '';
            if (!navigator.clipboard) {
                window.prompt('Salin tautan undangan:', value);
                return;
            }

            navigator.clipboard.writeText(value).then(function () {
                showToast('Tautan berhasil disalin.');
            }).catch(function () {
                window.prompt('Salin tautan undangan:', value);
            });
        });
    }

    function initCatalogFilters() {
        var search = document.querySelector('[data-template-search]');
        var cards = Array.prototype.slice.call(document.querySelectorAll('[data-template-card]'));
        if (!search || !cards.length) return;

        var filterButtons = Array.prototype.slice.call(document.querySelectorAll('[data-category-filter]'));
        var activeCategory = 'all';
        var empty = document.querySelector('[data-template-empty]');

        function apply() {
            var query = search.value.trim().toLowerCase();
            var visible = 0;

            cards.forEach(function (card) {
                var haystack = (card.dataset.templateSearch || '').toLowerCase();
                var category = card.dataset.templateCategory || '';
                var matchesQuery = !query || haystack.indexOf(query) !== -1;
                var matchesCategory = activeCategory === 'all' || category === activeCategory;
                var show = matchesQuery && matchesCategory;
                card.hidden = !show;
                if (show) visible += 1;
            });

            if (empty) empty.hidden = visible !== 0;
        }

        search.addEventListener('input', apply);
        filterButtons.forEach(function (button) {
            button.addEventListener('click', function () {
                activeCategory = button.getAttribute('data-category-filter') || 'all';
                filterButtons.forEach(function (item) { item.classList.remove('is-active'); });
                button.classList.add('is-active');
                apply();
            });
        });
    }

    function initFaq() {
        document.querySelectorAll('.faq-item').forEach(function (item, index) {
            var summary = item.querySelector('summary');
            if (!summary) return;
            summary.id = summary.id || 'faq-summary-' + index;
            summary.setAttribute('aria-controls', 'faq-panel-' + index);
            var panel = document.createElement('div');
            while (item.childNodes.length > 1) panel.appendChild(item.childNodes[1]);
            panel.id = 'faq-panel-' + index;
            panel.setAttribute('role', 'region');
            panel.setAttribute('aria-labelledby', summary.id);
            item.appendChild(panel);
        });
    }

    function initReveal() {
        if (!('IntersectionObserver' in window)) return;
        var observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    entry.target.classList.add('reveal');
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.08 });

        document.querySelectorAll('[data-reveal]').forEach(function (element) { observer.observe(element); });
    }

    document.addEventListener('DOMContentLoaded', function () {
        initNav();
        initProfileMenus();
        initCopyButtons();
        initCatalogFilters();
        initFaq();
        initReveal();
    });
})();
