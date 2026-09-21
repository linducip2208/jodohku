<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContactMessage extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'email', 'topic', 'message', 'ip', 'status', 'handled_by'];

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    public function markHandled(User $admin): void
    {
        $this->update(['status' => 'handled', 'handled_by' => $admin->id]);
    }
}
