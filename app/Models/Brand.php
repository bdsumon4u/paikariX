<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Brand extends Model
{
    protected $fillable = [
        'image_id', 'name', 'slug',
    ];

    public static function booted(): void
    {
        static::saved(function (): void {
            cache()->forget('brands');
        });

        static::deleting(function ($record): void {
            if ($record->source_id !== null) {
                throw new \Exception('Cannot delete a resource that has been sourced.');
            }

            cache()->forget('brands');
        });
    }

    public static function cached()
    {
        return cache()->rememberForever('brands', fn () => Brand::all());
    }

    public function image()
    {
        return $this->belongsTo(Image::class);
    }

    public function products()
    {
        return $this->hasMany(Product::class)
            ->whereNull('parent_id');
    }
}
