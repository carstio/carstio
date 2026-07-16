import { Controller } from '@hotwired/stimulus';
import flatpickr from 'flatpickr';

/**
 * Reusable flatpickr wrapper for Symfony Form date/time/datetime inputs
 * rendered with widget: 'single_text' and html5: false.
 *
 * Expected form options:
 *   DateTimeType: 'format' => 'yyyy-MM-dd HH:mm'  (maps to 'Y-m-d H:i')
 *   DateType:     'format' => 'yyyy-MM-dd'        (maps to 'Y-m-d')
 *   TimeType:     'format' => 'HH:mm'             (maps to 'H:i')
 *
 * Stimulus values (on the input):
 *   data-datetime-field-mode-value="datetime" | "date" | "time"  (default: datetime)
 *   data-datetime-field-now-value="true"                         (renders a "Now" button)
 *   data-datetime-field-minute-increment-value="5"               (default: 5)
 *
 * Turbo-safe: flatpickr is destroyed on disconnect(). The controller never
 * moves or replaces its own element - see `_mountNowButton` for why.
 */
export default class extends Controller {
    static values = {
        mode: { type: String, default: 'datetime' },
        now: { type: Boolean, default: false },
        minuteIncrement: { type: Number, default: 5 },
    };

    connect() {
        if (!(this.element instanceof HTMLInputElement)) return;
        if (this._fp) return;

        this._fp = flatpickr(this.element, this._buildOptions());

        if (this.nowValue) {
            this._mountNowButton();
        }
    }

    disconnect() {
        this._unmountNowButton();
        if (this._fp) {
            this._fp.destroy();
            this._fp = null;
        }
    }

    _buildOptions() {
        const shared = {
            allowInput: true,
            altInput: false,
            clickOpens: true,
            time_24hr: true,
            minuteIncrement: this.minuteIncrementValue,
            locale: { firstDayOfWeek: 1 },
        };

        if (this.modeValue === 'date') {
            return { ...shared, enableTime: false, dateFormat: 'Y-m-d' };
        }
        if (this.modeValue === 'time') {
            return {
                ...shared,
                enableTime: true,
                noCalendar: true,
                dateFormat: 'H:i',
            };
        }

        return { ...shared, enableTime: true, dateFormat: 'Y-m-d H:i' };
    }

    // Insert the "Now" button as a sibling next to the input. We must NOT move
    // or wrap `this.element`: Stimulus's MutationObserver fires disconnect/connect
    // when the controlled element changes parents, which produces an infinite loop.
    _mountNowButton() {
        if (this._button) return;

        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'btn btn-sm btn-outline-secondary datetime-field-now-btn mt-1';
        button.textContent = this._nowButtonLabel();
        button.addEventListener('click', this._setNow);

        this.element.insertAdjacentElement('afterend', button);
        this._button = button;
    }

    _unmountNowButton() {
        if (!this._button) return;
        this._button.removeEventListener('click', this._setNow);
        this._button.remove();
        this._button = null;
    }

    _nowButtonLabel() {
        const labels = document.body?.dataset ?? {};
        if (this.modeValue === 'date') {
            return labels.i18nButtonToday || 'Today';
        }

        return labels.i18nButtonNow || 'Now';
    }

    _setNow = () => {
        if (!this._fp) return;
        this._fp.setDate(new Date(), true);
    };
}
