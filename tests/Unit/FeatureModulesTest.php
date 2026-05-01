<?php

namespace Tests\Unit;

use App\Support\FeatureModules;
use Tests\TestCase;

class FeatureModulesTest extends TestCase
{
    public function test_disabled_module_returns_false(): void
    {
        config(['features.modules.blog' => false]);

        $this->assertFalse(FeatureModules::enabled('blog'));
        $this->assertArrayHasKey('blog', FeatureModules::all());
        $this->assertFalse(FeatureModules::all()['blog']);
    }

    public function test_all_canonical_keys_are_present(): void
    {
        config(['features.modules' => []]);

        $all = FeatureModules::all();
        foreach (FeatureModules::canonicalKeys() as $key) {
            $this->assertArrayHasKey($key, $all);
            $this->assertTrue($all[$key]);
        }
    }
}
