<?php

namespace Tests\Feature;

use Tests\TestCase;

class PublicSiteMetaTest extends TestCase
{
    public function test_public_site_meta_is_reachable_without_authentication(): void
    {
        $response = $this->getJson('/api/public/site-meta');

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'site_name',
                'name',
                'site_languages',
                'default_locale',
            ],
        ]);
    }
}
