document.addEventListener('DOMContentLoaded', () => {
    const canvas = document.getElementById('productivityChart');
    if (!canvas || !window.weeklyTrend) {
        return;
    }

    const labels = window.weeklyTrend.map((item) => {
        const date = new Date(item.week_start);
        return date.toLocaleDateString('uk-UA', { day: '2-digit', month: 'short' });
    });

    const bottlesData = window.weeklyTrend.map((item) => Number(item.bottles));
    const revenueData = window.weeklyTrend.map((item) => Number(item.revenue));
    const partsData = window.weeklyTrend.map((item) => Number(item.parts));

    if (!labels.length) {
        canvas.insertAdjacentHTML('afterend', '<p class="empty-state">Ще не додано даних для побудови графіка.</p>');
        canvas.remove();
        return;
    }

    const container = canvas.parentElement;
    if (container) {
        canvas.height = container.offsetHeight;
    }

    new Chart(canvas, {
        type: 'line',
        data: {
            labels,
            datasets: [
                {
                    label: 'Зібрано пляшок',
                    data: bottlesData,
                    borderColor: '#1b5bff',
                    backgroundColor: 'rgba(27, 91, 255, 0.15)',
                    tension: 0.3,
                    fill: true,
                    pointRadius: 4,
                },
                {
                    label: 'Отримано коштів (грн)',
                    data: revenueData,
                    borderColor: '#f6b801',
                    backgroundColor: 'rgba(246, 184, 1, 0.15)',
                    tension: 0.3,
                    fill: true,
                    pointRadius: 4,
                },
                {
                    label: 'Надруковано деталей',
                    data: partsData,
                    borderColor: '#16a34a',
                    backgroundColor: 'rgba(22, 163, 74, 0.15)',
                    tension: 0.3,
                    fill: true,
                    pointRadius: 4,
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        precision: 0,
                    },
                },
            },
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        font: {
                            family: 'Montserrat',
                            weight: '600',
                        },
                    },
                },
                tooltip: {
                    callbacks: {
                        label: (context) => {
                            const value = context.parsed.y;
                            if (context.dataset.label === 'Отримано коштів (грн)') {
                                return `${context.dataset.label}: ${value.toFixed(2)} грн`;
                            }
                            return `${context.dataset.label}: ${Math.round(value)}`;
                        },
                    },
                },
            },
        },
    });
});
