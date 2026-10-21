const form = document.getElementById('municipality-send-form');
if (form) {
    const options = JSON.parse(document.getElementById('municipality-send-options').textContent);
    const dialog = document.getElementById('municipality-confirmation');
    document.getElementById('send-to-municipality').addEventListener('click', () => {
        const recipient = document.getElementById('agency_id');
        if (!recipient.reportValidity()) return;
        const photo = document.getElementById('include_photo')?.checked
            ?? (form.querySelector('input[name=include_photo]').value === '1');
        const preview = options[recipient.value]?.[photo ? '1' : '0'];
        if (!preview) { alert('The selected approved photo or recipient is unavailable. Review the selection again.'); return; }
        document.getElementById('send-review-hash').value = preview.hash;
        document.getElementById('send-recipient-preview').textContent = preview.recipient;
        document.getElementById('send-message-preview').textContent = preview.text;
        document.getElementById('send-photo-preview').textContent = photo ? 'Approved supporting photo will be attached.' : 'No photo attachment.';
        form.querySelector('[name=approved]').checked = false;
        dialog.showModal();
        const heading = document.getElementById('municipality-confirm-heading');
        heading.tabIndex = -1;
        heading.focus({ preventScroll: true });
        dialog.scrollTop = 0;
    });
    document.getElementById('cancel-municipality-send').addEventListener('click', () => dialog.close());
}
document.querySelectorAll('form[method="POST"]').forEach((form) => {
    if (!/\/referral$|\/delivery\/[0-9]+\/retry$/.test(new URL(form.action).pathname)) return;
    form.addEventListener('submit', (event) => {
        if (form.dataset.submitting) { event.preventDefault(); return; }
        if (form.dataset.confirmSend && !confirm(form.dataset.confirmSend)) { event.preventDefault(); return; }
        form.dataset.submitting = '1';
        form.setAttribute('aria-busy', 'true');
        form.querySelectorAll('button').forEach((button) => { button.disabled = true; });
        const submit = form.querySelector('button[type=submit]') || form.querySelector('button:not([type])');
        if (submit) submit.textContent = 'Queueing approved referral...';
    });
});
const delivery = document.querySelector('[data-delivery-poll]');
if (delivery?.dataset.deliveryApproved === '1' && ['queued','preparing','sending'].includes(delivery.dataset.deliveryState)) {
    let polls = 0;
    const poll = async () => {
        if (document.hidden) { setTimeout(poll, 5000); return; }
        try {
            const response = await fetch(delivery.dataset.deliveryPoll, { headers: { Accept: 'application/json' }, cache: 'no-store' });
            if (!response.ok) return;
            const data = await response.json();
            delivery.textContent = data.label;
            if (['accepted','failed','uncertain'].includes(data.status)) { location.reload(); return; }
        } catch { /* A manual reload still shows the authoritative delivery history. */ }
        if (++polls < 300) setTimeout(poll, 3000);
    };
    setTimeout(poll, 3000);
}
window.addEventListener('pageshow', (event) => {
    if (event.persisted && document.querySelector('form[data-submitting]')) location.reload();
});
