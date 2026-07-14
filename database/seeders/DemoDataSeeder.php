<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Part;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\Supplier;
use Illuminate\Database\Seeder;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        // Categories
        $categories = [
            'فلاتر',
            'بطاريات',
            'فرامل',
            'زيوت',
            'كوبايات',
            'محرك',
        ];

        foreach ($categories as $cat) {
            Category::create(['name' => $cat]);
        }

        // Customers
        Customer::create([
            'name' => 'أحمد محمد',
            'phone' => '01001234567',
            'address' => 'القاهرة',
            'balance' => 150000,
            'status' => 'متوان',
        ]);

        Customer::create([
            'name' => 'محمود علي',
            'phone' => '01111234567',
            'address' => 'الجيزة',
            'balance' => -35000,
            'status' => 'مدين',
        ]);

        Customer::create([
            'name' => 'سعيد حسين',
            'phone' => '01221234567',
            'address' => 'الإسكندرية',
            'balance' => 0,
            'status' => 'متوان',
        ]);

        // Parts
        $parts = [
            [
                'name' => 'فلتر زيت',
                'part_number' => 'FO-001',
                'category_id' => 1,
                'quantity' => 22,
                'purchase_price' => 5000,
                'sale_price' => 8500,
                'supplier' => 'الوكيل الرئيسي',
                'alert_threshold' => 5,
            ],
            [
                'name' => 'بطارية 70 أمبير',
                'part_number' => 'BAT-070',
                'category_id' => 2,
                'quantity' => 8,
                'purchase_price' => 180000,
                'sale_price' => 220000,
                'supplier' => 'شركة البطاريات',
                'alert_threshold' => 3,
            ],
            [
                'name' => 'طقم فرامل أمامي',
                'part_number' => 'BR-202',
                'category_id' => 3,
                'quantity' => 11,
                'purchase_price' => 70000,
                'sale_price' => 95000,
                'supplier' => 'مستورد',
                'alert_threshold' => 5,
            ],
            [
                'name' => 'زيت محرك 5W-30',
                'part_number' => 'OIL-530',
                'category_id' => 4,
                'quantity' => 3,
                'purchase_price' => 30000,
                'sale_price' => 45000,
                'supplier' => 'الوكيل',
                'alert_threshold' => 5,
            ],
            [
                'name' => 'شمعات احتراق (بوجيهات)',
                'part_number' => 'SP-004',
                'category_id' => 5,
                'quantity' => 40,
                'purchase_price' => 12000,
                'sale_price' => 20000,
                'supplier' => 'مستورد',
                'alert_threshold' => 10,
            ],
            [
                'name' => 'سير توقيت',
                'part_number' => 'TB-101',
                'category_id' => 6,
                'quantity' => 6,
                'purchase_price' => 35000,
                'sale_price' => 55000,
                'supplier' => 'الوكيل الرئيسي',
                'alert_threshold' => 3,
            ],
        ];

        foreach ($parts as $part) {
            Part::create($part);
        }

        // Suppliers
        Supplier::create([
            'name' => 'الوكيل الرئيسي',
            'phone' => '01001111111',
            'address' => 'القاهرة',
            'balance' => 0,
            'status' => 'متوازن',
        ]);

        Supplier::create([
            'name' => 'شركة البطاريات',
            'phone' => '01002222222',
            'address' => 'الجيزة',
            'balance' => 0,
            'status' => 'متوازن',
        ]);

        Supplier::create([
            'name' => 'مستورد',
            'phone' => '01003333333',
            'address' => 'الإسكندرية',
            'balance' => 0,
            'status' => 'متوازن',
        ]);

        // Sales
        Sale::create([
            'customer_id' => 2,
            'part_id' => 3,
            'quantity' => 1,
            'total' => 95000,
            'paid' => 95000,
            'remaining' => 0,
            'status' => 'مسدد',
            'sale_date' => '2026-07-07',
        ]);

        Sale::create([
            'customer_id' => 1,
            'part_id' => 2,
            'quantity' => 3,
            'total' => 660000,
            'paid' => 100000,
            'remaining' => 560000,
            'status' => 'عليه دين',
            'sale_date' => '2026-07-06',
        ]);

        // Purchases
        Purchase::create([
            'supplier_id' => 1,
            'part_id' => 1,
            'quantity' => 30,
            'total' => 150000,
            'paid' => 150000,
            'remaining' => 0,
            'status' => 'مسدد',
            'purchase_date' => '2026-07-01',
        ]);

        Purchase::create([
            'supplier_id' => 2,
            'part_id' => 2,
            'quantity' => 15,
            'total' => 2700000,
            'paid' => 500000,
            'remaining' => 2200000,
            'status' => 'علينا دين',
            'purchase_date' => '2026-07-02',
        ]);
    }
}
