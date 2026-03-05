<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class App extends Model
{
    protected $fillable = ['name', 'slug', 'api_end_point', 'is_active'];


    public function metrics(): HasMany
    {
        return $this->hasMany(AppMetric::class);
    }
}