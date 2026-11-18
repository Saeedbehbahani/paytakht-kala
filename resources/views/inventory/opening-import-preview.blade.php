@extends('layouts.app')
@section('title','پیش‌نمایش موجودی افتتاحیه | پایتخت کالا')
@section('content')
<div class="hero"><div><span class="eyebrow">کنترل قبل از ثبت</span><h1>پیش‌نمایش ورود گروهی</h1><span class="muted">هیچ موجودی تا زمان نهایی‌سازی تغییر نمی‌کند.</span></div></div>
<div class="grid keep-two"><article class="card metric-card"><span class="metric-label">ردیف معتبر</span><div class="metric">{{ $import->valid_rows }}</div></article><article class="card metric-card"><span class="metric-label">نامعتبر / تکراری</span><div class="metric">{{ $import->invalid_rows + $import->duplicate_rows }}</div></article></div>
@if($import->invalid_rows || $import->duplicate_rows)<div class="notice notice-danger"><strong>فایل نیاز به اصلاح دارد</strong><span>ردیف‌های علامت‌خورده را اصلاح و فایل را دوباره بارگذاری کنید.</span></div>@endif
<div class="list">@foreach($rows as $row)<div class="list-item"><span><strong>ردیف {{ $row->line_number }} · {{ $row->name ?: 'بدون نام' }}</strong><small class="muted">{{ $row->sku ?: 'بدون SKU' }} · {{ $row->barcode ?: 'بدون بارکد' }} · تعداد {{ $row->quantity ?? '—' }} · {{ $row->error_message ?: ($row->product_id ? 'تطبیق با کالای موجود' : 'ساخت کالای موقت') }}</small></span><x-status :value="$row->row_status" /></div>@endforeach</div>
<div class="pagination">{{ $rows->links() }}</div>
@if($import->status==='PREVIEW' && !$import->invalid_rows && !$import->duplicate_rows)<form method="POST" action="{{ route('admin.opening-inventory.finalize',$import->id) }}" data-confirm="تمام ردیف‌ها و موجودی افتتاحیه به‌صورت قطعی ثبت شوند؟">@csrf<button class="btn btn-primary btn-block" type="submit">نهایی‌سازی همه ردیف‌ها</button></form>@endif
@endsection
