<?php

namespace Tests\Unit;

use App\Models\Material;
use PHPUnit\Framework\TestCase;

class MaterialVideoEmbedTest extends TestCase
{
    public function test_kinescope_share_link_becomes_embed_player(): void
    {
        $this->assertSame(
            'https://kinescope.io/embed/4vxiS3W7noYwHFFpcyKqxi',
            Material::videoEmbedUrl('https://kinescope.io/4vxiS3W7noYwHFFpcyKqxi')
        );
    }

    public function test_kinescope_embed_link_is_kept_as_is(): void
    {
        $this->assertSame(
            'https://kinescope.io/embed/4vxiS3W7noYwHFFpcyKqxi',
            Material::videoEmbedUrl('https://kinescope.io/embed/4vxiS3W7noYwHFFpcyKqxi')
        );
    }

    public function test_kinescope_site_page_is_not_a_video(): void
    {
        $this->assertNull(Material::videoEmbedUrl('https://kinescope.io/ru/pricing'));
    }

    public function test_body_with_kinescope_link_gets_iframe_player(): void
    {
        $material = new Material(['body' => '<p><a href="https://kinescope.io/4vxiS3W7noYwHFFpcyKqxi">видео</a></p>']);

        $this->assertStringContainsString(
            '<iframe src="https://kinescope.io/embed/4vxiS3W7noYwHFFpcyKqxi"',
            $material->bodyWithVideos()
        );
    }
}
