'use strict';

document.documentElement.classList.add('js');
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

document.querySelectorAll('form').forEach((form) => {
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
        if (form.dataset.confirm && !window.confirm(form.dataset.confirm)) {
            event.preventDefault();
            return;
        }
        if (event.defaultPrevented) return;
        if (form.dataset.submitting === 'true') {
            event.preventDefault();
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
    document.querySelectorAll('form[data-submitting]').forEach((form) => delete form.dataset.submitting);
    document.querySelectorAll('button[data-original-text]').forEach((button) => {
        button.textContent = button.dataset.originalText;
        delete button.dataset.originalText;
        button.removeAttribute('aria-busy');
        button.removeAttribute('aria-disabled');
    });
});
