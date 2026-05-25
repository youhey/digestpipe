<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * @internal
 */
class HealthEndpointTest extends TestCase
{
    /**
     * Laravel 標準の health endpoint は未認証で軽量な生存確認だけを返す。
     */
    public function testHealthEndpointReturnsSuccessfulResponseWithoutAuthentication(): void
    {
        $response = $this->get('/up');

        $response->assertOk()
            ->assertDontSeeText('analysis_json')
            ->assertDontSeeText('DIGESTPIPE_API_TOKEN')
            ->assertDontSeeText('OPENAI_API_KEY');
    }
}
