jQuery(document).ready(function($) {
    // Toggle analytics section
    $('.prooflinq-charts-toggle').click(function() {
        $('.prooflinq-charts-container').slideToggle();
        $(this).find('.dashicons-arrow-down-alt2').toggleClass('dashicons-arrow-up-alt2');
        
        // Only load charts when container is shown
        if ($('.prooflinq-charts-container').is(':visible')) {
            loadCharts();
        }
    });

    function loadCharts() {
        // Load feedback trends
        $.ajax({
            url: prooflinqCharts.ajaxurl,
            type: 'GET',
            data: {
                action: 'get_feedback_trends',
                nonce: prooflinqCharts.nonce
            },
            success: function(response) {
                if (response.success) {
                    renderTrendsChart(response.data);
                }
            }
        });

        // Load category distribution
        $.ajax({
            url: prooflinqCharts.ajaxurl,
            type: 'GET',
            data: {
                action: 'get_category_distribution',
                nonce: prooflinqCharts.nonce
            },
            success: function(response) {
                if (response.success) {
                    renderCategoryChart(response.data);
                }
            }
        });

        // Load status distribution
        $.ajax({
            url: prooflinqCharts.ajaxurl,
            type: 'GET',
            data: {
                action: 'get_status_distribution',
                nonce: prooflinqCharts.nonce
            },
            success: function(response) {
                if (response.success) {
                    renderStatusChart(response.data);
                }
            }
        });
    }

    function renderTrendsChart(data) {
        const ctx = document.getElementById('feedbackTrendsChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: data.labels,
                datasets: [{
                    label: 'Feedback Submissions',
                    data: data.values,
                    borderColor: '#2271b1',
                    backgroundColor: 'rgba(34, 113, 177, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    title: {
                        display: true,
                        text: 'Feedback Trends (Last 30 Days)'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });
    }

    function renderCategoryChart(data) {
        const ctx = document.getElementById('categoryDistributionChart').getContext('2d');
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: data.labels,
                datasets: [{
                    data: data.values,
                    backgroundColor: [
                        '#f44336',  // Bug
                        '#2196f3',  // Feature
                        '#4caf50',  // Improvement
                        '#ff9800'   // General
                    ]
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    title: {
                        display: true,
                        text: 'Feedback by Category'
                    }
                }
            }
        });
    }

    function renderStatusChart(data) {
        const ctx = document.getElementById('statusDistributionChart').getContext('2d');
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: data.labels,
                datasets: [{
                    data: data.values,
                    backgroundColor: [
                        '#ffc107',  // Open
                        '#00844B',  // In Progress
                        '#2271b1'   // Resolved
                    ]
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    title: {
                        display: true,
                        text: 'Feedback by Status'
                    }
                }
            }
        });
    }
}); 