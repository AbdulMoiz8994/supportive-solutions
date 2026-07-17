<div class="mb-10">
    <div class="flex flex-wrap justify-center gap-1 mb-2 font-bold text-title-md text-brand-500 dark:text-brand-400 xl:text-title-lg">
        <!-- timer days -->
        <div id="days-container" class="flex gap-1"></div>
        <span>:</span>
        <!-- timer hours -->
        <div id="hours-container" class="flex gap-1"></div>
        <span>:</span>
        <!-- timer minutes -->
        <div id="minutes-container" class="flex gap-1"></div>
        <span>:</span>
        <!-- timer seconds -->
        <div id="seconds-container" class="flex gap-1"></div>
    </div>
    <div class="text-base text-center text-gray-500 dark:text-gray-400">
        <div class="flex justify-center gap-0.5">
            <div id="days-label-container" class="flex gap-0.5"></div>
            <div>days left</div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const daysContainer = document.getElementById('days-container');
    const hoursContainer = document.getElementById('hours-container');
    const minutesContainer = document.getElementById('minutes-container');
    const secondsContainer = document.getElementById('seconds-container');
    const daysLabelContainer = document.getElementById('days-label-container');
    
    const endTime = new Date('December 20, 2025 23:59:59 GMT+0530').getTime();
    
    const format = (value) => {
        if (value < 10) {
            return '0' + Math.floor(value);
        }
        return Math.floor(value).toString();
    };
    
    const getMaxValueForUnit = (unit) => {
        switch (unit) {
            case 'days':
                return 365;
            case 'hours':
                return 24;
            case 'minutes':
                return 60;
            case 'seconds':
                return 60;
            default:
                return 1;
        }
    };
    
    const getTimeArray = (value, unit) => {
        let stringValue = format(value).toString();
        let percentage = (value / getMaxValueForUnit(unit)) * 100;
        return stringValue.split('').map((digit) => ({
            value: digit,
            visible: true,
            remainingPercentage: percentage,
        }));
    };
    
    const renderDigits = (container, digits) => {
        container.innerHTML = '';
        digits.forEach(digit => {
            if (digit.visible) {
                const box = document.createElement('div');
                box.className = 'timer-box';
                const span = document.createElement('span');
                span.textContent = digit.value;
                box.appendChild(span);
                container.appendChild(box);
            }
        });
    };
    
    const updateTimer = () => {
        const now = new Date().getTime();
        const timeLeft = (endTime - now) / 1000;
        
        if (timeLeft <= 0) {
            clearInterval(counter);
            renderDigits(daysContainer, [{ value: '0', visible: true }]);
            renderDigits(hoursContainer, [{ value: '0', visible: true }]);
            renderDigits(minutesContainer, [{ value: '0', visible: true }]);
            renderDigits(secondsContainer, [{ value: '0', visible: true }]);
            renderDigits(daysLabelContainer, [{ value: '0', visible: true }]);
            return;
        }
        
        const daysArray = getTimeArray(timeLeft / (60 * 60 * 24), 'days');
        const hoursArray = getTimeArray((timeLeft / (60 * 60)) % 24, 'hours');
        const minutesArray = getTimeArray((timeLeft / 60) % 60, 'minutes');
        const secondsArray = getTimeArray(timeLeft % 60, 'seconds');
        
        renderDigits(daysContainer, daysArray);
        renderDigits(hoursContainer, hoursArray);
        renderDigits(minutesContainer, minutesArray);
        renderDigits(secondsContainer, secondsArray);
        renderDigits(daysLabelContainer, daysArray);
    };
    
    // Initial render
    updateTimer();
    
    // Update every second
    const counter = setInterval(updateTimer, 1000);
});
</script>