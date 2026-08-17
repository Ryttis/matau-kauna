<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Metama, kai adresų paslauga nepasiekiama (tinklo klaida, timeout, blogas atsakymas).
 * Skiriasi nuo "nerasta" – tą geokoderis grąžina kaip null.
 */
class GeocoderUnavailableException extends RuntimeException {}
