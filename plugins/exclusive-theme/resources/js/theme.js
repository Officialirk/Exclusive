/**
 * Progressive enhancement only — the panel's Filament/Livewire markup is core-owned,
 * so this plugin never rewrites it, it only decorates it after each render.
 */
(function () {
    const GAUGE_LABELS = [
        { match: /^cpu$/i, className: 'ex-cpu' },
        { match: /^memory$/i, className: 'ex-memory' },
        { match: /^disk$/i, className: 'ex-disk' },
    ];

    function percentFromValueText(text) {
        const match = text.match(/(\d+(?:\.\d+)?)\s*%/);

        return match ? Math.min(100, parseFloat(match[1])) : null;
    }

    function decorateStat(labelEl) {
        const label = labelEl.textContent.trim();
        const gauge = GAUGE_LABELS.find((g) => g.match.test(label));
        if (!gauge) {
            return;
        }

        const statCard = labelEl.closest('[class*="fi-wi-stats-overview-stat"]') ?? labelEl.parentElement?.parentElement;
        if (!statCard || statCard.querySelector('.ex-gauge-track')) {
            return;
        }

        const valueEl = statCard.querySelector('[class*="fi-wi-stats-overview-stat-value"]') ?? statCard.querySelector('div + div');
        if (!valueEl) {
            return;
        }

        const percent = percentFromValueText(valueEl.textContent);
        if (percent === null) {
            return;
        }

        const track = document.createElement('div');
        track.className = 'ex-gauge-track';
        track.innerHTML = `<div class="ex-gauge-fill ${gauge.className}" style="width:${percent}%"></div>`;
        statCard.appendChild(track);
    }

    function decorateGauges() {
        document
            .querySelectorAll('[class*="fi-wi-stats-overview-stat-label"], [class*="fi-wi-stats-overview-stat"] > div:first-child')
            .forEach(decorateStat);
    }

    const CONSOLE_THEME = {
        background: '#030201',
        foreground: '#d9c08a',
        cursor: '#e8b84b',
        black: '#0c0a06',
        red: '#d0483e',
        green: '#5fd97a',
        yellow: '#e8b84b',
        blue: '#5fb4d9',
        magenta: '#9c6e20',
        cyan: '#7bc1e8',
        white: '#f6efe0',
        selection: 'rgba(232, 184, 75, 0.35)',
    };

    // The console widget (resources/views/filament/components/server-console.blade.php)
    // is core-owned and constructs its own hardcoded xterm theme inline, so the only
    // way to retint it without forking that view is to patch xterm's shared
    // Terminal.prototype.open — once patched it silently overrides the theme option
    // for every terminal opened afterwards, regardless of script load order.
    let consolePatchAttempts = 0;
    function patchConsoleTheme() {
        if (!window.Xterm?.Terminal || window.Xterm.Terminal.prototype.__exclusivePatched) {
            if (consolePatchAttempts++ < 40 && !window.Xterm?.Terminal) {
                setTimeout(patchConsoleTheme, 250);
            }

            return;
        }

        const originalOpen = window.Xterm.Terminal.prototype.open;
        window.Xterm.Terminal.prototype.open = function (...args) {
            this.options.theme = { ...this.options.theme, ...CONSOLE_THEME };

            return originalOpen.apply(this, args);
        };
        window.Xterm.Terminal.prototype.__exclusivePatched = true;
    }

    function run() {
        decorateGauges();
    }

    document.addEventListener('livewire:init', () => {
        Livewire.hook('morph.updated', () => run());
    });

    document.addEventListener('livewire:navigated', run);
    document.addEventListener('DOMContentLoaded', run);
    patchConsoleTheme();
    setInterval(decorateGauges, 1000);
})();
