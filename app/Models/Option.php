<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Option extends Model
{
    protected $guarded = ['id'];

    public static function booted(): void
    {
        static::deleting(function ($record): void {
            if ($record->source_id !== null) {
                throw new \Exception('Cannot delete a resource that has been sourced.');
            }
        });
    }
}
