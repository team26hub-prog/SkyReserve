<?php
declare(strict_types=1);

return [
    'bank_name' => getenv('PAYMENT_BANK_NAME') ?: 'SkyReserve Demo Bank',
    'account_name' => getenv('PAYMENT_ACCOUNT_NAME') ?: 'SkyReserve Coursework Demo',
    'account_number' => getenv('PAYMENT_ACCOUNT_NUMBER') ?: 'DEMO-ONLY-000123',
    'instructions' => getenv('PAYMENT_INSTRUCTIONS') ?: 'Sample airline payment details for coursework only. Use a sample bank transfer, cash, or other payment reference and include your booking reference. Submit the details below for manual review.',
];
