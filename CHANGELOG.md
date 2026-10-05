# Changelog

All notable changes to `laravel-booking-com` will be documented in this file.

## 0.1.0 - 2026-10-05

First release, for the Booking.com Demand API v3.2 (v3.1 also supported).

- `BookingCom` facade with resources for accommodations (search, availability, details, changes, reviews, chains, constants, third-party suppliers), locations, common reference data, orders (preview, create, details, modify, cancel), car rentals and messaging.
- `Response` with `data()`, `metadata()`, `requestId()`, `total()`, and `next()` / `lazy()` for paginated endpoints.
- Default booker country, platform and currency from config; dates accepted as `DateTimeInterface`.
- Cached reference data; reads retried on connection errors and 5xx, order writes never retried.
- `BookingComException`, `AuthenticationException` and `RateLimitException` (with `retryAfter()`).
- `sandbox()`, `version()` and `forAffiliate()` for per-call scoping.
- `booking-com:install` and `booking-com:status` commands.
- Tests and GitHub Actions CI on PHP 8.2–8.5 with Laravel 12 and 13.
