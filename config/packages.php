<?php

return [
    // When set, only update packages signed with this key can be installed.
    'signing_key' => env('PACKAGE_SIGNING_KEY', ''),

    // Enables public/iso-package-rescue.php for emergency file rollback.
    'rescue_key' => env('PACKAGE_RESCUE_KEY', ''),
];
