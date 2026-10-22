<?php

declare(strict_types=1);

namespace Aslan\Laravel\Tests\Feature;

use Aslan\Laravel\Facades\Aslan;
use Aslan\Laravel\Tests\TestCase;
use Aslan\Payment\Dto\SubscriptionCheckout;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;

final class PaywallTest extends TestCase
{
    private function checkout(): SubscriptionCheckout
    {
        return SubscriptionCheckout::fromArray([
            'subscriptionId' => 'sub_1',
            'transactionId' => 'txn_1',
            'redirectUrl' => 'https://paywall.test/pay?a=1&b=2',
            'paywall' => [
                'payload' => '{"amount":"14900","x":"<script>alert(1)</script>"}',
                'signature' => 'abc"def\'ghi',
            ],
        ]);
    }

    public function test_paywall_returns_an_uncached_auto_submit_html_form(): void
    {
        $response = Aslan::paywall($this->checkout());

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringStartsWith('text/html', (string) $response->headers->get('Content-Type'));
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertFalse($response->headers->has('Location'));
    }

    public function test_paywall_form_posts_escaped_fields_to_the_redirect_url(): void
    {
        $html = Aslan::paywall($this->checkout())->getContent();

        $this->assertStringContainsString('method="post" action="https://paywall.test/pay?a=1&amp;b=2"', $html);
        $this->assertStringContainsString(
            '<input type="hidden" name="payload" value="{&quot;amount&quot;:&quot;14900&quot;,&quot;x&quot;:&quot;&lt;script&gt;alert(1)&lt;/script&gt;&quot;}">',
            $html,
        );
        $this->assertStringContainsString('<input type="hidden" name="signature" value="abc&quot;def&apos;ghi">', $html);
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('.submit()', $html);
    }

    public function test_paywall_can_be_returned_from_a_route(): void
    {
        Route::post('/subscribe', fn () => Aslan::paywall($this->checkout()));

        $this->post('/subscribe')
            ->assertOk()
            ->assertHeaderMissing('Location')
            ->assertSee('action="https://paywall.test/pay?a=1&amp;b=2"', false);
    }
}
