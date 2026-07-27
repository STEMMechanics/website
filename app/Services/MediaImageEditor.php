<?php

namespace App\Services;

use App\Models\Media;
use Illuminate\Http\UploadedFile;
use Intervention\Image\Drivers\Imagick\Driver;
use Intervention\Image\ImageManager;

class MediaImageEditor
{
    /**
     * @param array<string, mixed> $input
     * @return array{rotation:int,crop_top:int,crop_right:int,crop_bottom:int,crop_left:int}
     */
    public function normalize(array $input): array
    {
        $rotation = (int) ($input['rotation'] ?? 0);
        $rotation = (($rotation % 360) + 360) % 360;
        $rotation = (int) (round($rotation / 90) * 90) % 360;

        $edits = [
            'rotation' => $rotation,
            'crop_top' => $this->clampPercent($input['crop_top'] ?? 0),
            'crop_right' => $this->clampPercent($input['crop_right'] ?? 0),
            'crop_bottom' => $this->clampPercent($input['crop_bottom'] ?? 0),
            'crop_left' => $this->clampPercent($input['crop_left'] ?? 0),
        ];

        return $this->normalizeCropTotals($edits);
    }

    /**
     * @param array<string, mixed> $input
     */
    public function hasEdits(array $input): bool
    {
        $edits = $this->normalize($input);

        return $edits['rotation'] !== 0
            || $edits['crop_top'] !== 0
            || $edits['crop_right'] !== 0
            || $edits['crop_bottom'] !== 0
            || $edits['crop_left'] !== 0;
    }

    public function supportsMime(?string $mimeType): bool
    {
        return is_string($mimeType) && str_starts_with($mimeType, 'image/');
    }

    public function supportsMedia(Media $media): bool
    {
        return $this->supportsMime($media->mime_type);
    }

    /**
     * @param array<string, mixed> $input
     */
    public function applyToUploadedFile(UploadedFile $file, array $input): UploadedFile
    {
        $edits = $this->normalize($input);
        if (! $this->hasEdits($edits) || ! $this->supportsMime($file->getMimeType())) {
            return $file;
        }

        $outputPath = $this->renderEditedTempFile($file->getRealPath() ?: $file->path(), pathinfo($file->getClientOriginalName(), PATHINFO_EXTENSION), $edits);

        return new UploadedFile(
            $outputPath,
            $file->getClientOriginalName(),
            mime_content_type($outputPath) ?: $file->getMimeType(),
            null,
            true,
        );
    }

    /**
     * @param array{rotation:int,crop_top:int,crop_right:int,crop_bottom:int,crop_left:int} $edits
     */
    public function renderEditedTempFile(string $sourcePath, string $extension, array $edits): string
    {
        $extension = trim(strtolower($extension)) ?: 'png';
        $outputPath = tempnam(sys_get_temp_dir(), 'media-edit-');
        if ($outputPath === false) {
            throw new \RuntimeException('Could not create a temporary image file.');
        }

        $targetPath = $outputPath.'.'.$extension;
        @rename($outputPath, $targetPath);

        $manager = new ImageManager(new Driver());
        $image = $manager->read($sourcePath);

        if ($edits['rotation'] !== 0) {
            $image->rotate($edits['rotation']);
        }

        $width = max(1, (int) $image->width());
        $height = max(1, (int) $image->height());

        $left = (int) round($width * ($edits['crop_left'] / 100));
        $right = (int) round($width * ($edits['crop_right'] / 100));
        $top = (int) round($height * ($edits['crop_top'] / 100));
        $bottom = (int) round($height * ($edits['crop_bottom'] / 100));

        $cropWidth = max(1, $width - $left - $right);
        $cropHeight = max(1, $height - $top - $bottom);

        if ($cropWidth !== $width || $cropHeight !== $height) {
            $image->crop($cropWidth, $cropHeight, $left, $top);
        }

        $image->save($targetPath, quality: 90);

        return $targetPath;
    }

    /**
     * @param array{rotation:int,crop_top:int,crop_right:int,crop_bottom:int,crop_left:int} $edits
     * @return array{rotation:int,crop_top:int,crop_right:int,crop_bottom:int,crop_left:int}
     */
    private function normalizeCropTotals(array $edits): array
    {
        $maxTotal = 95;

        $horizontal = $edits['crop_left'] + $edits['crop_right'];
        if ($horizontal > $maxTotal) {
            $scale = $maxTotal / $horizontal;
            $edits['crop_left'] = (int) round($edits['crop_left'] * $scale);
            $edits['crop_right'] = (int) round($edits['crop_right'] * $scale);
        }

        $vertical = $edits['crop_top'] + $edits['crop_bottom'];
        if ($vertical > $maxTotal) {
            $scale = $maxTotal / $vertical;
            $edits['crop_top'] = (int) round($edits['crop_top'] * $scale);
            $edits['crop_bottom'] = (int) round($edits['crop_bottom'] * $scale);
        }

        return $edits;
    }

    /**
     * @param mixed $value
     */
    private function clampPercent($value): int
    {
        return max(0, min(90, (int) round((float) $value)));
    }
}
