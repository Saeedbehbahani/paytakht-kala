<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class EmployeeController extends Controller
{
    public function index()
    {
        $monthStart = now()->startOfMonth();
        $employees = User::query()->where('role', 'EMPLOYEE')
            ->withCount(['sales as sales_count' => fn ($q) => $q->where('status', 'COMPLETED')->where('created_at', '>=', $monthStart)])
            ->withSum(['sales as sales_total' => fn ($q) => $q->where('status', 'COMPLETED')->where('created_at', '>=', $monthStart)], 'total_toman')
            ->orderByDesc('active')->orderBy('name')->get();

        return view('admin.employees.index', compact('employees'));
    }

    public function store(Request $request, AuditService $audit)
    {
        $data = $this->validateEmployee($request);
        $user = User::create($data + ['role' => 'EMPLOYEE', 'active' => true]);
        $audit->record('employee_create', 'USER', $user->id, [], $user->only('id', 'name', 'email', 'phone', 'role', 'active'), "کارمند {$user->name} ایجاد شد");

        return back()->with('success', 'کارمند جدید ایجاد شد.');
    }

    public function update(Request $request, User $user, AuditService $audit)
    {
        $this->employeeOnly($user);
        $before = $user->only('name', 'phone', 'active');
        $data = $request->validate(['name' => 'required|string|max:120', 'phone' => ['nullable', 'string', 'max:30', Rule::unique('users', 'phone')->ignore($user->id)], 'active' => 'required|boolean']);
        $user->update($data);
        $audit->record($user->active ? 'employee_enable' : 'employee_disable', 'USER', $user->id, $before, $user->only('name', 'phone', 'active'), "وضعیت کارمند {$user->name} تغییر کرد");

        return back()->with('success', $user->active ? 'کارمند فعال شد.' : 'کارمند غیرفعال شد؛ دیگر نمی‌تواند وارد شود.');
    }

    public function password(Request $request, User $user, AuditService $audit)
    {
        $this->employeeOnly($user);
        $data = $request->validate(['password' => 'required|string|min:8|confirmed']);
        $user->update(['password' => $data['password']]);
        $audit->record('employee_password_change', 'USER', $user->id, [], [], "رمز ورود {$user->name} تغییر کرد");

        return back()->with('success', 'رمز ورود کارمند تغییر کرد.');
    }

    public function activity(User $user)
    {
        $this->employeeOnly($user);
        $sales = $user->sales()->with('items.product')->latest()->paginate(20);
        $activity = DB::table('audit_logs')->where('user_id', $user->id)->latest('created_at')->limit(50)->get();
        $summary = ['today' => (int) $user->sales()->where('status', 'COMPLETED')->where('created_at', '>=', now()->startOfDay())->sum('total_toman'), 'month' => (int) $user->sales()->where('status', 'COMPLETED')->where('created_at', '>=', now()->startOfMonth())->sum('total_toman')];

        return view('admin.employees.activity', compact('user', 'sales', 'activity', 'summary'));
    }

    private function validateEmployee(Request $request): array
    {
        return $request->validate(['name' => 'required|string|max:120', 'email' => 'required|email|max:255|unique:users,email', 'phone' => 'nullable|string|max:30|unique:users,phone', 'password' => 'required|string|min:8|confirmed']);
    }

    private function employeeOnly(User $user): void
    {
        abort_unless($user->role === 'EMPLOYEE', 404);
    }
}
