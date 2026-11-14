<?php
declare(strict_types=1);

namespace App;

/** Δεν ήταν δυνατή η λήψη (ιδιωτικό post κ.λπ.) — ο διαχειριστής μπορεί να ανεβάσει ή να καταγράψει το βίντεο. */
final class DownloadException extends \RuntimeException
{
}
