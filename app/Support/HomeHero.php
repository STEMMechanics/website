<?php

namespace App\Support;

use App\Models\Media;
use App\Models\SiteOption;

class HomeHero
{
    public const OPTION = 'home.hero';

    public static function defaults(): array
    {
        return [
            'image' => '',
            'caption' => 'Steady Hand Game in Ravenshoe',
            'eyebrow' => 'Join the fun',
            'heading' => 'Workshops that feel playful, practical, and a little unexpected.',
            'body' => "To keep up with our ever-changing world, it's important to encourage and support a new generation of curious minds who love science, engineering, art, and leadership.\n\nOur fun and exciting workshops can unlock countless opportunities for new ideas and improvements, giving kids the skills they need to solve any problem that comes their way.",
        ];
    }

    public static function content(): array
    {
        $saved = json_decode(SiteOption::value(self::OPTION, '{}'), true);
        if (is_array($saved) && !array_key_exists('body', $saved) && (isset($saved['paragraph_one']) || isset($saved['paragraph_two']))) {
            $saved['body'] = implode("\n\n", array_filter([$saved['paragraph_one'] ?? '', $saved['paragraph_two'] ?? ''], fn ($value) => is_string($value) && trim($value) !== ''));
        }
        return array_replace(self::defaults(), is_array($saved) ? array_filter(array_intersect_key($saved, self::defaults()), 'is_string') : []);
    }

    public static function imageUrl(array $hero): string
    {
        $media = empty($hero['image']) ? null : self::publicImages()->find($hero['image']);
        return $media?->url('lg') ?: asset('home-hero-1024.webp');
    }

    public static function publicImages(): \Illuminate\Database\Eloquent\Builder
    {
        return Media::query()->where('visibility', 'public')->whereNull('password')->where('mime_type', 'like', 'image/%');
    }
}
