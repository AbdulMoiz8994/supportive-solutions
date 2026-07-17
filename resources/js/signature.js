// Simple Signature Canvas Logic
document.addEventListener('DOMContentLoaded', () => {
    const signaturePads = document.querySelectorAll('.signature-pad');
    
    signaturePads.forEach(pad => {
        const canvas = pad.querySelector('canvas');
        const ctx = canvas.getContext('2d');
        const clearBtn = pad.querySelector('.clear-signature');
        let painting = false;

        function startPosition(e) {
            painting = true;
            draw(e);
        }

        function finishedPosition() {
            painting = false;
            ctx.beginPath();
        }

        function draw(e) {
            if(!painting) return;
            ctx.lineWidth = 2;
            ctx.lineCap = 'round';
            ctx.strokeStyle = '#3C50E0'; // Brand Color

            const rect = canvas.getBoundingClientRect();
            const x = (e.clientX || e.touches[0].clientX) - rect.left;
            const y = (e.clientY || e.touches[0].clientY) - rect.top;

            ctx.lineTo(x, y);
            ctx.stroke();
            ctx.beginPath();
            ctx.moveTo(x, y);
        }

        canvas.addEventListener('mousedown', startPosition);
        canvas.addEventListener('mouseup', finishedPosition);
        canvas.addEventListener('mousemove', draw);
        canvas.addEventListener('touchstart', startPosition);
        canvas.addEventListener('touchend', finishedPosition);
        canvas.addEventListener('touchmove', draw);

        clearBtn.addEventListener('click', () => {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
        });
    });
});
