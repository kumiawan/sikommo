<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Contact extends Model
{
    protected $fillable = [
        'name',
        'phone',
        'tag',
        'status',
        'agent_id',
        'last_visit_at',
    ];

    protected function casts(): array
    {
        return ['last_visit_at' => 'datetime'];
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    public function lastVisitDays(): ?int
    {
        return $this->last_visit_at ? (int) $this->last_visit_at->diffInDays(now()) : null;
    }

    public function lastVisitColor(): string
    {
        $days = $this->lastVisitDays();

        return match (true) {
            $days === null => 'zinc',
            $days <= 30    => 'green',
            $days <= 90    => 'amber',
            default        => 'red',
        };
    }
    public function visits(): HasMany
    {
        return $this->hasMany(Visit::class);
    }
}
