<?php

namespace Tests\Feature\Notifications;

use App\Models\User;
use App\Models\WhatsAppTemplate;
use Database\Seeders\WhatsAppTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WhatsAppTemplateSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('whatsapp.cloud.enabled', true);
        Config::set('whatsapp.cloud.token', 'test-token');
        Config::set('whatsapp.cloud.phone_number_id', 'phone-123');
        Config::set('whatsapp.cloud.waba_id', 'waba-456');
        Config::set('whatsapp.cloud.api_version', 'v21.0');

        $this->seed(WhatsAppTemplateSeeder::class);
    }

    public function test_seeder_sets_default_v2_meta_names(): void
    {
        $template = WhatsAppTemplate::query()->where('key', 'order_placed')->firstOrFail();

        $this->assertSame('order_placed_v2', $template->cloud_template_name);

        $cod = WhatsAppTemplate::query()->where('key', 'order_placed_cod_confirm')->firstOrFail();
        $this->assertNull($cod->cloud_template_name);
    }

    public function test_command_creates_meta_template_when_missing(): void
    {
        Http::fake(function ($request) {
            if (str_contains((string) $request->url(), 'message_templates') && $request->method() === 'GET') {
                return Http::response(['data' => []], 200);
            }
            if (str_contains((string) $request->url(), 'message_templates') && $request->method() === 'POST') {
                return Http::response(['id' => 'tpl-new'], 200);
            }

            return Http::response([], 404);
        });

        $template = WhatsAppTemplate::query()->where('key', 'order_placed')->firstOrFail();
        $template->update(['cloud_template_name' => 'order_placed_test']);

        $this->artisan('whatsapp:sync-templates', ['--template' => 'order_placed'])
            ->assertSuccessful();

        $template->refresh();
        $this->assertSame('pending_review', $template->meta_sync_status);
        $this->assertSame(['name', 'order', 'total', 'payment'], $template->meta_parameter_order);

        Http::assertSent(function ($request): bool {
            if (! str_contains((string) $request->url(), '/message_templates') || $request->method() !== 'POST') {
                return false;
            }

            $data = $request->data();
            $body = collect($data['components'] ?? [])->firstWhere('type', 'BODY');

            return ($data['name'] ?? null) === 'order_placed_test'
                && ($data['category'] ?? null) === 'UTILITY'
                && str_contains((string) ($body['text'] ?? ''), '{{1}}');
        });
    }

    public function test_sync_payload_includes_header_footer_and_url_buttons(): void
    {
        Http::fake(function ($request) {
            if (str_contains((string) $request->url(), 'message_templates') && $request->method() === 'GET') {
                return Http::response(['data' => []], 200);
            }
            if (str_contains((string) $request->url(), 'message_templates') && $request->method() === 'POST') {
                return Http::response(['id' => 'tpl-new'], 200);
            }

            return Http::response([], 404);
        });

        $this->artisan('whatsapp:sync-templates', ['--template' => 'order_shipped'])
            ->assertSuccessful();

        Http::assertSent(function ($request): bool {
            if (! str_contains((string) $request->url(), '/message_templates') || $request->method() !== 'POST') {
                return false;
            }

            $components = collect($request->data()['components'] ?? []);

            $header = $components->firstWhere('type', 'HEADER');
            $footer = $components->firstWhere('type', 'FOOTER');
            $buttons = $components->firstWhere('type', 'BUTTONS');
            $button = $buttons['buttons'][0] ?? null;

            return ($header['format'] ?? null) === 'TEXT'
                && ($header['text'] ?? null) === 'Order Shipped'
                && str_contains((string) ($footer['text'] ?? ''), 'thank you for shopping with us')
                && ($button['type'] ?? null) === 'URL'
                && ($button['text'] ?? null) === 'Track Order'
                && str_ends_with((string) ($button['url'] ?? ''), '?order={{1}}')
                && str_contains((string) ($button['example'][0] ?? ''), 'ORD-1001');
        });
    }

    public function test_sync_fails_when_header_contains_placeholder(): void
    {
        $template = WhatsAppTemplate::query()->where('key', 'order_shipped')->firstOrFail();
        $template->update(['header_text' => 'Order {order} Shipped']);

        $this->artisan('whatsapp:sync-templates', ['--template' => 'order_shipped']);

        $template->refresh();
        $this->assertSame('failed', $template->meta_sync_status);
        $this->assertStringContainsString('Header text must be static', (string) $template->meta_sync_error);
    }

    public function test_command_skips_interactive_button_templates(): void
    {
        $this->artisan('whatsapp:sync-templates', ['--template' => 'order_placed_cod_confirm'])
            ->assertSuccessful();

        $template = WhatsAppTemplate::query()->where('key', 'order_placed_cod_confirm')->firstOrFail();
        $this->assertSame('skipped', $template->meta_sync_status);
    }

    public function test_resolves_waba_id_from_business_hierarchy_when_not_configured(): void
    {
        Config::set('whatsapp.cloud.waba_id', '');

        Http::fake(function ($request) {
            $url = (string) $request->url();

            if (str_contains($url, '/me/businesses')) {
                return Http::response(['data' => [['id' => 'biz-1']]], 200);
            }
            if (str_contains($url, '/biz-1/owned_whatsapp_business_accounts')) {
                return Http::response(['data' => [['id' => 'waba-resolved']]], 200);
            }
            if (str_contains($url, '/waba-resolved/phone_numbers')) {
                return Http::response(['data' => [['id' => 'phone-123']]], 200);
            }
            if (str_contains($url, '/waba-resolved/message_templates') && $request->method() === 'GET') {
                return Http::response(['data' => []], 200);
            }
            if (str_contains($url, '/waba-resolved/message_templates') && $request->method() === 'POST') {
                return Http::response(['id' => 'tpl-new'], 200);
            }

            return Http::response([], 404);
        });

        $this->artisan('whatsapp:sync-templates', ['--template' => 'order_placed'])
            ->assertSuccessful();
    }

    public function test_admin_can_sync_single_template(): void
    {
        Http::fake(function ($request) {
            if (str_contains((string) $request->url(), 'message_templates') && $request->method() === 'GET') {
                return Http::response(['data' => []], 200);
            }
            if (str_contains((string) $request->url(), 'message_templates') && $request->method() === 'POST') {
                return Http::response(['id' => 'tpl-new'], 200);
            }

            return Http::response([], 404);
        });

        $admin = User::factory()->admin()->create();
        $template = WhatsAppTemplate::query()->where('key', 'order_shipped')->firstOrFail();

        $this->actingAs($admin)
            ->post(route('admin.whatsapp-templates.sync-meta', $template))
            ->assertRedirect();

        $template->refresh();
        $this->assertSame('pending_review', $template->meta_sync_status);
    }
}
