<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CloudFileParent extends Model
{
    protected $fillable = [
        'user_id', 'integration_id', 'provider_file_id', 'provider_parent_id',
    ];
}
