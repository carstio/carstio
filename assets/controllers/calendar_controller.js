import { Controller } from '@hotwired/stimulus';

let bundlePromise = null;

function loadBundle(url) {
    if (window.FullCalendar) {
        return Promise.resolve();
    }
    if (!bundlePromise) {
        bundlePromise = new Promise((resolve, reject) => {
            const script = document.createElement('script');
            script.src = url;
            script.onload = () => resolve();
            script.onerror = () => {
                bundlePromise = null;
                reject(new Error(`Failed to load FullCalendar bundle from ${url}`));
            };
            document.head.appendChild(script);
        });
    }
    return bundlePromise;
}

export default class extends Controller {
    static values = {
        eventsUrl: String,
        bundleUrl: String,
        initialView: { type: String, default: 'dayGridMonth' },
    };

    connect() {
        this.alive = true;
        loadBundle(this.bundleUrlValue)
            .then(() => {
                if (!this.alive) return;
                this.calendar = new window.FullCalendar.Calendar(this.element, {
                    initialView: this.initialViewValue,
                    headerToolbar: {
                        left: 'prev,next today',
                        center: 'title',
                        right: '',
                    },
                    height: 'auto',
                    firstDay: 1,
                    nowIndicator: true,
                    navLinks: false,
                    dayMaxEventRows: 3,
                    events: this.eventsUrlValue,
                    eventDisplay: 'block',
                    eventTimeFormat: { hour: '2-digit', minute: '2-digit', hour12: false },
                    eventDidMount: (info) => {
                        info.el.setAttribute('title', this.buildTooltip(info.event));
                    },
                });
                this.calendar.render();
            })
            .catch((err) => {
                console.error(err);
            });
    }

    disconnect() {
        this.alive = false;
        if (this.calendar) {
            this.calendar.destroy();
            this.calendar = null;
        }
    }

    buildTooltip(event) {
        const p = event.extendedProps || {};
        const lines = [];
        if (p.number) lines.push(p.number);
        if (p.client) lines.push(p.client);
        if (p.vehicle) lines.push(p.vehicle);
        if (p.mechanic) lines.push(p.mechanic);
        if (p.status) lines.push(p.status.replace(/_/g, ' '));
        if (p.scheduledAt) lines.push(p.scheduledAt);
        return lines.join('\n');
    }
}
