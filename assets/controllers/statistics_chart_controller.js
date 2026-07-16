import { Controller } from '@hotwired/stimulus';
import Chart from 'chart.js/auto';

/**
 * Renders a Chart.js chart from a JSON payload prepared on the server.
 *
 * The controller never computes business values - it only chooses
 * presentation settings (colors, scale labels, money formatting) for
 * the labels and values it receives. Keeping the data shape PHP-built
 * means the page renders the same numbers under JS and under tests.
 */
export default class extends Controller {
    static targets = ['canvas'];
    static values = { config: Object };

    static palette = [
        '#2563eb',
        '#16a34a',
        '#d97706',
        '#dc2626',
        '#0891b2',
        '#7c3aed',
        '#475569',
        '#0ea5e9',
        '#ea580c',
        '#65a30d',
    ];

    connect() {
        if (!this.hasCanvasTarget || !this.configValue) return;

        const config = this.configValue;
        const datasets = this._buildDatasets(config);

        this._chart = new Chart(this.canvasTarget, {
            type: config.type,
            data: {
                labels: config.labels,
                datasets,
            },
            options: this._buildOptions(config),
        });
    }

    disconnect() {
        if (this._chart) {
            this._chart.destroy();
            this._chart = null;
        }
    }

    _buildDatasets(config) {
        const palette = this.constructor.palette;

        if (config.type === 'doughnut') {
            return [{
                data: config.values,
                backgroundColor: config.values.map((_, i) => palette[i % palette.length]),
                borderWidth: 0,
            }];
        }

        if (config.type === 'line') {
            return [{
                data: config.values,
                borderColor: palette[0],
                backgroundColor: 'rgba(37, 99, 235, 0.12)',
                borderWidth: 2,
                fill: true,
                tension: 0.3,
                pointRadius: 2,
                pointHoverRadius: 4,
            }];
        }

        return [{
            data: config.values,
            backgroundColor: palette[0],
            borderRadius: 4,
            borderWidth: 0,
        }];
    }

    _buildOptions(config) {
        const isMoney = !!config.isMoney;
        const moneyFormatter = isMoney ? this._buildMoneyFormatter(config.currencyCode) : null;

        const baseTooltip = {
            callbacks: {
                label: (item) => {
                    const value = item.parsed.y ?? item.parsed;
                    if (typeof value !== 'number') return item.formattedValue;
                    return isMoney ? moneyFormatter(value) : String(value);
                },
            },
        };

        if (config.type === 'doughnut') {
            return {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom' },
                    tooltip: baseTooltip,
                },
            };
        }

        return {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: baseTooltip,
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: (value) => isMoney ? moneyFormatter(value) : value,
                    },
                },
            },
        };
    }

    _buildMoneyFormatter(currencyCode) {
        try {
            const formatter = new Intl.NumberFormat(undefined, {
                style: 'currency',
                currency: currencyCode || 'EUR',
                maximumFractionDigits: 2,
            });
            return (value) => formatter.format(value);
        } catch (_) {
            return (value) => `${Number(value).toFixed(2)} ${currencyCode || ''}`.trim();
        }
    }
}
