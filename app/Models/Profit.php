<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Profit extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_id',
        'invoice_item_id',
        'customer_id',
        'part_id',
        'quantity',
        'returned_quantity',
        'unit_price',
        'purchase_price',
        'currency',
        'sale_date',
        'profit',
        'returned_profit',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'returned_quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'purchase_price' => 'decimal:2',
        'profit' => 'decimal:2',
        'returned_profit' => 'decimal:2',
        'sale_date' => 'date',
    ];
}
