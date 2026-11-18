<?php

namespace App\Http\Controllers;

use App\Services\BulkOpeningInventoryService;
use App\Services\OpeningObligationService;
use App\Support\IntegerMath;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OpeningController extends Controller
{
    public function obligations()
    {
        $batch = DB::table('opening_obligation_batches')->where('status', 'DRAFT')->latest()->first();

        return view('admin.opening-obligations', [
            'batch' => $batch,
            'items' => $batch ? DB::table('opening_obligation_items')->where('opening_obligation_batch_id', $batch->id)->orderBy('id')->get() : collect(),
            'batches' => DB::table('opening_obligation_batches')->latest()->limit(20)->get(),
            'customers' => DB::table('customers')->orderBy('name')->get(),
            'suppliers' => DB::table('suppliers')->orderBy('name')->get(),
        ]);
    }

    public function obligationCreate(Request $request, OpeningObligationService $service)
    {
        $data = $request->validate(['request_uuid' => 'required|uuid', 'note' => 'nullable|string|max:1000']);
        $id = $service->createBatch($data['request_uuid'], $request->user()->id, $data['note'] ?? null);

        return back()->with('success', "بسته تعهدات افتتاحیه {$id} ایجاد شد.");
    }

    public function obligationItem(Request $request, int $batch, OpeningObligationService $service)
    {
        $data = $request->validate([
            'obligation_type' => 'required|in:RECEIVABLE,PAYABLE,INCOMING_CHEQUE,OUTGOING_CHEQUE',
            'customer_id' => 'nullable|exists:customers,id', 'supplier_id' => 'nullable|exists:suppliers,id',
            'amount_toman' => 'required|integer|min:1|max:'.IntegerMath::MAX_MONEY, 'due_date' => 'nullable|date',
            'installment_count' => 'nullable|integer|min:1|max:120', 'bank_name' => 'nullable|string|max:100',
            'cheque_number' => 'nullable|string|max:100', 'note' => 'nullable|string|max:1000',
        ]);
        $service->addItem($batch, $data, $request->user()->id);

        return back()->with('success', 'تعهد افتتاحیه به پیش‌نویس اضافه شد.');
    }

    public function obligationFinalize(Request $request, int $batch, OpeningObligationService $service)
    {
        $service->finalize($batch, $request->user()->id);

        return back()->with('success', 'تعهدات افتتاحیه نهایی شد.');
    }

    public function bulkPreview(Request $request, BulkOpeningInventoryService $service)
    {
        $data = $request->validate(['request_uuid' => 'required|uuid', 'session_id' => 'required|exists:opening_inventory_sessions,id', 'default_category_id' => 'required|exists:categories,id', 'csv' => 'required|file|mimes:csv,txt|max:20480']);
        $id = $service->preview($request->file('csv'), $data['request_uuid'], (int) $data['session_id'], (int) $data['default_category_id'], $request->user()->id);

        return redirect()->route('admin.opening-inventory.preview', $id);
    }

    public function bulkShow(int $import)
    {
        $record = DB::table('opening_inventory_imports')->find($import);
        abort_unless($record, 404);

        return view('inventory.opening-import-preview', ['import' => $record, 'rows' => DB::table('opening_inventory_import_rows')->where('opening_inventory_import_id', $import)->orderBy('line_number')->paginate(100)]);
    }

    public function bulkFinalize(Request $request, int $import, BulkOpeningInventoryService $service)
    {
        $service->finalize($import, $request->user()->id);

        return redirect()->route('inventory.index')->with('success', 'موجودی افتتاحیه گروهی نهایی شد.');
    }

    public function bulkTemplate()
    {
        return response("barcode,sku,name,brand,model,quantity,purchase_cost_toman\n,SKU-001,نام کالا,برند,مدل,1,0\n", 200, ['Content-Type' => 'text/csv; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="paytakht-opening-inventory.csv"']);
    }
}
