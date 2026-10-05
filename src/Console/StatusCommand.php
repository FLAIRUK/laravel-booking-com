<?php

namespace FLAIRUK\BookingCom\Console;

use FLAIRUK\BookingCom\BookingCom;
use FLAIRUK\BookingCom\Exceptions\BookingComException;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'booking-com:status')]
class StatusCommand extends Command
{
    protected $signature = 'booking-com:status';

    protected $description = 'Check the Booking.com Demand API credentials';

    public function handle(BookingCom $bookingCom): int
    {
        try {
            $response = $bookingCom->post('common/languages');
        } catch (BookingComException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $this->components->info('Connected to the Booking.com Demand API.');

        $this->components->twoColumnDetail('Endpoint', $bookingCom->baseUrl());
        $this->components->twoColumnDetail('Environment', $bookingCom->isSandbox() ? 'sandbox' : 'production');
        $this->components->twoColumnDetail('Languages available', (string) count($response));
        $this->components->twoColumnDetail('Request ID', (string) $response->requestId());

        return self::SUCCESS;
    }
}
