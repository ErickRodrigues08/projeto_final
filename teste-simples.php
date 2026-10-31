<?php
echo '<pre style="font-family:monospace;padding:20px;background:#1a1a1a;color:#fff">';
echo "Timezone PHP: " . date_default_timezone_get() . "\n";
echo "Horário PHP : " . date('Y-m-d H:i:s') . "\n";
echo "Timestamp   : " . time() . "\n";
echo '</pre>';
