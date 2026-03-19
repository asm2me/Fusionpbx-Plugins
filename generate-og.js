// Run: node generate-og.js
// Requires: npm install canvas
const { createCanvas } = require('canvas');
const fs = require('fs');

const width = 1200;
const height = 630;
const canvas = createCanvas(width, height);
const ctx = canvas.getContext('2d');

// Background gradient
const grad = ctx.createLinearGradient(0, 0, width, height);
grad.addColorStop(0, '#5B21B6');
grad.addColorStop(0.5, '#6C2BD9');
grad.addColorStop(1, '#7C3AED');
ctx.fillStyle = grad;
ctx.fillRect(0, 0, width, height);

// Decorative circles
ctx.fillStyle = 'rgba(255,255,255,0.04)';
ctx.beginPath(); ctx.arc(1050, 100, 200, 0, Math.PI * 2); ctx.fill();
ctx.beginPath(); ctx.arc(150, 530, 150, 0, Math.PI * 2); ctx.fill();
ctx.fillStyle = 'rgba(255,255,255,0.06)';
ctx.beginPath(); ctx.arc(900, 500, 100, 0, Math.PI * 2); ctx.fill();

// Logo text
ctx.textAlign = 'center';
ctx.font = 'bold 72px Arial, Helvetica, sans-serif';
ctx.fillStyle = '#FFFFFF';
ctx.fillText('VOIP', 555, 260);
ctx.fillStyle = '#06B6D4';
ctx.fillText('@', 635, 260);
ctx.fillStyle = '#FFFFFF';
ctx.fillText(' Cloud', 735, 260);

// Tagline
ctx.font = '32px Arial, Helvetica, sans-serif';
ctx.fillStyle = 'rgba(255,255,255,0.85)';
ctx.fillText('Enterprise Cloud PBX Platform', 600, 320);

// Divider
const divGrad = ctx.createLinearGradient(450, 0, 750, 0);
divGrad.addColorStop(0, '#06B6D4');
divGrad.addColorStop(1, '#8B5CF6');
ctx.fillStyle = divGrad;
ctx.fillRect(450, 350, 300, 3);

// Features
ctx.font = '22px Arial, Helvetica, sans-serif';
ctx.fillStyle = 'rgba(255,255,255,0.6)';
ctx.fillText('Smart IVR  •  Call Recording  •  SIP Trunking  •  WebRTC', 600, 400);

// CTA Button
ctx.fillStyle = '#FFFFFF';
const btnW = 340, btnH = 56, btnX = 430, btnY = 440;
ctx.beginPath();
ctx.roundRect(btnX, btnY, btnW, btnH, 28);
ctx.fill();

ctx.font = 'bold 22px Arial, Helvetica, sans-serif';
ctx.fillStyle = '#6C2BD9';
ctx.fillText('Start Your Free Trial', 600, 477);

// URL at bottom
ctx.font = '16px Arial, Helvetica, sans-serif';
ctx.fillStyle = 'rgba(255,255,255,0.35)';
ctx.fillText('www.voipat.com', 600, 590);

// Save
const buffer = canvas.toBuffer('image/png');
fs.writeFileSync(__dirname + '/og-image.png', buffer);
console.log('og-image.png created!');
