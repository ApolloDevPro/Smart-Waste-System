// Initialize clock
function updateClock() {
    const clock = document.getElementById('dashboardClock');
    const now = new Date();
    clock.textContent = now.toLocaleString();
}
setInterval(updateClock, 1000);
updateClock();

// Initialize charts
document.addEventListener('DOMContentLoaded', function () {
    // Monthly Requests Chart
    const ctx1 = document.getElementById('monthlyRequestsChart').getContext('2d');
    const monthlyData = <?= json_encode($monthly_requests) ?>;
    const labels = monthlyData.map(item => item.month);
    const data = monthlyData.map(item => item.count);

    new Chart(ctx1, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Requests per Month',
                data: data,
                borderColor: '#28a745',
                tension: 0.3,
                fill: true,
                backgroundColor: 'rgba(40, 167, 69, 0.2)'
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { display: false }
            }
        }
    });

    // Waste Type Chart
    const ctx2 = document.getElementById('wasteTypeChart').getContext('2d');
    const wasteData = <?= json_encode($waste_types) ?>;
    const wasteLabels = wasteData.map(item => item.waste_type);
    const wasteCounts = wasteData.map(item => item.count);

    new Chart(ctx2, {
        type: 'pie',
        data: {
            labels: wasteLabels,
            datasets: [{
                label: 'Waste Types',
                data: wasteCounts,
                backgroundColor: ['#28a745', '#1e5631', '#a8e6a3', '#218838', '#34c38f']
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { position: 'right' }
            }
        }
    });
});

// Table Filter
document.querySelectorAll('.table-filter').forEach(input => {
    input.addEventListener('input', function () {
        const filter = this.value.toLowerCase();
        const table = document.getElementById(this.dataset.table);
        const rows = table.querySelectorAll('tbody tr');

        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            row.style.display = text.includes(filter) ? '' : 'none';
        });
    });
});