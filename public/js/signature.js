/**
 * signature.js
 * Simple canvas signature pad for iPad/Tablet clinical assessments.
 */
class SignaturePad {
    constructor(canvasId) {
        this.canvas = document.getElementById(canvasId);
        if (!this.canvas) return;

        this.ctx = this.canvas.getContext('2d');
        this.drawing = false;
        this.ctx.lineWidth = 2;
        this.ctx.lineCap = 'round';
        this.ctx.strokeStyle = '#000000';

        this.init();
    }

    init() {
        const events = {
            mousedown: (e) => this.start(e.offsetX, e.offsetY),
            mousemove: (e) => this.draw(e.offsetX, e.offsetY),
            mouseup: () => this.stop(),
            touchstart: (e) => {
                const rect = this.canvas.getBoundingClientRect();
                this.start(e.touches[0].clientX - rect.left, e.touches[0].clientY - rect.top);
            },
            touchmove: (e) => {
                const rect = this.canvas.getBoundingClientRect();
                this.draw(e.touches[0].clientX - rect.left, e.touches[0].clientY - rect.top);
                e.preventDefault();
            },
            touchend: () => this.stop()
        };

        Object.keys(events).forEach(event => {
            this.canvas.addEventListener(event, events[event]);
        });
    }

    start(x, y) {
        this.drawing = true;
        this.ctx.beginPath();
        this.ctx.moveTo(x, y);
    }

    draw(x, y) {
        if (!this.drawing) return;
        this.ctx.lineTo(x, y);
        this.ctx.stroke();
    }

    stop() {
        this.drawing = false;
    }

    clear() {
        this.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);
    }

    getBase64() {
        return this.canvas.toDataURL('image/png');
    }
}

window.SignaturePad = SignaturePad;
