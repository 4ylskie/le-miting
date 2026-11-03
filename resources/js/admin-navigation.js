const shell = document.querySelector('[data-admin-shell]');

if (shell) {
    const sidebar = document.getElementById('admin-sidebar');
    const navigation = document.getElementById('admin-navigation');
    const dialog = document.getElementById('admin-navigation-dialog');
    const opener = shell.querySelector('[data-admin-open]');
    const closer = shell.querySelector('[data-admin-close]');
    const desktop = window.matchMedia('(min-width: 1024px)');

    const syncNavigation = () => {
        if (dialog.open) {
            dialog.close();
        }
        (desktop.matches ? sidebar : dialog).append(navigation);
        sidebar.hidden = !desktop.matches;
        opener.hidden = desktop.matches;
        closer.hidden = desktop.matches;
        opener.setAttribute('aria-expanded', 'false');
        document.body.style.overflow = '';
    };

    opener.addEventListener('click', () => {
        dialog.showModal();
        opener.setAttribute('aria-expanded', 'true');
        document.body.style.overflow = 'hidden';
        closer.focus();
    });

    closer.addEventListener('click', () => dialog.close());
    dialog.addEventListener('keydown', (event) => {
        if (event.key !== 'Tab') {
            return;
        }
        const controls = [...dialog.querySelectorAll('a[href], button:not(:disabled)')];
        const first = controls[0];
        const last = controls.at(-1);
        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    });
    dialog.addEventListener('close', () => {
        opener.setAttribute('aria-expanded', 'false');
        document.body.style.overflow = '';
        if (!desktop.matches) {
            opener.focus();
        }
    });
    dialog.addEventListener('click', (event) => {
        const bounds = dialog.getBoundingClientRect();
        if (event.target === dialog && (event.clientX > bounds.right || event.clientX < bounds.left
            || event.clientY < bounds.top || event.clientY > bounds.bottom)) {
            dialog.close();
        }
    });

    desktop.addEventListener('change', syncNavigation);
    syncNavigation();
}
