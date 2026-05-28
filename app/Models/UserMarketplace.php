<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserMarketplace extends Model
{
    use HasFactory;

    protected $table = 'user_marketplaces';

    protected $fillable = [
        'user_id',
        'marketplace_name',
        'marketplace_email',
        'password',
        'status',
    ];

    /**
     * Relationship to the user who owns this marketplace account.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
