export default function initMotion() {
    const root = document.querySelector('[data-motion]');
    if (!root || !('IntersectionObserver' in window)) return;
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

    const skip = '[x-data^="reservationPage"], [data-no-reveal]';
    let targets = [...root.querySelectorAll('section > div > *, .grid > *, article')].filter((el) => !el.closest(skip));
    targets = targets.filter((el) => !targets.some((other) => other !== el && el.contains(other)));

    const delays = new Map();
    for (const el of targets) {
        const index = delays.get(el.parentElement) ?? 0;
        delays.set(el.parentElement, index + 1);
        el.style.setProperty('--reveal-delay', `${Math.min(index, 6) * 80}ms`);
        el.classList.add('reveal');
    }

    const observer = new IntersectionObserver((entries) => {
        for (const entry of entries) {
            const el = entry.target;
            if (entry.isIntersecting) {
                el.classList.add('is-visible');
                el.classList.remove('is-above');
            } else {
                el.classList.remove('is-visible');
                el.classList.toggle('is-above', entry.boundingClientRect.bottom < 0);
            }
        }
    }, { threshold: 0.1, rootMargin: '0px 0px -6% 0px' });

    targets.forEach((el) => observer.observe(el));

    const bar = document.createElement('div');
    bar.className = 'scroll-progress';
    document.body.appendChild(bar);

    const header = document.querySelector('[data-header]');
    const parallax = [...document.querySelectorAll('[data-parallax]')];
    const fades = [...document.querySelectorAll('[data-scroll-fade]')];
    let ticking = false;

    const update = () => {
        ticking = false;
        const y = window.scrollY;
        const max = document.documentElement.scrollHeight - window.innerHeight;
        bar.style.transform = `scaleX(${max > 0 ? Math.min(1, y / max) : 0})`;
        if (header) header.classList.toggle('is-scrolled', y > 24);
        if (y > window.innerHeight * 1.4) {
            for (const el of fades) el.style.opacity = '0';
            return;
        }
        for (const el of parallax) el.style.transform = `translate3d(0, ${y * Number(el.dataset.parallax || 0.25)}px, 0)`;
        for (const el of fades) {
            const t = Math.min(1, y / (window.innerHeight * 0.65));
            el.style.opacity = String(1 - t);
            el.style.transform = `translate3d(0, ${y * 0.18}px, 0)`;
        }
    };

    window.addEventListener('scroll', () => {
        if (ticking) return;
        ticking = true;
        requestAnimationFrame(update);
    }, { passive: true });
    update();
}
