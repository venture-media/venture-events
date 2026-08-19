/**
 * Venture Events – results charts (Chart.js)
 */
(function () {
    'use strict';

    if (typeof Chart === 'undefined') {
        return;
    }

    Chart.defaults.font.family = '"Effra", system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif';
    Chart.defaults.font.weight = '300';
    Chart.defaults.color = '#221f21';

    function parsePayload(root) {
        var node = root.querySelector('.ve-results-json');
        if (!node) {
            return null;
        }
        try {
            return JSON.parse(node.textContent || '{}');
        } catch (e) {
            return null;
        }
    }

    function isNarrow() {
        return window.matchMedia && window.matchMedia('(max-width: 700px)').matches;
    }

    function renderBar(canvas, payload, set) {
        var labels = payload.month_labels || [];
        new Chart(canvas.getContext('2d'), {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    data: set.monthly || [],
                    backgroundColor: set.color,
                    borderWidth: 0,
                    maxBarThickness: 48
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function (ctx) {
                                var n = ctx.parsed && typeof ctx.parsed.y === 'number' ? ctx.parsed.y : 0;
                                return String(n);
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { maxRotation: 0, autoSkip: true }
                    },
                    y: {
                        beginAtZero: true,
                        ticks: { precision: 0 },
                        grid: { color: 'rgba(34, 31, 33, 0.08)' }
                    }
                }
            }
        });
    }

    function renderIndustryBar(canvas, set) {
        var industries = set.industries || [];
        var many = industries.length > 8;
        new Chart(canvas.getContext('2d'), {
            type: 'bar',
            data: {
                labels: industries.map(function (row) { return row.label; }),
                datasets: [{
                    data: industries.map(function (row) { return row.count; }),
                    backgroundColor: set.color || '#f88c00',
                    borderWidth: 0,
                    maxBarThickness: 48
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function (ctx) {
                                var n = ctx.parsed && typeof ctx.parsed.y === 'number' ? ctx.parsed.y : 0;
                                return String(n);
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: {
                            maxRotation: many ? 60 : 0,
                            minRotation: many ? 45 : 0,
                            autoSkip: false,
                            font: { size: 11 }
                        }
                    },
                    y: {
                        beginAtZero: true,
                        ticks: { precision: 0 },
                        grid: { color: 'rgba(34, 31, 33, 0.08)' }
                    }
                }
            }
        });
    }

    function renderDoughnut(canvas, set) {
        var tiers = set.tiers || [];
        new Chart(canvas.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: tiers.map(function (t) { return t.label; }),
                datasets: [{
                    data: tiers.map(function (t) { return t.count; }),
                    backgroundColor: tiers.map(function (t) { return t.color; }),
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '58%',
                plugins: {
                    legend: {
                        position: isNarrow() ? 'bottom' : 'right',
                        labels: {
                            boxWidth: 12,
                            boxHeight: 12,
                            padding: 10,
                            font: { size: 12, weight: '300' }
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function (ctx) {
                                var n = typeof ctx.parsed === 'number' ? ctx.parsed : 0;
                                var data = ctx.dataset.data || [];
                                var total = data.reduce(function (sum, v) { return sum + Number(v || 0); }, 0);
                                var pct = total ? Math.round((n / total) * 100) : 0;
                                return (ctx.label || '') + ': ' + n + ' (' + pct + '%)';
                            }
                        }
                    }
                }
            }
        });
    }

    function initRoot(root) {
        var payload = parsePayload(root);
        if (!payload || !Array.isArray(payload.sets)) {
            return;
        }

        var sections = root.querySelectorAll('.ve-results-set');
        payload.sets.forEach(function (set, i) {
            var section = sections[i];
            if (!section || !set) {
                return;
            }
            var bar = section.querySelector('canvas.ve-results-bar');
            var doughnut = section.querySelector('canvas.ve-results-doughnut');
            var industry = section.querySelector('canvas.ve-results-industry');
            if (bar && (set.total || 0) > 0) {
                renderBar(bar, payload, set);
            }
            if (doughnut && set.tiers && set.tiers.length) {
                renderDoughnut(doughnut, set);
            }
            if (industry && set.industries && set.industries.length) {
                renderIndustryBar(industry, set);
            }
        });
    }

    function init() {
        document.querySelectorAll('.venture-events-results').forEach(initRoot);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
