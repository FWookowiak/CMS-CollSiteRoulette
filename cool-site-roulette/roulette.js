
document.addEventListener('DOMContentLoaded', function () {
    const button  = document.getElementById('csr-spin');
    const select  = document.getElementById('csr-category');
    const display = document.getElementById('csr-display');
    const link    = document.getElementById('csr-link');

    if (!button) return;

    button.addEventListener('click', function () {
        const category = select.value;
        const sites = csrData.sites.filter(function (site) {
            return category === '' || site.category === category;
        });

        if (sites.length === 0) {
            display.textContent = 'Brak stron w tej kategorii :(';
            return;
        }

        const winner = sites[Math.floor(Math.random() * sites.length)];

        button.disabled = true;
        link.hidden = true;
        let delay = 50;

        function tick() {
            const random = sites[Math.floor(Math.random() * sites.length)];
            display.textContent = random.name;
            delay = delay * 1.15;

            if (delay < 500) {
                setTimeout(tick, delay);
            } else {
                display.textContent = winner.name;
                link.href = winner.url;
                link.hidden = false;
                button.disabled = false;
            }
        }

        tick();
    });
});