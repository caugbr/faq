
window.addEventListener('load', () => {
    const tabEl = document.querySelector('.tabs');
    if (tabEl) {
        const tabs = tabEl.querySelectorAll('.tab-links a');
        Array.from(tabs).forEach(tab => {
            tab.addEventListener('click', evt => {
                evt.preventDefault();
                const name = evt.target.getAttribute('data-tab');
                tabEl.setAttribute('data-tab', name);
            });
        });
    }
});