const buildMonthSeries = (monthly) => {
    const values = [];
    for (let m = 1; m <= 12; m += 1) {
        values.push(monthly?.[m] ?? 0);
    }
    return values;
};

const initExpensesChart = () => {
    const canvas = document.querySelector('[data-expenses-chart]');
    if (!canvas || !window.Chart) return;

    let monthly = {};
    try {
        monthly = JSON.parse(canvas.dataset.monthly || '{}');
    } catch (e) {
        monthly = {};
    }

    const data = buildMonthSeries(monthly);

    new window.Chart(canvas, {
        type: 'bar',
        data: {
            labels: ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'],
            datasets: [{
                label: 'Expenses',
                data,
                backgroundColor: '#fee2e266',
                borderColor: '#c0392b',
                borderWidth: 1.5,
                borderRadius: 4,
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: (context) => `KES ${Math.round(context.raw).toLocaleString()}`,
                    },
                },
            },
            scales: {
                x: { grid: { display: false }, ticks: { font: { size: 10 } } },
                y: {
                    grid: { color: '#f3f4f6' },
                    ticks: {
                        font: { size: 10 },
                        callback: (value) => (value >= 1000 ? `${value / 1000}k` : value),
                    },
                },
            },
        },
    });
};

document.addEventListener('DOMContentLoaded', initExpensesChart);
