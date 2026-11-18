<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            throw new \RuntimeException('Demo seeding is disabled in production.');
        }

        $password = Str::password(20);
        $user = User::firstOrCreate(['email' => 'demo@paytakht.local'], [
            'name' => 'کاربر نمونه',
            'phone' => '09120000001',
            'role' => 'EMPLOYEE',
            'active' => true,
            'password' => $password,
        ]);
        $category = Category::firstOrCreate(['name' => 'عمومی'], ['active' => true]);
        DB::table('expense_categories')->insertOrIgnore([
            'name' => 'هزینه عمومی', 'active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        Product::firstOrCreate(['sku' => 'DEMO-001'], [
            'name' => 'کالای نمونه', 'slug' => 'demo-001', 'category_id' => $category->id,
            'sale_price_toman' => 150000, 'minimum_stock' => 2, 'stock_quantity' => 0,
            'status' => 'ACTIVE', 'active' => true, 'created_by_user_id' => $user->id,
        ]);

        if ($this->command && $user->wasRecentlyCreated) {
            $this->command->warn("Synthetic demo user created: demo@paytakht.local / {$password}");
        }
    }
}
