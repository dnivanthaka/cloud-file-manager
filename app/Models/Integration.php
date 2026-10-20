<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ProviderType;
use Illuminate\Database\Eloquent\Model;

class Integration extends Model
{
    protected $fillable = [
        'user_id',
        'provider',
        'account_email',
        'account_name',
        'access_token',
        'refresh_token',
        'expires_at',
    ];

    protected $casts = [
        'provider' => ProviderType::class,
        'access_token' => 'encrypted',
        'refresh_token' => 'encrypted',
        'access_token_expires_at' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function isExpired(): bool
    {
        return time() >= ($this->expires_at - 300); // 5 min buffer
    }
}
