import flatpickr from 'flatpickr';

function parseTypedTime(datestr) {
    if (!datestr || !String(datestr).trim()) {
        return null;
    }

    const raw = String(datestr).trim();
    const today = new Date();
    const base = today.getFullYear() + '-' +
        String(today.getMonth() + 1).padStart(2, '0') + '-' +
        String(today.getDate()).padStart(2, '0');

    const candidates = [
        base + ' ' + raw,
        '1970-01-01 ' + raw.replace(/(\d)(AM|PM)/i, '$1 $2'),
    ];

    for (const candidate of candidates) {
        const parsed = Date.parse(candidate);
        if (!Number.isNaN(parsed)) {
            return new Date(parsed);
        }
    }

    const match = raw.match(/^(\d{1,2}):(\d{2})(?::(\d{2}))?\s*(AM|PM)?$/i);
    if (!match) {
        return null;
    }

    let hours = parseInt(match[1], 10);
    const minutes = parseInt(match[2], 10);
    const meridiem = (match[4] || '').toUpperCase();

    if (meridiem === 'PM' && hours < 12) {
        hours += 12;
    }
    if (meridiem === 'AM' && hours === 12) {
        hours = 0;
    }

    const result = new Date();
    result.setHours(hours, minutes, 0, 0);

    return result;
}

export function registerFormDatePicker(Alpine) {
    Alpine.data('formDatePicker', (config = {}) => ({
        flatpickrInstance: null,
        config,

        commitTypedTime(instance) {
            const raw = instance.input.value?.trim();
            if (!raw) {
                return;
            }

            const parsed = parseTypedTime(raw);
            if (parsed) {
                instance.setDate(parsed, true);
            }
        },

        init() {
            this.$nextTick(() => {
                const isTimeOnly = Boolean(this.config.isTimeOnly);
                const fpConfig = {
                    mode: this.config.flatpickrMode || 'single',
                    static: !isTimeOnly,
                    disableMobile: true,
                    monthSelectorType: 'static',
                    allowInput: true,
                    dateFormat: this.config.dateFormat || 'Y-m-d',
                    enableTime: Boolean(this.config.enableTime),
                    noCalendar: Boolean(this.config.noCalendar),
                    minuteIncrement: 1,
                    onChange: (selectedDates, dateStr, instance) => {
                        this.$dispatch('date-change', {
                            selectedDates,
                            dateStr,
                            instance,
                        });
                    },
                };

                if (isTimeOnly) {
                    fpConfig.autoFillDefaultTime = false;
                    fpConfig.parseDate = (datestr) => parseTypedTime(datestr);
                    fpConfig.onClose = (selectedDates, dateStr, instance) => {
                        this.commitTypedTime(instance);
                    };
                }

                if (this.config.defaultDate) {
                    fpConfig.defaultDate = this.config.defaultDate;
                }

                this.flatpickrInstance = flatpickr(this.$refs.dateInput, fpConfig);
            });
        },

        destroy() {
            if (this.flatpickrInstance) {
                this.flatpickrInstance.destroy();
                this.flatpickrInstance = null;
            }
        },
    }));
}
