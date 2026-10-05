<?php

namespace FLAIRUK\BookingCom\Tests;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;

class CommandsTest extends TestCase
{
    #[Test]
    public function install_publishes_config_and_adds_missing_env_keys_once(): void
    {
        $env = $this->app->environmentFilePath();
        $original = File::exists($env) ? File::get($env) : null;
        File::put($env, "APP_NAME=Test\nBOOKING_COM_TOKEN=existing\n");

        try {
            $this->artisan('booking-com:install')->assertSuccessful();
            $this->artisan('booking-com:install')->assertSuccessful();

            $contents = File::get($env);
            $this->assertSame(1, substr_count($contents, 'BOOKING_COM_AFFILIATE_ID='));
            $this->assertSame(1, substr_count($contents, 'BOOKING_COM_TOKEN='));
            $this->assertStringContainsString('BOOKING_COM_TOKEN=existing', $contents);
            $this->assertStringContainsString('BOOKING_COM_SANDBOX=true', $contents);
            $this->assertFileExists(config_path('booking-com.php'));
        } finally {
            $original === null ? File::delete($env) : File::put($env, $original);
            File::delete(config_path('booking-com.php'));
        }
    }

    #[Test]
    public function status_reports_a_working_connection(): void
    {
        Http::fake(['*/common/languages' => $this->ok([['id' => 'en-gb'], ['id' => 'nl']])]);

        $this->artisan('booking-com:status')
            ->expectsOutputToContain('Connected')
            ->assertSuccessful();
    }

    #[Test]
    public function status_reports_failures(): void
    {
        Http::fake(['*' => Http::response(['errors' => [['id' => 'unauthorised', 'message' => 'Invalid token']]], 401)]);

        $this->artisan('booking-com:status')->assertFailed();
    }
}
