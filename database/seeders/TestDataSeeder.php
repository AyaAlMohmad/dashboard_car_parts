<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Part;
use App\Models\Payment;
use App\Models\Profit;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Models\User;
use App\Models\Withdrawal;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TestDataSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            // users
            for ($i = 1; $i <= 10; $i++) {
                User::create([
                    'name' => "User {$i}",
                    'email' => "user{$i}@test.com",
                    'password' => bcrypt('password'),
                ]);
            }

            // categories
            $categoryIds = [];
            for ($i = 1; $i <= 10; $i++) {
                $c = Category::firstOrCreate(['name' => "فئة اختبار {$i}"]);
                $categoryIds[] = $c->id;
            }

            // parts
            $partIds = [];
            for ($i = 1; $i <= 10; $i++) {
                $p = Part::firstOrCreate(
                    ['part_number' => "TEST-{$i}"],
                    [
                        'name' => "قطعة اختبار {$i}",
                        'category_id' => $categoryIds[($i - 1) % 10],
                        'quantity' => $i * 5,
                        'purchase_price' => $i * 1000,
                        'sale_price' => $i * 1500,
                        'purchase_price_usd' => 0,
                        'sale_price_usd' => 0,
                        'supplier' => 'مورد اختبار',
                        'alert_threshold' => 5,
                        'image' => '',
                        'notes' => '',
                    ]
                );
                $partIds[] = $p->id;
            }

            // customers
            $customerIds = [];
            for ($i = 1; $i <= 10; $i++) {
                $balance = ($i % 3 === 0) ? -$i * 1000 : ($i % 2 === 0 ? $i * 1000 : 0);
                $c = Customer::firstOrCreate(
                    ['phone' => "0999000{$i}"],
                    [
                        'name' => "عميل اختبار {$i}",
                        'address' => 'دمشق',
                        'balance' => 0,
                        'balance_syp' => $balance,
                        'balance_usd' => 0,
                        'status' => $balance < 0 ? 'مدين' : ($balance > 0 ? 'دائن' : 'متوان'),
                    ]
                );
                $customerIds[] = $c->id;
            }

            // suppliers
            $supplierIds = [];
            for ($i = 1; $i <= 10; $i++) {
                $balance = ($i % 3 === 0) ? -$i * 1000 : ($i % 2 === 0 ? $i * 1000 : 0);
                $s = Supplier::firstOrCreate(
                    ['phone' => "0888000{$i}"],
                    [
                        'name' => "مورد اختبار {$i}",
                        'address' => 'دمشق',
                        'balance' => 0,
                        'balance_syp' => $balance,
                        'balance_usd' => 0,
                        'status' => $balance > 0 ? 'علينا' : ($balance < 0 ? 'لنا' : 'متوازن'),
                    ]
                );
                $supplierIds[] = $s->id;
            }

            // sales
            for ($i = 1; $i <= 10; $i++) {
                $total = $i * 5000;
                $paid = $i * 2000;
                $remaining = $total - $paid;
                Sale::create([
                    'customer_id' => $customerIds[($i - 1) % 10],
                    'part_id' => $partIds[($i - 1) % 10],
                    'quantity' => $i,
                    'total' => $total,
                    'paid' => $paid,
                    'remaining' => $remaining,
                    'status' => $remaining > 0 ? 'عليه دين' : 'مسدد',
                    'sale_date' => now()->subDays($i)->toDateString(),
                ]);
            }

            // purchases
            for ($i = 1; $i <= 10; $i++) {
                $total = $i * 3000;
                $paid = $i * 1000;
                $remaining = $total - $paid;
                Purchase::create([
                    'supplier_id' => $supplierIds[($i - 1) % 10],
                    'part_id' => $partIds[($i - 1) % 10],
                    'quantity' => $i * 2,
                    'returned_quantity' => 0,
                    'unit_price' => 1500,
                    'total' => $total,
                    'paid' => $paid,
                    'credit_used' => 0,
                    'remaining' => $remaining,
                    'status' => $remaining > 0 ? 'علينا دين' : 'مسدد',
                    'currency' => $i % 2 === 0 ? 'USD' : 'SYP',
                    'purchase_date' => now()->subDays($i)->toDateString(),
                    'notes' => 'شراء اختبار',
                ]);
            }

            // invoices + items
            $invoiceIds = [];
            $invoiceItemIds = [];
            for ($i = 1; $i <= 10; $i++) {
                $total = $i * 1000;
                $paid = $i * 1000;
                $remaining = 0;
                $invoice = Invoice::create([
                    'customer_id' => $customerIds[($i - 1) % 10],
                    'invoice_number' => 'INV-TEST-' . $i,
                    'total' => $total,
                    'paid' => $paid,
                    'credit_used' => 0,
                    'debt' => 0,
                    'remaining' => $remaining,
                    'status' => $remaining > 0 ? 'عليه دين' : 'مسدد',
                    'currency' => $i % 2 === 0 ? 'USD' : 'SYP',
                    'exchange_rate' => 1,
                    'sale_date' => now()->subDays($i)->toDateString(),
                    'notes' => 'فاتورة اختبار',
                ]);

                $item = InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'part_id' => $partIds[($i - 1) % 10],
                    'quantity' => $i,
                    'returned_quantity' => 0,
                    'unit_price' => 1000,
                    'total' => $i * 1000,
                ]);

                $invoiceIds[] = $invoice->id;
                $invoiceItemIds[] = $item->id;
            }

            // payments
            for ($i = 1; $i <= 10; $i++) {
                Payment::create([
                    'customer_id' => $customerIds[($i - 1) % 10],
                    'amount' => $i * 500,
                    'currency' => $i % 2 === 0 ? 'USD' : 'SYP',
                    'payment_date' => now()->subDays($i)->toDateString(),
                    'notes' => 'دفعة اختبار',
                ]);
            }

            // supplier payments
            for ($i = 1; $i <= 10; $i++) {
                SupplierPayment::create([
                    'supplier_id' => $supplierIds[($i - 1) % 10],
                    'purchase_id' => null,
                    'amount' => $i * 400,
                    'currency' => $i % 2 === 0 ? 'USD' : 'SYP',
                    'payment_date' => now()->subDays($i)->toDateString(),
                    'notes' => 'دفعة مورد اختبار',
                ]);
            }

            // withdrawals
            for ($i = 1; $i <= 10; $i++) {
                Withdrawal::create([
                    'person_name' => "شخص {$i}",
                    'amount' => $i * 200,
                    'currency' => $i % 2 === 0 ? 'USD' : 'SYP',
                    'reason' => 'سحب اختبار',
                    'withdrawal_date' => now()->subDays($i)->toDateString(),
                    'notes' => '',
                ]);
            }

            // profits
            for ($i = 1; $i <= 10; $i++) {
                Profit::create([
                    'invoice_id' => $invoiceIds[($i - 1) % 10],
                    'invoice_item_id' => $invoiceItemIds[($i - 1) % 10],
                    'customer_id' => $customerIds[($i - 1) % 10],
                    'part_id' => $partIds[($i - 1) % 10],
                    'quantity' => $i,
                    'returned_quantity' => 0,
                    'unit_price' => 1000,
                    'purchase_price' => 600,
                    'currency' => $i % 2 === 0 ? 'USD' : 'SYP',
                    'sale_date' => now()->subDays($i)->toDateString(),
                    'profit' => $i * 400,
                    'returned_profit' => 0,
                ]);
            }
        });
    }
}
