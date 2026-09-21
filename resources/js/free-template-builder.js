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

    const syncHeaderText = () => {
        const headerType = document.getElementById('free_header_type');
        const wrap = document.getElementById('free-header-text-wrap');
        if (!headerType || !wrap) {
            return;
        }
        wrap.classList.toggle('hidden', headerType.value !== 'text');
    };

    const sync = () => {
        const type = typeSelect.value || 'button';
        Object.entries(sections).forEach(([key, section]) => {
            if (!section) {
                return;
            }
            const show = key === type;
            section.classList.toggle('hidden', !show);
            // Disabled fields are not validated or submitted, so hidden
            // required inputs (list/product/flow) cannot block other types.
            section.querySelectorAll('input, select, textarea').forEach((field) => {
                field.disabled = !show;
            });
        });
        syncHeaderText();
    };

    typeSelect.addEventListener('change', sync);
    document.getElementById('free_header_type')?.addEventListener('change', () => {
        syncHeaderText();
        document.getElementById('header_text')?.dispatchEvent(new Event('input', { bubbles: true }));
    });
    sync();

    const listRoot = document.getElementById('free-list-sections');
    const addSectionBtn = document.getElementById('free-add-list-section');
    if (listRoot && addSectionBtn) {
        let sectionCount = listRoot.querySelectorAll('[data-list-section]').length || 1;

        const renderSection = (index, title = '', rows = [{ title: '', description: '' }]) => {
            const rowsHtml = rows
                .map(
                    (row, rowIndex) => `
              <div class="grid grid-cols-1 gap-2 md:grid-cols-[1fr_1fr_auto]" data-list-row>
                <input name="list_sections[${index}][rows][${rowIndex}][title]" value="${row.title || ''}" placeholder="Row title *" required class="fd-input rounded-xl border border-border p-3">
                <input name="list_sections[${index}][rows][${rowIndex}][description]" value="${row.description || ''}" placeholder="Description" class="fd-input rounded-xl border border-border p-3">
                <button type="button" data-remove-list-row class="text-sm text-red-500">Remove</button>
              </div>`,
                )
                .join('');

            return `
            <div class="flex flex-col gap-2 rounded-lg border border-divider p-3" data-list-section>
              <input name="list_sections[${index}][title]" value="${title}" placeholder="Section title *" required class="fd-input rounded-xl border border-border p-3">
              <div data-list-rows class="flex flex-col gap-2">${rowsHtml}</div>
              <div class="flex gap-3">
                <button type="button" data-add-list-row class="fd-btn-sm w-fit text-sm text-green-500">+ Add row</button>
                <button type="button" data-remove-list-section class="fd-btn-sm w-fit text-sm text-red-500">Remove section</button>
              </div>
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
            const removeSection = event.target.closest('[data-remove-list-section]');
            if (removeSection) {
                const section = removeSection.closest('[data-list-section]');
                if (listRoot.querySelectorAll('[data-list-section]').length > 1) {
                    section?.remove();
                }
                return;
            }

            const removeRow = event.target.closest('[data-remove-list-row]');
            if (removeRow) {
                const row = removeRow.closest('[data-list-row]');
                const rowsHost = row?.parentElement;
                if (rowsHost && rowsHost.querySelectorAll('[data-list-row]').length > 1) {
                    row.remove();
                }
                return;
            }

            const addRow = event.target.closest('[data-add-list-row]');
            if (addRow) {
                const section = addRow.closest('[data-list-section]');
                const sectionIndex = [...listRoot.querySelectorAll('[data-list-section]')].indexOf(section);
                const rowsHost = section?.querySelector('[data-list-rows]');
                const rowIndex = rowsHost?.querySelectorAll('[data-list-row]').length || 0;
                rowsHost?.insertAdjacentHTML(
                    'beforeend',
                    `<div class="grid grid-cols-1 gap-2 md:grid-cols-[1fr_1fr_auto]" data-list-row>
                      <input name="list_sections[${sectionIndex}][rows][${rowIndex}][title]" placeholder="Row title *" required class="fd-input rounded-xl border border-border p-3">
                      <input name="list_sections[${sectionIndex}][rows][${rowIndex}][description]" placeholder="Description" class="fd-input rounded-xl border border-border p-3">
                      <button type="button" data-remove-list-row class="text-sm text-red-500">Remove</button>
                    </div>`,
                );
            }
        });
    }
}
