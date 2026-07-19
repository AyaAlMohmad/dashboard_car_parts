<?php

namespace App\Exports;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Part;
use App\Models\Sale;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\Supplier;
use App\Models\Purchase;
use App\Models\SupplierPayment;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class DatabaseBackupExport implements WithMultipleSheets
{
    use Exportable;

    public function sheets(): array
    {
        return [
            new SingleSheetExport('Categories', $this->toRows(Category::all(), ['id', 'name', 'created_at'])),
            new SingleSheetExport('Customers', $this->toRows(Customer::all(), ['id', 'name', 'phone', 'address', 'balance', 'status', 'created_at'])),
            new SingleSheetExport('Parts', $this->toRows(Part::all(), ['id', 'name', 'part_number', 'category_id', 'quantity', 'purchase_price', 'sale_price', 'purchase_price_usd', 'sale_price_usd', 'supplier', 'status', 'created_at'])),
            new SingleSheetExport('Invoices', $this->toRows(Invoice::all(), ['id', 'customer_id', 'invoice_number', 'total', 'paid', 'remaining', 'status', 'currency', 'exchange_rate', 'notes', 'sale_date', 'created_at'])),
            new SingleSheetExport('InvoiceItems', $this->toRows(InvoiceItem::all(), ['id', 'invoice_id', 'part_id', 'quantity', 'unit_price', 'total', 'created_at'])),
            new SingleSheetExport('Sales', $this->toRows(Sale::all(), ['id', 'customer_id', 'part_id', 'quantity', 'total', 'paid', 'remaining', 'status', 'notes', 'sale_date', 'created_at'])),
            new SingleSheetExport('Payments', $this->toRows(Payment::all(), ['id', 'customer_id', 'amount', 'notes', 'payment_date', 'created_at'])),
            new SingleSheetExport('Suppliers', $this->toRows(Supplier::all(), ['id', 'name', 'phone', 'address', 'balance', 'status', 'created_at'])),
            new SingleSheetExport('Purchases', $this->toRows(Purchase::all(), ['id', 'supplier_id', 'part_id', 'quantity', 'total', 'paid', 'remaining', 'status', 'purchase_date', 'created_at'])),
            new SingleSheetExport('SupplierPayments', $this->toRows(SupplierPayment::all(), ['id', 'supplier_id', 'amount', 'notes', 'payment_date', 'created_at'])),
            new SingleSheetExport('Settings', $this->toRows(Setting::all(), ['id', 'key', 'value', 'created_at'])),
        ];
    }

    private function toRows($collection, array $columns): array
    {
        $rows = [];
        $rows[] = $columns;
        foreach ($collection as $item) {
            $row = [];
            foreach ($columns as $col) {
                $row[] = $item->{$col} ?? '';
            }
            $rows[] = $row;
        }
        return $rows;
    }
}
