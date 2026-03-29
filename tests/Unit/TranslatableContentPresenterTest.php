<?php

namespace Tests\Unit;

use App\Models\BlogPost;
use App\Models\BlogPostTranslation;
use App\Support\TranslatableContentPresenter;
use Illuminate\Support\Collection;
use Tests\TestCase;

class TranslatableContentPresenterTest extends TestCase
{
    public function test_blog_post_overlays_translation_when_locale_differs_from_primary(): void
    {
        $post = new BlogPost([
            'content_locale' => null,
            'title' => 'English title',
            'excerpt' => 'English excerpt',
            'content' => 'English body',
        ]);

        $post->setRelation('translations', new Collection([
            new BlogPostTranslation([
                'locale' => 'ar',
                'title' => 'عنوان',
                'excerpt' => 'ملخص',
                'content' => 'محتوى',
            ]),
        ]));

        TranslatableContentPresenter::applyBlogPost($post, 'ar');

        $this->assertSame('عنوان', $post->title);
        $this->assertSame('ملخص', $post->excerpt);
        $this->assertSame('محتوى', $post->content);
    }
}
