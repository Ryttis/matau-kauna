<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class PasswordPolicy extends Model
{
    protected $table = 'password_policy';

    protected $fillable = [
        'min_length',
        'require_lowercase',
        'require_uppercase',
        'require_special',
        'two_factor_can_enable',
        'prohibit_name_email',
    ];

    protected $casts = [
        'require_lowercase' => 'boolean',
        'require_uppercase' => 'boolean',
        'require_special' => 'boolean',
        'two_factor_can_enable' => 'boolean',
        'prohibit_name_email' => 'boolean',
    ];

    public static function get(): self
    {
        $key = 'password_policy';

        return Cache::remember($key, 300, function () {
            try {
                $row = static::query()->first();

                return $row ?? new static([
                    'min_length' => 8,
                    'require_lowercase' => true,
                    'require_uppercase' => true,
                    'require_special' => true,
                    'two_factor_can_enable' => true,
                    'prohibit_name_email' => true,
                ]);
            } catch (\Throwable $e) {
                return new static([
                    'min_length' => 8,
                    'require_lowercase' => true,
                    'require_uppercase' => true,
                    'require_special' => true,
                    'two_factor_can_enable' => true,
                    'prohibit_name_email' => true,
                ]);
            }
        });
    }

    public static function clearCache(): void
    {
        Cache::forget('password_policy');
    }

    public function rules(): array
    {
        $r = ['required', 'string', 'min:'.$this->min_length, 'confirmed'];
        if ($this->require_lowercase) {
            $r[] = 'regex:/[a-z]/';
        }
        if ($this->require_uppercase) {
            $r[] = 'regex:/[A-Z]/';
        }
        if ($this->require_special) {
            $r[] = 'regex:/[^A-Za-z0-9]/';
        }

        return $r;
    }

    public function validateProhibitNameEmail(string $password, ?string $name, ?string $email): bool
    {
        if (! $this->prohibit_name_email) {
            return true;
        }
        $lower = mb_strtolower($password);
        if ($name) {
            $parts = preg_split('/\s+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY);
            foreach ($parts as $part) {
                if (mb_strlen($part) >= 2 && mb_strpos($lower, mb_strtolower($part)) !== false) {
                    return false;
                }
            }
        }
        if ($email && mb_strlen($email) >= 3) {
            $local = explode('@', $email)[0] ?? '';
            if ($local && mb_strpos($lower, mb_strtolower($local)) !== false) {
                return false;
            }
        }

        return true;
    }
}
