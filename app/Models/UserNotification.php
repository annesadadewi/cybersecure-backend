<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserNotification extends Model
{
    protected $fillable = [
        'user_id',
        'category',
        'title',
        'message',
        'marketplace_name',
        'product_name',
        'amount',
        'is_read',
        'occurred_at',
    ];

    protected $casts = [
        'is_read' => 'boolean',
        'amount' => 'integer',
        'occurred_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
