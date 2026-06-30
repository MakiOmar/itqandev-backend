<?php

namespace Tests\Unit;

use App\Models\AppMedia;
use App\Support\MediaLibrary\DatePathGenerator;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DatePathGeneratorTest extends TestCase
{
    private function media(): AppMedia
    {
        $media = new AppMedia();
        $media->file_name = 'logo-light.webp';
        $media->created_at = Carbon::create(2026, 6, 30, 12, 0, 0);

        return $media;
    }

    public function test_get_path_ends_with_trailing_slash(): void
    {
        $path = (new DatePathGenerator())->getPath($this->media());

        // Spatie concatenates the file name directly: the directory MUST end with "/".
        $this->assertSame('media/2026/06/30/', $path);
    }

    public function test_conversions_and_responsive_paths_end_with_trailing_slash(): void
    {
        $generator = new DatePathGenerator();
        $media = $this->media();

        $this->assertSame('media/2026/06/30/conversions/', $generator->getPathForConversions($media));
        $this->assertSame('media/2026/06/30/responsive-images/', $generator->getPathForResponsiveImages($media));
    }

    public function test_full_relative_path_has_separator_before_file_name(): void
    {
        $media = $this->media();
        $relative = (new DatePathGenerator())->getPath($media).$media->file_name;

        $this->assertSame('media/2026/06/30/logo-light.webp', $relative);
    }
}
