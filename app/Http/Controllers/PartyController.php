<?php

namespace App\Http\Controllers;

use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PartyController extends Controller
{
    public function index()
    {
        return view('operations.parties', [
            'customers' => DB::table('customers')->latest()->paginate(30, ['*'], 'customers_page'),
            'suppliers' => DB::table('suppliers')->latest()->paginate(30, ['*'], 'suppliers_page'),
        ]);
    }

    public function customer(Request $r, AuditService $audit)
    {
        $d = $r->validate(['name' => 'required|string|max:150', 'phone' => 'nullable|string|max:30', 'note' => 'nullable|string|max:1000']);
        $id = DB::table('customers')->insertGetId($d + ['created_at' => now(), 'updated_at' => now()]);
        $audit->record('customer_create', 'CUSTOMER', $id, [], $d, 'مشتری مالی ایجاد شد');

        return back()->with('success', 'مشتری ایجاد شد.');
    }

    public function supplier(Request $r, AuditService $audit)
    {
        $d = $r->validate(['name' => 'required|string|max:150', 'phone' => 'nullable|string|max:30', 'note' => 'nullable|string|max:1000']);
        $id = DB::table('suppliers')->insertGetId($d + ['created_at' => now(), 'updated_at' => now()]);
        $audit->record('supplier_create', 'SUPPLIER', $id, [], $d, 'تأمین‌کننده ایجاد شد');

        return back()->with('success', 'تأمین‌کننده ایجاد شد.');
    }

    public function supplierUpdate(Request $r, int $id, AuditService $audit)
    {
        $data = $r->validate(['name' => 'required|string|max:150', 'phone' => 'nullable|string|max:30', 'note' => 'nullable|string|max:1000']);
        $before = (array) DB::table('suppliers')->where('id', $id)->first();
        abort_unless($before, 404);
        DB::table('suppliers')->where('id', $id)->update($data + ['updated_at' => now()]);
        $audit->record('supplier_update', 'SUPPLIER', $id, $before, $data, 'اطلاعات تأمین‌کننده به‌روزرسانی شد', $r->user()->id);

        return back()->with('success', 'اطلاعات تأمین‌کننده به‌روزرسانی شد.');
    }
}
