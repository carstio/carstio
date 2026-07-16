import { Controller } from '@hotwired/stimulus';

/**
 * Header global search.
 *
 * Targets:
 *   input    - the text input
 *   results  - the dropdown panel that holds rendered groups
 *
 * Values:
 *   url            - autocomplete endpoint URL
 *   minLength      - minimum query length before fetching (default 2)
 *   debounce       - debounce delay in ms (default 250)
 *   loadingText    - translated "Searching..." text
 *   emptyText      - translated "No results found" text
 *   errorText      - translated generic error text
 */
export default class extends Controller {
    static targets = ['input', 'results'];
    static values = {
        url: String,
        minLength: { type: Number, default: 2 },
        debounce: { type: Number, default: 250 },
        loadingText: { type: String, default: 'Searching…' },
        emptyText: { type: String, default: 'No results found' },
        errorText: { type: String, default: 'Search failed. Please try again.' },
    };

    connect() {
        this._debounceTimer = null;
        this._abortController = null;

        this._onDocumentClick = this._onDocumentClick.bind(this);
        this._onDocumentKeydown = this._onDocumentKeydown.bind(this);

        document.addEventListener('click', this._onDocumentClick);
        document.addEventListener('keydown', this._onDocumentKeydown);
    }

    disconnect() {
        document.removeEventListener('click', this._onDocumentClick);
        document.removeEventListener('keydown', this._onDocumentKeydown);

        if (this._debounceTimer) {
            clearTimeout(this._debounceTimer);
            this._debounceTimer = null;
        }
        if (this._abortController) {
            this._abortController.abort();
            this._abortController = null;
        }
    }

    onInput() {
        const query = this.inputTarget.value.trim();

        if (this._debounceTimer) {
            clearTimeout(this._debounceTimer);
        }

        if (query.length === 0) {
            this._closeAndAbort();
            return;
        }

        if (query.length < this.minLengthValue) {
            this._abort();
            this._showPanel();
            this._renderHint(this.emptyTextValue);
            return;
        }

        this._debounceTimer = setTimeout(() => this._fetch(query), this.debounceValue);
    }

    onFocus() {
        const query = this.inputTarget.value.trim();
        if (query.length >= this.minLengthValue && this.resultsTarget.children.length > 0) {
            this._showPanel();
        }
    }

    async _fetch(query) {
        this._abort();
        this._showPanel();
        this._renderHint(this.loadingTextValue);

        const controller = new AbortController();
        this._abortController = controller;

        const url = `${this.urlValue}?q=${encodeURIComponent(query)}`;

        try {
            const response = await fetch(url, {
                signal: controller.signal,
                credentials: 'same-origin',
                headers: { Accept: 'application/json' },
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const data = await response.json();
            if (controller.signal.aborted) return;

            this._render(data);
        } catch (err) {
            if (err.name === 'AbortError') return;
            this._renderHint(this.errorTextValue);
        } finally {
            if (this._abortController === controller) {
                this._abortController = null;
            }
        }
    }

    _render(data) {
        const groups = Array.isArray(data?.groups) ? data.groups : [];
        const totalItems = groups.reduce((sum, g) => sum + (g.items?.length || 0), 0);

        if (totalItems === 0) {
            this._renderHint(this.emptyTextValue);
            return;
        }

        this.resultsTarget.replaceChildren();

        for (const group of groups) {
            if (!group.items || group.items.length === 0) continue;

            const section = document.createElement('div');
            section.className = 'global-search-group';
            section.dataset.type = group.type || '';

            const header = document.createElement('div');
            header.className = 'global-search-group-label';
            header.textContent = group.label || '';
            section.appendChild(header);

            const list = document.createElement('ul');
            list.className = 'global-search-list';

            for (const item of group.items) {
                const li = document.createElement('li');
                const link = document.createElement('a');
                link.className = 'global-search-item';
                link.href = item.url || '#';

                const label = document.createElement('span');
                label.className = 'global-search-item-label';
                label.textContent = item.label || '';
                link.appendChild(label);

                const description = (item.description || '').trim();
                if (description !== '') {
                    const desc = document.createElement('span');
                    desc.className = 'global-search-item-description';
                    desc.textContent = description;
                    link.appendChild(desc);
                }

                link.addEventListener('click', () => this._close());

                li.appendChild(link);
                list.appendChild(li);
            }

            section.appendChild(list);
            this.resultsTarget.appendChild(section);
        }
    }

    _renderHint(message) {
        this.resultsTarget.replaceChildren();

        const hint = document.createElement('div');
        hint.className = 'global-search-hint';
        hint.textContent = message;
        this.resultsTarget.appendChild(hint);
    }

    _showPanel() {
        this.element.classList.add('is-open');
    }

    _close() {
        this.element.classList.remove('is-open');
    }

    _abort() {
        if (this._abortController) {
            this._abortController.abort();
            this._abortController = null;
        }
    }

    _closeAndAbort() {
        this._abort();
        this.resultsTarget.replaceChildren();
        this._close();
    }

    _onDocumentClick(event) {
        if (!this.element.contains(event.target)) {
            this._close();
        }
    }

    _onDocumentKeydown(event) {
        if (event.key === 'Escape' && this.element.classList.contains('is-open')) {
            this._close();
            this.inputTarget.blur();
        }
    }
}
