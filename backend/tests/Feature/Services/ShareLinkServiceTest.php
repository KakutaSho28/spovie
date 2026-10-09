<?php

namespace Tests\Feature\Services;

use App\Exceptions\ServiceException;
use App\Models\Annotation;
use App\Models\ShareLink;
use App\Services\ShareLinkService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShareLinkServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_generates_64_char_token(): void
    {
        $link = app(ShareLinkService::class)->create(Annotation::factory()->create(), null);

        $this->assertSame(64, strlen($link->token));
        $this->assertNull($link->expires_at);
    }

    public function test_resolve_returns_link_or_throws_404_410(): void
    {
        $service = app(ShareLinkService::class);
        $valid = ShareLink::factory()->create();
        $expired = ShareLink::factory()->create(['expires_at' => now()->subMinute()]);

        $this->assertTrue($service->resolve($valid->token)->is($valid));

        foreach ([['missing', 404], [$expired->token, 410]] as [$token, $status]) {
            try {
                $service->resolve($token);
                $this->fail('ServiceException expected');
            } catch (ServiceException $e) {
                $this->assertSame($status, $e->status);
            }
        }
    }
}
