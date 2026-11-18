<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Product extends Model
{
    protected $fillable = ['category_id', 'name', 'sku', 'barcode', 'brand', 'model', 'image_path', 'sale_price_toman', 'last_sale_price_toman', 'last_replacement_cost_toman', 'minimum_stock', 'track_serial', 'warranty_enabled', 'active', 'status', 'created_by_user_id', 'approved_by_user_id', 'approved_at', 'is_demo', 'public_name', 'slug', 'public_description', 'public_image_path', 'public_sale_price_toman', 'is_public', 'online_sale_enabled', 'public_stock_display_mode', 'last_sale_at'];

    protected $casts = ['active' => 'boolean', 'is_demo' => 'boolean', 'track_serial' => 'boolean', 'warranty_enabled' => 'boolean', 'is_public' => 'boolean', 'online_sale_enabled' => 'boolean', 'last_sale_at' => 'datetime', 'approved_at' => 'datetime'];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function availableStock(): int
    {
        return $this->stock_quantity;
    }
}
