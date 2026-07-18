<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class Teacher extends User
{
    protected $table= 'users';
    protected $guard_name = 'web';

    public function getForeignKey()
    {
        return 'user_id';
    }

    protected static function boot()
    {
        parent::boot();

        //Todo: make use of role package to determine if model is teacher or not.
        static::addGlobalScope('teacher', function (Builder $builder) {
            $builder->whereExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('teacher_offering')
                    ->whereRaw('teacher_offering.user_id = users.id');
            });
        });
    }

    public function offerings()
    {
        return $this->belongsToMany(Offering::class, 'teacher_offering');
    }
}
