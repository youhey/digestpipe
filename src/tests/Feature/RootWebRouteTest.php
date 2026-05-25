<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * @internal
 */
class RootWebRouteTest extends TestCase
{
    /**
     * Root の Web ルートは公開ページを持たず、キャッシュ可能な 404 を返す。
     */
    public function testRootWebRouteReturnsCacheableNotFound(): void
    {
        $response = $this->get('/');

        $response->assertNotFound()
            ->assertHeader('Cache-Control', 'max-age=3600, public, s-maxage=86400')
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->assertSeeText('Not Found')
            ->assertDontSeeText('Laravel');
    }
}
