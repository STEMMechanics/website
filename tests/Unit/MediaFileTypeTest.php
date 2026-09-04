<?php

namespace Tests\Unit;

use App\Models\Media;
use Tests\TestCase;

class MediaFileTypeTest extends TestCase
{
    public function test_stop_motion_zip_bundle_uses_stop_motion_project_type_and_icon(): void
    {
        $media = new Media([
            'name' => 'My Movie.stopmotion.zip',
            'mime_type' => 'application/zip',
        ]);

        $this->assertSame('Stop Motion Studio Project', $media->file_type);
        $this->assertStringEndsWith('/thumbnails/stopmotionstudiomobile.webp', $media->thumbnail);
    }

    public function test_regular_zip_remains_a_generic_file(): void
    {
        $media = new Media([
            'name' => 'resources.zip',
            'mime_type' => 'application/zip',
        ]);

        $this->assertSame('File (zip)', $media->file_type);
        $this->assertStringEndsWith('/thumbnails/zip.webp', $media->thumbnail);
    }
}
