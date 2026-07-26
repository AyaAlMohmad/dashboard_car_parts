<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Withdrawal extends Model
{
    use HasFactory;

    protected $fillable = [
        'person_name',
        'amount',
        'currency',
        'reason',
        'withdrawal_date',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'withdrawal_date' => 'date',
    ];
}
