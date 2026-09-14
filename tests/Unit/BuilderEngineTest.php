<?php

namespace Tests\Unit;

use App\Services\Appearance\BuilderCssCompiler;
use App\Services\Appearance\BuilderUrlSanitizer;
use App\Services\Appearance\DynamicTagRegistry;
use App\Services\Appearance\LottieDocument;
use App\Services\Appearance\ShapeDividerDocument;
use App\Services\Forms\FormConditionDocument;
use App\Services\Forms\FormMergeTags;
use App\Models\Form;
use Tests\TestCase;

class BuilderEngineTest extends TestCase
{
    public function test_url_sanitizer_rejects_javascript(): void
    {
        $this->assertSame('', BuilderUrlSanitizer::sanitize('javascript:alert(1)'));
        $this->assertSame('/about', BuilderUrlSanitizer::sanitize('/about'));
        $this->assertSame('#cta', BuilderUrlSanitizer::sanitize('#cta'));
        $this->assertSame('https://example.com', BuilderUrlSanitizer::sanitize('https://example.com'));
    }

    public function test_shape_divider_allows_presets_only(): void
    {
        $out = ShapeDividerDocument::normalize([
            'top' => ['preset' => 'wave', 'color' => '#0389a1', 'height' => 80],
            'bottom' => ['preset' => '<script>', 'svg' => '<svg onload=alert(1)>'],
        ]);
        $this->assertIsArray($out);
        $this->assertSame('wave', $out['top']['preset']);
        $this->assertArrayNotHasKey('bottom', $out);
    }

    public function test_lottie_json_rejects_script(): void
    {
        $this->assertFalse(LottieDocument::isValidJson('{"v":"5","layers":[],"<script>":true}'));
        $this->assertFalse(LottieDocument::isValidJson('<script>alert(1)</script>'));
        $this->assertTrue(LottieDocument::isValidJson('{"v":"5.7.4","layers":[],"assets":[]}'));
    }

    public function test_dynamic_tags_are_allowlisted(): void
    {
        $this->assertTrue(DynamicTagRegistry::isAllowed('post.title'));
        $this->assertFalse(DynamicTagRegistry::isAllowed('post.query'));
        $this->assertFalse(DynamicTagRegistry::isAllowed('db.dump'));
    }

    public function test_form_conditions_and_or(): void
    {
        $and = FormConditionDocument::normalize([
            'relation' => 'and',
            'rules' => [
                ['field' => 'a', 'op' => 'equals', 'value' => 'yes'],
                ['field' => 'b', 'op' => 'not_empty', 'value' => ''],
            ],
        ]);
        $this->assertFalse(FormConditionDocument::isVisible($and, ['a' => 'yes', 'b' => '']));
        $this->assertTrue(FormConditionDocument::isVisible($and, ['a' => 'yes', 'b' => 'x']));

        $or = FormConditionDocument::normalize([
            'relation' => 'or',
            'rules' => [
                ['field' => 'a', 'op' => 'contains', 'value' => 'hi'],
                ['field' => 'b', 'op' => 'empty', 'value' => ''],
            ],
        ]);
        $this->assertTrue(FormConditionDocument::isVisible($or, ['a' => 'oh hi', 'b' => 'x']));
    }

    public function test_merge_tags_replace_allowlisted_keys(): void
    {
        $form = new Form(['title' => 'Contact']);
        $out = FormMergeTags::apply('Hello {{name}} from {{form_title}}', $form, ['name' => 'Ada']);
        $this->assertSame('Hello Ada from Contact', $out);
        $this->assertSame('', FormMergeTags::apply('{{unknown}}', $form, []));
    }

    public function test_css_compiler_hashes_used_types(): void
    {
        $a = BuilderCssCompiler::compile([['type' => 'flip_box', 'id' => 'a']]);
        $b = BuilderCssCompiler::compile([['type' => 'heading', 'id' => 'b']]);
        $this->assertNotSame('', $a['css']);
        $this->assertNotSame($a['hash'], $b['hash']);
        $this->assertStringContainsString('.b-flip', $a['css']);
    }

    public function test_overlay_meta_requires_delay_or_sitewide(): void
    {
        $this->assertNull(\App\Services\Appearance\ChromeLayoutSupport::normalizeOverlayMeta(['once' => true]));
        $out = \App\Services\Appearance\ChromeLayoutSupport::normalizeOverlayMeta([
            'delay_ms' => 1500,
            'once' => true,
            'sitewide' => true,
        ]);
        $this->assertSame(1500, $out['delay_ms']);
        $this->assertTrue($out['sitewide']);
    }
}
