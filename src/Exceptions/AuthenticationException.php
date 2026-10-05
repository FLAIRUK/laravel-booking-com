<?php

namespace FLAIRUK\BookingCom\Exceptions;

/**
 * Missing credentials, or a 401 / 403 from Booking.com.
 */
class AuthenticationException extends BookingComException {}
