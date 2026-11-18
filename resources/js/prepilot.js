import { normalizeBarcode, scannerErrorMessage, startBarcodeScannerSession } from './barcode-scanner.js';

(() => {
    document.querySelectorAll('label:not([for])').forEach((label, index) => {
        if (label.querySelector('input, select, textarea')) return;
        const field = label.closest('.field');
        const control = field?.querySelector('input:not([type="hidden"]), select, textarea');
        if (!control) return;
        if (!control.id) control.id = `prepilot-field-${index + 1}`;
        label.htmlFor = control.id;
    });

    const compatibleType = method => ({CASH: 'CASH', POS: 'POS', BANK: 'BANK', TRANSFER: 'BANK'})[method] || null;

    function filterAccounts(methodSelect) {
        const scope = methodSelect.closest('[data-payment-row],[data-refund-allocation],.form-card,section') || methodSelect.parentElement;
        const account = scope?.querySelector('[data-payment-account]');
        if (!account) return;
        const required = compatibleType(methodSelect.value);
        [...account.options].forEach(option => {
            if (!option.value) return;
            option.hidden = Boolean(required) && option.dataset.accountType !== required;
            option.disabled = Boolean(required) && option.dataset.accountType !== required;
        });
        if (!required) {
            account.value = '';
            account.disabled = true;
        } else {
            account.disabled = false;
            if (account.selectedOptions[0]?.disabled) account.value = '';
            if (!account.value) account.querySelector(`option[data-account-type="${required}"]:not(:disabled)`)?.setAttribute('data-suggested', '1');
            const suggested = account.querySelector('option[data-suggested="1"]');
            if (suggested && !account.value) account.value = suggested.value;
        }
    }

    document.querySelectorAll('[data-payment-method]').forEach(filterAccounts);
    document.addEventListener('change', event => {
        if (event.target.matches('[data-payment-method]')) filterAccounts(event.target);
    });

    const receiving = document.querySelector('[data-receiving-form]');
    if (receiving) {
        const search = receiving.querySelector('[data-receiving-search]');
        const results = receiving.querySelector('[data-receiving-results]');
        const source = receiving.querySelector('[data-barcode-target]');
        const render = value => {
            if (!results || !source) return;
            const query = value.trim().toLocaleLowerCase('fa');
            results.replaceChildren();
            if (!query) { results.hidden = true; return; }
            const matches = [...source.options].slice(1).filter(option => (option.dataset.search || option.textContent).toLocaleLowerCase('fa').includes(query)).slice(0, 12);
            if (!matches.length) {
                const button = document.createElement('button');
                button.type = 'button';
                button.dataset.openDraft = '1';
                button.textContent = 'کالا پیدا نشد؛ ساخت کالای موقت';
                results.append(button);
            }
            matches.forEach(option => {
                const button = document.createElement('button');
                button.type = 'button';
                button.dataset.receivingProduct = option.value;
                button.textContent = option.textContent;
                results.append(button);
            });
            results.hidden = false;
        };
        search?.addEventListener('input', () => render(search.value));
        results?.addEventListener('click', event => {
            const product = event.target.closest('[data-receiving-product]');
            if (product) {
                const rows = [...receiving.querySelectorAll('[data-receiving-row]')];
                const row = rows.find(item => !item.querySelector('[data-barcode-target]')?.value) || rows[0];
                const select = row?.querySelector('[data-barcode-target]');
                if (select) select.value = product.dataset.receivingProduct;
                results.hidden = true;
                search.value = '';
                row?.querySelector('[name$="[quantity]"]')?.focus();
            }
            if (event.target.closest('[data-open-draft]')) {
                const details = document.querySelector('#draft-product');
                if (details) { details.open = true; details.scrollIntoView({behavior: 'smooth'}); }
            }
        });
    }

    document.querySelectorAll('[data-return-form]').forEach(form => {
        const update = () => {
            const saleTotal = Number(form.dataset.saleTotal || 0);
            const allocationBasis = Number(form.dataset.allocationBasis || 0);
            const remainingQuantity = Number(form.dataset.remainingQuantity || 0);
            const remainingRefund = Number(form.dataset.remainingRefund || 0);
            let refund = 0;
            let requestedQuantity = 0;
            form.querySelectorAll('[data-return-quantity]').forEach(input => {
                const quantity = Number(input.value || 0);
                const lineTotal = Number(input.dataset.lineTotal || 0);
                const lineQuantity = Number(input.dataset.lineQuantity || 1);
                requestedQuantity += quantity;
                const effectiveLineTotal = allocationBasis > 0 ? Math.round(saleTotal * lineTotal / allocationBasis) : 0;
                refund += Math.round(effectiveLineTotal / lineQuantity * quantity);
            });
            if (requestedQuantity === remainingQuantity) refund = remainingRefund;
            refund = Math.min(refund, remainingRefund);
            const credit = Math.min(refund, Number(form.dataset.receivable || 0));
            let cash = refund - credit;
            form.querySelector('[data-refund-total]').textContent = new Intl.NumberFormat('fa-IR').format(refund) + ' تومان';
            form.querySelector('[data-refund-credit]').textContent = 'کاهش طلب: ' + new Intl.NumberFormat('fa-IR').format(credit) + ' · پرداخت واقعی: ' + new Intl.NumberFormat('fa-IR').format(cash);
            const allocationRows = [...form.querySelectorAll('[data-refund-allocation]')];
            allocationRows.forEach((row, index) => {
                const input = row.querySelector('[data-refund-amount]');
                const amount = index === allocationRows.length - 1 ? cash : Math.min(cash, Number(row.dataset.originalAmount || 0));
                input.value = amount > 0 ? amount : '';
                cash -= amount;
            });
        };
        form.addEventListener('input', event => { if (event.target.matches('[data-return-quantity]')) update(); });
        update();
    });

    const transferForm = document.querySelector('form[action*="/finance/transfer"]');
    if (transferForm) {
        const from = transferForm.querySelector('[name="from_account_id"]');
        const to = transferForm.querySelector('[name="to_account_id"]');
        const sync = () => {
            [...to.options].forEach(option => { option.disabled = option.value === from.value; option.hidden = option.disabled; });
            if (to.value === from.value) to.value = [...to.options].find(option => !option.disabled)?.value || '';
        };
        from?.addEventListener('change', sync);
        sync();
    }

    let activeScannerStop = null;

    function applyScannedBarcode(button, rawValue) {
        const value = normalizeBarcode(rawValue);
        if (!value) return;
        const localOutput = button.closest('.input-action')?.querySelector('[data-barcode-output]');
        const target = localOutput || button.closest('form')?.querySelector('[data-barcode-target]');
        if (target?.tagName === 'SELECT') {
            const option = [...target.options].find(item => normalizeBarcode(item.dataset.barcode) === value);
            if (option) {
                target.value = option.value;
                target.dispatchEvent(new Event('change', { bubbles: true }));
            } else {
                const draft = document.querySelector('#draft-product');
                const barcode = draft?.querySelector('[data-barcode-output]');
                if (draft && barcode) {
                    draft.open = true;
                    barcode.value = value;
                    barcode.dispatchEvent(new Event('input', { bubbles: true }));
                    draft.scrollIntoView({ behavior: 'smooth' });
                } else if (button.dataset.unknownBarcodeUrl) {
                    location.assign(`${button.dataset.unknownBarcodeUrl}?q=${encodeURIComponent(value)}&scan=1#add-product`);
                } else {
                    alert('این بارکد در کالاهای داخلی پیدا نشد: ' + value);
                }
            }
        } else if (target) {
            target.value = value;
            target.dispatchEvent(new Event('input', { bubbles: true }));
            if (button.hasAttribute('data-barcode-submit')) {
                const flag = target.closest('form')?.querySelector('[data-scan-flag]');
                if (flag) flag.value = '1';
                target.closest('form')?.requestSubmit();
            }
        }
    }

    async function scanBarcode(button) {
        if (activeScannerStop) return;
        const overlay = document.createElement('div');
        overlay.className = 'scanner-overlay';
        overlay.innerHTML = '<div class="scanner-card"><video playsinline muted></video><p data-scanner-status>در حال آماده‌سازی دوربین…</p><button class="btn btn-soft" type="button">بستن</button></div>';
        document.body.append(overlay);
        const video = overlay.querySelector('video');
        const status = overlay.querySelector('[data-scanner-status]');
        let sessionStop = null;
        const close = () => {
            sessionStop?.();
            overlay.remove();
            activeScannerStop = null;
        };
        activeScannerStop = close;
        overlay.querySelector('button').addEventListener('click', close);
        try {
            const session = await startBarcodeScannerSession({
                video,
                onDecode: value => {
                    applyScannedBarcode(button, value);
                    close();
                },
                onError: error => {
                    close();
                    alert(scannerErrorMessage(error));
                },
            });
            sessionStop = session.stop;
            overlay.dataset.scannerEngine = session.path;
            status.textContent = session.path === 'native'
                ? 'بارکد را داخل کادر نگه دارید.'
                : 'اسکن سازگار فعال است؛ بارکد را داخل کادر نگه دارید.';
            if (!overlay.isConnected) session.stop();
        } catch (error) {
            close();
            alert(scannerErrorMessage(error));
        }
    }
    document.addEventListener('click', event => {
        const button = event.target.closest('[data-barcode-scan]');
        if (button) scanBarcode(button);
    });
    window.addEventListener('pagehide', () => activeScannerStop?.());
})();
