'use strict';

document.documentElement.classList.add('js');
const backToTop = document.querySelector('.back-to-top');
if (backToTop) {
    const updateBackToTop = () => { backToTop.hidden = window.scrollY <= 200; };
    window.addEventListener('scroll', updateBackToTop, { passive: true });
    window.addEventListener('pageshow', updateBackToTop);
    updateBackToTop();
}
const header = document.querySelector('.site-header');
const showToast = (message, kind = 'info', title = '') => {
    const region = document.querySelector('.toast-region');
    if (!region || !message) return;
    if ([...region.children].some(toast => toast.querySelector('.toast-message')?.textContent === message)) return;
    const toast = document.createElement('div');
    toast.className = 'toast';
    toast.dataset.kind = kind;
    toast.setAttribute('role', kind === 'error' ? 'alert' : 'status');
    toast.setAttribute('aria-atomic', 'true');
    const icon = document.createElement('span');
    icon.className = 'toast-icon';
    icon.setAttribute('aria-hidden', 'true');
    const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    svg.setAttribute('viewBox', '0 0 48 48');
    svg.setAttribute('fill', 'none');
    const ring = document.createElementNS(svg.namespaceURI, 'circle');
    ring.setAttribute('cx', '24'); ring.setAttribute('cy', '24'); ring.setAttribute('r', '20');
    ring.setAttribute('stroke', 'currentColor'); ring.setAttribute('stroke-width', '4');
    ring.setAttribute('class', 'toast-icon-ring');
    const mark = document.createElementNS(svg.namespaceURI, 'path');
    mark.setAttribute('d', kind === 'success' ? 'm13 24 7 7 15-15' : kind === 'error' ? 'M24 14v13m0 6v1' : 'M24 22v12m0-20v1');
    mark.setAttribute('stroke', 'currentColor'); mark.setAttribute('stroke-width', '5');
    mark.setAttribute('stroke-linecap', 'round'); mark.setAttribute('stroke-linejoin', 'round');
    svg.append(ring, mark);
    icon.append(svg);
    const content = document.createElement('div');
    content.className = 'toast-content';
    const heading = document.createElement('p');
    heading.className = 'toast-title';
    const actionTitles = [
        [/^Welcome back/i, 'Signed in'], [/^You have been logged out/i, 'Signed out'],
        [/^Account created/i, 'Account created'], [/^Invalid email or password/i, 'Sign-in failed'],
        [/^Payment verified/i, 'Payment verified'], [/^Payment rejected/i, 'Payment rejected'],
        [/^Payment submitted/i, 'Payment submitted'], [/^Cancellation approved/i, 'Cancellation approved'],
        [/^Cancellation rejected/i, 'Cancellation rejected'], [/^Cancellation requested/i, 'Cancellation requested'],
        [/^Seat selected/i, 'Seat selected'], [/^Ticket generated/i, 'Ticket ready'], [/^Booking created/i, 'Booking created'],
    ];
    heading.textContent = title || actionTitles.find(([pattern]) => pattern.test(message))?.[1] || (kind === 'error' ? 'Please check your details' : kind === 'success' ? 'Success' : 'Notification');
    const text = document.createElement('p');
    text.className = 'toast-message';
    text.textContent = message;
    const dismiss = document.createElement('button');
    dismiss.type = 'button';
    dismiss.className = 'toast-dismiss';
    dismiss.setAttribute('aria-label', 'Dismiss notification');
    dismiss.textContent = '×';
    const progress = document.createElement('div');
    progress.className = 'toast-progress';
    progress.setAttribute('aria-hidden', 'true');
    progress.hidden = kind === 'error';
    const progressBar = document.createElement('span');
    progress.append(progressBar);
    const duration = 8000;
    let remaining = duration, startedAt;
    const countdown = kind === 'error' || window.matchMedia('(prefers-reduced-motion: reduce)').matches ? null : progressBar.animate([{ transform: 'scaleX(1)' }, { transform: 'scaleX(0)' }], { duration, fill: 'forwards' });
    countdown?.pause();
    let timer;
    const remove = () => { clearTimeout(timer); countdown?.cancel(); toast.remove(); };
    const pauseTimer = () => {
        countdown?.pause();
        if (!timer) return;
        remaining = Math.max(0, remaining - (performance.now() - startedAt));
        clearTimeout(timer); timer = null;
    };
    const startTimer = () => {
        if (timer || kind === 'error' || toast.matches(':hover') || toast.contains(document.activeElement)) return;
        startedAt = performance.now();
        countdown?.play();
        timer = setTimeout(remove, remaining);
    };
    dismiss.addEventListener('click', remove);
    toast.addEventListener('mouseenter', pauseTimer);
    toast.addEventListener('mouseleave', startTimer);
    toast.addEventListener('focusin', pauseTimer);
    toast.addEventListener('focusout', () => setTimeout(startTimer, 0));
    content.append(heading, text);
    toast.append(icon, content, dismiss, progress);
    region.append(toast);
    startTimer();
};
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
const navigation = document.getElementById(menuButton?.getAttribute('aria-controls') || 'main-navigation');
if (menuButton && navigation) {
    const fullscreenSidebar = navigation.matches('.customer-sidebar, .admin-sidebar');
    let backgroundState = [];
    const closeMenu = () => {
        navigation.classList.remove('is-open');
        menuButton.setAttribute('aria-expanded', 'false');
        menuButton.setAttribute('aria-label', 'Open navigation');
        if (fullscreenSidebar) {
            document.body.classList.remove('navigation-open');
            navigation.removeAttribute('role');
            navigation.removeAttribute('aria-modal');
            backgroundState.forEach(([element, wasInert]) => { element.inert = wasInert; });
            backgroundState = [];
        }
    };
    menuButton.addEventListener('click', () => {
        if (navigation.classList.contains('is-open')) { closeMenu(); return; }
        navigation.classList.add('is-open');
        menuButton.setAttribute('aria-expanded', 'true');
        menuButton.setAttribute('aria-label', 'Close navigation');
        if (fullscreenSidebar && window.matchMedia('(max-width: 800px)').matches) {
            document.body.classList.add('navigation-open');
            navigation.setAttribute('role', 'dialog');
            navigation.setAttribute('aria-modal', 'true');
            backgroundState = [...document.querySelectorAll('.site-header, .app-main, .site-footer, .mobile-quick-nav, .back-to-top, .skip-link, .toast-region')].map(element => [element, element.inert]);
            backgroundState.forEach(([element]) => { element.inert = true; });
            navigation.querySelector('.sidebar-close')?.focus();
        }
    });
    navigation.querySelector('.sidebar-close')?.addEventListener('click', () => { closeMenu(); menuButton.focus(); });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && navigation.classList.contains('is-open')) {
            if (document.querySelector('.confirmation-dialog[open]')) return;
            event.preventDefault();
            closeMenu();
            menuButton.focus();
        }
        if (event.key === 'Tab' && fullscreenSidebar && document.body.classList.contains('navigation-open') && !document.querySelector('.confirmation-dialog[open]')) {
            const controls = [...navigation.querySelectorAll('a, button')].filter(element => element.getClientRects().length && !element.disabled);
            const first = controls[0], last = controls[controls.length - 1];
            if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last?.focus(); }
            else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first?.focus(); }
        }
    });
    document.addEventListener('click', (event) => {
        if (!event.target.closest('.header-inner') && !navigation.contains(event.target)) closeMenu();
    });
    window.matchMedia('(max-width: 800px)').addEventListener('change', () => { closeMenu(); });
}

const confirmationDialog = document.getElementById('confirmation-dialog');
const askConfirmation = (message, label = 'Confirm', discard = false, notice = null) => {
    if (!confirmationDialog?.showModal) {
        if (notice) { window.alert(message); return Promise.resolve(true); }
        return Promise.resolve(window.confirm(message));
    }
    if (confirmationDialog.open) return Promise.resolve(false);
    const confirm = confirmationDialog.querySelector('[data-dialog-confirm]');
    const cancel = confirmationDialog.querySelector('[data-dialog-cancel]');
    confirmationDialog.querySelector('#confirmation-title').textContent = notice ? (notice === 'success' ? 'Success' : 'Please check your details') : (discard ? 'Leave without saving?' : 'Please confirm');
    confirmationDialog.dataset.kind = notice ?? 'confirm';
    confirmationDialog.querySelector('.confirmation-icon').textContent = notice === 'success' ? '✓' : '!';
    confirmationDialog.querySelector('#confirmation-message').textContent = message;
    confirm.textContent = label;
    confirm.classList.toggle('button-danger', discard || /delete|reject|cancel/i.test(label));
    cancel.textContent = discard ? 'Keep editing' : 'Cancel';
    cancel.hidden = !!notice;
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
        if (notice) confirm.focus();
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
        if (pathname === '/logout' || pathname === '/admin/logout') form.dataset.confirm = 'Are you sure you want to log out of SkyReserve?';
        else if (form.classList.contains('account-form') && pathname.startsWith('/admin/') && pathname !== '/admin/login') form.dataset.confirm = 'Save these changes?';
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
        showToast('Please check the highlighted fields before continuing.', 'error');
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
            let message = submitter?.dataset.confirm ?? form.dataset.confirm;
            const logout = pathname === '/logout' || pathname === '/admin/logout';
            if (logout && dirtyForms().length) message += ' Unsaved changes on this page will be discarded.';
            askConfirmation(message, submitter?.textContent.trim() || 'Confirm').then((accepted) => {
                confirmationPending = false;
                if (!accepted || !form.isConnected) return;
                if (logout) leavingPage = true;
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

// Use the existing password controls so names, values, and autocomplete stay intact.
document.querySelectorAll('.page-auth input[type="password"]').forEach((input) => {
    const wrapper = document.createElement('div');
    wrapper.className = 'password-field';
    input.before(wrapper);
    wrapper.append(input);
    const toggle = document.createElement('button');
    toggle.type = 'button';
    toggle.className = 'password-toggle';
    toggle.setAttribute('aria-controls', input.id);
    toggle.setAttribute('aria-label', 'Show ' + (input.id === 'password_confirmation' ? 'confirmation password' : 'password'));
    toggle.setAttribute('aria-pressed', 'false');
    toggle.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/><path class="eye-slash" d="m3 3 18 18"/></svg>';
    toggle.addEventListener('click', () => {
        const visible = input.type === 'password';
        input.type = visible ? 'text' : 'password';
        toggle.setAttribute('aria-pressed', String(visible));
        toggle.setAttribute('aria-label', (visible ? 'Hide ' : 'Show ') + (input.id === 'password_confirmation' ? 'confirmation password' : 'password'));
    });
    wrapper.append(toggle);
});

document.querySelectorAll('[data-toast], .notice.error[role="alert"], .notice.success[role="status"]').forEach(notice => {
    const kind = notice.dataset.toast || (notice.classList.contains('error') ? 'error' : 'success');
    showToast(notice.textContent.trim(), kind);
    notice.dataset.toastShown = 'true';
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
