import { createPopper } from '@popperjs/core';
import Alpine from 'alpinejs';
import ApexCharts from 'apexcharts';
import './bootstrap';
import './components/popover';
import './components/tooltip';
import { registerFormDatePicker } from './components/form-date-picker';
import { registerAppDialog } from './components/app-dialog';
import { initFlashToastr } from './components/toastr-flash';
// Prism
import Prism from 'prismjs';
import 'prismjs/components/prism-bash';
import 'prismjs/components/prism-css';
import 'prismjs/components/prism-json';
import 'prismjs/components/prism-jsx';
import 'prismjs/components/prism-markdown';
import 'prismjs/components/prism-scss';
import 'prismjs/components/prism-tsx';
import 'prismjs/components/prism-typescript';
import 'prismjs/plugins/line-numbers/prism-line-numbers';
import 'prismjs/plugins/line-numbers/prism-line-numbers.css';
// flatpickr
import flatpickr from 'flatpickr';
import 'flatpickr/dist/flatpickr.min.css';
// FullCalendar
import { Calendar } from '@fullcalendar/core';

import 'swiper/css';
import 'swiper/css/navigation';
import 'swiper/css/pagination';
import { register } from 'swiper/element/bundle';
// register Swiper custom elements
register();


window.Alpine = Alpine;
window.createPopper = createPopper;
window.ApexCharts = ApexCharts;
window.Prism = Prism;
window.flatpickr = flatpickr;
window.FullCalendar = Calendar;


// Register Alpine.js components before initializing
registerFormDatePicker(Alpine);
registerAppDialog(Alpine);

Alpine.data("dropdown", () => ({
    open: false,
    toggle() {
        this.open = !this.open;
        if (this.open) this.position();
    },
    position() {
        this.$nextTick(() => {
            const button = this.$el;
            const dropdown = this.$refs.dropdown;
            const rect = button.getBoundingClientRect();

            dropdown.style.position = "fixed";
            dropdown.style.top = `${rect.bottom + window.scrollY}px`;
            dropdown.style.right = `${window.innerWidth - rect.right}px`;
            dropdown.style.zIndex = "999";

            // Reposition if would overflow viewport
            const dropdownRect = dropdown.getBoundingClientRect();
            if (dropdownRect.bottom > window.innerHeight) {
                dropdown.style.top = `${rect.top + window.scrollY - dropdownRect.height}px`;
            }
        });
    },
    init() {
        this.$watch("open", (value) => {
            if (value) this.position();
        });
    },
}));

async function bootAlpine() {
    if (document.querySelector('[x-data*="communicationsCompose"]')) {
        const { communicationsCompose } = await import('./components/communications-compose');
        window.communicationsCompose = communicationsCompose;
    }

    Alpine.start();
}

bootAlpine();

// Initialize components on DOM ready
document.addEventListener('DOMContentLoaded', () => {
    initFlashToastr();

    // Map imports
    if (document.querySelector('#mapOne')) {
        import('./components/map').then(module => module.initMap());
    }

    // Chart imports
    if (document.querySelector('#chartOne')) {
        import('./components/chart/chart-1').then(module => module.initChartOne());
    }
    if (document.querySelector('#chartTwo')) {
        import('./components/chart/chart-2').then(module => module.initChartTwo());
    }
    if (document.querySelector('#chartThree')) {
        import('./components/chart/chart-3').then(module => module.initChartThree());
    }
    if (document.querySelector('#chartFour')) {
        import('./components/chart/chart-4').then(module => module.initChartFour());
    }
    if (document.querySelector('#chartFive')) {
        import('./components/chart/chart-5').then(module => module.initChartFive());
    }
    if (document.querySelector('#chartSix')) {
        import('./components/chart/chart-6').then(module => module.initChartSix());
    }
    if (document.querySelector('#chartSeven')) {
        import('./components/chart/chart-7').then(module => module.initChartSeven());
    }
    if (document.querySelector('#chartEight')) {
        import('./components/chart/chart-8').then(module => module.initChartEight());
    }
    if (document.querySelectorAll('.chartNine').length) {
        import('./components/chart/chart-9').then(module => module.initChartNine());
    }
    if (document.querySelector('#chartTen')) {
        import('./components/chart/chart-10').then(module => module.initChartTen());
    }
    if (document.querySelector('#chartEleven')) {
        import('./components/chart/chart-11').then(module => module.initChartEleven());
    }
    if (document.querySelector('#chartTwelve')) {
        import('./components/chart/chart-12').then(module => module.initChartTwelve());
    }
    if (document.querySelector('#chartThirteen')) {
        import('./components/chart/chart-13').then(module => module.initChartThirteen());
    }
    if (document.querySelector('#chartFourteen')) {
        import('./components/chart/chart-14').then(module => module.initChartFourteen());
    }
    if (document.querySelector('#chartFifteen')) {
        import('./components/chart/chart-15').then(module => module.initChartFifteen());
    }
    if (document.querySelector('#chartSixteen')) {
        import('./components/chart/chart-16').then(module => module.initChartSixteen());
    }
    if (document.querySelector('#chartSeventeen')) {
        import('./components/chart/chart-17').then(module => module.initChartSeventeen());
    }
    if (document.querySelector('#chartEighteen')) {
        import('./components/chart/chart-18').then(module => module.initChartEighteen());
    }
    if (document.querySelector('#chartNineteen')) {
        import('./components/chart/chart-19').then(module => module.initChartNineteen());
    }
    if (document.querySelector('#chartTwenty')) {
        import('./components/chart/chart-20').then(module => module.initChartTwenty());
    }
    if (document.querySelector('#chartTwentyOne')) {
        import('./components/chart/chart-21').then(module => module.initChartTwentyOne());
    }
    if (document.querySelector('#chartTwentyTwo')) {
        import('./components/chart/chart-22').then(module => module.initChartTwentyTwo());
    }

    // Prism highlight
    Prism.highlightAll();

    // Calendar init
    if (document.querySelector('#calendar')) {
        import('./components/calendar-init').then(module => module.calendarInit());
    }

    // Select2 multi-select (locations, clients, etc.)
    if (document.querySelector('.js-select2-multi')) {
        import('./components/select2-init').then(module => module.initSelect2());
    }

    // copy button
    const copyInput = document.getElementById("copy-input");
    if (copyInput) {
        // Select the copy button and input field
        const copyButton = document.getElementById("copy-button");
        const copyText = document.getElementById("copy-text");
        const websiteInput = document.getElementById("website-input");

        // Event listener for the copy button
        copyButton.addEventListener("click", () => {
            // Copy the input value to the clipboard
            navigator.clipboard.writeText(websiteInput.value).then(() => {
                // Change the text to "Copied"
                copyText.textContent = "Copied";

                // Reset the text back to "Copy" after 2 seconds
                setTimeout(() => {
                    copyText.textContent = "Copy";
                }, 2000);
            });
        });
    }
});
