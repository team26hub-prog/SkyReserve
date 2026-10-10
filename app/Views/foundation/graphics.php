<?php
// Local SVG symbols reused by the homepage sections below the hero.
$homeIcon = static function (string $icon) use ($escape): string {
    return '<svg class="home-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><use href="#home-' . $escape($icon) . '"/></svg>';
};
?>
<svg class="home-svg-definitions" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">
    <defs>
        <symbol id="home-route" viewBox="0 0 24 24"><path d="M8 8c0 3-4 6-4 6S0 11 0 8a4 4 0 0 1 8 0Z" transform="translate(2 -2)"/><circle cx="6" cy="6" r="1"/><path d="M18 21s4-3 4-6a4 4 0 0 0-8 0c0 3 4 6 4 6Z"/><circle cx="18" cy="15" r="1"/><path d="M6 16c0 5 6 5 6 1s-1-7 4-10" stroke-dasharray="2 3"/></symbol>
        <symbol id="home-passenger" viewBox="0 0 24 24"><circle cx="9" cy="6" r="3"/><path d="M3 21v-3a6 6 0 0 1 9-5M15 10h7v4a2 2 0 0 0 0 4v3h-7v-3a2 2 0 0 0 0-4v-4Z"/></symbol>
        <symbol id="home-seat" viewBox="0 0 24 24"><path d="M7 3v9h11v5H5V8M5 17v4m13-4v4M11 3v5h6V3M3 12v5"/></symbol>
        <symbol id="home-search" viewBox="0 0 24 24"><circle cx="10" cy="10" r="6"/><path d="m15 15 6 6M7 10h6m-3-3v6"/></symbol>
        <symbol id="home-payment" viewBox="0 0 24 24"><rect x="2" y="4" width="20" height="15" rx="3"/><path d="M2 9h20M6 14h3m5 1 2 2 4-4"/></symbol>
        <symbol id="home-verify" viewBox="0 0 24 24"><path d="m12 2 8 3v6c0 5-8 11-8 11S4 16 4 11V5l8-3Z"/><path d="m8 11 3 3 5-6"/></symbol>
        <symbol id="home-ticket" viewBox="0 0 24 24"><path d="M3 5h18v5a2 2 0 0 0 0 4v5H3v-5a2 2 0 0 0 0-4V5Z"/><path d="M15 5v3m0 3v2m0 3v3M7 9h4m-4 6h4"/></symbol>
        <symbol id="home-plane" viewBox="0 0 24 24"><path d="M21 3a2.1 2.1 0 0 1 0 3l-5 5 2 8-2 2-4-7-5 5v3l-2-2-2-2h3l5-5-7-4 2-2 8 2 5-5Z"/></symbol>
        <symbol id="home-lahore" viewBox="0 0 160 90"><path d="M12 76h136M32 76V42h10v34M118 76V42h10v34M35 42V24m90 18V24M52 76V48h56v28M59 48c0-12 21-22 21-22s21 10 21 22M72 76V62a8 8 0 0 1 16 0v14M20 76V60m120 16V60"/><path d="M18 16c27-8 40-4 52 0" stroke-dasharray="3 5"/></symbol>
        <symbol id="home-karachi" viewBox="0 0 160 90"><path d="M12 67h136M45 67l8-12h54l8 12M59 55V43h42v12M66 43V22h28v21M71 22l9-10 9 10M74 22v21m12-21v21M12 77c8-5 16-5 24 0s16 5 24 0 16-5 24 0 16 5 24 0 16-5 24 0 16 5 24 0"/></symbol>
        <symbol id="home-islamabad" viewBox="0 0 160 90"><path d="m12 45 20-22 18 18 27-29 30 31 22-20 19 22M25 76h110M43 76V56l37-30 37 30v20M43 56l37 7 37-7M80 26v37m0 0v13M27 76V34m0 0-4-14-4 14v42m114 0V34m0 0 4-14 4 14v42"/></symbol>
        <symbol id="home-dubai" viewBox="0 0 160 90"><path d="M12 76h136M65 76V42h6V27h6V14h6v13h6v15h6v34M80 14V4M30 76V36h20v40M37 36V26h6v10M109 76V47h20v29M116 47V33h6v14M16 76V58h8v18m113 0V59h7v17M65 55h30m-30 10h30"/></symbol>
    </defs>
</svg>
