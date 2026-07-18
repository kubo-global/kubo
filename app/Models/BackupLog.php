<?php

namespace App\Models;

class BackupLog extends Model
{
    protected $guarded = [];

    protected $casts = [
        'size_bytes' => 'integer',
    ];

    public function triggeredBy()
    {
        return $this->belongsTo(User::class, 'triggered_by');
    }

    public static function lastSuccessful(): ?self
    {
        return static::where('status', 'success')
            ->latest()
            ->first();
    }

    public static function isOverdue(int $days = 7): bool
    {
        $last = static::lastSuccessful();

        if (!$last) {
            return true;
        }

        return $last->created_at->diffInDays(now()) >= $days;
    }

    /**
     * Record a successful backup.
     * Called by whatever backup mechanism the school uses.
     */
    public static function recordSuccess(string $method, string $destination, ?int $sizeBytes = null, ?string $filePath = null, ?int $triggeredBy = null): self
    {
        return static::create([
            'method' => $method,
            'destination' => $destination,
            'status' => 'success',
            'size_bytes' => $sizeBytes,
            'file_path' => $filePath,
            'triggered_by' => $triggeredBy,
        ]);
    }

    /**
     * Record a failed backup.
     */
    public static function recordFailure(string $method, string $destination, string $error, ?int $triggeredBy = null): self
    {
        return static::create([
            'method' => $method,
            'destination' => $destination,
            'status' => 'failed',
            'error' => $error,
            'triggered_by' => $triggeredBy,
        ]);
    }

    public function humanSize(): string
    {
        if (!$this->size_bytes) return '—';
        if ($this->size_bytes < 1024 * 1024) return round($this->size_bytes / 1024) . ' KB';
        return round($this->size_bytes / 1024 / 1024, 1) . ' MB';
    }
}
