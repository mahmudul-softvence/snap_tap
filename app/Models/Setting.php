<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class Setting extends Model
{
    protected $fillable = ['key', 'value', 'is_sensitive'];

    public function getValueAttribute($value)
    {
        return $this->is_sensitive && $value
            ? Crypt::decryptString($value)
            : $value;
    }

    public function setValueAttribute($value)
    {
        $this->attributes['value'] = $this->is_sensitive && $value
            ? Crypt::encryptString($value)
            : $value;
    }
}
