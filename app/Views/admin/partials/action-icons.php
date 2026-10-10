<?php
// Decorative icons shared by admin action buttons; labels remain accessible.
return array_map(static fn (string $path): string => '<svg class="mobile-action-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="' . $path . '"/></svg>', [
    'add' => 'M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0M12 8v8m-4-4h8',
    'view' => 'M9 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-4M14 3h7v7m0-7L10 14',
    'edit' => 'M11 4H5a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2h13a2 2 0 0 0 2-2v-6m-5-9 5 5m-12 6 1-4L17 3a2.1 2.1 0 0 1 3 3l-9 9-4 1Z',
    'delete' => 'M4 7h16M9 4h6M6 7v12a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V7M10 11v6m4-6v6',
    'seats' => 'M6 10V6a3 3 0 0 1 3-3h6a3 3 0 0 1 3 3v4M6 14h12M6 10a2 2 0 0 0-4 0v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6a2 2 0 0 0-4 0v4M6 10v4M5 18v3m14-3v3',
]);
