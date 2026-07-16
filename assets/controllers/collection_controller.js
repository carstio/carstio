import { Controller } from '@hotwired/stimulus';

/**
 * Manages a Symfony CollectionType split across labor / parts tabs.
 *
 * Place a single controller on a wrapper that contains both tab panes.
 * Use separate targets for each list, and addLabor / addPart actions.
 */
export default class extends Controller {
    static targets = ['laborList', 'partsList'];
    static values  = {
        prototype: String,
        index:     Number,
    };

    addLabor() {
        this._add('labor', this.laborListTarget);
    }

    addPart() {
        this._add('part', this.partsListTarget);
    }

    remove(event) {
        const item = event.target.closest('.collection-item');
        if (item) {
            item.remove();
        }
    }

    _add(type, listEl) {
        const index = this.indexValue++;

        // Build the wrapper directly from the prototype so any property we
        // set on its children survives. Going through an intermediate div
        // and reading `tmp.innerHTML` would re-serialize using attributes
        // and silently drop programmatic .value assignments (the type
        // hidden input would submit empty, crashing the form).
        const wrapper = document.createElement('div');
        wrapper.classList.add('collection-item', 'mb-3', 'p-3', 'border', 'rounded', 'bg-white', 'position-relative');
        wrapper.innerHTML = this.prototypeValue.replace(/__item__/g, index);

        const typeInput = wrapper.querySelector('[id$="_type"]');
        if (typeInput) {
            typeInput.setAttribute('value', type);
            typeInput.value = type;
        }

        const sortInput = wrapper.querySelector('[id$="_sortOrder"]');
        if (sortInput) {
            sortInput.setAttribute('value', String(index));
            sortInput.value = String(index);
        }

        if (type === 'labor') {
            const partSelect = wrapper.querySelector('[id$="_part"]');
            if (partSelect) {
                const row = partSelect.closest('.mb-3');
                if (row) row.style.display = 'none';
            }
        }

        const removeLabel = document.body?.dataset?.i18nActionRemove || 'Remove';
        const removeBtn = document.createElement('button');
        removeBtn.type = 'button';
        removeBtn.className = 'btn btn-sm btn-outline-danger position-absolute top-0 end-0 mt-2 me-2';
        removeBtn.innerHTML = '&times;';
        removeBtn.title = removeLabel;
        removeBtn.setAttribute('aria-label', removeLabel);
        removeBtn.setAttribute('data-action', 'collection#remove');
        wrapper.appendChild(removeBtn);

        listEl.appendChild(wrapper);
    }
}
