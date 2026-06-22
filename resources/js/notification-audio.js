/**
 * Bildirim sesleri — tarayıcı autoplay kilidini kullanıcı etkileşimiyle açar.
 */
let audioCtx = null;
let soundEnabled = localStorage.getItem('hsp_sound_enabled') === '1';
let unlockHandler = null;

export function isNotificationSoundEnabled() {
    return soundEnabled;
}

export async function initNotificationAudio(options = {}) {
    const { gateId = 'notificationSoundGate', buttonId = 'notificationSoundEnable' } = options;
    const gate = document.getElementById(gateId);
    const button = document.getElementById(buttonId);

    if (!soundEnabled) {
        gate?.removeAttribute('hidden');
        bindAudioUnlockOnGesture();
    } else {
        await ensureAudioContext(false);
        gate?.setAttribute('hidden', 'hidden');
    }

    button?.addEventListener('click', async () => {
        const ok = await ensureAudioContext(true);
        if (!ok) return;

        soundEnabled = true;
        localStorage.setItem('hsp_sound_enabled', '1');
        unbindAudioUnlockOnGesture();
        gate?.setAttribute('hidden', 'hidden');
        playOrderDing();
    });

    if (soundEnabled) {
        bindAudioUnlockOnGesture();
    }
}

function bindAudioUnlockOnGesture() {
    if (unlockHandler) return;

    unlockHandler = async () => {
        if (!soundEnabled) return;
        const ok = await ensureAudioContext(false);
        if (ok) {
            unbindAudioUnlockOnGesture();
        }
    };

    document.addEventListener('pointerdown', unlockHandler, { capture: true });
    document.addEventListener('keydown', unlockHandler, { capture: true });
}

function unbindAudioUnlockOnGesture() {
    if (!unlockHandler) return;
    document.removeEventListener('pointerdown', unlockHandler, { capture: true });
    document.removeEventListener('keydown', unlockHandler, { capture: true });
    unlockHandler = null;
}

async function ensureAudioContext(playTest = false) {
    const AudioCtx = window.AudioContext || window.webkitAudioContext;
    if (!AudioCtx) return false;

    if (!audioCtx) {
        audioCtx = new AudioCtx();
    }

    if (audioCtx.state === 'suspended') {
        try {
            await audioCtx.resume();
        } catch {
            return false;
        }
    }

    if (playTest && audioCtx.state !== 'running') {
        return false;
    }

    return audioCtx.state === 'running';
}

function playTone(fn) {
    if (!soundEnabled) return;

    try {
        if (!audioCtx || audioCtx.state !== 'running') {
            void ensureAudioContext(false);
        }
        if (!audioCtx || audioCtx.state !== 'running') return;
        fn(audioCtx);
    } catch {
        /* sessiz */
    }
}

export function playOrderDing(subtle = false) {
    playTone((ctx) => {
        const t = ctx.currentTime;
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.type = 'sine';
        osc.frequency.setValueAtTime(880, t);
        osc.frequency.exponentialRampToValueAtTime(660, t + 0.12);
        gain.gain.setValueAtTime(0.0001, t);
        gain.gain.exponentialRampToValueAtTime(subtle ? 0.07 : 0.12, t + 0.02);
        gain.gain.exponentialRampToValueAtTime(0.0001, t + (subtle ? 0.22 : 0.35));
        osc.connect(gain);
        gain.connect(ctx.destination);
        osc.start(t);
        osc.stop(t + (subtle ? 0.25 : 0.4));
    });
}

export function playCallAlert(subtle = false) {
    playTone((ctx) => {
        const t = ctx.currentTime;
        [0, 0.18].forEach((offset, i) => {
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.type = 'square';
            osc.frequency.setValueAtTime(1200 + i * 200, t + offset);
            gain.gain.setValueAtTime(0.0001, t + offset);
            gain.gain.exponentialRampToValueAtTime(subtle ? 0.09 : 0.14, t + offset + 0.02);
            gain.gain.exponentialRampToValueAtTime(0.0001, t + offset + (subtle ? 0.14 : 0.2));
            osc.connect(gain);
            gain.connect(ctx.destination);
            osc.start(t + offset);
            osc.stop(t + offset + (subtle ? 0.16 : 0.22));
        });
    });
}
