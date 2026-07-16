import { Controller } from '@hotwired/stimulus';

/**
 * Toggles the custom date-range fields when the period preset changes.
 * The form still submits as a regular GET - no AJAX, no extra state.
 */
export default class extends Controller {
    static targets = ['range', 'custom'];

    connect() {
        this._sync();
    }

    toggleCustom() {
        this._sync();
    }

    _sync() {
        if (!this.hasRangeTarget || !this.hasCustomTarget) return;
        const isCustom = this.rangeTarget.value === 'custom';
        this.customTarget.hidden = !isCustom;
    }
}
