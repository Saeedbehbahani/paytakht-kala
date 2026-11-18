<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained();
            $table->string('name');
            $table->string('sku')->unique();
            $table->string('barcode')->nullable()->unique();
            $table->string('brand')->nullable()->index();
            $table->string('model')->nullable()->index();
            $table->string('image_path')->nullable();
            $table->unsignedBigInteger('average_cost_toman')->default(0);
            $table->unsignedBigInteger('sale_price_toman')->default(0);
            $table->bigInteger('stock_quantity')->default(0);
            $table->unsignedBigInteger('minimum_stock')->nullable();
            $table->boolean('track_serial')->default(false);
            $table->boolean('warranty_enabled')->default(false);
            $table->boolean('active')->default(true)->index();
            $table->string('public_name')->nullable();
            $table->string('slug')->unique();
            $table->text('public_description')->nullable();
            $table->string('public_image_path')->nullable();
            $table->unsignedBigInteger('public_sale_price_toman')->nullable();
            $table->boolean('is_public')->default(false)->index();
            $table->boolean('online_sale_enabled')->default(false);
            $table->string('public_stock_display_mode')->default('EXACT');
            $table->timestamp('last_sale_at')->nullable()->index();
            $table->timestamps();
            $table->index(['name', 'brand', 'model']);
        });
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name')->index();
            $table->string('phone')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
        });
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->string('name')->index();
            $table->string('phone')->nullable()->index();
            $table->text('note')->nullable();
            $table->timestamps();
        });
        Schema::create('financial_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('type');
            $table->bigInteger('balance_toman')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
        Schema::create('opening_inventory_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('status')->default('DRAFT');
            $table->foreignId('created_by_user_id')->constrained('users');
            $table->foreignId('finalized_by_user_id')->nullable()->constrained('users');
            $table->timestamp('finalized_at')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
        });
        Schema::create('opening_inventory_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('opening_inventory_session_id')->constrained();
            $table->foreignId('product_id')->constrained();
            $table->unsignedBigInteger('quantity');
            $table->unsignedBigInteger('unit_cost_toman');
            $table->unsignedBigInteger('sale_price_toman');
            $table->timestamps();
            $table->unique(['opening_inventory_session_id', 'product_id'], 'opening_product_unique');
        });
        Schema::create('purchases', function (Blueprint $table) {
            $table->id();
            $table->uuid('request_uuid')->unique();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->string('invoice_number')->nullable();
            $table->string('invoice_image_path')->nullable();
            $table->unsignedBigInteger('subtotal_toman');
            $table->unsignedBigInteger('discount_toman')->default(0);
            $table->unsignedBigInteger('shipping_cost_toman')->default(0);
            $table->unsignedBigInteger('total_toman');
            $table->unsignedBigInteger('paid_amount_toman')->default(0);
            $table->unsignedBigInteger('remaining_amount_toman')->default(0);
            $table->string('status')->default('COMPLETED')->index();
            $table->text('note')->nullable();
            $table->foreignId('created_by_user_id')->constrained('users');
            $table->foreignId('voided_by_user_id')->nullable()->constrained('users');
            $table->timestamp('voided_at')->nullable();
            $table->text('void_reason')->nullable();
            $table->timestamps();
        });
        Schema::create('purchase_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_id')->constrained();
            $table->foreignId('product_id')->constrained();
            $table->unsignedBigInteger('quantity');
            $table->unsignedBigInteger('unit_cost_toman');
            $table->unsignedBigInteger('line_total_toman');
            $table->unsignedBigInteger('returned_quantity')->default(0);
            $table->timestamps();
        });
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->uuid('request_uuid')->unique();
            $table->string('invoice_number')->unique();
            $table->string('channel')->default('IN_STORE')->index();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('seller_user_id')->constrained('users');
            $table->foreignId('created_by_user_id')->constrained('users');
            $table->unsignedBigInteger('subtotal_toman');
            $table->unsignedBigInteger('discount_toman')->default(0);
            $table->unsignedBigInteger('total_toman');
            $table->unsignedBigInteger('paid_amount_toman')->default(0);
            $table->unsignedBigInteger('receivable_amount_toman')->default(0);
            $table->bigInteger('estimated_profit_toman')->default(0);
            $table->boolean('sensitive_flag')->default(false)->index();
            $table->text('sensitive_reason')->nullable();
            $table->string('status')->default('COMPLETED')->index();
            $table->foreignId('voided_by_user_id')->nullable()->constrained('users');
            $table->timestamp('voided_at')->nullable();
            $table->text('void_reason')->nullable();
            $table->timestamps();
        });
        Schema::create('sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained();
            $table->foreignId('product_id')->constrained();
            $table->unsignedBigInteger('quantity');
            $table->unsignedBigInteger('unit_price_toman');
            $table->unsignedBigInteger('cost_snapshot_toman');
            $table->unsignedBigInteger('line_discount_toman')->default(0);
            $table->unsignedBigInteger('line_total_toman');
            $table->unsignedBigInteger('returned_quantity')->default(0);
            $table->timestamps();
        });
        Schema::create('payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->string('payable_type');
            $table->unsignedBigInteger('payable_id');
            $table->foreignId('financial_account_id')->nullable()->constrained()->nullOnDelete();
            $table->string('method');
            $table->unsignedBigInteger('amount_toman');
            $table->timestamps();
            $table->index(['payable_type', 'payable_id']);
        });
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->uuid('request_uuid')->nullable()->unique();
            $table->foreignId('product_id')->constrained();
            $table->bigInteger('quantity_delta');
            $table->bigInteger('stock_after');
            $table->string('movement_type')->index();
            $table->string('reference_type');
            $table->unsignedBigInteger('reference_id');
            $table->unsignedBigInteger('unit_cost_snapshot_toman')->nullable();
            $table->foreignId('created_by_user_id')->constrained('users');
            $table->text('reason')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['reference_type', 'reference_id']);
        });
        Schema::create('financial_movements', function (Blueprint $table) {
            $table->id();
            $table->uuid('request_uuid')->nullable()->unique();
            $table->foreignId('financial_account_id')->constrained();
            $table->string('direction');
            $table->unsignedBigInteger('amount_toman');
            $table->bigInteger('balance_after_toman');
            $table->string('type')->index();
            $table->string('reference_type');
            $table->unsignedBigInteger('reference_id');
            $table->foreignId('created_by_user_id')->constrained('users');
            $table->text('note')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['reference_type', 'reference_id']);
        });
        Schema::create('receivables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained();
            $table->foreignId('sale_id')->unique()->constrained();
            $table->unsignedBigInteger('original_amount_toman');
            $table->unsignedBigInteger('remaining_amount_toman');
            $table->string('status')->default('OPEN')->index();
            $table->timestamps();
        });
        Schema::create('payables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained();
            $table->foreignId('purchase_id')->unique()->constrained();
            $table->unsignedBigInteger('original_amount_toman');
            $table->unsignedBigInteger('remaining_amount_toman');
            $table->string('status')->default('OPEN')->index();
            $table->timestamps();
        });
        Schema::create('installments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('receivable_id')->constrained();
            $table->foreignId('sale_id')->constrained();
            $table->foreignId('customer_id')->constrained();
            $table->unsignedInteger('sequence');
            $table->unsignedBigInteger('amount_toman');
            $table->date('due_date')->index();
            $table->unsignedBigInteger('paid_amount_toman')->default(0);
            $table->timestamp('paid_at')->nullable();
            $table->string('status')->default('PENDING')->index();
            $table->timestamps();
            $table->unique(['sale_id', 'sequence']);
        });
        Schema::create('cheques', function (Blueprint $table) {
            $table->id();
            $table->uuid('request_uuid')->unique();
            $table->string('direction');
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('sale_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('purchase_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('financial_account_id')->nullable()->constrained()->nullOnDelete();
            $table->string('bank_name');
            $table->string('cheque_number')->index();
            $table->unsignedBigInteger('amount_toman');
            $table->date('due_date')->index();
            $table->string('image_path')->nullable();
            $table->string('status')->default('PENDING')->index();
            $table->text('note')->nullable();
            $table->foreignId('created_by_user_id')->constrained('users');
            $table->timestamps();
            $table->unique(['direction', 'bank_name', 'cheque_number']);
        });
        Schema::create('expense_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->uuid('request_uuid')->unique();
            $table->foreignId('expense_category_id')->constrained();
            $table->foreignId('financial_account_id')->constrained();
            $table->unsignedBigInteger('amount_toman');
            $table->text('note')->nullable();
            $table->string('receipt_path')->nullable();
            $table->boolean('sensitive_flag')->default(false)->index();
            $table->string('status')->default('COMPLETED');
            $table->foreignId('created_by_user_id')->constrained('users');
            $table->timestamps();
        });
        Schema::create('sale_returns', function (Blueprint $table) {
            $table->id();
            $table->uuid('request_uuid')->unique();
            $table->foreignId('sale_id')->constrained();
            $table->foreignId('refund_account_id')->nullable()->constrained('financial_accounts');
            $table->unsignedBigInteger('total_refund_toman');
            $table->text('reason');
            $table->foreignId('created_by_user_id')->constrained('users');
            $table->timestamps();
        });
        Schema::create('sale_return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_return_id')->constrained();
            $table->foreignId('sale_item_id')->constrained();
            $table->foreignId('product_id')->constrained();
            $table->unsignedBigInteger('quantity');
            $table->unsignedBigInteger('refund_toman');
            $table->timestamps();
        });
        Schema::create('purchase_returns', function (Blueprint $table) {
            $table->id();
            $table->uuid('request_uuid')->unique();
            $table->foreignId('purchase_id')->constrained();
            $table->foreignId('refund_account_id')->nullable()->constrained('financial_accounts');
            $table->unsignedBigInteger('total_refund_toman');
            $table->text('reason');
            $table->foreignId('created_by_user_id')->constrained('users');
            $table->timestamps();
        });
        Schema::create('purchase_return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_return_id')->constrained();
            $table->foreignId('purchase_item_id')->constrained();
            $table->foreignId('product_id')->constrained();
            $table->unsignedBigInteger('quantity');
            $table->unsignedBigInteger('refund_toman');
            $table->timestamps();
        });
        Schema::create('stock_count_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('status')->default('DRAFT');
            $table->foreignId('created_by_user_id')->constrained('users');
            $table->foreignId('finalized_by_user_id')->nullable()->constrained('users');
            $table->timestamp('finalized_at')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
        });
        Schema::create('stock_count_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_count_session_id')->constrained();
            $table->foreignId('product_id')->constrained();
            $table->bigInteger('system_quantity');
            $table->bigInteger('counted_quantity');
            $table->bigInteger('difference');
            $table->text('reason')->nullable();
            $table->timestamps();
            $table->unique(['stock_count_session_id', 'product_id'], 'count_product_unique');
        });
        Schema::create('day_closings', function (Blueprint $table) {
            $table->id();
            $table->uuid('request_uuid')->unique();
            $table->foreignId('user_id')->constrained();
            $table->timestamp('period_start');
            $table->timestamp('period_end');
            $table->json('expected_values');
            $table->json('actual_values');
            $table->json('differences');
            $table->text('reason')->nullable();
            $table->timestamps();
            $table->index(['period_start', 'period_end']);
        });
        Schema::create('payrolls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->string('month');
            $table->unsignedBigInteger('base_salary_toman');
            $table->unsignedBigInteger('overtime_toman')->default(0);
            $table->unsignedBigInteger('bonus_toman')->default(0);
            $table->unsignedBigInteger('commission_toman')->default(0);
            $table->unsignedBigInteger('advance_toman')->default(0);
            $table->unsignedBigInteger('deductions_toman')->default(0);
            $table->unsignedBigInteger('net_salary_toman');
            $table->unsignedBigInteger('paid_amount_toman')->default(0);
            $table->unsignedBigInteger('remaining_amount_toman');
            $table->foreignId('payment_account_id')->nullable()->constrained('financial_accounts');
            $table->string('status')->default('PENDING');
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('created_by_user_id')->constrained('users');
            $table->timestamps();
            $table->unique(['user_id', 'month']);
        });
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action')->index();
            $table->string('entity_type')->index();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('summary')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['entity_type', 'entity_id']);
        });
        Schema::create('private_uploads', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('uploaded_by_user_id')->constrained('users');
            $table->string('purpose');
            $table->string('disk_path');
            $table->string('original_name');
            $table->string('mime_type');
            $table->unsignedBigInteger('size_bytes');
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['reference_type', 'reference_id']);
        });
    }

    public function down(): void
    {
        foreach (['private_uploads', 'audit_logs', 'payrolls', 'day_closings', 'stock_count_items', 'stock_count_sessions', 'purchase_return_items', 'purchase_returns', 'sale_return_items', 'sale_returns', 'expenses', 'expense_categories', 'cheques', 'installments', 'payables', 'receivables', 'financial_movements', 'stock_movements', 'payment_allocations', 'sale_items', 'sales', 'purchase_items', 'purchases', 'opening_inventory_items', 'opening_inventory_sessions', 'financial_accounts', 'customers', 'suppliers', 'products', 'categories', 'settings'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
