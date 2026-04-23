$(document).ready(function() {
    
    // ==========================================
    // DATA SOURCE (Uses data injected from Twig)
    // ==========================================
    const chartData = window.projectChartData || {
        months: [],
        transactionCounts: [],
        transactionVolumes: [],
        categories: [],
        categoryCounts: []
    };

    window.mixedChartInstance = null;
    window.pieChartInstance = null;


    //
    // LINE, COLUMN & AREA CHART (Overview Mixed Chart)
    //
    if ($("#transactions-mixed-chart").length > 0) {
        var colorsMixed = ["#5b69bc", "#10c469", "#fa5c7c"];
        var dataColorsMixed = $("#transactions-mixed-chart").data('colors');
        if (dataColorsMixed) {
            colorsMixed = dataColorsMixed.split(",");
        }

        var optionsMixed = {
            chart: {
                height: 380,
                type: 'line',
                stacked: false,
                toolbar: {
                    show: false
                }
            },
            stroke: {
                width: [0, 2, 4],
                curve: 'smooth'
            },
            plotOptions: {
                bar: {
                    columnWidth: '50%'
                }
            },
            colors: colorsMixed,
            series: [{
                name: 'Transaction Count',
                type: 'column',
                data: chartData.transactionCounts
            }, {
                name: 'Volume',
                type: 'area',
                data: chartData.transactionVolumes
            }, {
                name: 'Trend',
                type: 'line',
                data: chartData.transactionCounts
            }],
            fill: {
                opacity: [0.85, 0.25, 1],
                gradient: {
                    inverseColors: false,
                    shade: 'light',
                    type: "vertical",
                    opacityFrom: 0.85,
                    opacityTo: 0.55,
                    stops: [0, 100, 100, 100]
                }
            },
            labels: chartData.months,
            markers: {
                size: 0
            },
            legend: {
                offsetY: 7,
            },
            xaxis: {
                type: 'category'
            },
            yaxis: [{
                title: {
                    text: 'Number of Transactions',
                }
            }, {
                opposite: true,
                title: {
                    text: 'Transaction Volume'
                },
                labels: {
                    formatter: function (value) {
                        return window.faCurrentSymbol ? window.faCurrentSymbol + value.toFixed(2) : value.toFixed(2);
                    }
                }
            }, {
                show: false
            }],
            tooltip: {
                shared: true,
                intersect: false,
                y: {
                    formatter: function (y, { seriesIndex }) {
                        if (typeof y !== "undefined") {
                            if (seriesIndex === 1) {
                                return window.faCurrentSymbol ? window.faCurrentSymbol + y.toFixed(2) : y.toFixed(2);
                            }
                            return y.toFixed(0) + " transactions";
                        }
                        return y;
                    }
                }
            },
            grid: {
                borderColor: '#f1f3fa',
                padding: {
                    bottom: 5
                }
            }
        };

        window.mixedChartInstance = new ApexCharts(
            document.querySelector("#transactions-mixed-chart"),
            optionsMixed
        );

        window.mixedChartInstance.render();
    }

    //
    // SIMPLE PIE CHART (Expense Categories)
    //
    if ($("#category-pie-chart").length > 0) {
        var colorsPie = ["#5b69bc", "#35b8e0", "#10c469", "#fa5c7c", "#e3eaef"];
        var dataColorsPie = $("#category-pie-chart").data('colors');
        if (dataColorsPie) {
            colorsPie = dataColorsPie.split(",");
        }
        var optionsPie = {
            chart: {
                height: 320,
                type: 'pie',
            },
            series: chartData.categoryCounts,
            labels: chartData.categories,
            colors: colorsPie,
            legend: {
                show: true,
                position: 'bottom',
                horizontalAlign: 'center',
                verticalAlign: 'middle',
                floating: false,
                fontSize: '14px',
                offsetX: 0,
                offsetY: 7
            },
            responsive: [{
                breakpoint: 600,
                options: {
                    chart: {
                        height: 240
                    },
                    legend: {
                        show: false
                    },
                }
            }]
        };

        window.pieChartInstance = new ApexCharts(
            document.querySelector("#category-pie-chart"),
            optionsPie
        );

        window.pieChartInstance.render();
    }

    // Currency update hook
    window.updateChartCurrencies = function(rate, symbol) {
        window.faCurrentSymbol = symbol;
        
        if (window.mixedChartInstance) {
            var convertedVolumes = chartData.transactionVolumes.map(function(vol) { 
                return vol * rate; 
            });
            
            window.mixedChartInstance.updateSeries([{
                name: 'Transaction Count',
                type: 'column',
                data: chartData.transactionCounts
            }, {
                name: 'Volume',
                type: 'area',
                data: convertedVolumes
            }, {
                name: 'Trend',
                type: 'line',
                data: chartData.transactionCounts
            }]);
        }
    };
});
