<?php
if (function_exists('opcache_reset')) {
    if (opcache_reset()) {
        echo "<h1>OPCache successfully cleared!</h1><p>Please refresh your resident records page now.</p>";
    } else {
        echo "<h1>OPCache reset failed!</h1>";
    }
} else {
    echo "<h1>OPCache is not enabled/installed.</h1>";
}
