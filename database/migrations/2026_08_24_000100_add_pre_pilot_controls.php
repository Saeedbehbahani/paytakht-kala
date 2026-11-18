<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('status')->default('ACTIVE')->index()->after('active');
            $table->foreignId('created_by_user_id')->nullable()->after('status')->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by_user_id')->nullable()->after('created_by_user_id')->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('approved_by_user_id');
        });
        DB::table('products')->where('active', false)->update(['status' => 'INACTIVE']);

        Schema::table('purchase_items', function (Blueprint $table) {
            $table->unsignedBigInteger('allocated_cost_toman')->default(0)->after('line_total_toman');
        });
        DB::table('purchase_items')->update(['allocated_cost_toman' => DB::raw('line_total_toman')]);

        Schema::table('purchase_return_items', function (Blueprint $table) {
            $table->unsignedBigInteger('cost_reversal_toman')->default(0)->after('refund_toman');
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->foreignId('voided_by_user_id')->nullable()->after('created_by_user_id')->constrained('users')->nullOnDelete();
            $table->timestamp('voided_at')->nullable()->after('voided_by_user_id');
            $table->text('void_reason')->nullable()->after('voided_at');
        });

        Schema::create('review_exceptions', function (Blueprint $table) {
            $table->id();
            $table->string('type')->index();
            $table->string('severity')->default('MEDIUM')->index();
            $table->string('subject_type');
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('reason_code')->index();
            $table->json('metrics')->nullable();
            $table->string('status')->default('OPEN')->index();
            $table->timestamp('detected_at')->useCurrent()->index();
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->string('fingerprint', 64)->unique();
            $table->timestamps();
            $table->index(['subject_type', 'subject_id']);
        });

        Schema::table('stock_count_items', function (Blueprint $table) {
            $table->foreignId('counted_by_user_id')->nullable()->after('reason')->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->after('counted_by_user_id')->constrained('users')->nullOnDelete();
            $table->unsignedInteger('version')->default(1)->after('updated_by_user_id');
        });

        Schema::table('day_closings', function (Blueprint $table) {
            $table->date('business_date')->nullable()->unique()->after('request_uuid');
            $table->string('status')->default('FINALIZED')->after('differences');
            $table->string('review_status')->default('NOT_REQUIRED')->index()->after('status');
            $table->boolean('operations_confirmed')->default(false)->after('review_status');
        });

        Schema::create('day_closing_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('day_closing_id')->constrained()->cascadeOnDelete();
            $table->foreignId('financial_account_id')->constrained();
            $table->string('account_type');
            $table->bigInteger('expected_toman');
            $table->bigInteger('actual_toman');
            $table->bigInteger('difference_toman');
            $table->string('evidence_path')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
            $table->unique(['day_closing_id', 'financial_account_id']);
        });

        Schema::create('day_closing_corrections', function (Blueprint $table) {
            $table->id();
            $table->uuid('request_uuid')->unique();
            $table->foreignId('day_closing_id')->constrained()->cascadeOnDelete();
            $table->foreignId('financial_account_id')->constrained();
            $table->bigInteger('corrected_actual_toman');
            $table->text('reason');
            $table->foreignId('created_by_user_id')->constrained('users');
            $table->timestamps();
        });

        Schema::create('opening_obligation_batches', function (Blueprint $table) {
            $table->id();
            $table->uuid('request_uuid')->unique();
            $table->string('status')->default('DRAFT')->index();
            $table->text('note')->nullable();
            $table->foreignId('created_by_user_id')->constrained('users');
            $table->foreignId('finalized_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('finalized_at')->nullable();
            $table->timestamps();
        });

        Schema::create('opening_obligation_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('opening_obligation_batch_id')->constrained()->cascadeOnDelete();
            $table->string('obligation_type')->index();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('amount_toman');
            $table->date('due_date')->nullable();
            $table->unsignedInteger('installment_count')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('cheque_number')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
        });

        Schema::table('receivables', function (Blueprint $table) {
            $table->foreignId('sale_id')->nullable()->change();
            $table->string('source_type')->default('SALE')->after('sale_id');
            $table->date('due_date')->nullable()->after('remaining_amount_toman');
            $table->foreignId('opening_batch_id')->nullable()->after('due_date')->constrained('opening_obligation_batches')->nullOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->after('opening_batch_id')->constrained('users')->nullOnDelete();
        });

        Schema::table('payables', function (Blueprint $table) {
            $table->foreignId('purchase_id')->nullable()->change();
            $table->string('source_type')->default('PURCHASE')->after('purchase_id');
            $table->date('due_date')->nullable()->after('remaining_amount_toman');
            $table->foreignId('opening_batch_id')->nullable()->after('due_date')->constrained('opening_obligation_batches')->nullOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->after('opening_batch_id')->constrained('users')->nullOnDelete();
        });

        Schema::table('installments', function (Blueprint $table) {
            $table->foreignId('sale_id')->nullable()->change();
        });

        Schema::table('cheques', function (Blueprint $table) {
            $table->foreignId('receivable_id')->nullable()->after('purchase_id')->constrained('receivables')->nullOnDelete();
            $table->foreignId('payable_id')->nullable()->after('receivable_id')->constrained('payables')->nullOnDelete();
            $table->foreignId('opening_batch_id')->nullable()->after('payable_id')->constrained('opening_obligation_batches')->nullOnDelete();
        });

        Schema::create('opening_inventory_imports', function (Blueprint $table) {
            $table->id();
            $table->uuid('request_uuid')->unique();
            $table->string('file_hash', 64)->index();
            $table->string('status')->default('PREVIEW')->index();
            $table->foreignId('opening_inventory_session_id')->constrained();
            $table->foreignId('default_category_id')->constrained('categories');
            $table->unsignedInteger('valid_rows')->default(0);
            $table->unsignedInteger('invalid_rows')->default(0);
            $table->unsignedInteger('duplicate_rows')->default(0);
            $table->foreignId('created_by_user_id')->constrained('users');
            $table->foreignId('finalized_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('finalized_at')->nullable();
            $table->timestamps();
        });

        Schema::create('opening_inventory_import_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('opening_inventory_import_id');
            $table->foreign('opening_inventory_import_id', 'opening_import_rows_import_fk')->references('id')->on('opening_inventory_imports')->cascadeOnDelete();
            $table->unsignedInteger('line_number');
            $table->string('barcode')->nullable();
            $table->string('sku')->nullable();
            $table->string('name')->nullable();
            $table->string('brand')->nullable();
            $table->string('model')->nullable();
            $table->unsignedBigInteger('quantity')->nullable();
            $table->unsignedBigInteger('purchase_cost_toman')->nullable();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('row_status')->index();
            $table->text('error_message')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamps();
            $table->unique(['opening_inventory_import_id', 'line_number'], 'opening_import_line_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opening_inventory_import_rows');
        Schema::dropIfExists('opening_inventory_imports');

        Schema::table('cheques', function (Blueprint $table) {
            $table->dropConstrainedForeignId('opening_batch_id');
            $table->dropConstrainedForeignId('payable_id');
            $table->dropConstrainedForeignId('receivable_id');
        });
        Schema::table('installments', fn (Blueprint $table) => $table->foreignId('sale_id')->nullable(false)->change());
        Schema::table('payables', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by_user_id');
            $table->dropConstrainedForeignId('opening_batch_id');
            $table->dropColumn(['source_type', 'due_date']);
            $table->foreignId('purchase_id')->nullable(false)->change();
        });
        Schema::table('receivables', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by_user_id');
            $table->dropConstrainedForeignId('opening_batch_id');
            $table->dropColumn(['source_type', 'due_date']);
            $table->foreignId('sale_id')->nullable(false)->change();
        });
        Schema::dropIfExists('opening_obligation_items');
        Schema::dropIfExists('opening_obligation_batches');
        Schema::dropIfExists('day_closing_corrections');
        Schema::dropIfExists('day_closing_entries');
        Schema::table('day_closings', fn (Blueprint $table) => $table->dropColumn(['business_date', 'status', 'review_status', 'operations_confirmed']));
        Schema::table('stock_count_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('updated_by_user_id');
            $table->dropConstrainedForeignId('counted_by_user_id');
            $table->dropColumn('version');
        });
        Schema::dropIfExists('review_exceptions');
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('voided_by_user_id');
            $table->dropColumn(['voided_at', 'void_reason']);
        });
        Schema::table('purchase_return_items', fn (Blueprint $table) => $table->dropColumn('cost_reversal_toman'));
        Schema::table('purchase_items', fn (Blueprint $table) => $table->dropColumn('allocated_cost_toman'));
        Schema::table('products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('approved_by_user_id');
            $table->dropConstrainedForeignId('created_by_user_id');
            $table->dropColumn(['status', 'approved_at']);
        });
    }
};
