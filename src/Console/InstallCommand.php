<?php

namespace FLAIRUK\BookingCom\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'booking-com:install')]
class InstallCommand extends Command
{
    protected $signature = 'booking-com:install';

    protected $description = 'Publish the Booking.com config and add its environment variables to .env';

    /** @var array<string, string> */
    protected array $variables = [
        'BOOKING_COM_TOKEN' => '',
        'BOOKING_COM_AFFILIATE_ID' => '',
        'BOOKING_COM_SANDBOX' => 'true',
        'BOOKING_COM_BOOKER_COUNTRY' => '',
        'BOOKING_COM_CURRENCY' => '',
    ];

    public function handle(Filesystem $files): int
    {
        $this->call('vendor:publish', ['--tag' => 'booking-com-config']);

        foreach ([$this->laravel->environmentFilePath(), base_path('.env.example')] as $path) {
            if (! $files->exists($path)) {
                continue;
            }

            $contents = $files->get($path);
            $missing = array_filter(
                $this->variables,
                fn (string $key) => ! preg_match("/^{$key}=/m", $contents),
                ARRAY_FILTER_USE_KEY,
            );

            if ($missing) {
                $lines = array_map(fn ($key, $value) => "{$key}={$value}", array_keys($missing), $missing);
                $files->append($path, PHP_EOL.implode(PHP_EOL, $lines).PHP_EOL);
                $this->components->info('Added '.implode(', ', array_keys($missing)).' to '.basename($path).'.');
            }
        }

        $this->components->info('Add your API key and affiliate ID, then run `php artisan booking-com:status` to check the connection.');

        return self::SUCCESS;
    }
}
