
export const initChartFourteen = () => {
    const chartFourteenEl = document.querySelector('#chartFourteen');

    if (chartFourteenEl) {
        const chartOptions = {
            series: [
                {
                    name: "Sales",
                    data: [168, 385, 201, 298, 187, 195],
                },
            ],
            colors: ["#465fff"],
            chart: {
                fontFamily: "Outfit, sans-serif",
                type: "bar",
                height: 200,
                toolbar: {
                    show: false,
                },
            },
            plotOptions: {
                bar: {
                    horizontal: false,
                    columnWidth: "39%",
                    borderRadius: 5,
                    borderRadiusApplication: "end",
                },
            },
            dataLabels: {
                enabled: false,
            },
            stroke: {
                show: true,
                width: 4,
                colors: ["transparent"],
            },
            xaxis: {
                categories: ["Jan", "Feb", "Mar", "Apr", "May", "Jun"],
                axisBorder: {
                    show: false,
                },
                axisTicks: {
                    show: false,
                },
            },
            legend: {
                show: true,
                position: "top",
                horizontalAlign: "left",
                fontFamily: "Outfit",
                markers: {
                    radius: 99,
                },
            },
            yaxis: {
                title: false,
            },
            grid: {
                yaxis: {
                    lines: {
                        show: true,
                    },
                },
            },
            fill: {
                opacity: 1,
            },

            tooltip: {
                x: {
                    show: false,
                },
                y: {
                    formatter: function (val) {
                        return val;
                    },
                },
            },
        };

        const chartFourteen = new ApexCharts(chartFourteenEl, chartOptions);
        chartFourteen.render();
        return chartFourteen;
    }

}

export default initChartFourteen;
