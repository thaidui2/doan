// Dashboard JS
document.addEventListener('DOMContentLoaded', function() {
    console.log("Dashboard.js loaded successfully");
    
    // Setup revenue chart with a full replacement approach
    setupRevenueChart();
    
    // Setup daily revenue chart
    setupDailyRevenueChart();
    
    // Event handlers for other dashboard elements
    setupDashboardEvents();
});

// Function to initialize the revenue chart
function setupRevenueChart() {
    const chartCanvas = document.getElementById('revenueChart');
    
    if (!chartCanvas) {
        console.error('Revenue chart canvas element not found');
        return;
    }
    
    // Ensure we have data
    if (!window.revenueChartData || !window.revenueChartData.labels || !window.revenueChartData.data) {
        console.error('Revenue chart data is not properly defined');
        return;
    }
    
    console.log('Setting up chart with data:', window.revenueChartData);
    
    // Force a specific height for the chart container
    const chartContainer = chartCanvas.parentElement;
    if (chartContainer && chartContainer.classList.contains('chart-container')) {
        chartContainer.style.height = '300px';
    }
    
    // Clear any existing chart to prevent conflicts
    if (window.revenueChart instanceof Chart) {
        window.revenueChart.destroy();
    }
    
    // Create the chart with a slight delay to ensure DOM is fully ready
    setTimeout(() => {
        try {
            const ctx = chartCanvas.getContext('2d');
            
            // Create the chart
            window.revenueChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: window.revenueChartData.labels,
                    datasets: [{
                        label: 'Doanh thu (VNĐ)',
                        data: window.revenueChartData.data,
                        backgroundColor: 'rgba(78, 115, 223, 0.05)',
                        borderColor: 'rgba(78, 115, 223, 1)',
                        pointRadius: 3,
                        pointBackgroundColor: 'rgba(78, 115, 223, 1)',
                        pointBorderColor: 'rgba(78, 115, 223, 1)',
                        pointHoverRadius: 5,
                        pointHoverBackgroundColor: 'rgba(78, 115, 223, 1)',
                        pointHoverBorderColor: 'rgba(78, 115, 223, 1)',
                        pointHitRadius: 10,
                        pointBorderWidth: 2,
                        borderWidth: 4,
                        fill: true,
                        tension: 0.3
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    layout: {
                        padding: {
                            left: 10,
                            right: 25,
                            top: 25,
                            bottom: 0
                        }
                    },
                    scales: {
                        x: {
                            grid: {
                                display: false,
                                drawBorder: false
                            },
                            ticks: {
                                font: {
                                    size: 12,
                                    weight: 'bold'
                                }
                            }
                        },
                        y: {
                            ticks: {
                                maxTicksLimit: 5,
                                padding: 10,
                                callback: function(value) {
                                    return new Intl.NumberFormat('vi-VN', { 
                                        style: 'currency', 
                                        currency: 'VND',
                                        maximumFractionDigits: 0
                                    }).format(value);
                                },
                                font: {
                                    size: 12
                                }
                            },
                            grid: {
                                color: "rgb(234, 236, 244)",
                                zeroLineColor: "rgb(234, 236, 244)",
                                drawBorder: false,
                                borderDash: [2],
                                zeroLineBorderDash: [2]
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            backgroundColor: "rgb(255, 255, 255)",
                            bodyColor: "#858796",
                            titleMarginBottom: 10,
                            titleColor: "#6e707e",
                            titleFont: {
                                size: 14,
                                weight: 'bold'
                            },
                            borderColor: '#dddfeb',
                            borderWidth: 1,
                            displayColors: false,
                            caretPadding: 10,
                            callbacks: {
                                label: function(context) {
                                    return new Intl.NumberFormat('vi-VN', { 
                                        style: 'currency', 
                                        currency: 'VND',
                                        maximumFractionDigits: 0
                                    }).format(context.parsed.y);
                                }
                            }
                        }
                    }
                }
            });
            
            console.log('Chart successfully created');
        } catch (error) {
            console.error('Error creating chart:', error);
        }
    }, 200);
}

// Function to initialize the daily revenue chart
function setupDailyRevenueChart() {
    const dailyChartCanvas = document.getElementById('dailyRevenueChart');
    
    if (!dailyChartCanvas) {
        console.error('Daily revenue chart canvas element not found');
        return;
    }
    
    // Ensure we have data
    if (!window.dailyRevenueData || !window.dailyRevenueData.labels) {
        console.error('Daily revenue chart data is not properly defined');
        return;
    }
    
    console.log('Setting up daily chart with data:', window.dailyRevenueData);
    
    // Force a specific height for the chart container
    const chartContainer = dailyChartCanvas.parentElement;
    if (chartContainer && chartContainer.classList.contains('chart-container')) {
        chartContainer.style.height = '250px';
    }
    
    // Clear any existing chart to prevent conflicts
    if (window.dailyRevenueChart instanceof Chart) {
        window.dailyRevenueChart.destroy();
    }
    
    // Create the chart with a slight delay to ensure DOM is fully ready
    setTimeout(() => {
        try {
            const ctx = dailyChartCanvas.getContext('2d');
            
            // Create the daily revenue chart - bar chart with two datasets
            window.dailyRevenueChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: window.dailyRevenueData.labels,
                    datasets: [
                        {
                            label: 'Doanh thu',
                            data: window.dailyRevenueData.revenue,
                            backgroundColor: 'rgba(78, 115, 223, 0.7)',
                            borderColor: 'rgba(78, 115, 223, 1)',
                            borderWidth: 1,
                            order: 2
                        },
                        {
                            label: 'Đơn hàng',
                            data: window.dailyRevenueData.orders,
                            type: 'line',
                            backgroundColor: 'rgba(28, 200, 138, 0.2)',
                            borderColor: 'rgba(28, 200, 138, 1)',
                            borderWidth: 2,
                            pointBorderWidth: 2,
                            pointRadius: 4,
                            pointBackgroundColor: 'rgba(28, 200, 138, 1)',
                            pointBorderColor: 'rgba(28, 200, 138, 1)',
                            yAxisID: 'y1',
                            order: 1
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        mode: 'index',
                        intersect: false
                    },
                    scales: {
                        x: {
                            grid: {
                                display: false
                            },
                            ticks: {
                                font: {
                                    size: 11
                                }
                            }
                        },
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return new Intl.NumberFormat('vi-VN', { 
                                        style: 'currency', 
                                        currency: 'VND',
                                        maximumFractionDigits: 0
                                    }).format(value);
                                }
                            },
                            grid: {
                                borderDash: [2],
                                color: 'rgba(0, 0, 0, 0.1)'
                            }
                        },
                        y1: {
                            position: 'right',
                            beginAtZero: true,
                            min: 0,
                            grid: {
                                display: false
                            },
                            ticks: {
                                stepSize: 1
                            }
                        }
                    },
                    plugins: {
                        tooltip: {
                            backgroundColor: "rgb(255, 255, 255)",
                            bodyColor: "#858796",
                            titleColor: "#6e707e",
                            borderColor: '#dddfeb',
                            borderWidth: 1,
                            callbacks: {
                                title: function(tooltipItems) {
                                    const index = tooltipItems[0].dataIndex;
                                    return window.dailyRevenueData.fullDates[index];
                                },
                                label: function(context) {
                                    if (context.datasetIndex === 0) {
                                        return 'Doanh thu: ' + new Intl.NumberFormat('vi-VN', { 
                                            style: 'currency', 
                                            currency: 'VND',
                                            maximumFractionDigits: 0
                                        }).format(context.raw);
                                    } else {
                                        return 'Số đơn: ' + context.raw;
                                    }
                                }
                            }
                        },
                        legend: {
                            position: 'top',
                            display: true
                        }
                    }
                }
            });
            
            console.log('Daily chart successfully created');
        } catch (error) {
            console.error('Error creating daily chart:', error);
        }
    }, 300);
}

// Setup dashboard event handlers
function setupDashboardEvents() {
    // Export report button handling
    const exportReportBtn = document.querySelector('.btn-export-report');
    if (exportReportBtn) {
        exportReportBtn.addEventListener('click', function(e) {
            e.preventDefault();
            
            // Redirect to the export page
            window.location.href = 'order_export.php';
        });
    }
    
    // Refresh daily stats
    const refreshDailyStatsBtn = document.getElementById('refreshDailyStats');
    if (refreshDailyStatsBtn) {
        refreshDailyStatsBtn.addEventListener('click', function(e) {
            e.preventDefault();
            
            // Reload the page to refresh the stats
            window.location.reload();
        });
    }
    
    // Export daily stats
    const exportDailyStatsBtn = document.getElementById('exportDailyStats');
    if (exportDailyStatsBtn) {
        exportDailyStatsBtn.addEventListener('click', function(e) {
            e.preventDefault();
            
            // Create temporary table with daily stats
            const table = document.createElement('table');
            table.innerHTML = `
                <thead>
                    <tr>
                        <th>Ngày</th>
                        <th>Đơn hàng</th>
                        <th>Doanh thu</th>
                    </tr>
                </thead>
                <tbody></tbody>
            `;
            
            const tbody = table.querySelector('tbody');
            
            // Add data rows
            for (let i = 0; i < window.dailyRevenueData.labels.length; i++) {
                const row = document.createElement('tr');
                
                const dateCell = document.createElement('td');
                dateCell.textContent = window.dailyRevenueData.fullDates[i];
                
                const ordersCell = document.createElement('td');
                ordersCell.textContent = window.dailyRevenueData.orders[i];
                
                const revenueCell = document.createElement('td');
                revenueCell.textContent = new Intl.NumberFormat('vi-VN', { 
                    style: 'currency', 
                    currency: 'VND',
                    maximumFractionDigits: 0
                }).format(window.dailyRevenueData.revenue[i]);
                
                row.appendChild(dateCell);
                row.appendChild(ordersCell);
                row.appendChild(revenueCell);
                tbody.appendChild(row);
            }
            
            // Calculate total
            const totalRow = document.createElement('tr');
            
            const totalLabelCell = document.createElement('td');
            totalLabelCell.textContent = 'Tổng';
            
            const totalOrdersCell = document.createElement('td');
            const totalOrders = window.dailyRevenueData.orders.reduce((sum, val) => sum + val, 0);
            totalOrdersCell.textContent = totalOrders;
            
            const totalRevenueCell = document.createElement('td');
            const totalRevenue = window.dailyRevenueData.revenue.reduce((sum, val) => sum + val, 0);
            totalRevenueCell.textContent = new Intl.NumberFormat('vi-VN', { 
                style: 'currency', 
                currency: 'VND',
                maximumFractionDigits: 0
            }).format(totalRevenue);
            
            totalRow.appendChild(totalLabelCell);
            totalRow.appendChild(totalOrdersCell);
            totalRow.appendChild(totalRevenueCell);
            tbody.appendChild(totalRow);
            
            // Convert to CSV
            let csv = [];
            const rows = table.querySelectorAll('tr');
            
            for (const row of rows) {
                const rowData = [];
                const cols = row.querySelectorAll('td, th');
                
                for (const col of cols) {
                    rowData.push('"' + col.textContent.replace(/"/g, '""') + '"');
                }
                
                csv.push(rowData.join(','));
            }
            
            const csvContent = csv.join('\n');
            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const url = URL.createObjectURL(blob);
            
            // Create download link
            const link = document.createElement('a');
            link.setAttribute('href', url);
            link.setAttribute('download', 'doanh-thu-theo-ngay.csv');
            link.style.visibility = 'hidden';
            
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        });
    }
    
    // Add animations to stat cards
    const statCards = document.querySelectorAll('.stat-card');
    statCards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-5px)';
            this.style.boxShadow = '0 0.5rem 1rem rgba(0, 0, 0, 0.15)';
            this.style.transition = 'all 0.3s ease';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = '';
            this.style.boxShadow = '';
        });
    });
}
