
export function initChartEleven() {
    const chartElevenSelector = document.querySelector("#chartEleven");
    if (chartElevenSelector) {
        const chartOptions = {
            series: [90],
            colors: ["#465FFF"],
            chart: {
                fontFamily: "Outfit, sans-serif",
                type: "radialBar",
                height: 360,
                sparkline: {
                    enabled: true,
                },
            },
            plotOptions: {
                radialBar: {
                    startAngle: -85,
                    endAngle: 85,
                    hollow: {
                        size: "80%",
                    },
                    track: {
                        background: "#E4E7EC",
                        strokeWidth: "100%",
                        margin: 5, // margin is in pixels
                    },
                    dataLabels: {
                        name: {
                            show: false,
                        },
                        value: {
                            fontSize: "36px",
                            fontWeight: "600",
                            offsetY: -25,
                            color: "#1D2939",
                            formatter: function (val) {
                                return "$" + val;
                            },
                        },
                    },
                },
            },
            fill: {
                type: "solid",
                colors: ["#465FFF"],
            },
            stroke: {
                lineCap: "round",
            },
            labels: ["June Goals"],
        };
        const chartEleven = new ApexCharts(chartElevenSelector, chartOptions);
        chartEleven.render();
        return chartEleven;
    }

}

export default initChartEleven;
