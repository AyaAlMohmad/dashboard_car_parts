<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'invoice_number',
        'total',
        'paid',
        'remaining',
        'status',
        'currency',
        'exchange_rate',
        'notes',
        'sale_date',
    ];

    protected $casts = [
        'total' => 'decimal:2',
        'paid' => 'decimal:2',
        'remaining' => 'decimal:2',
        'exchange_rate' => 'decimal:2',
        'sale_date' => 'date',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }
}
