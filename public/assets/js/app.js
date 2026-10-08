'use strict';

document.documentElement.classList.add('js');
const header = document.querySelector('.site-header');
document.querySelectorAll('select').forEach((select) => {
    const sizePicker = () => {
        const rect = select.getBoundingClientRect();
        const space = window.innerHeight - rect.bottom - 12;
        const width = Math.min(rect.width, window.innerWidth - 32);
        select.style.setProperty('--picker-top', `${rect.bottom + 6}px`);
        select.style.setProperty('--picker-left', `${Math.max(16, Math.min(rect.left, window.innerWidth - width - 16))}px`);
        select.style.setProperty('--picker-width', `${width}px`);
        select.style.setProperty('--picker-space', `${Math.max(48, Math.min(360, space))}px`);
    };
    select.addEventListener('pointerdown', sizePicker);
    select.addEventListener('focus', sizePicker);
    select.addEventListener('keydown', sizePicker);
    window.addEventListener('resize', sizePicker);
    window.addEventListener('scroll', sizePicker, {passive: true});
});
// Keep normal GET routes and server-side validation; submit valid filter changes automatically.
document.querySelectorAll('form[data-auto-filter][method="get"]').forEach((form) => {
    let timer;
    form.addEventListener('change', () => {
        clearTimeout(timer);
        timer = setTimeout(() => {
            const valid = [...form.elements].every((field) => !field.willValidate || field.validity.valid);
            if (valid && form.dataset.submitting !== 'true') {
                const submitter = form.querySelector('button[type="submit"]');
                if (submitter) form.requestSubmit(submitter);
                else form.requestSubmit();
            }
        }, 300);
    });
    form.addEventListener('submit', () => clearTimeout(timer));
    window.addEventListener('pagehide', () => clearTimeout(timer));
});
if (header && 'ResizeObserver' in window) {
    new ResizeObserver(() => {
        document.documentElement.style.setProperty('--header-height', `${header.offsetHeight}px`);
    }).observe(header);
}
document.querySelectorAll('[data-print-ticket]').forEach((button) => {
    button.addEventListener('click', () => window.print());
});
const errorNotice = document.querySelector('.notice.error[role="alert"]');
if (errorNotice) {
    errorNotice.setAttribute('tabindex', '-1');
    errorNotice.focus();
}
const menuButton = document.querySelector('.menu-toggle');
const navigation = document.getElementById('main-navigation');
if (menuButton && navigation) {
    const closeMenu = () => {
        navigation.classList.remove('is-open');
        menuButton.setAttribute('aria-expanded', 'false');
    };
    menuButton.addEventListener('click', () => {
        const open = navigation.classList.toggle('is-open');
        menuButton.setAttribute('aria-expanded', String(open));
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && navigation.classList.contains('is-open')) {
            closeMenu();
            menuButton.focus();
        }
    });
    document.addEventListener('click', (event) => {
        if (!event.target.closest('.header-inner')) closeMenu();
    });
}

const confirmationDialog = document.getElementById('confirmation-dialog');
const askConfirmation = (message, label = 'Confirm', discard = false) => {
    if (!confirmationDialog?.showModal) return Promise.resolve(window.confirm(message));
    if (confirmationDialog.open) return Promise.resolve(false);
    const confirm = confirmationDialog.querySelector('[data-dialog-confirm]');
    const cancel = confirmationDialog.querySelector('[data-dialog-cancel]');
    confirmationDialog.querySelector('#confirmation-title').textContent = discard ? 'Leave without saving?' : 'Please confirm';
    confirmationDialog.querySelector('#confirmation-message').textContent = message;
    confirm.textContent = label;
    confirm.classList.toggle('button-danger', discard || /delete|reject|cancel/i.test(label));
    cancel.textContent = discard ? 'Keep editing' : 'Cancel';
    return new Promise((resolve) => {
        const accept = () => confirmationDialog.close('confirmed');
        const decline = () => confirmationDialog.close('cancelled');
        const escape = (event) => { event.preventDefault(); decline(); };
        const close = () => {
            confirm.removeEventListener('click', accept);
            cancel.removeEventListener('click', decline);
            confirmationDialog.removeEventListener('cancel', escape);
            confirmationDialog.removeEventListener('close', close);
            resolve(confirmationDialog.returnValue === 'confirmed');
        };
        confirmationDialog.returnValue = '';
        confirm.addEventListener('click', accept);
        cancel.addEventListener('click', decline);
        confirmationDialog.addEventListener('cancel', escape);
        confirmationDialog.addEventListener('close', close);
        confirmationDialog.showModal();
    });
};

// Give existing return links the same hover, focus, and pressed states as other buttons.
document.querySelectorAll('main a').forEach((link) => {
    if (/^(cancel and return|return to|return home|back to)/i.test(link.textContent.trim())) {
        link.classList.add('button', 'button-secondary', 'return-link');
    }
});
const editableForms = [...document.querySelectorAll('main form[method="post"].account-form')]
    .filter((form) => !['/login', '/admin/login', '/register'].includes(new URL(form.action).pathname));
const initialValues = new Map(editableForms.map((form) => [form, [...new FormData(form)].map(([key, value]) => [key, value instanceof File ? `${value.name}:${value.size}` : value])]));
const dirtyForms = () => editableForms.filter((form) => !form.dataset.submitting && JSON.stringify([...new FormData(form)].map(([key, value]) => [key, value instanceof File ? `${value.name}:${value.size}` : value])) !== JSON.stringify(initialValues.get(form)));
let leavingPage = false;
document.addEventListener('click', async (event) => {
    const link = event.target.closest('a[href]');
    if (!link || event.defaultPrevented || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey || link.target || link.hasAttribute('download')) return;
    const destination = new URL(link.href);
    if (destination.pathname === location.pathname && destination.search === location.search && destination.hash) return;
    if (!dirtyForms().length) return;
    event.preventDefault();
    if (await askConfirmation('Your unsaved changes will be discarded. Do you want to leave this page?', 'Leave page', true)) {
        leavingPage = true;
        location.assign(link.href);
    }
});
window.addEventListener('beforeunload', (event) => {
    if (!leavingPage && dirtyForms().length) { event.preventDefault(); event.returnValue = ''; }
});

document.querySelectorAll('form').forEach((form) => {
    const pathname = new URL(form.action).pathname;
    // A field named "method" (payment method) shadows HTMLFormElement.method.
    if (form.getAttribute('method')?.toLowerCase() === 'post' && !form.dataset.confirm) {
        if (form.classList.contains('account-form') && pathname.startsWith('/admin/') && pathname !== '/admin/login') form.dataset.confirm = 'Save these changes?';
        else if (pathname === '/bookings') form.dataset.confirm = 'Create this booking with the passenger details provided?';
        else if (pathname === '/bookings/seats') form.dataset.confirm = 'Assign the selected seat to this passenger?';
        else if (pathname === '/bookings/payment') form.dataset.confirm = 'Submit these payment details for administrator verification?';
        else if (pathname.endsWith('/bookings/tickets')) form.dataset.confirm = 'Generate tickets for this confirmed booking?';
    }
    let approvedSubmitter;
    let confirmationPending = false;
    let feedback;
    const announce = (message) => {
        if (!feedback) {
            feedback = document.createElement('p');
            feedback.className = 'form-feedback';
            feedback.setAttribute('role', 'status');
            form.append(feedback);
        }
        feedback.textContent = message;
    };
    form.addEventListener('invalid', (event) => {
        event.target.setAttribute('aria-invalid', 'true');
        announce('Please check the highlighted fields before continuing.');
    }, true);
    form.addEventListener('input', (event) => {
        if (event.target.validity?.valid) event.target.removeAttribute('aria-invalid');
        if (![...form.elements].some((field) => field.hasAttribute('aria-invalid')) && feedback) feedback.textContent = '';
    });
    form.addEventListener('submit', (event) => {
        if (confirmationPending || form.dataset.submitting === 'true') {
            event.preventDefault();
            return;
        }
        if (event.defaultPrevented) return;
        const submitter = event.submitter ?? null;
        if (form.dataset.confirm && approvedSubmitter !== submitter) {
            event.preventDefault();
            confirmationPending = true;
            // A button-specific prompt keeps multi-action forms honest about the selected action.
            const message = submitter?.dataset.confirm ?? form.dataset.confirm;
            askConfirmation(message, submitter?.textContent.trim() || 'Confirm').then((accepted) => {
                confirmationPending = false;
                if (!accepted || !form.isConnected) return;
                approvedSubmitter = submitter;
                try { submitter ? form.requestSubmit(submitter) : form.requestSubmit(); }
                finally { approvedSubmitter = undefined; }
            });
            return;
        }
        form.dataset.submitting = 'true';
        const button = event.submitter;
        if (button instanceof HTMLButtonElement) {
            button.dataset.originalText = button.textContent;
            button.setAttribute('aria-busy', 'true');
            button.setAttribute('aria-disabled', 'true');
            button.textContent = 'Please wait…';
        }
        // Keep all successful form controls intact for normal browser submission.
    });
});

window.addEventListener('pageshow', () => {
    leavingPage = false;
    document.querySelectorAll('form[data-submitting]').forEach((form) => delete form.dataset.submitting);
    document.querySelectorAll('button[data-original-text]').forEach((button) => {
        button.textContent = button.dataset.originalText;
        delete button.dataset.originalText;
        button.removeAttribute('aria-busy');
        button.removeAttribute('aria-disabled');
    });
});
