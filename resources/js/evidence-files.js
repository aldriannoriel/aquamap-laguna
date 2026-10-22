document.querySelectorAll('[data-evidence-input]').forEach((input) => {
    const form = input.form;
    const list = form.querySelector('[data-selected-evidence]');
    const error = form.querySelector('[data-evidence-error]');
    let selected = [];
    let previewUrls = [];
    const releasePreviews = () => {
        previewUrls.forEach(url => URL.revokeObjectURL(url));
        previewUrls = [];
    };
    const render = () => {
        releasePreviews();
        const transfer = new DataTransfer();
        selected.forEach(file => transfer.items.add(file));
        input.files = transfer.files;
        const invalid = selected.length > 5 || selected.some(file => file.size > 5 * 1024 * 1024);
        const message = invalid ? 'Choose up to five files, no larger than 5 MB each. Remove extra or oversized files.' : '';
        input.setCustomValidity(message);
        error.textContent = message;
        error.hidden = !invalid;
        list.replaceChildren();
        selected.forEach((file, index) => {
            const item = document.createElement('li');
            item.className = 'flex min-w-0 items-center gap-3 rounded-lg border border-line p-3';
            if (['image/jpeg','image/png','image/webp'].includes(file.type) && file.size <= 5 * 1024 * 1024) {
                const preview = document.createElement('img');
                const url = URL.createObjectURL(file);
                previewUrls.push(url);
                preview.src = url;
                preview.alt = 'Selected evidence: '+file.name;
                preview.className = 'h-16 w-16 shrink-0 rounded-md object-contain';
                item.append(preview);
            }
            const name = document.createElement('span');
            name.className = 'min-w-0 flex-1 break-words text-sm';
            name.textContent = file.name;
            const remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'secondary-button shrink-0';
            remove.textContent = 'Remove';
            remove.setAttribute('aria-label', 'Remove '+file.name);
            remove.addEventListener('click', () => {
                selected.splice(index, 1);
                render();
                input.focus();
            });
            item.append(name, remove);
            list.append(item);
        });
        input.dispatchEvent(new Event('evidencechanged'));
    };
    input.addEventListener('change', () => {
        for (const file of input.files) {
            if (!selected.some(old => old.name === file.name && old.size === file.size && old.lastModified === file.lastModified)) selected.push(file);
        }
        render();
    });
    form.addEventListener('reset', () => { selected = []; render(); });
    window.addEventListener('pagehide', releasePreviews);
    window.addEventListener('pageshow', event => { if (event.persisted) render(); });
});
