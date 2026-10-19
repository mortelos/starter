<?php

declare(strict_types=1);

// Every visible text on the page against its real background (translucent layers stacked up to the
// first opaque ancestor), WCAG contrast below 4.5. Colours go through a canvas pixel, so oklch() counts.
const LOW_CONTRAST_TEXTS = <<<'JS'
() => {
    const x = Object.assign(document.createElement('canvas'), { width: 1, height: 1 }).getContext('2d', { willReadFrequently: true });
    const px = (c, under) => { x.clearRect(0, 0, 1, 1); if (under) { x.fillStyle = `rgb(${under})`; x.fillRect(0, 0, 1, 1); } x.fillStyle = '#0000'; x.fillStyle = c; x.fillRect(0, 0, 1, 1); return [...x.getImageData(0, 0, 1, 1).data]; };
    const lum = (rgb) => rgb.slice(0, 3).map(v => (v /= 255) <= 0.03928 ? v / 12.92 : ((v + 0.055) / 1.055) ** 2.4).reduce((s, v, i) => s + v * [0.2126, 0.7152, 0.0722][i], 0);
    const background = (el) => {
        const layers = [];
        for (let e = el; e; e = e.parentElement) { const c = getComputedStyle(e).backgroundColor, a = px(c)[3]; if (a > 0) { layers.push(c); if (a === 255) break; } }
        return layers.reverse().reduce((under, c) => px(c, under.slice(0, 3)), [255, 255, 255]);
    };
    return [...document.body.querySelectorAll('*')].filter(e => {
        const r = e.getBoundingClientRect();
        return r.width > 1 && r.height > 1 && getComputedStyle(e).visibility !== 'hidden' && !e.closest('template')
            && [...e.childNodes].some(n => n.nodeType === 3 && n.textContent.trim().length > 1);
    }).map(e => {
        const bg = background(e), [a, b] = [lum(px(getComputedStyle(e).color, bg.slice(0, 3))), lum(bg)];
        return [e.textContent.trim().slice(0, 40), +((Math.max(a, b) + 0.05) / (Math.min(a, b) + 0.05)).toFixed(2)];
    }).filter(([, ratio]) => ratio < 4.5);
}
JS;
