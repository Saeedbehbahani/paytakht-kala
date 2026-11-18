import assert from 'node:assert/strict';
import { spawn, spawnSync } from 'node:child_process';
import { mkdirSync, mkdtempSync, writeFileSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';
import { createServer } from 'node:net';
import { chromium } from 'playwright';

const root = resolve(import.meta.dirname, '../..');
const php = process.env.PAYTAKHT_TEST_PHP || 'php';
const temp = mkdtempSync(join(tmpdir(), 'paytakht-browser-'));
const screenshots = resolve(root, 'tmp/mobile-qa');
mkdirSync(screenshots, { recursive: true });
const database = join(temp, 'browser.sqlite');
writeFileSync(database, '');
const env = { ...process.env, APP_ENV: 'testing', DB_CONNECTION: 'sqlite', DB_DATABASE: database,
    DB_URL: '', CACHE_STORE: 'array', SESSION_DRIVER: 'file', QUEUE_CONNECTION: 'sync' };
const run = args => {
    const result = spawnSync(php, args, { cwd: root, env, encoding: 'utf8' });
    assert.equal(result.status, 0, `${args.join(' ')}: ${result.stderr}\n${result.stdout}`);
};
const signIn = async (page, base) => {
    await page.goto(`${base}/login`);
    await page.locator('[name=email]').fill('browser@paytakht.test');
    await page.locator('[name=password]').fill('BrowserOnly-2026!');
    await page.locator('button[type=submit]').click();
    assert.match(page.url(), /\/dashboard$/, `login failed: ${page.url()}`);
};
const assertLayout = async page => {
    const geometry = await page.evaluate(() => {
        const nav = document.querySelector('.bottom-nav')?.getBoundingClientRect();
        return {
            overflow: document.documentElement.scrollWidth > window.innerWidth || document.body.scrollWidth > window.innerWidth,
            navBottom: nav?.bottom,
            viewport: window.innerHeight,
            touchTargets: [...document.querySelectorAll('.bottom-nav a')].every(el => el.getBoundingClientRect().height >= 44),
        };
    });
    assert.equal(geometry.overflow, false, 'horizontal overflow');
    assert.ok(Math.abs(geometry.navBottom - geometry.viewport) < 2, 'bottom nav is not attached to viewport/safe area');
    assert.equal(geometry.touchTargets, true, 'bottom nav target smaller than 44px');
};
const selectSearchResult = async (page, text) => {
    await page.locator('[data-product-search]').fill(text);
    const result = page.locator('.search-result').filter({ hasText: text }).first();
    await result.waitFor({ state: 'visible' });
    const resultText = await result.innerText();
    assert.match(resultText, /موجودی/);
    await result.click();
};

let server;
let browser;
try {
    let base = process.env.PAYTAKHT_BASE_URL;
    if (!base) {
        run(['artisan', 'migrate', '--force']);
        run(['tests/Browser/seed.php']);
        const port = await new Promise((done, reject) => {
            const probe = createServer().once('error', reject).listen(0, '127.0.0.1', () => {
                const selected = probe.address().port;
                probe.close(() => done(selected));
            });
        });
        server = spawn(php, ['-S', `127.0.0.1:${port}`, '-t', 'public', 'tests/Browser/router.php'], { cwd: root, env, stdio: 'ignore' });
        base = `http://127.0.0.1:${port}`;
    }
    let ready = false;
    for (let attempt = 0; attempt < 60; attempt++) {
        try { if ((await fetch(`${base}/login`)).ok) { ready = true; break; } } catch { /* server starting */ }
        await new Promise(done => setTimeout(done, 200));
    }
    assert.ok(ready, 'local browser test server did not start');

    browser = await chromium.launch({ headless: true, executablePath: process.env.PAYTAKHT_TEST_CHROME || undefined });
    const context = await browser.newContext({ viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true, serviceWorkers: 'block' });
    const page = await context.newPage();
    page.setDefaultTimeout(60000);
    page.setDefaultNavigationTimeout(60000);
    await signIn(page, base);
    await page.locator('.brand small').waitFor();
    assert.equal((await page.locator('.brand small').innerText()).trim(), 'مرورگر آزمایشی');
    await page.getByRole('heading', { name: /سلام/ }).waitFor();
    assert.deepEqual((await page.locator('nav[aria-label="ناوبری اصلی"] a').allTextContents()).map(text => text.trim()), ['خانه', 'فروش', 'ورود', 'موجودی', 'هزینه']);
    await assertLayout(page);

    // Stock In via the in-wizard new-product path.
    await page.goto(`${base}/purchases/new`);
    await page.locator('[data-new-product]').click();
    await page.locator('[data-new-product-name]').fill('کالای جریان مرورگر');
    await page.locator('[name="new_product[model]"]').fill('FLOW-MODEL-2026');
    await page.locator('[name="new_product[barcode]"]').fill('6260000000001');
    await page.locator('[data-wizard-next]').click();
    await page.locator('[name="items[0][quantity]"]').fill('5');
    await page.locator('[data-wizard-next]').click();
    await page.locator('[name="items[0][unit_cost_toman]"]').fill('3800000');
    assert.match(await page.locator('[data-stockin-total]').innerText(), /۱۹٬۰۰۰٬۰۰۰/);
    await page.locator('[data-wizard-next]').click();
    assert.match(await page.locator('[data-review-product]').innerText(), /کالای جریان مرورگر/);
    await page.locator('[data-wizard-submit]').click();
    await page.waitForURL('**/purchases/*/success');
    await page.getByRole('heading', { name: 'ورود کالا با موفقیت ثبت شد' }).waitFor();
    assert.match(await page.locator('.success-receipt').innerText(), /مرورگر آزمایشی/);
    await page.goto(`${base}/purchases`);
    assert.match(await page.locator('.history-list').innerText(), /کالای جریان مرورگر/);
    assert.match(await page.locator('.history-list').innerText(), /مرورگر آزمایشی/);

    // Sale: search, visible stock, manual purchase price, live profit, review, persistence and history.
    await page.goto(`${base}/sales/new`);
    await selectSearchResult(page, 'کالای جریان مرورگر');
    assert.match(await page.locator('[data-current-stock]').innerText(), /۵/);
    await page.locator('#sale-quantity').fill('2');
    await page.locator('[data-wizard-next]').click();
    assert.equal(await page.locator('#sale-purchase-price').inputValue(), '', 'purchase price must not be prefilled');
    await page.locator('#sale-purchase-price').fill('3800000');
    await page.locator('#sale-price').fill('4500000');
    assert.match(await page.locator('[data-unit-profit]').innerText(), /۷۰۰٬۰۰۰/);
    assert.match(await page.locator('[data-total-profit]').innerText(), /۱٬۴۰۰٬۰۰۰/);
    await page.locator('[data-wizard-next]').click();
    assert.match(await page.locator('[data-review-total-profit]').innerText(), /۱٬۴۰۰٬۰۰۰/);
    const sticky = await page.locator('.wizard-actions').evaluate(el => ({ position: getComputedStyle(el).position, bottom: el.getBoundingClientRect().bottom, viewport: innerHeight }));
    assert.equal(sticky.position, 'sticky');
    assert.ok(sticky.bottom <= sticky.viewport);
    await page.locator('[data-wizard-submit]').click();
    await page.waitForURL('**/sales/*/success');
    assert.match(await page.locator('.success-receipt').innerText(), /فروش با موفقیت ثبت شد/);
    assert.match(await page.locator('.success-receipt').innerText(), /موجودی باقی‌مانده/);
    assert.match(await page.locator('.success-receipt').innerText(), /مرورگر آزمایشی/);
    await page.goto(`${base}/sales`);
    assert.match(await page.locator('.history-list').innerText(), /کالای جریان مرورگر/);
    assert.match(await page.locator('.history-list').innerText(), /مرورگر آزمایشی/);

    // Negative profit and large Toman values.
    await page.goto(`${base}/sales/new`);
    await selectSearchResult(page, 'آب مرکبات‌گیری حرفه‌ای');
    await page.locator('#sale-quantity').fill('1');
    await page.locator('[data-wizard-next]').click();
    await page.locator('#sale-purchase-price').fill('9500000000');
    await page.locator('#sale-price').fill('9200000000');
    assert.match(await page.locator('[data-total-profit]').innerText(), /−۳۰۰٬۰۰۰٬۰۰۰/);
    assert.equal(await page.locator('[data-profit-panel]').evaluate(el => el.classList.contains('negative')), true);
    await page.locator('[data-wizard-next]').click();
    await page.locator('[data-wizard-submit]').click();
    await page.waitForURL('**/sales/*/success');
    assert.match(await page.locator('.success-receipt').innerText(), /-300,000,000/);

    // Inventory edge cases, bidi, hidden internal identifiers, correction and timeline.
    await page.goto(`${base}/inventory?q=${encodeURIComponent('آب مرکبات‌گیری حرفه‌ای')}`);
    const longCard = page.locator('.product-card').first();
    const longText = await longCard.innerText();
    assert.match(longText, /123,455/);
    assert.match(longText, /کم‌موجود/);
    assert.doesNotMatch(longText, /BOOTSTRAP|AUTO/);
    assert.equal(await longCard.locator('bdi').first().evaluate(el => getComputedStyle(el).direction), 'ltr');
    assert.equal(await longCard.locator('.identity').evaluate(el => getComputedStyle(el).direction), 'rtl');
    await longCard.locator('.product-card-main').click();
    await page.locator('#adjust').evaluate(el => { el.open = true; });
    await page.locator('#adjust [name=actual_quantity]').fill('123454');
    assert.match(await page.locator('[data-difference]').innerText(), /−۱/);
    await page.locator('#adjust button').click();
    await page.waitForURL('**/inventory/adjustments/*/success');
    assert.match(await page.locator('.success-receipt').innerText(), /موجودی اصلاح شد/);
    await page.getByRole('link', { name: 'تاریخچه کالا' }).click();
    assert.match(await page.locator('#timeline').innerText(), /اصلاح موجودی/);
    await page.goto(`${base}/inventory?filter=out`);
    const zeroText = await page.locator('.inventory-list').innerText();
    assert.match(zeroText, /کتری بدون موجودی/);
    assert.match(zeroText, /ناموجود/);
    assert.doesNotMatch(zeroText, /AUTO-INTERNAL-ZERO/);

    // Expense receipt and history.
    await page.goto(`${base}/expenses/new`);
    await page.locator('[name=amount_toman]').fill('9876543210');
    await page.locator('[data-wizard-next]').click();
    await page.locator('[name=title]').fill('بسته‌بندی آزمایشی');
    await page.locator('[data-wizard-next]').click();
    await page.locator('[data-wizard-submit]').click();
    await page.waitForURL('**/expenses/*/success');
    assert.match(await page.locator('.success-receipt').innerText(), /9,876,543,210/);
    assert.match(await page.locator('.success-receipt').innerText(), /مرورگر آزمایشی/);
    await page.goto(`${base}/expenses/new?history=1`);
    assert.match(await page.locator('[data-tab-panel="expense-history"]').innerText(), /بسته‌بندی آزمایشی/);

    // Reports and all date filters.
    for (const period of ['today', 'week', 'month']) {
        await page.goto(`${base}/reports?period=${period}`);
        assert.equal(await page.locator('[name=period]').inputValue(), period);
        await page.getByRole('heading', { name: 'خلاصه عملکرد' }).waitFor();
    }
    const today = new Date().toISOString().slice(0, 10);
    await page.goto(`${base}/reports?period=range&from=${today}&to=${today}`);
    assert.equal(await page.locator('[name=period]').inputValue(), 'range');
    await assertLayout(page);
    const authState = await context.storageState();
    await context.close();

    // Screenshot/layout matrix and keyboard-height simulation.
    for (const width of [320, 360, 375, 390, 430]) {
        const mobile = await browser.newContext({ viewport: { width, height: 844 }, isMobile: true, hasTouch: true, serviceWorkers: 'block', storageState: authState });
        const shot = await mobile.newPage();
        shot.setDefaultTimeout(60000);
        await shot.goto(`${base}/dashboard`);
        assert.match(shot.url(), /\/dashboard$/);
        await assertLayout(shot);
        await shot.screenshot({ path: join(screenshots, `home-${width}.png`), fullPage: true });
        await shot.goto(`${base}/inventory?q=${encodeURIComponent('آب مرکبات‌گیری حرفه‌ای')}`);
        await assertLayout(shot);
        assert.equal(await shot.locator('body').evaluate(el => el.scrollWidth <= innerWidth), true);
        await shot.screenshot({ path: join(screenshots, `inventory-${width}.png`), fullPage: true });
        await shot.goto(`${base}/sales/new`);
        await selectSearchResult(shot, 'کالای جریان مرورگر');
        await shot.locator('#sale-quantity').focus();
        await shot.setViewportSize({ width, height: 520 });
        await shot.locator('#sale-quantity').evaluate(el => el.scrollIntoView({ block: 'center' }));
        const focused = await shot.locator('#sale-quantity').boundingBox();
        const footer = await shot.locator('.wizard-actions').boundingBox();
        const nav = await shot.locator('.bottom-nav').boundingBox();
        assert.ok(focused && focused.x >= 0 && focused.x + focused.width <= width, `focused quantity clipped at ${width}px`);
        assert.ok(footer && nav && footer.y + footer.height <= nav.y + 1, `sticky actions overlap bottom nav at ${width}px`);
        await shot.screenshot({ path: join(screenshots, `keyboard-${width}.png`) });
        await mobile.close();
    }
    const css = await (await fetch(`${base}/assets/app.css`)).text();
    assert.match(css, /safe-area-inset-bottom/);
    console.log('Authenticated browser E2E and mobile matrix passed: 15 screenshots, 320/360/375/390/430px.');
} finally {
    await browser?.close();
    server?.kill();
    rmSync(temp, { recursive: true, force: true });
}
