'use strict';
document.querySelectorAll('.compact-status-filter').forEach(filter => {
    const control = filter.querySelector('summary');
    const syncExpanded = () => control.setAttribute('aria-expanded', String(filter.open));
    syncExpanded();
    filter.addEventListener('toggle', syncExpanded);
    filter.addEventListener('keydown', event => {
        if (event.key === 'Escape' && filter.open) {
            event.preventDefault();
            event.stopPropagation();
            filter.open = false;
            control.focus();
        }
    });
    document.addEventListener('click', event => {
        if (!filter.contains(event.target)) filter.open = false;
    });
    const mobile = matchMedia('(max-width: 600px)');
    mobile.addEventListener('change', () => { filter.open = false; });
});
