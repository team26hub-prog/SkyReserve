// Passenger details only; native validation, Tab navigation and submission stay intact.
document.querySelectorAll('form[data-passenger-form]').forEach((form) => {
    const cnic = form.querySelector('#cnic');
    const passport = form.querySelector('#passport_number');
    const order = ['#full_name', '#cnic', '#passport_number', '#date_of_birth', '#gender', '#phone', 'button[type="submit"]'].map(selector => form.querySelector(selector));
    const formatCnic = (value) => {
        const digits = value.replace(/[^0-9]/g, '').slice(0, 13);
        return digits.slice(0, 5) + (digits.length > 5 ? '-' + digits.slice(5, 12) : '') + (digits.length > 12 ? '-' + digits.slice(12) : '');
    };
    const completeCnic = () => /^[0-9]{5}-[0-9]{7}-[0-9]$/.test(cnic.value);
    const normalizeCnic = () => {
        const value = cnic.value;
        const digitPosition = value.slice(0, cnic.selectionStart ?? value.length).replace(/[^0-9]/g, '').length;
        cnic.value = formatCnic(value);
        let position = 0, count = 0;
        while (position < cnic.value.length && count < digitPosition) {
            if (/[0-9]/.test(cnic.value[position])) count++;
            position++;
        }
        cnic.setSelectionRange(position, position);
    };
    // Track completeness before each edit, including paste and mobile keyboard input.
    let previouslyComplete;
    normalizeCnic();
    previouslyComplete = completeCnic();
    cnic.addEventListener('input', (event) => {
        if (event.isComposing) return;
        normalizeCnic();
        const complete = completeCnic() && cnic.validity.valid;
        if (!previouslyComplete && complete && document.activeElement === cnic) passport.focus();
        previouslyComplete = complete;
    });
    cnic.addEventListener('compositionend', () => {
        normalizeCnic();
        if (!previouslyComplete && completeCnic() && cnic.validity.valid && document.activeElement === cnic) passport.focus();
        previouslyComplete = completeCnic();
    });
    const normalizePassport = () => {
        const position = passport.selectionStart;
        const value = passport.value;
        const removedPrefix = value.length - value.trimStart().length;
        // Keep invalid characters visible so native pattern validation can reject them.
        passport.value = value.trim().replace(/[a-z]/g, letter => letter.toUpperCase());
        if (position !== null) passport.setSelectionRange(Math.max(0, position - removedPrefix), Math.max(0, position - removedPrefix));
    };
    normalizePassport();
    passport.addEventListener('input', normalizePassport);
    passport.addEventListener('blur', normalizePassport);
    form.addEventListener('keydown', (event) => {
        if (event.key !== 'Enter' || event.isComposing || event.altKey || event.ctrlKey || event.metaKey || event.shiftKey) return;
        const index = order.indexOf(event.target);
        if (index < 0 || index === order.length - 1) return;
        event.preventDefault();
        if (event.target === cnic) normalizeCnic();
        if (event.target === passport) normalizePassport();
        order[index + 1].focus();
    });
});
