<?php

namespace Tests\Unit;

use App\Exceptions\GoogleDriveException;
use App\Models\User;
use App\Services\Google\GoogleDriveService;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GoogleDriveServiceTest extends TestCase
{
    use RefreshDatabase;

    private array $history = [];

    private function http(array $responses): Client
    {
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($this->history));

        return new Client(['handler' => $stack]);
    }

    private function jr(array $body, int $status = 200): Response
    {
        return new Response($status, ['Content-Type' => 'application/json'], json_encode($body));
    }

    private function user(array $attrs = []): User
    {
        return User::create(array_merge([
            'name' => 'Tester', 'email' => 't@example.com', 'google_id' => 'g1',
            'google_access_token' => 'AT', 'google_refresh_token' => 'RT',
            'token_expires_at' => now()->addHour(),
        ], $attrs));
    }

    public function test_tokens_are_encrypted_at_rest(): void
    {
        $u = $this->user();
        $raw = \DB::table('users')->where('id', $u->id)->value('google_refresh_token');
        $this->assertNotSame('RT', $raw);
        $this->assertSame('RT', $u->fresh()->google_refresh_token);
    }

    public function test_folder_path_is_created_once_then_served_from_cache(): void
    {
        $u = $this->user();
        $http = $this->http([
            // root: search (kosong) + create
            $this->jr(['files' => []]), $this->jr(['id' => 'ROOT']),
            // 2025: search (kosong) + create
            $this->jr(['files' => []]), $this->jr(['id' => 'Y2025']),
            // RHK-01: sudah ada di Drive
            $this->jr(['files' => [['id' => 'RHK1']]]),
        ]);

        $svc = GoogleDriveService::forUser($u)->withHttpClient($http);
        $this->assertSame('RHK1', $svc->ensureFolderPath(['2025', 'RHK-01']));
        $this->assertCount(5, $this->history);

        // Panggilan kedua: nol request tambahan (Redis/array L1 + MySQL L2).
        $this->assertSame('RHK1', $svc->ensureFolderPath(['2025', 'RHK-01']));
        $this->assertCount(5, $this->history);
        $this->assertSame('ROOT', $u->fresh()->gdrive_root_folder_id);
    }

    public function test_query_escapes_quotes_in_folder_names(): void
    {
        $u = $this->user(['gdrive_root_folder_id' => 'ROOT']);
        $http = $this->http([$this->jr(['files' => [['id' => 'X']]])]);

        GoogleDriveService::forUser($u)->withHttpClient($http)->ensureFolderPath(["Bob's"]);

        parse_str($this->history[0]['request']->getUri()->getQuery(), $q);
        $this->assertStringContainsString("name = 'Bob\\'s'", $q['q']);
    }

    public function test_expired_token_is_refreshed_and_persisted(): void
    {
        $u = $this->user(['token_expires_at' => now()->subMinute(), 'gdrive_root_folder_id' => 'ROOT']);
        $http = $this->http([
            $this->jr(['access_token' => 'AT2', 'expires_in' => 3600]),
            $this->jr(['files' => [['id' => 'F']]]),
        ]);

        GoogleDriveService::forUser($u)->withHttpClient($http)->ensureFolderPath(['2025']);

        $u->refresh();
        $this->assertSame('AT2', $u->google_access_token);
        $this->assertSame('RT', $u->google_refresh_token); // refresh token lama dipertahankan
        $this->assertTrue($u->token_expires_at->isFuture());
    }

    public function test_revoked_refresh_token_requires_reauth(): void
    {
        $u = $this->user(['token_expires_at' => now()->subMinute()]);
        $http = $this->http([$this->jr(['error' => 'invalid_grant', 'error_description' => 'revoked'], 400)]);

        $this->expectException(GoogleDriveException::class);
        $this->expectExceptionCode(401);
        GoogleDriveService::forUser($u)->withHttpClient($http)->ensureFolderPath(['2025']);
    }

    public function test_upload_streams_in_chunks_and_sets_public_reader(): void
    {
        config(['evident.upload_chunk_size' => 262144]); // 256 KiB
        $u = $this->user();
        $tmp = tempnam(sys_get_temp_dir(), 'ev');
        file_put_contents($tmp, str_repeat('a', 262144 * 2 + 10)); // 3 chunk

        $location = ['Location' => 'https://upload.example/session'];
        $http = $this->http([
            new Response(200, $location),                                  // initiate resumable
            new Response(308, ['Range' => 'bytes=0-262143']),             // chunk 1
            new Response(308, ['Range' => 'bytes=0-524287']),             // chunk 2
            $this->jr(['id' => 'FILE', 'name' => 'a.pdf', 'mimeType' => 'application/pdf', 'size' => '524298', 'webViewLink' => 'https://drive.google.com/file/d/FILE/view']),
            $this->jr(['id' => 'perm']),                                 // permission
        ]);

        $res = GoogleDriveService::forUser($u)->withHttpClient($http)
            ->uploadFile($tmp, 'a.pdf', 'FOLDER', 'application/pdf');
        unlink($tmp);

        $this->assertSame('FILE', $res['id']);
        $this->assertSame('FOLDER', $res['folder_id']);
        $last = end($this->history)['request'];
        $this->assertStringContainsString('/files/FILE/permissions', (string) $last->getUri());
        $this->assertStringContainsString('"role":"reader"', (string) $last->getBody());
        $this->assertStringContainsString('"type":"anyone"', (string) $last->getBody());
    }

    public function test_find_by_app_property_returns_existing_file_and_ensures_public(): void
    {
        $u = $this->user();
        $http = $this->http([
            $this->jr(['files' => [['id' => 'OLD', 'name' => 'a.pdf', 'mimeType' => 'application/pdf', 'size' => '10', 'webViewLink' => 'https://x/OLD']]]),
            $this->jr(['id' => 'perm']),
        ]);

        $r = GoogleDriveService::forUser($u)->withHttpClient($http)->findByAppProperty('FOL', 'evident_id', 'abc');

        $this->assertSame('OLD', $r['id']);
        parse_str($this->history[0]['request']->getUri()->getQuery(), $q);
        $this->assertStringContainsString("appProperties has { key='evident_id' and value='abc' }", $q['q']);
        $this->assertStringContainsString('/files/OLD/permissions', (string) $this->history[1]['request']->getUri());
    }

    public function test_find_by_app_property_returns_null_when_absent(): void
    {
        $http = $this->http([$this->jr(['files' => []])]);

        $this->assertNull(GoogleDriveService::forUser($this->user())->withHttpClient($http)->findByAppProperty('FOL', 'evident_id', 'abc'));
    }

    public function test_upload_sends_app_properties_marker(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'ev');
        file_put_contents($tmp, 'hello');
        $http = $this->http([
            new Response(200, ['Location' => 'https://upload.example/s']),
            $this->jr(['id' => 'F', 'name' => 'a.txt', 'webViewLink' => 'https://x/F']),
            $this->jr(['id' => 'perm']),
        ]);

        GoogleDriveService::forUser($this->user())->withHttpClient($http)
            ->uploadFile($tmp, 'a.txt', 'FOL', 'text/plain', ['evident_id' => 'abc']);
        unlink($tmp);

        $this->assertStringContainsString('"appProperties":{"evident_id":"abc"}', (string) $this->history[0]['request']->getBody());
    }

    public function test_google_401_forces_one_token_refresh_then_succeeds(): void
    {
        $u = $this->user(['gdrive_root_folder_id' => 'ROOT']);
        $http = $this->http([
            $this->jr(['error' => ['code' => 401, 'message' => 'Invalid Credentials']], 401), // search ditolak
            $this->jr(['access_token' => 'AT2', 'expires_in' => 3600]),                      // refresh paksa
            $this->jr(['files' => [['id' => 'Y']]]),                                         // search ulang OK
        ]);

        $svc = GoogleDriveService::forUser($u)->withHttpClient($http);
        $id = $svc->withAuthRetry(fn () => $svc->ensureFolderPath(['2025']));

        $this->assertSame('Y', $id);
        $this->assertSame('AT2', $u->fresh()->google_access_token);
    }

    public function test_persistent_google_401_requires_reauth(): void
    {
        $u = $this->user(['gdrive_root_folder_id' => 'ROOT']);
        $err = $this->jr(['error' => ['code' => 401, 'message' => 'Invalid Credentials']], 401);
        $http = $this->http([$err, $this->jr(['access_token' => 'AT2', 'expires_in' => 3600]), $err]);
        $svc = GoogleDriveService::forUser($u)->withHttpClient($http);

        $this->expectException(GoogleDriveException::class);
        $this->expectExceptionCode(401);
        $svc->withAuthRetry(fn () => $svc->ensureFolderPath(['2025']));
    }
}
