import { BrowserMultiFormatReader } from '@zxing/browser';
import { BarcodeFormat, DecodeHintType } from '@zxing/library';

export const NATIVE_BARCODE_FORMATS = ['ean_13', 'ean_8', 'upc_a', 'upc_e', 'code_128'];
export const ZXING_BARCODE_FORMATS = [
    BarcodeFormat.EAN_13,
    BarcodeFormat.EAN_8,
    BarcodeFormat.UPC_A,
    BarcodeFormat.UPC_E,
    BarcodeFormat.CODE_128,
];

const digitMap = new Map([
    ['۰', '0'], ['۱', '1'], ['۲', '2'], ['۳', '3'], ['۴', '4'],
    ['۵', '5'], ['۶', '6'], ['۷', '7'], ['۸', '8'], ['۹', '9'],
    ['٠', '0'], ['١', '1'], ['٢', '2'], ['٣', '3'], ['٤', '4'],
    ['٥', '5'], ['٦', '6'], ['٧', '7'], ['٨', '8'], ['٩', '9'],
]);

export class ScannerError extends Error {
    constructor(code, cause = null) {
        super(code);
        this.name = 'ScannerError';
        this.code = code;
        this.cause = cause;
    }
}

export function normalizeBarcode(rawValue) {
    const converted = [...String(rawValue ?? '').trim()]
        .map(character => digitMap.get(character) ?? character)
        .join('')
        .replace(/[\u0000-\u001f\u007f]/g, '');

    return /^[\d\s-]+$/.test(converted)
        ? converted.replace(/[\s-]+/g, '')
        : converted.trim();
}

export function findProductByBarcode(products, rawValue) {
    const barcode = normalizeBarcode(rawValue);
    if (!barcode) return null;

    return products.find(product => normalizeBarcode(product.barcode) === barcode) ?? null;
}

export function createDecodeGate({ debounceMs = 1200, now = () => Date.now(), singleShot = true } = {}) {
    let completed = false;
    let lastValue = null;
    let lastAcceptedAt = Number.NEGATIVE_INFINITY;

    return {
        accept(rawValue) {
            const value = normalizeBarcode(rawValue);
            const acceptedAt = now();
            if (!value || (singleShot && completed)) return null;
            if (value === lastValue && acceptedAt - lastAcceptedAt < debounceMs) return null;

            completed = true;
            lastValue = value;
            lastAcceptedAt = acceptedAt;

            return value;
        },
    };
}

export function scannerErrorMessage(error) {
    const code = error?.code;
    const name = error?.cause?.name ?? error?.name;

    if (code === 'INSECURE_CONTEXT') return 'برای دسترسی امن به دوربین، برنامه باید با HTTPS باز شود.';
    if (code === 'CAMERA_UNAVAILABLE' || ['NotFoundError', 'OverconstrainedError'].includes(name)) return 'دوربین سازگار در این دستگاه پیدا نشد. بارکد را دستی وارد کنید.';
    if (['NotAllowedError', 'SecurityError'].includes(name)) return 'اجازهٔ دسترسی به دوربین داده نشد. مجوز دوربین را فعال کنید و دوباره تلاش کنید.';
    if (['NotReadableError', 'AbortError'].includes(name)) return 'دوربین در دسترس نیست یا برنامهٔ دیگری از آن استفاده می‌کند.';
    if (code === 'FORMAT_UNAVAILABLE') return 'این مرورگر فرمت‌های بارکد موردنیاز فروشگاه را پشتیبانی نمی‌کند.';

    return 'راه‌اندازی اسکنر ممکن نشد. بارکد را دستی وارد کنید.';
}

function stopStream(stream) {
    stream?.getTracks?.().forEach(track => track.stop());
}

function createZxingReader() {
    const hints = new Map();
    hints.set(DecodeHintType.POSSIBLE_FORMATS, ZXING_BARCODE_FORMATS);

    return new BrowserMultiFormatReader(hints, {
        delayBetweenScanAttempts: 120,
        delayBetweenScanSuccess: 500,
    });
}

export async function startBarcodeScannerSession({
    video,
    onDecode,
    onError = () => {},
    secureContext = globalThis.isSecureContext === true,
    hostname = globalThis.location?.hostname ?? '',
    barcodeDetectorClass = globalThis.BarcodeDetector,
    mediaDevices = globalThis.navigator?.mediaDevices,
    requestFrame = globalThis.requestAnimationFrame?.bind(globalThis) ?? (callback => setTimeout(callback, 16)),
    cancelFrame = globalThis.cancelAnimationFrame?.bind(globalThis) ?? clearTimeout,
    readerFactory = createZxingReader,
} = {}) {
    if (!secureContext && !['localhost', '127.0.0.1'].includes(hostname)) {
        throw new ScannerError('INSECURE_CONTEXT');
    }
    if (!video || typeof onDecode !== 'function' || !mediaDevices?.getUserMedia) {
        throw new ScannerError('CAMERA_UNAVAILABLE');
    }

    const gate = createDecodeGate();
    let stopped = false;
    let stream = null;
    let fallbackControls = null;
    let frameId = null;

    const stop = () => {
        if (stopped) return;
        stopped = true;
        if (frameId !== null) cancelFrame(frameId);
        fallbackControls?.stop?.();
        stopStream(stream);
        video.pause?.();
        if ('srcObject' in video) video.srcObject = null;
    };

    const accept = rawValue => {
        const value = gate.accept(rawValue);
        if (!value) return false;
        stop();
        onDecode(value);

        return true;
    };

    try {
        if (typeof barcodeDetectorClass === 'function') {
            const supported = typeof barcodeDetectorClass.getSupportedFormats === 'function'
                ? await barcodeDetectorClass.getSupportedFormats()
                : [];
            const formats = NATIVE_BARCODE_FORMATS.filter(format => !supported.length || supported.includes(format));
            if (supported.length && !formats.length) throw new ScannerError('FORMAT_UNAVAILABLE');

            stream = await mediaDevices.getUserMedia({ video: { facingMode: { ideal: 'environment' } }, audio: false });
            video.srcObject = stream;
            await video.play();
            const detector = new barcodeDetectorClass(formats.length ? { formats } : undefined);
            let consecutiveErrors = 0;
            const tick = async () => {
                if (stopped) return;
                try {
                    const result = await detector.detect(video);
                    consecutiveErrors = 0;
                    if (accept(result[0]?.rawValue)) return;
                } catch (error) {
                    consecutiveErrors += 1;
                    if (consecutiveErrors >= 3) {
                        stop();
                        onError(error);
                        return;
                    }
                }
                frameId = requestFrame(tick);
            };
            frameId = requestFrame(tick);

            return { path: 'native', stop };
        }

        const reader = readerFactory();
        const controls = await reader.decodeFromConstraints(
            { video: { facingMode: { ideal: 'environment' } }, audio: false },
            video,
            (result, _error, callbackControls) => {
                if (stopped || !result) return;
                fallbackControls ??= callbackControls;
                accept(typeof result.getText === 'function' ? result.getText() : result.text);
            },
        );
        const controlsWereAvailable = fallbackControls !== null;
        fallbackControls ??= controls;
        if (stopped && !controlsWereAvailable) fallbackControls.stop?.();

        return { path: 'zxing', stop };
    } catch (error) {
        stop();
        if (error instanceof ScannerError) throw error;
        throw new ScannerError('INITIALIZATION_FAILED', error);
    }
}
