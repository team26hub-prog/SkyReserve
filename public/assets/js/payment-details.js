document.querySelectorAll('[data-copy-payment-account]').forEach(button => {
    const section = button.closest('.manual-payment-details');
    const account = section.querySelector('#manual-payment-account');
    const status = section.querySelector('.payment-copy-status');
    button.hidden = false;
    button.addEventListener('click', async () => {
        try {
            await navigator.clipboard.writeText(account.textContent.trim());
            status.textContent = 'Account / IBAN copied to clipboard.';
            button.textContent = 'Copied';
            setTimeout(() => { button.textContent = 'Copy'; }, 2000);
        } catch {
            const selection = window.getSelection();
            const range = document.createRange();
            range.selectNodeContents(account);
            selection.removeAllRanges();
            selection.addRange(range);
            status.classList.remove('sr-only');
            status.textContent = 'Automatic copying is unavailable. The account / IBAN is selected; copy it using your device’s copy command.';
        }
    });
});
