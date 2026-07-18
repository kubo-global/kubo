<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Profile extends Model
{
    use HasFactory;

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'birth_date' => 'datetime:Y-m-d',
    ];

    /**
     * Gender is stored canonically as 'M', 'F', 'O' (other) or null, normalised on
     * write. The app feeds it in several shapes — 'm'/'f' (seeder), 'M'/'F' (profile
     * form), 'male'/'female' (rollover) — so normalising here keeps every reader
     * (students list, NAT per-sex stats, growth chart) consistent instead of each
     * guessing the casing.
     */
    protected function gender(): Attribute
    {
        return Attribute::make(
            set: fn ($value) => self::normalizeGender($value),
        );
    }

    public static function normalizeGender($value): ?string
    {
        return match (substr(strtoupper(trim((string) $value)), 0, 1)) {
            'F' => 'F',
            'M' => 'M',
            'O' => 'O',
            default => null,
        };
    }

    public function isFemale(): bool
    {
        return $this->gender === 'F';
    }

    public function isMale(): bool
    {
        return $this->gender === 'M';
    }

    public function user()
    {
        return $this->belongsTo('App\Models\User');
    }

    /**
     * Carbon 3's diffInYears is signed, so now()->diffInYears(birth) came out
     * negative: the profile read "(-11 years old)".
     */
    public function age(): ?int
    {
        return $this->birth_date ? Carbon::parse($this->birth_date)->age : null;
    }
}
