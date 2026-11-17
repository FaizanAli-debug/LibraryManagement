(function () {
    'use strict';

    var body = document.body;
    var menuToggle = document.querySelector('[data-menu-toggle]');
    var sidebar = document.getElementById('staffSidebar');
    var overlay = document.querySelector('[data-sidebar-overlay]');

    function closeMenu() {
        body.classList.remove('sidebar-open');

        if (menuToggle) {
            menuToggle.setAttribute('aria-expanded', 'false');
        }
    }

    if (menuToggle && sidebar) {
        menuToggle.addEventListener('click', function () {
            var isOpen = body.classList.toggle('sidebar-open');
            menuToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        });
    }

    if (overlay) {
        overlay.addEventListener('click', closeMenu);
    }

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            closeMenu();
        }
    });

    if (sidebar) {
        sidebar.querySelectorAll('a').forEach(function (link) {
            link.addEventListener('click', closeMenu);
        });
    }

    document.querySelectorAll('[data-toggle-password]').forEach(function (button) {
        button.addEventListener('click', function () {
            var target = document.querySelector(button.getAttribute('data-toggle-password'));

            if (!target) {
                return;
            }

            var showPassword = target.type === 'password';
            target.type = showPassword ? 'text' : 'password';
            button.textContent = showPassword ? 'Hide' : 'Show';
            button.setAttribute('aria-pressed', showPassword ? 'true' : 'false');
        });
    });

    document.querySelectorAll('form[data-confirm]').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            var message = form.getAttribute('data-confirm');

            if (message && !window.confirm(message)) {
                event.preventDefault();
            }
        });
    });

    document.querySelectorAll('[data-print-report]').forEach(function (button) {
        button.addEventListener('click', function () {
            window.print();
        });
    });
})();
