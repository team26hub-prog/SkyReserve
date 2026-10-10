<?php
// Local SVG symbols reused by the homepage sections below the hero.
$homeIcon = static function (string $icon) use ($escape): string {
    return '<svg class="home-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><use href="#home-' . $escape($icon) . '"/></svg>';
};
?>
<svg class="home-svg-definitions" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">
    <defs>
        <symbol id="home-journey-search" viewBox="0 0 24 24"><circle cx="10.5" cy="10.5" r="6.5"/><path d="m15.5 15.5 5 5M7.5 10.5h6m-3-3v6"/></symbol>
        <symbol id="home-journey-book" viewBox="0 0 24 24"><rect x="5" y="4" width="14" height="17" rx="2"/><rect x="9" y="2" width="6" height="4" rx="1"/><path d="M9 10h6m-6 4h6m-6 4h3"/></symbol>
        <symbol id="home-journey-seat" viewBox="0 0 24 24"><path d="M6 3h5a2 2 0 0 1 2 2v7h5a2 2 0 0 1 2 2v3H8a2 2 0 0 1-2-2V3ZM4 10v5a4 4 0 0 0 4 4h12M8 19v2m10-2v2"/></symbol>
        <symbol id="home-journey-payment" viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="2.5"/><path d="M3 10h18M7 15h3m4 0h3"/></symbol>
        <symbol id="home-journey-verify" viewBox="0 0 24 24"><path d="m12 3 8 3v5c0 5-8 10-8 10S4 16 4 11V6l8-3Z"/><path d="m8.5 11.5 2.5 2.5 4.5-5"/></symbol>
        <symbol id="home-journey-ticket" viewBox="0 0 24 24"><path d="M3 6h18v4a2 2 0 0 0 0 4v4H3v-4a2 2 0 0 0 0-4V6Z"/><path d="M15 6v2m0 3v2m0 3v2M7 10h4m-4 4h4"/></symbol>
        <symbol id="home-route" viewBox="0 0 24 24"><path d="M8 8c0 3-4 6-4 6S0 11 0 8a4 4 0 0 1 8 0Z" transform="translate(2 -2)"/><circle cx="6" cy="6" r="1"/><path d="M18 21s4-3 4-6a4 4 0 0 0-8 0c0 3 4 6 4 6Z"/><circle cx="18" cy="15" r="1"/><path d="M6 16c0 5 6 5 6 1s-1-7 4-10" stroke-dasharray="2 3"/></symbol>
        <symbol id="home-passenger" viewBox="0 0 24 24"><circle cx="9" cy="6" r="3"/><path d="M3 21v-3a6 6 0 0 1 9-5M15 10h7v4a2 2 0 0 0 0 4v3h-7v-3a2 2 0 0 0 0-4v-4Z"/></symbol>
        <symbol id="home-seat" viewBox="0 0 24 24"><path d="M7 3v9h11v5H5V8M5 17v4m13-4v4M11 3v5h6V3M3 12v5"/></symbol>
        <symbol id="home-search" viewBox="0 0 24 24"><circle cx="10" cy="10" r="6"/><path d="m15 15 6 6M7 10h6m-3-3v6"/></symbol>
        <symbol id="home-payment" viewBox="0 0 24 24"><rect x="2" y="4" width="20" height="15" rx="3"/><path d="M2 9h20M6 14h3m5 1 2 2 4-4"/></symbol>
        <symbol id="home-verify" viewBox="0 0 24 24"><path d="m12 2 8 3v6c0 5-8 11-8 11S4 16 4 11V5l8-3Z"/><path d="m8 11 3 3 5-6"/></symbol>
        <symbol id="home-ticket" viewBox="0 0 24 24"><path d="M3 5h18v5a2 2 0 0 0 0 4v5H3v-5a2 2 0 0 0 0-4V5Z"/><path d="M15 5v3m0 3v2m0 3v3M7 9h4m-4 6h4"/></symbol>
        <symbol id="home-plane" viewBox="0 0 24 24"><path d="M21 3a2.1 2.1 0 0 1 0 3l-5 5 2 8-2 2-4-7-5 5v3l-2-2-2-2h3l5-5-7-4 2-2 8 2 5-5Z"/></symbol>
    </defs>
</svg>
