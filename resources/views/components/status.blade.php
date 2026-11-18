@props(['value'])
@php
    $labels = [
        'COMPLETED' => ['ثبت‌شده', 'success'], 'FINALIZED' => ['نهایی‌شده', 'success'],
        'PAID' => ['پرداخت‌شده', 'success'], 'CLEARED' => ['وصول‌شده', 'success'],
        'ACTIVE' => ['فعال', 'success'], 'PARTIAL' => ['پرداخت جزئی', 'warning'],
        'PENDING' => ['در انتظار', 'warning'], 'DRAFT' => ['پیش‌نویس', 'warning'],
        'OVERDUE' => ['سررسید گذشته', 'danger'], 'BOUNCED' => ['برگشتی', 'danger'],
        'VOID' => ['باطل‌شده', 'danger'], 'CANCELLED' => ['لغوشده', 'danger'],
        'REPLACED' => ['جایگزین‌شده', 'info'], 'INACTIVE' => ['غیرفعال', 'info'],
        'REVIEW' => ['نیازمند بررسی', 'info'], 'PENDING_REVIEW' => ['نیازمند بررسی', 'warning'],
        'NOT_REQUIRED' => ['بدون نیاز به بررسی', 'success'], 'OPEN' => ['باز', 'warning'],
        'REVIEWED' => ['بررسی‌شده', 'success'], 'VALID' => ['معتبر', 'success'],
        'INVALID' => ['نامعتبر', 'danger'], 'DUPLICATE' => ['تکراری', 'danger'],
    ];
    [$label, $tone] = $labels[$value] ?? [$value, 'info'];
@endphp
<span {{ $attributes->class("status status-$tone") }}>{{ $label }}</span>
