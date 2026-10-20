<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CloudFile extends Model
{
    protected $fillable = [
        'integration_id',
        'user_id',
        'provider_file_id',
        'name',
        'mime_type',
        'size',
        'view_link',
        'checksum',
        'is_folder',
    ];

    protected $casts = [
        'is_folder' => 'boolean',
        'size' => 'integer',
    ];

    public function integration(): BelongsTo
    {
        return $this->belongsTo(Integration::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
