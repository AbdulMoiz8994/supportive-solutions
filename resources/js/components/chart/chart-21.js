
export function initChartTwentyOne() {
    const chartEl = document.querySelector('#chartTwentyOne');
    if (chartEl) {
        const chartOptions = {
            series: [
                {
                    name: "New Sales",
                    data: [300, 350, 310, 370, 248, 287, 295, 191, 269, 201, 185, 252, 151],
                },
            ],
            grid: {
                show: false,
            },
            colors: ["#12B76A"],
            fill: {
                gradient: {
                    enabled: true,
                    opacityFrom: 0.55,
                    opacityTo: 0,
                },
            },
            legend: {
                show: false,
            },
            chart: {
                fontFamily: "Outfit, sans-serif",
                height: 70,
                type: "area",
                parentHeightOffset: 0,

                toolbar: {
                    show: false,
                },
            },
            tooltip: {
                enabled: false,
            },
            dataLabels: {
                enabled: false,
            },
            stroke: {
                curve: "smooth",
                width: 1,
            },
            xaxis: {
                type: "datetime",
                categories: [
                    "2018-09-19T00:00:00.000Z",
                    "2018-09-19T01:30:00.000Z",
                    "2018-09-19T02:30:00.000Z",
                    "2018-09-19T03:30:00.000Z",
                    "2018-09-19T04:30:00.000Z",
                    "2018-09-19T05:30:00.000Z",
                    "2018-09-19T06:30:00.000Z",
                    "2018-09-19T07:30:00.000Z",
                    "2018-09-19T08:30:00.000Z",
                    "2018-09-19T09:30:00.000Z",
                    "2018-09-19T10:30:00.000Z",
                    "2018-09-19T11:30:00.000Z",
                    "2018-09-19T12:30:00.000Z",
                ],
                labels: {
                    show: false,
                },
                axisBorder: {
                    show: false,
                },
                axisTicks: {
                    show: false,
                },
                tooltip: {
                    enabled: false,
                },
            },
            yaxis: {
                labels: {
                    show: false,
                },
            },
        };

        const chartTwentyOne = new ApexCharts(chartEl, chartOptions);
        chartTwentyOne.render();
        return chartTwentyOne;
    }
}
export default initChartTwentyOne;
