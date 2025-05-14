<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Support\Str;

class User extends Authenticatable
{

    use HasApiTokens, HasFactory, Notifiable;

    protected $primaryKey = 'id';
    protected $fillable = [
        'google_id',
        'name',
        'email',
        'nip',
        'position',
        'study_program_id',
        'initial',
        'role',
        'avatar',
    ];

    public function setNameAttribute($value)
    {
        $this->attributes['name'] = Str::title(trim($value));
    }
    public function setInitialAttribute($value)
    {
        $this->attributes['initial'] = Str::upper(trim($value));
    }

    public function checkouts()
    {
        return $this->hasMany(Checkout::class);
    }

    public function studyProgram()
    {
        return $this->belongsTo(StudyProgram::class, 'study_program_id');
    }

    public $incrementing = false;

    protected $keyType = 'string';

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = (string) Str::uuid();
            }
        });
    }

    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
