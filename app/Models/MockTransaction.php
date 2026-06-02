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
        'transaction_id',
        'status',
        'type',
        'reviewed',
        'reviewed_at',
        'reviewed_by',
        'buyer_name',
        'buyer_email',
        'payment_method',
        'notes',
    ];

    protected $casts = [
        'transaction_date' => 'datetime',
        'reviewed_at' => 'datetime',
        'reviewed' => 'boolean',
        'amount' => 'integer',
    ];

    /**
     * Transaction status constants
     */
    const STATUS_PENDING = 'pending';
    const STATUS_SUCCESS = 'success';
    const STATUS_FAILED = 'failed';
    const STATUS_REFUND = 'refund';
    const STATUS_SUSPICIOUS = 'suspicious';

    /**
     * Transaction type constants
     */
    const TYPE_INCOME = 'income';
    const TYPE_REFUND = 'refund';

    /**
     * Relationship to the user who owns this transaction.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope to filter by income transactions
     */
    public function scopeIncome($query)
    {
        return $query->where('type', self::TYPE_INCOME);
    }

    /**
     * Scope to filter by refund transactions
     */
    public function scopeRefunds($query)
    {
        return $query->where('type', self::TYPE_REFUND);
    }

    /**
     * Scope to filter successful transactions
     */
    public function scopeSuccessful($query)
    {
        return $query->where('status', self::STATUS_SUCCESS);
    }

    /**
     * Scope to filter pending transactions
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope to filter unreviewed transactions
     */
    public function scopeUnreviewed($query)
    {
        return $query->where('reviewed', false);
    }
}
