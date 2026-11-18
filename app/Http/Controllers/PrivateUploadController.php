<?php

namespace App\Http\Controllers;

use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PrivateUploadController extends Controller
{
    public function index(Request $r)
    {
        $q = DB::table('private_uploads')->latest('created_at');
        if (! $r->user()->isAdmin()) {
            $q->where('uploaded_by_user_id', $r->user()->id);
        }

        return view('operations.uploads', ['files' => $q->limit(30)->get()]);
    }

    public function store(Request $r, AuditService $audit)
    {
        $d = $r->validate(['purpose' => 'required|in:PRODUCT_IMAGE,PURCHASE_INVOICE,EXPENSE_RECEIPT,CHEQUE_IMAGE', 'file' => 'required|file|mimes:jpg,jpeg,png,webp,pdf|max:5120', 'reference_type' => 'nullable|in:PRODUCT,PURCHASE,EXPENSE,CHEQUE', 'reference_id' => 'nullable|integer|min:1']);
        $file = $r->file('file');
        $extension = strtolower($file->getClientOriginalExtension());
        $detectedMime = (new \finfo(FILEINFO_MIME_TYPE))->file($file->getRealPath());
        $allowedSignatures = [
            'jpg' => ['image/jpeg'],
            'jpeg' => ['image/jpeg'],
            'png' => ['image/png'],
            'webp' => ['image/webp'],
            'pdf' => ['application/pdf'],
        ];
        if (! isset($allowedSignatures[$extension]) || ! in_array($detectedMime, $allowedSignatures[$extension], true)) {
            throw ValidationException::withMessages(['file' => 'محتوای واقعی فایل با نوع مجاز تطبیق ندارد.']);
        }
        $uuid = (string) Str::uuid();
        $path = $file->storeAs('private/'.strtolower($d['purpose']).'/'.now()->format('Y/m'), $uuid.'.'.$extension, 'local');
        $id = DB::table('private_uploads')->insertGetId(['uuid' => $uuid, 'uploaded_by_user_id' => $r->user()->id, 'purpose' => $d['purpose'], 'disk_path' => $path, 'original_name' => mb_substr($file->getClientOriginalName(), 0, 255), 'mime_type' => $detectedMime, 'size_bytes' => $file->getSize(), 'reference_type' => $d['reference_type'] ?? null, 'reference_id' => $d['reference_id'] ?? null, 'created_at' => now()]);
        $audit->record('private_upload', 'PRIVATE_UPLOAD', $id, [], ['purpose' => $d['purpose'], 'mime' => $detectedMime, 'size' => $file->getSize()], 'فایل خصوصی بارگذاری شد');

        return back()->with('success', 'فایل در فضای خصوصی ذخیره شد.');
    }

    public function show(Request $r, int $id)
    {
        $file = DB::table('private_uploads')->where('id', $id)->first();
        abort_unless($file && ($r->user()->isAdmin() || $file->uploaded_by_user_id === $r->user()->id), 403);
        abort_unless(Storage::disk('local')->exists($file->disk_path), 404);

        return Storage::disk('local')->download($file->disk_path, $file->original_name, ['Content-Type' => $file->mime_type, 'X-Content-Type-Options' => 'nosniff']);
    }
}
