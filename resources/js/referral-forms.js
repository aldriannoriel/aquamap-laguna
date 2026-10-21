document.querySelectorAll('form[method="POST"]').forEach((form) => {
    if (!/\/(referral|referrals\/(test|[0-9]+\/retry))$/.test(new URL(form.action).pathname)) return;
    form.addEventListener('submit', (event) => {
        if (form.dataset.submitting) { event.preventDefault(); return; }
        form.dataset.submitting = '1';
        form.setAttribute('aria-busy', 'true');
        form.querySelectorAll('button[type="submit"], button:not([type])').forEach((button) => {
            button.disabled = true;
            button.textContent = 'Processing approval...';
        });
    });
});
window.addEventListener('pageshow', (event) => {
    if (event.persisted && document.querySelector('form[data-submitting]')) location.reload();
});
