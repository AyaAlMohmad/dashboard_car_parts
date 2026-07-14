<?php

namespace App\Exports;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Part;
use App\Models\Sale;
use App\Models\Payment;
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
            new SingleSheetExport('Parts', $this->toRows(Part::all(), ['id', 'name', 'part_number', 'category_id', 'quantity', 'purchase_price', 'sale_price', 'supplier', 'status', 'created_at'])),
            new SingleSheetExport('Sales', $this->toRows(Sale::all(), ['id', 'customer_id', 'part_id', 'quantity', 'total', 'paid', 'remaining', 'status', 'sale_date', 'created_at'])),
            new SingleSheetExport('Payments', $this->toRows(Payment::all(), ['id', 'customer_id', 'amount', 'notes', 'payment_date', 'created_at'])),
            new SingleSheetExport('Suppliers', $this->toRows(Supplier::all(), ['id', 'name', 'phone', 'address', 'balance', 'status', 'created_at'])),
            new SingleSheetExport('Purchases', $this->toRows(Purchase::all(), ['id', 'supplier_id', 'part_id', 'quantity', 'total', 'paid', 'remaining', 'status', 'purchase_date', 'created_at'])),
            new SingleSheetExport('SupplierPayments', $this->toRows(SupplierPayment::all(), ['id', 'supplier_id', 'amount', 'notes', 'payment_date', 'created_at'])),
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
