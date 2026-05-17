<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalaryHistory extends Model
{
    protected $fillable = [
        'user_id',
        'previous_salary',
        'new_salary',
        'effective_date',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'effective_date' => 'date',
            'previous_salary' => 'decimal:2',
            'new_salary' => 'decimal:2',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
