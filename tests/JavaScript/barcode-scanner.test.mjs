import test from 'node:test';
import assert from 'node:assert/strict';
import {
    BarcodeFormat,
    BinaryBitmap,
    DecodeHintType,
    HybridBinarizer,
    MultiFormatReader,
    RGBLuminanceSource,
} from '@zxing/library';
import {
    NATIVE_BARCODE_FORMATS,
    ZXING_BARCODE_FORMATS,
    ScannerError,
    createDecodeGate,
    findProductByBarcode,
    normalizeBarcode,
    scannerErrorMessage,
    startBarcodeScannerSession,
} from '../../resources/js/barcode-scanner.js';

function fakeVideo() {
    return {
        srcObject: null,
        played: 0,
        paused: 0,
        async play() { this.played += 1; },
        pause() { this.paused += 1; },
    };
}

function createEan13Bitmap(value) {
    const left = ['0001101', '0011001', '0010011', '0111101', '0100011', '0110001', '0101111', '0111011', '0110111', '0001011'];
    const leftEven = ['0100111', '0110011', '0011011', '0100001', '0011101', '0111001', '0000101', '0010001', '0001001', '0010111'];
    const right = ['1110010', '1100110', '1101100', '1000010', '1011100', '1001110', '1010000', '1000100', '1001000', '1110100'];
    const parity = ['LLLLLL', 'LLGLGG', 'LLGGLG', 'LLGGGL', 'LGLLGG', 'LGGLLG', 'LGGGLL', 'LGLGLG', 'LGLGGL', 'LGGLGL'][Number(value[0])];
    let modules = '101';

    for (let index = 1; index <= 6; index += 1) {
        modules += (parity[index - 1] === 'L' ? left : leftEven)[Number(value[index])];
    }
    modules += '01010';
    for (let index = 7; index <= 12; index += 1) modules += right[Number(value[index])];
    modules = `${'0'.repeat(12)}${modules}101${'0'.repeat(12)}`;

    const scale = 4;
    const width = modules.length * scale;
    const height = 140;
    const pixels = new Int32Array(width * height);
    for (let y = 0; y < height; y += 1) {
        for (let x = 0; x < width; x += 1) {
            pixels[(y * width) + x] = modules[Math.floor(x / scale)] === '1' ? 0xff000000 : 0xffffffff;
        }
    }

    return new BinaryBitmap(new HybridBinarizer(new RGBLuminanceSource(pixels, width, height)));
}

test('normalizes numeric barcodes without damaging Code-128 text', () => {
    assert.equal(normalizeBarcode(' ۶۲۶-۱۲۳ ۴۵۶۷۸۹۰ '), '6261234567890');
    assert.equal(normalizeBarcode(' CODE 128-A '), 'CODE 128-A');
    assert.equal(normalizeBarcode(''), '');
    assert.deepEqual(NATIVE_BARCODE_FORMATS, ['ean_13', 'ean_8', 'upc_a', 'upc_e', 'code_128']);
    assert.equal(ZXING_BARCODE_FORMATS.length, 5);
});

test('installed ZXing decoder reads a real EAN-13 image matrix', () => {
    const expected = '5901234123457';
    const hints = new Map([[DecodeHintType.POSSIBLE_FORMATS, [BarcodeFormat.EAN_13]]]);
    const result = new MultiFormatReader().decode(createEan13Bitmap(expected), hints);

    assert.equal(result.getText(), expected);
    assert.equal(result.getBarcodeFormat(), BarcodeFormat.EAN_13);
});

test('single-shot decode gate debounces duplicate and subsequent decodes', () => {
    let time = 1000;
    const gate = createDecodeGate({ now: () => time });
    assert.equal(gate.accept('6261234567890'), '6261234567890');
    time += 10;
    assert.equal(gate.accept('6261234567890'), null);
    time += 2000;
    assert.equal(gate.accept('12345670'), null);
});

test('product lookup resolves existing and leaves inventory quantities untouched', () => {
    const products = [{ id: 11, barcode: '۶۲۶۱۲۳۴۵۶۷۸۹۰', stock_quantity: 7 }];
    const found = findProductByBarcode(products, '6261234567890');
    assert.equal(found.id, 11);
    assert.equal(products[0].stock_quantity, 7);
    assert.equal(findProductByBarcode(products, '9999999999999'), null);
});

test('native BarcodeDetector path decodes once and tears down its stream', async () => {
    const video = fakeVideo();
    const frames = [];
    let trackStops = 0;
    let decoded = null;
    let detectorOptions = null;
    const stream = { getTracks: () => [{ stop: () => { trackStops += 1; } }] };

    class FakeBarcodeDetector {
        static async getSupportedFormats() { return NATIVE_BARCODE_FORMATS; }
        constructor(options) { detectorOptions = options; }
        async detect() { return [{ rawValue: ' ۶۲۶۱۲۳۴۵۶۷۸۹۰ ' }]; }
    }

    const session = await startBarcodeScannerSession({
        video,
        onDecode: value => { decoded = value; },
        secureContext: true,
        barcodeDetectorClass: FakeBarcodeDetector,
        mediaDevices: { getUserMedia: async () => stream },
        requestFrame: callback => { frames.push(callback); return frames.length; },
        cancelFrame: () => {},
    });

    assert.equal(session.path, 'native');
    assert.deepEqual(detectorOptions.formats, NATIVE_BARCODE_FORMATS);
    await frames.shift()();
    assert.equal(decoded, '6261234567890');
    assert.equal(trackStops, 1);
    assert.equal(video.srcObject, null);
    assert.equal(video.paused, 1);
});

test('ZXing fallback initializes when BarcodeDetector is absent and stops after success', async () => {
    const video = fakeVideo();
    let decoded = null;
    let stopCalls = 0;
    let receivedConstraints = null;
    const controls = { stop: () => { stopCalls += 1; } };
    const reader = {
        async decodeFromConstraints(constraints, preview, callback) {
            receivedConstraints = constraints;
            assert.equal(preview, video);
            queueMicrotask(() => callback({ getText: () => '12345670' }, undefined, controls));
            return controls;
        },
    };

    const session = await startBarcodeScannerSession({
        video,
        onDecode: value => { decoded = value; },
        secureContext: true,
        barcodeDetectorClass: undefined,
        mediaDevices: { getUserMedia: async () => { throw new Error('reader owns stream'); } },
        readerFactory: () => reader,
    });
    await Promise.resolve();

    assert.equal(session.path, 'zxing');
    assert.equal(decoded, '12345670');
    assert.deepEqual(receivedConstraints, { video: { facingMode: { ideal: 'environment' } }, audio: false });
    assert.equal(stopCalls, 1);
});

test('manual close tears down an initialized fallback scanner', async () => {
    const video = fakeVideo();
    let stopCalls = 0;
    const session = await startBarcodeScannerSession({
        video,
        onDecode: () => assert.fail('decode should not run'),
        secureContext: true,
        barcodeDetectorClass: undefined,
        mediaDevices: { getUserMedia: async () => ({}) },
        readerFactory: () => ({
            decodeFromConstraints: async () => ({ stop: () => { stopCalls += 1; } }),
        }),
    });

    session.stop();
    session.stop();
    assert.equal(stopCalls, 1);
    assert.equal(video.paused, 1);
});

test('scanner exposes clear Persian messages for security and camera errors', async () => {
    await assert.rejects(
        startBarcodeScannerSession({ video: fakeVideo(), onDecode: () => {}, secureContext: false, hostname: 'shop.example' }),
        error => error instanceof ScannerError && error.code === 'INSECURE_CONTEXT',
    );
    await assert.rejects(
        startBarcodeScannerSession({ video: fakeVideo(), onDecode: () => {}, secureContext: true, mediaDevices: null }),
        error => error instanceof ScannerError && error.code === 'CAMERA_UNAVAILABLE',
    );

    const denied = new Error('denied');
    denied.name = 'NotAllowedError';
    const wrapped = new ScannerError('INITIALIZATION_FAILED', denied);
    assert.match(scannerErrorMessage(wrapped), /اجازهٔ دسترسی/);
    assert.match(scannerErrorMessage(new ScannerError('CAMERA_UNAVAILABLE')), /دوربین سازگار/);
    assert.match(scannerErrorMessage(new ScannerError('INSECURE_CONTEXT')), /HTTPS/);
});
