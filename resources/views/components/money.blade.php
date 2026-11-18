@props(['value' => 0, 'unit' => true])
<span {{ $attributes->class('amount') }}>{{ $value === null ? 'نامشخص' : number_format((int) $value) }}@if($unit && $value !== null) <span class="unit">تومان</span>@endif</span>
