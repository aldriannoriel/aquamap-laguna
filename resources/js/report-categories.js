const categorySelect = document.getElementById('water_body_category');
const waterBodySelect = document.getElementById('water_body_id');

if (categorySelect && waterBodySelect) {
    const choices = [...waterBodySelect.querySelectorAll('option[data-category]')];
    const status = document.getElementById('water-category-status');
    categorySelect.required = true;

    function filterWaterBodies() {
        const previousId = waterBodySelect.value;
        const category = categorySelect.value;
        const matching = choices.filter(option => option.dataset.category === category);
        waterBodySelect.replaceChildren(new Option(category ? 'Choose a water body' : 'Choose a category first', ''), ...matching.map(option => option.cloneNode(true)));
        waterBodySelect.value = matching.some(option => option.value === previousId) ? previousId : '';
        waterBodySelect.disabled = !category || matching.length === 0;
        status.textContent = category ? `${matching.length} water ${matching.length === 1 ? 'body is' : 'bodies are'} available in this category.` : 'Choose a category to show its water bodies.';
        waterBodySelect.dispatchEvent(new Event('change', { bubbles: true }));
    }

    categorySelect.addEventListener('change', filterWaterBodies);
    filterWaterBodies();
}
