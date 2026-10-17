const canvas = document.getElementById('wheel-canvas');
const ctx = canvas.getContext('2d');
const btnSpin = document.getElementById('btn-spin');
const btnUpdate = document.getElementById('btn-update');
const inputArea = document.getElementById('items-input');
const modal = document.getElementById('result-modal');
const resultText = document.getElementById('result-text');
const btnCloseModal = document.getElementById('btn-close-modal');

let items = [];
let colors = ['#f87171', '#fb923c', '#fbbf24', '#a3e635', '#34d399', '#22d3ee', '#818cf8', '#c084fc', '#f472b6'];
let currentRotation = 0;
let isSpinning = false;

function init() {
    updateItems();
    drawWheel();
}

function updateItems() {
    const text = inputArea.value;
    items = text.split('\n').map(item => item.trim()).filter(item => item !== '');
    if (items.length === 0) {
        items = ['១', '២', '៣', '៤'];
    }
}

function drawWheel() {
    const numSlices = items.length;
    const sliceAngle = 2 * Math.PI / numSlices;
    const cx = canvas.width / 2;
    const cy = canvas.height / 2;
    const radius = cx;

    ctx.clearRect(0, 0, canvas.width, canvas.height);

    for (let i = 0; i < numSlices; i++) {
        const startAngle = i * sliceAngle;
        const endAngle = startAngle + sliceAngle;

        ctx.beginPath();
        ctx.moveTo(cx, cy);
        ctx.arc(cx, cy, radius, startAngle, endAngle);
        ctx.fillStyle = colors[i % colors.length];
        ctx.fill();
        ctx.stroke();

        // Draw Text
        ctx.save();
        ctx.translate(cx, cy);
        ctx.rotate(startAngle + sliceAngle / 2);
        ctx.textAlign = 'right';
        ctx.fillStyle = '#fff';
        ctx.font = 'bold 20px Kantumruy Pro';
        ctx.shadowColor = 'rgba(0,0,0,0.5)';
        ctx.shadowBlur = 4;
        ctx.fillText(items[i], radius - 20, 8);
        ctx.restore();
    }
}

btnUpdate.addEventListener('click', () => {
    if (isSpinning) return;
    updateItems();
    currentRotation = 0;
    canvas.style.transition = 'none';
    canvas.style.transform = `rotate(0deg)`;
    drawWheel();
});

btnSpin.addEventListener('click', () => {
    if (isSpinning) return;
    isSpinning = true;

    // Random spins (5 to 10 full rotations) + random angle
    const spinTurns = Math.floor(Math.random() * 5) + 5;
    const randomAngle = Math.floor(Math.random() * 360);
    const totalRotation = spinTurns * 360 + randomAngle;
    
    currentRotation += totalRotation;
    
    canvas.style.transition = 'transform 4s cubic-bezier(0.17, 0.67, 0.12, 0.99)';
    canvas.style.transform = `rotate(${currentRotation}deg)`;

    // Calculate result
    // Wheel rotates clockwise, so the top pointer (which is at -90deg or 270deg relative to canvas 0)
    // 0 deg of canvas is at 3 o'clock. Top is 270 deg (or -90).
    setTimeout(() => {
        isSpinning = false;
        
        const actualRotation = currentRotation % 360;
        // Pointer is at top (270 degrees on canvas)
        // Let's find which slice is at 270 degrees
        // Because canvas rotated clockwise by actualRotation, 
        // the slice at top is (270 - actualRotation + 360) % 360
        let pointerAngle = (270 - actualRotation + 360) % 360;
        
        const sliceAngle = 360 / items.length;
        const winningIndex = Math.floor(pointerAngle / sliceAngle);
        
        const winner = items[winningIndex];
        resultText.textContent = winner;
        modal.classList.remove('hidden');
    }, 4000);
});

btnCloseModal.addEventListener('click', () => {
    modal.classList.add('hidden');
});

// Initial draw
init();
