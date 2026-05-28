<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MockTransaction extends Model
{
    use HasFactory;

    protected $table = 'mock_transactions';

    protected $fillable = [
        'user_id',
        'marketplace_name',
        'product_name',
        'amount',
        'transaction_date',
    ];

    protected $casts = [
        'transaction_date' => 'datetime',
    ];

    /**
     * Relationship to the user who owns this transaction.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
