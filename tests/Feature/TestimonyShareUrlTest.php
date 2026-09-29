<?php

namespace Tests\Feature;

use App\Http\Resources\TestimonyResource;
use App\Models\Testimony;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TestimonyShareUrlTest extends TestCase
{
    use RefreshDatabase;

    private function makeTestimony(): Testimony
    {
        $user = User::create(['display_name' => 'Jean Test', 'email' => 'jean@example.com', 'password' => 'secret123', 'role' => 'utilisateur', 'status' => 'active']);

        return Testimony::create([
            'user_id' => $user->id, 'title' => 'Titre', 'type' => 'text', 'category_slug' => 'guerison',
            'body_text' => 'Texte', 'visibility' => 'public', 'status' => 'approved',
        ]);
    }

    public function test_share_url_is_stored_on_creation(): void
    {
        config(['app.share_url' => 'https://testiapp.example.org/']);
        $testimony = $this->makeTestimony();

        $expected = "https://testiapp.example.org/testimonies/{$testimony->id}";
        $this->assertSame($expected, $testimony->share_url);
        $this->assertSame($expected, DB::table('testimonies')->where('id', $testimony->id)->value('share_url'));
    }

    public function test_share_url_is_exposed_by_the_api_resource(): void
    {
        $testimony = $this->makeTestimony()->load('user');
        $data = (new TestimonyResource($testimony))->toArray(Request::create('/'));

        $this->assertSame($testimony->share_url, $data['shareUrl']);
    }

    public function test_share_url_link_opens_the_public_page(): void
    {
        $testimony = $this->makeTestimony();
        $path = parse_url($testimony->share_url, PHP_URL_PATH);

        $this->get($path)->assertOk()->assertSee('Titre');
    }

    public function test_refresh_command_rebuilds_links_for_a_new_domain(): void
    {
        $testimony = $this->makeTestimony();
        config(['app.share_url' => 'https://nouveau-domaine.org']);

        Artisan::call('testimonies:refresh-share-urls');

        $this->assertSame("https://nouveau-domaine.org/testimonies/{$testimony->id}", $testimony->fresh()->share_url);
    }

    public function test_a_local_server_never_produces_a_localhost_link(): void
    {
        // Cas signalé : serveur avec APP_URL=http://localhost → liens « http://localhost/testimonies/… ».
        config(['app.url' => 'http://localhost', 'app.share_url' => 'https://testi.airid-africa.com']);
        $testimony = $this->makeTestimony()->load('user');

        $this->assertSame("https://testi.airid-africa.com/testimonies/{$testimony->id}", $testimony->share_url);
        $data = (new TestimonyResource($testimony))->toArray(Request::create('http://192.168.1.74:8000/api/v1/testimonies'));
        $this->assertSame("https://testi.airid-africa.com/testimonies/{$testimony->id}", $data['shareUrl']);
    }

    public function test_an_old_stored_link_is_corrected_on_read(): void
    {
        $testimony = $this->makeTestimony();
        DB::table('testimonies')->where('id', $testimony->id)->update(['share_url' => "http://localhost/testimonies/{$testimony->id}"]);
        config(['app.share_url' => 'https://testi.airid-africa.com']);

        $this->assertSame("https://testi.airid-africa.com/testimonies/{$testimony->id}", $testimony->fresh()->share_url);
        $this->getJson("/api/v1/testimonies/{$testimony->id}")->assertOk()
            ->assertJsonPath('data.shareUrl', "https://testi.airid-africa.com/testimonies/{$testimony->id}");
    }
}
