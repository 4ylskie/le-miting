document.querySelectorAll('[data-menu-item-remove-dialog]').forEach((dialog) => {
    const form = dialog.closest('form');
    const opener = form.querySelector('[data-menu-item-remove-open]');
    const cancel = dialog.querySelector('[data-menu-item-remove-cancel]');

    opener.addEventListener('click', () => dialog.showModal());
    cancel.addEventListener('click', () => dialog.close());
    dialog.addEventListener('close', () => opener.focus());
});

document.querySelectorAll('form[data-confirm-submit]').forEach((form) => {
    form.addEventListener('submit', (event) => {
        if (!window.confirm(form.dataset.confirmSubmit)) {
            event.preventDefault();
        }
    });
});
