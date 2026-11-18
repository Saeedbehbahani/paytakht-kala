<?php

namespace App\Http\Controllers;

use App\Services\SimpleReportingService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class ReportController extends Controller
{
    public function __invoke(Request $request, SimpleReportingService $reports)
    {
        $data = $request->validate(['period' => 'nullable|in:today,week,month,range', 'from' => 'required_if:period,range|nullable|date', 'to' => 'required_if:period,range|nullable|date']);
        $period = $data['period'] ?? 'today';
        [$from, $until] = match ($period) {
            'week' => [now()->startOfWeek(Carbon::SATURDAY), now()->startOfWeek(Carbon::SATURDAY)->addWeek()],
            'month' => [now()->startOfMonth(), now()->startOfMonth()->addMonth()],
            'range' => [Carbon::parse($data['from'])->startOfDay(), Carbon::parse($data['to'])->addDay()->startOfDay()],
            default => [now()->startOfDay(), now()->startOfDay()->addDay()],
        };
        if ($from->gte($until)) {
            throw ValidationException::withMessages(['to' => 'تاریخ پایان باید پس از آغاز باشد.']);
        }

        return view('reports.index', ['metrics' => $reports->summarize($from, $until), 'period' => $period, 'from' => $from, 'until' => $until]);
    }
}
