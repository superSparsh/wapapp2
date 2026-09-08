export function initFreeTemplateBuilder() {
    const typeSelect = document.getElementById('free_type');
    const sectionsRoot = document.getElementById('free-type-sections');
    if (!typeSelect || !sectionsRoot) {
        return;
    }

    const buttonSection = document.getElementById('free-section-button');
    const listSection = document.getElementById('free-section-list');
    const productSection = document.getElementById('free-section-product');
    const flowSection = document.getElementById('free-section-flow');

    const sections = {
        button: buttonSection,
        list: listSection,
        product: productSection,
        flow: flowSection,
    };

    const sync = () => {
        const type = typeSelect.value || 'button';
        Object.entries(sections).forEach(([key, section]) => {
            if (!section) {
                return;
            }
            const show = key === type;
            section.classList.toggle('hidden', !show);
        });
    };

    typeSelect.addEventListener('change', sync);
    sync();

    const listRoot = document.getElementById('free-list-sections');
    const addSectionBtn = document.getElementById('free-add-list-section');
    if (listRoot && addSectionBtn) {
        let sectionCount = listRoot.querySelectorAll('[data-list-section]').length || 1;

        const renderSection = (index, title = '', rows = [{ title: '', description: '' }]) => {
            const rowsHtml = rows
                .map(
                    (row, rowIndex) => `
              <div class="grid grid-cols-1 gap-2 md:grid-cols-2" data-list-row>
                <input name="list_sections[${index}][rows][${rowIndex}][title]" value="${row.title || ''}" placeholder="Row title *" required class="fd-input rounded-xl border border-border p-3">
                <input name="list_sections[${index}][rows][${rowIndex}][description]" value="${row.description || ''}" placeholder="Description" class="fd-input rounded-xl border border-border p-3">
              </div>`,
                )
                .join('');

            return `
            <div class="flex flex-col gap-2 rounded-lg border border-divider p-3" data-list-section>
              <input name="list_sections[${index}][title]" value="${title}" placeholder="Section title *" required class="fd-input rounded-xl border border-border p-3">
              <div data-list-rows class="flex flex-col gap-2">${rowsHtml}</div>
              <button type="button" data-add-list-row class="fd-btn-sm w-fit text-sm text-green-500">+ Add row</button>
            </div>`;
        };

        if (!listRoot.children.length) {
            listRoot.innerHTML = renderSection(0);
        }

        addSectionBtn.addEventListener('click', () => {
            listRoot.insertAdjacentHTML('beforeend', renderSection(sectionCount));
            sectionCount += 1;
        });

        listRoot.addEventListener('click', (event) => {
            const addRow = event.target.closest('[data-add-list-row]');
            if (addRow) {
                const section = addRow.closest('[data-list-section]');
                const sectionIndex = [...listRoot.querySelectorAll('[data-list-section]')].indexOf(section);
                const rowsHost = section?.querySelector('[data-list-rows]');
                const rowIndex = rowsHost?.querySelectorAll('[data-list-row]').length || 0;
                rowsHost?.insertAdjacentHTML(
                    'beforeend',
                    `<div class="grid grid-cols-1 gap-2 md:grid-cols-2" data-list-row>
                      <input name="list_sections[${sectionIndex}][rows][${rowIndex}][title]" placeholder="Row title *" required class="fd-input rounded-xl border border-border p-3">
                      <input name="list_sections[${sectionIndex}][rows][${rowIndex}][description]" placeholder="Description" class="fd-input rounded-xl border border-border p-3">
                    </div>`,
                );
            }
        });
    }
}
