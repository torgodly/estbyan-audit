async function exportInsuranceCards(output, personKey) {
    const root = document.getElementById('insurance-cards-print');

    if (! root) {
        throw new Error('Insurance card print root is missing.');
    }

    if (typeof html2media !== 'function') {
        throw new Error('html2media is not loaded.');
    }

    await document.fonts.ready;

    await Promise.all([...root.querySelectorAll('img')].map((image) => {
        if (image.complete) {
            return Promise.resolve();
        }

        return new Promise((resolve) => {
            image.addEventListener('load', resolve, { once: true });
            image.addEventListener('error', resolve, { once: true });
        });
    }));

    const source = root.querySelector('.employee-insurance-cards') ?? root;
    const pack = source.cloneNode(true);

    if (personKey) {
        pack.querySelectorAll('.employee-id-card').forEach((page) => {
            if (page.dataset.cardPerson !== personKey) {
                page.remove();
            }
        });
    }

    if (pack.querySelectorAll('.employee-id-card').length === 0) {
        throw new Error('No insurance cards match the print selection.');
    }

    const holder = document.createElement('div');
    holder.setAttribute('aria-hidden', 'true');
    holder.style.position = 'fixed';
    holder.style.left = '-12000px';
    holder.style.top = '0';
    holder.style.width = '1004px';
    holder.appendChild(pack);
    document.body.appendChild(holder);

    const filename = (root.dataset.filename || 'insurance-cards') + '.pdf';

    try {
        const instance = html2media()
            .from(pack)
            .pageBreakMode('class')
            .selector('.employee-id-card')
            .enableLinks(false)
            .format([1004, 634])
            .orientation('landscape')
            .margins(0)
            .overflow('cut')
            .showPageNumbers(false);

        if (output === 'print') {
            await instance.print();

            return;
        }

        await instance.save(filename);
    } finally {
        holder.remove();
    }
}

window.exportInsuranceCards = exportInsuranceCards;
