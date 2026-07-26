<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Purchase extends Model
{
    use HasFactory;

    protected $fillable = [
        'supplier_id',
        'part_id',
        'quantity',
        'unit_price',
        'total',
        'paid',
        'remaining',
        'status',
        'currency',
        'purchase_date',
        'notes',
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'total' => 'decimal:2',
        'paid' => 'decimal:2',
        'remaining' => 'decimal:2',
        'purchase_date' => 'date',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function part(): BelongsTo
    {
        return $this->belongsTo(Part::class);
    }
}
