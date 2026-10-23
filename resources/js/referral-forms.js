const form = document.getElementById('municipality-send-form');
document.querySelectorAll('[data-report-follow-up]').forEach((followUp) => {
    if (followUp.querySelector('fieldset[disabled]')) return;
    const status = followUp.elements.status;
    const evidence = followUp.querySelector('[data-evidence-input]');
    const confirmation = followUp.elements.resolution_confirmed;
    const updateRequirements = () => {
        const resolved = status.value === 'resolved';
        const closed = status.value === 'closed';
        const attachmentsAllowed = ['resolved','municipality_update','under_review','in_progress'].includes(status.value);
        evidence.required = resolved;
        evidence.disabled = !attachmentsAllowed;
        confirmation.required = resolved;
        confirmation.disabled = !resolved;
        if (!resolved) confirmation.checked = false;
        followUp.elements.review_note.required = status.value === 'municipality_update' && evidence.files.length === 0;
        followUp.elements.closure_reason.required = closed;
        followUp.querySelector('[data-evidence-fields]').hidden = !attachmentsAllowed;
        followUp.querySelector('[data-evidence-required]').hidden = !resolved;
        followUp.querySelector('[data-resolution-fields]').hidden = !resolved;
        followUp.querySelector('[data-closure-fields]').hidden = !closed;
        followUp.querySelector('[data-status-submit]').textContent = resolved ? 'Confirm resolution' : (closed ? 'Confirm closure' : 'Save follow-up');
    };
    followUp.querySelectorAll('[data-status-choice]').forEach((button) => button.addEventListener('click', () => {
        status.value = button.dataset.statusChoice;
        updateRequirements();
        (status.value === 'closed' ? followUp.elements.closure_reason : followUp.elements.review_note).focus();
    }));
    status.addEventListener('change', updateRequirements);
    evidence.addEventListener('evidencechanged', () => { confirmation.checked = false; updateRequirements(); });
    updateRequirements();
});
if (form) {
    const dialog = document.getElementById('municipality-confirmation');
    const recipientSelect = document.getElementById('agency_id');
    const reason = form.elements.routing_reason;
    const sendButton = document.getElementById('send-to-municipality');
    const updateSendButton = () => {
        sendButton.disabled = form.dataset.smtpReady !== '1' || !recipientSelect.value || !reason.value.trim() || form.dataset.previewing === '1';
    };
    recipientSelect.addEventListener('change', updateSendButton);
    reason.addEventListener('input', updateSendButton);
    reason.addEventListener('change', updateSendButton);
    updateSendButton();
    sendButton.addEventListener('click', async () => {
        const recipient = document.getElementById('agency_id');
        if (!recipient.reportValidity() || !reason.reportValidity()) return;
        const photo = document.getElementById('include_photo')?.checked
            ?? (form.querySelector('input[name=include_photo]').value === '1');
        const error = document.getElementById('send-preview-error');
        const controls = [recipientSelect, reason, document.getElementById('include_photo')].filter(Boolean);
        error.hidden = true;
        form.dataset.previewing = '1';
        updateSendButton();
        try {
            const body = new FormData(form);
            body.set('include_photo', photo ? '1' : '0');
            controls.forEach((control) => { control.disabled = true; });
            const response = await fetch(form.dataset.reviewUrl, { method: 'POST', body, headers: { Accept: 'application/json' }, cache: 'no-store' });
            if (!response.ok) {
                const problem = await response.json().catch(() => null);
                const message = problem?.errors ? Object.values(problem.errors).flat().join(' ') : 'The report, location or recipient may have changed. Reload and review before sending.';
                throw new Error(message);
            }
            const preview = await response.json();
            document.getElementById('incident-municipality').textContent = preview.municipality;
            document.getElementById('send-review-hash').value = preview.hash;
            document.getElementById('send-recipient-preview').textContent = preview.recipient;
            document.getElementById('send-message-preview').textContent = preview.text;
            document.getElementById('send-photo-preview').textContent = photo ? 'Approved supporting photo will be attached.' : 'No photo attachment.';
            dialog.showModal();
            const heading = document.getElementById('municipality-confirm-heading');
            heading.tabIndex = -1;
            heading.focus({ preventScroll: true });
            dialog.scrollTop = 0;
        } catch (problem) {
            error.textContent = problem.message || 'Unable to prepare the referral. Reload and try again. No email was sent.';
            error.hidden = false;
        } finally {
            controls.forEach((control) => { control.disabled = false; });
            delete form.dataset.previewing;
            updateSendButton();
        }
    });
    dialog.addEventListener('close', () => {
        if (!sendButton.disabled) sendButton.focus();
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
        if (submit) submit.textContent = 'Sending...';
    });
});
const delivery = document.querySelector('[data-delivery-poll]');
if (delivery?.dataset.deliveryApproved === '1' && delivery.dataset.deliveryInterrupted !== '1' && ['preparing','sending'].includes(delivery.dataset.deliveryState)) {
    let polls = 0;
    const poll = async () => {
        if (document.hidden) { setTimeout(poll, 5000); return; }
        try {
            const response = await fetch(delivery.dataset.deliveryPoll, { headers: { Accept: 'application/json' }, cache: 'no-store' });
            if (!response.ok) return;
            const data = await response.json();
            delivery.textContent = data.label;
            if (data.interrupted || ['accepted','failed','uncertain','cancelled'].includes(data.status)) { location.reload(); return; }
            } catch { /* A manual reload still shows the authoritative delivery history. */ }
            if (++polls < 300) setTimeout(poll, 3000);
        };
        setTimeout(poll, 3000);
    }
    window.addEventListener('pageshow', (event) => {
        if (event.persisted && document.querySelector('form[data-submitting]')) location.reload();
    });
