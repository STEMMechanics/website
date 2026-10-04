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
            'heading' => 'Build, test and create in hands-on STEM workshops.',
            'body' => "Explore coding, robotics, engineering and creative technology through practical workshops for families, schools and community groups.\n\nEvery session gives young people room to make something, try an idea and learn by doing.",
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
        $image = $hero['image'] ?? '';
        $media = is_string($image) && $image !== '' ? self::publicImages()->whereKey($image)->first() : null;
        return $media?->url('lg') ?: asset('home-hero-1024.webp');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<Media>
     */
    public static function publicImages(): \Illuminate\Database\Eloquent\Builder
    {
        return Media::query()->where('visibility', 'public')->whereNull('password')->where('mime_type', 'like', 'image/%');
    }
}
