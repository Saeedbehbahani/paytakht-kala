<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
    <link rel="stylesheet" href="/assets/app.css?v=20260822-ui">
    <link rel="stylesheet" href="/assets/ui-consolidation.css?v=20260822-ui">
    <link rel="stylesheet" href="/assets/final-personalization.css?v=20260822-ui">
    <link rel="stylesheet" href="/assets/prepilot.css?v=20260824">
    <title>فاکتور {{ $sale->invoice_number }} | {{ $storeProfile['name'] }}</title>
</head>
<body class="invoice-page">
<main class="invoice-sheet">
    <header class="invoice-header">
        <span class="brand-mark" aria-hidden="true">پ</span>
        <div><h1>{{ $storeProfile['name'] }}</h1><p>فاکتور فروش @if($storeProfile['phone']) · {{ $storeProfile['phone'] }} @endif</p></div>
    </header>

    <section class="invoice-meta">
        <div><span>شماره فاکتور</span><strong>{{ $sale->invoice_number }}</strong></div>
        <div><span>تاریخ</span><strong>{{ $sale->created_at->format('Y/m/d H:i') }}</strong></div>
        <div><span>فروشنده</span><strong>{{ $sale->seller?->name ?? '—' }}</strong></div>
        <div><span>مشتری</span><strong>{{ $customer?->name ?? 'مشتری نقدی' }}</strong></div>
    </section>

    <div class="invoice-table-wrap">
        <table class="invoice-table">
            <thead><tr><th>کالا</th><th>تعداد</th><th>قیمت واحد</th><th>تخفیف</th><th>جمع</th></tr></thead>
            <tbody>
            @foreach($sale->items as $item)
                <tr>
                    <td data-label="کالا">{{ $item->product?->name ?? 'کالا' }}</td>
                    <td data-label="تعداد">{{ number_format($item->quantity) }}</td>
                    <td data-label="قیمت واحد">{{ number_format($item->unit_price_toman) }}</td>
                    <td data-label="تخفیف">{{ number_format($item->line_discount_toman) }}</td>
                    <td data-label="جمع">{{ number_format($item->line_total_toman) }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>

    <section class="invoice-totals">
        <div><span>جمع کالاها</span><x-money :value="$sale->subtotal_toman" /></div>
        <div><span>تخفیف فاکتور</span><x-money :value="$sale->discount_toman" /></div>
        <div class="invoice-grand-total"><span>مبلغ نهایی</span><x-money :value="$sale->total_toman" /></div>
        <div><span>پرداخت‌شده</span><x-money :value="$sale->paid_amount_toman" /></div>
        <div><span>مانده</span><x-money :value="$sale->receivable_amount_toman" /></div>
    </section>

    <section class="invoice-payments">
        <h2>خلاصه پرداخت</h2>
        @foreach($sale->payments as $payment)
            <div><span>{{ ['CASH'=>'نقد','POS'=>'کارتخوان','BANK'=>'واریز بانکی','TRANSFER'=>'انتقال بانکی','INSTALLMENT'=>'اقساط','CHEQUE'=>'چک'][$payment->method] ?? $payment->method }}</span><x-money :value="$payment->amount_toman" /></div>
        @endforeach
    </section>

    <footer class="invoice-footer">@if($storeProfile['invoiceInfo'])<p>{{ $storeProfile['invoiceInfo'] }}</p>@endif<p>سپاس از خرید شما</p></footer>
    <div class="invoice-actions">
        <a class="btn btn-soft" href="{{ route('sales.index') }}">بازگشت</a>
        <button class="btn btn-primary" type="button" onclick="window.print()">چاپ / ذخیره PDF</button>
    </div>
</main>
</body>
</html>
