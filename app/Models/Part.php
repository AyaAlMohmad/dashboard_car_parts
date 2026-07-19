<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Part extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'part_number',
        'category_id',
        'quantity',
        'purchase_price',
        'sale_price',
        'purchase_price_usd',
        'sale_price_usd',
        'supplier',
        'alert_threshold',
        'status',
    ];

    protected $casts = [
        'purchase_price' => 'decimal:2',
        'sale_price' => 'decimal:2',
        'purchase_price_usd' => 'decimal:2',
        'sale_price_usd' => 'decimal:2',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::saving(function ($part) {
            if ($part->quantity <= 0) {
                $part->status = 'غير متوفر';
            } elseif ($part->quantity <= $part->alert_threshold) {
                $part->status = 'منخفض';
            } else {
                $part->status = 'متوفر';
            }
        });
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function invoiceItems(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }
}
