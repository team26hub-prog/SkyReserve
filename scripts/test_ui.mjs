// Dependency-free Chrome DevTools checks. Node 22+ is used only by this test tool.
import { spawn, execFileSync } from 'node:child_process';
import { mkdtempSync, writeFileSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const base = new URL(process.argv[2] || 'http://127.0.0.1:8000');
if (base.protocol !== 'http:' || !['127.0.0.1', 'localhost'].includes(base.hostname) || base.username || base.password || base.pathname !== '/' || base.search || base.hash) throw new Error('Use a local server URL.');
const php = process.argv[3] || 'php';
const fixtureScript = join(dirname(fileURLToPath(import.meta.url)), 'test_ui_fixtures.php');
const chromePath = process.env.SKYRESERVE_TEST_CHROME || 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
const artifacts = mkdtempSync(join(tmpdir(), 'skyreserve-ui-'));
const profile = join(artifacts, 'chrome-profile');
const port = 9300 + Math.floor(Math.random() * 500);
const chrome = spawn(chromePath, ['--headless=new', `--remote-debugging-port=${port}`, `--user-data-dir=${profile}`, '--no-first-run', '--no-default-browser-check', '--disable-background-networking', 'about:blank'], { windowsHide: true, stdio: 'ignore' });
chrome.on('error', (error) => { console.error(error.message); });
const pause = (ms) => new Promise((resolve) => setTimeout(resolve, ms));
let fixture, ws, sequence = 0, checks = 0;
const pending = new Map();
const assert = (condition, message) => { if (!condition) throw new Error(message); checks++; };
const command = (method, params = {}) => new Promise((resolve, reject) => {
    const id = ++sequence;
    const timer = setTimeout(() => { pending.delete(id); reject(new Error(`Timed out: ${method}`)); }, 15000);
    pending.set(id, { resolve, reject, timer });
    ws.send(JSON.stringify({ id, method, params }));
});
const evaluate = async (expression) => {
    const result = await command('Runtime.evaluate', { expression, returnByValue: true, awaitPromise: true });
    if (result.exceptionDetails) throw new Error(result.exceptionDetails.exception?.description || result.exceptionDetails.text);
    return result.result.value;
};
const ready = async (path) => {
    for (let attempt = 0; attempt < 80; attempt++) {
        try { if (await evaluate(`document.readyState === 'complete' && location.pathname === ${JSON.stringify(new URL(path, base).pathname)}`)) return; } catch {}
        await pause(100);
    }
    const state = await evaluate(`({path: location.pathname, error: document.querySelector('.notice.error')?.textContent.trim(), dialog: document.querySelector('#confirmation-dialog')?.open, forms: [...document.querySelectorAll('form')].map(form => ({action: form.action, submitting: form.dataset.submitting}))})`);
    throw new Error(`Page did not load: ${path}; ${JSON.stringify(state)}`);
};
const visit = async (path) => { await command('Page.navigate', { url: new URL(path, base).href }); await ready(path); await pause(80); };
const waitFor = async (expression, label) => {
    for (let attempt = 0; attempt < 80; attempt++) {
        try { if (await evaluate(expression)) return; } catch {}
        await pause(100);
    }
    throw new Error(label);
};
const respondToConfirmation = async (accept = true) => {
    const state = await evaluate(`({open: document.querySelector('#confirmation-dialog')?.open, path: location.pathname, forms: [...document.querySelectorAll('main form')].map(form => ({action: form.action, confirm: form.dataset.confirm, invalid: [...form.elements].filter(field => field.willValidate && !field.validity.valid).map(field => ({name: field.name, value: field.value, error: field.validationMessage}))}))})`);
    assert(state.open, `Accessible confirmation dialog opens: ${JSON.stringify(state)}`);
    await evaluate(`document.querySelector('[data-dialog-${accept ? 'confirm' : 'cancel'}]').click(); true`);
    await pause(100);
};
const audit = () => {
    const visible = (el) => el.getClientRects().length && getComputedStyle(el).visibility !== 'hidden';
    const controls = [...document.querySelectorAll('input:not([type="hidden"]), select, textarea')];
    const unlabeled = controls.filter((el) => !el.labels?.length && !el.getAttribute('aria-label') && !el.getAttribute('aria-labelledby')).map((el) => el.name);
    const ids = [...document.querySelectorAll('[id]')].map((el) => el.id);
    const rgb = (value) => (value.match(/[\d.]+/g) || []).map(Number);
    const luminance = (color) => color.slice(0, 3).map((v) => { const c = v / 255; return c <= .04045 ? c / 12.92 : ((c + .055) / 1.055) ** 2.4; }).reduce((total, v, index) => total + v * [.2126, .7152, .0722][index], 0);
    const contrastIssues = [];
    for (const el of document.querySelectorAll('h1, h2, h3, p, a, button, label, dt, dd, small, .badge')) {
        if (!visible(el) || el.disabled || !el.textContent.trim()) continue;
        const style = getComputedStyle(el);
        let parent = el, background = [255, 255, 255];
        while (parent) {
            const color = rgb(getComputedStyle(parent).backgroundColor);
            if (color.length === 3 || color[3] === 1) { background = color; break; }
            parent = parent.parentElement;
        }
        const foreground = rgb(style.color), l1 = luminance(foreground), l2 = luminance(background);
        const ratio = (Math.max(l1, l2) + .05) / (Math.min(l1, l2) + .05);
        const large = parseFloat(style.fontSize) >= 24 || (parseFloat(style.fontSize) >= 18.66 && Number(style.fontWeight) >= 700);
        if (ratio < (large ? 3 : 4.5)) contrastIssues.push({ text: el.textContent.trim().slice(0, 35), ratio: ratio.toFixed(2) });
    }
    return { overflow: document.documentElement.scrollWidth > innerWidth + 1, unlabeled, duplicateIds: ids.length !== new Set(ids).size, headings: document.querySelectorAll('h1').length, landmark: !!document.querySelector('main#main-content'), contrastIssues };
};
const checkPages = async (pages, group) => {
    for (const [name, path] of pages) {
        await visit(path);
        assert(await evaluate(`location.pathname === '/' || !!document.querySelector('.back-navigation a[href^="/"]')`), `${name} provides a safe parent navigation button`);
        for (const width of group === 'admin' ? [375, 768, 1440] : [320, 375, 390, 768, 1440]) {
            await command('Emulation.setDeviceMetricsOverride', { width, height: 1000, deviceScaleFactor: 1, mobile: false });
            const result = await evaluate(`(${audit.toString()})()`);
            assert(!result.overflow, `${name} overflows at ${width}px`);
            assert(!result.unlabeled.length && !result.duplicateIds && result.headings === 1 && result.landmark, `${name} semantic/label issue: ${JSON.stringify(result)}`);
            assert(!result.contrastIssues.length, `${name} contrast issues: ${JSON.stringify(result.contrastIssues)}`);
            if (group === 'customer') {
                assert(await evaluate(`!document.querySelector('.site-footer')`), `${name} omits the customer footer`);
                assert(await evaluate(`[...document.querySelectorAll('.app-main .actions a')].every(link => link.classList.contains('button') && link.getBoundingClientRect().height >= 44 && getComputedStyle(link).textDecorationLine === 'none')`), `${name} presents action links as consistent touch-friendly buttons`);
            }
            if (['search', 'search-results'].includes(name) && width <= 800) {
                assert(await evaluate(`(() => { const hero = document.querySelector('.search-hero').getBoundingClientRect(); const form = document.querySelector('.search-form').getBoundingClientRect(); return hero.bottom + 8 <= form.top && [...document.querySelectorAll('.search-form input, .search-form select, .search-form button')].every(control => { const rect = control.getBoundingClientRect(); return rect.left >= form.left && rect.right <= form.right + 1; }); })()`), `${name} hero and form controls do not overlap at ${width}px`);
            }
            if (width <= 800) {
                assert(await evaluate(`(() => { const brand = document.querySelector('.site-header .brand').getBoundingClientRect(); const button = document.querySelector('.menu-toggle'); const menu = button.getBoundingClientRect(); return menu.right <= brand.left && Math.abs(brand.top + brand.height / 2 - menu.top - menu.height / 2) < 1 && !button.textContent.trim() && !!button.getAttribute('aria-label'); })()`), `${name} icon-only hamburger sits left of the brand at ${width}px`);
                assert(await evaluate(`(() => { const nav = document.querySelector('.mobile-quick-nav'); const rect = nav.getBoundingClientRect(); const expected = document.querySelector('.customer-sidebar') || nav.querySelector('a[href="/admin/airports"]') ? 7 : 4; return getComputedStyle(nav).position === 'fixed' && Math.abs(rect.bottom - innerHeight) < 1 && nav.querySelectorAll('a').length === expected && [...nav.querySelectorAll('a')].every(link => link.getBoundingClientRect().height >= 44 && !link.textContent.trim() && !!link.getAttribute('aria-label')) && parseFloat(getComputedStyle(document.body).paddingBottom) >= rect.height; })()`), `${name} icon-only quick navigation stays at bottom with accessible tap targets at ${width}px`);
                assert(await evaluate(`!document.body.classList.contains('customer-layout') || (getComputedStyle(document.querySelector('.header-account-name')).display === 'none' && document.querySelector('.header-account .avatar').getBoundingClientRect().right <= innerWidth)`), `${name} mobile customer header shows only the avatar`);
            } else {
                assert(await evaluate(`getComputedStyle(document.querySelector('.mobile-quick-nav')).display === 'none'`), `${name} hides mobile quick navigation on desktop`);
            }
            if (['home', 'search-results', 'admin-flights', 'booking-summary', 'seat-map', 'payment-form', 'payment-summary', 'admin-payments', 'admin-payment-details', 'admin-ticket', 'customer-ticket', 'my-bookings', 'cancellation-form', 'admin-cancellations', 'admin-cancellation-details', 'cancelled-booking', 'void-ticket', 'dashboard', 'report-bookings', 'report-passengers'].includes(name) && width !== 768) {
                const screenshot = await command('Page.captureScreenshot', { captureBeyondViewport: true });
                writeFileSync(join(artifacts, `${name}-${width}.png`), Buffer.from(screenshot.data, 'base64'));
            }
        }
        console.log(`PASS: ${group}/${name}, phone/tablet/desktop, labels, landmarks, contrast`);
    }
};
const signIn = async (role) => {
    await visit('/login');
    await evaluate(`document.getElementById('email').value = ${JSON.stringify(fixture[role + 'Email'])}; document.getElementById('password').value = ${JSON.stringify(fixture.password)}; document.querySelector('.account-form').requestSubmit(); true`);
    await ready(role === 'admin' ? '/admin' : '/');
    assert(await evaluate(`!!document.querySelector('.toast[data-kind="success"]') && !document.querySelector('#confirmation-dialog').open`), 'Login shows a nonblocking success toast');
    assert(await evaluate(`(() => { const toast = document.querySelector('.toast[data-kind="success"]'); const bounds = toast.getBoundingClientRect(); return toast.querySelector('.toast-title').textContent === 'Signed in' && toast.querySelector('.toast-message').textContent.includes('Welcome back,') && !!toast.querySelector('.toast-icon svg circle') && !toast.querySelector('.toast-progress').hidden && bounds.left >= 0 && bounds.right <= innerWidth && (innerWidth > 800 ? bounds.top < 32 : bounds.bottom < document.querySelector('.mobile-quick-nav').getBoundingClientRect().top); })()`), 'Sign-in toast matches the reference card and stays inside responsive screen bounds');
    await pause(250);
    const toastWidth = await evaluate('innerWidth');
    const toastScreenshot = await command('Page.captureScreenshot', { captureBeyondViewport: false });
    writeFileSync(join(artifacts, `${role}-signin-toast-${toastWidth}.png`), Buffer.from(toastScreenshot.data, 'base64'));
    await command('Emulation.setFocusEmulationEnabled', { enabled: true });
    await evaluate(`document.querySelector('.toast-dismiss').focus(); true`); await pause(80);
    const toastFocusState = await evaluate(`({focus:document.querySelector('.toast').contains(document.activeElement),reduced:matchMedia('(prefers-reduced-motion: reduce)').matches,animations:document.querySelector('.toast-progress span').getAnimations().map(animation=>animation.playState)})`);
    assert(toastFocusState.focus && (toastFocusState.reduced ? toastFocusState.animations.length === 0 : toastFocusState.animations[0] === 'paused'), 'Toast countdown pauses on focus and respects reduced motion');
    await evaluate(`document.querySelector('.brand').focus(); true`); await pause(50);
    assert(await evaluate(`matchMedia('(prefers-reduced-motion: reduce)').matches || document.querySelector('.toast-progress span').getAnimations()[0].playState === 'running'`), 'Toast countdown resumes after focus leaves');
};
const signOut = async (role) => {
    await evaluate(`document.querySelector('.logout-form').requestSubmit(); true`);
    await respondToConfirmation();
    await ready('/login');
    assert(await evaluate(`!!document.querySelector('.toast[data-kind="success"]') && !document.querySelector('#confirmation-dialog').open`), 'Logout shows a one-time success toast');
};
try {
    let targets;
    for (let attempt = 0; attempt < 100; attempt++) {
        try { targets = await (await fetch(`http://127.0.0.1:${port}/json/list`)).json(); if (targets.length) break; } catch {}
        await pause(100);
    }
    if (!targets?.length) throw new Error('Chrome did not start. Set SKYRESERVE_TEST_CHROME to its executable path.');
    ws = new WebSocket(targets.find((target) => target.type === 'page').webSocketDebuggerUrl);
    await new Promise((resolve, reject) => { ws.addEventListener('open', resolve, { once: true }); ws.addEventListener('error', reject, { once: true }); });
    ws.addEventListener('message', (event) => {
        const message = JSON.parse(event.data), request = pending.get(message.id);
        if (message.method === 'Page.javascriptDialogOpening' && message.params.type === 'beforeunload') {
            command('Page.handleJavaScriptDialog', { accept: true }).catch(() => {});
        }
        if (!request) return;
        clearTimeout(request.timer); pending.delete(message.id);
        if (message.error) request.reject(new Error(message.error.message)); else request.resolve(message.result);
    });
    await command('Page.enable');
    fixture = JSON.parse(execFileSync(php, [fixtureScript, '--create'], { encoding: 'utf8' }));
    const search = `/flights?from_airport_id=${fixture.from}&to_airport_id=${fixture.to}&travel_date=${fixture.date}`;
    await checkPages([['home', '/'], ['search', '/flights'], ['search-results', search], ['login', '/login'], ['register', '/register'], ['flight-details', `/flights/show?id=${fixture.flightId}`]], 'guest');
    await command('Page.navigate', { url: new URL('/admin/login', base).href });
    await ready('/login');
    assert(await evaluate(`document.querySelector('.account-form').getAttribute('action') === '/login' && !document.querySelector('a[href="/admin/login"]')`), 'Legacy admin URL reaches the shared login form');
    await visit('/register');
    assert(await evaluate(`(() => { document.querySelector('#password').value = 'Visibility-Test-123'; const toggle = document.querySelector('[aria-controls="password"]'); toggle.click(); const shown = document.querySelector('#password').type === 'text' && toggle.getAttribute('aria-pressed') === 'true' && document.querySelector('#password_confirmation').type === 'password'; toggle.click(); return shown && document.querySelector('#password').type === 'password' && document.querySelector('#password').value === 'Visibility-Test-123'; })()`), 'Registration eye toggle preserves values and operates independently');
    assert(await evaluate(`(() => { const toggle = document.querySelector('[aria-controls="password_confirmation"]'); toggle.click(); const shown = document.querySelector('#password_confirmation').type === 'text'; toggle.click(); return shown && document.querySelector('#password_confirmation').type === 'password'; })()`), 'Confirmation password has its own visibility toggle');
    fixture.registeredEmail = fixture.customerEmail.replace('ui-customer-', 'ui-registered-');
    await evaluate(`document.querySelector('#name').value = 'UI Registration Check'; document.querySelector('#email').value = ${JSON.stringify(fixture.registeredEmail)}; document.querySelector('#password').value = ${JSON.stringify(fixture.password)}; document.querySelector('#password_confirmation').value = ${JSON.stringify(fixture.password)}; document.querySelector('.account-form').requestSubmit(); true`);
    await ready('/login');
    assert(await evaluate(`document.querySelector('.toast[data-kind="success"]').textContent.includes('Account created') && !document.querySelector('#confirmation-dialog').open`), 'Successful registration displays a success toast');
    assert(await evaluate(`(() => { const toggle = document.querySelector('[aria-controls="password"]'); toggle.click(); const shown = document.querySelector('#password').type === 'text'; toggle.click(); return shown && document.querySelector('#password').type === 'password'; })()`), 'Login eye toggle reveals and conceals the password');
    await evaluate(`document.querySelector('#email').value = ${JSON.stringify(fixture.customerEmail)}; document.querySelector('#password').value = 'Wrong-Password'; document.querySelector('.account-form').requestSubmit(); true`);
    await pause(400); await ready('/login');
    assert(await evaluate(`document.querySelector('.toast[data-kind="error"]').textContent.includes('Invalid email or password') && !document.querySelector('#confirmation-dialog').open`), 'Invalid login displays a generic error toast');
    await evaluate(`document.querySelector('.toast-dismiss').click(); true`);
    assert(await evaluate(`!document.querySelector('.toast')`), 'Toast dismiss button removes the notification');
    await visit('/');
    assert(await evaluate(`document.querySelector('.mobile-quick-nav a[aria-current="page"]').getAttribute('href') === '/' && document.querySelector('.mobile-quick-nav a[aria-label="Log in to view your bookings"]').getAttribute('href') === '/login'`), 'Guest quick navigation protects account destinations and marks Home active');
    assert(await evaluate(`!document.querySelector('#upcoming-title') && !document.querySelector('[data-upcoming-flight]') && document.querySelector('.home-final-cta a[href="/login"]') !== null && document.querySelectorAll('.benefit-card').length === 4 && document.querySelectorAll('.booking-journey li').length === 6`), 'Guest Home keeps welcome and overview without upcoming flights');
    await visit('/flights');
    assert(await evaluate(`!!document.querySelector('#upcoming-title') && !document.querySelector('.flight-results') && !document.querySelector('.search-form button') && document.querySelectorAll('[data-upcoming-flight]').length <= 6`), 'Search page initially displays upcoming flights without a submit button');
    for (const emptyMode of ['--search-empty', '--search-empty-customer']) {
    const emptyHomepage = execFileSync(php, [fixtureScript, emptyMode], {encoding: 'utf8'});
    const homeFrame = await command('Page.getFrameTree');
    await command('Page.setDocumentContent', {frameId: homeFrame.frameTree.frame.id, html: emptyHomepage});
    await pause(150);
    for (const width of [375, 768, 1440]) {
        await command('Emulation.setDeviceMetricsOverride', {width, height:1000, deviceScaleFactor:1, mobile:false});
        const result = await evaluate(`(${audit.toString()})()`);
        assert(!result.overflow && !result.unlabeled.length && !result.duplicateIds && !result.contrastIssues.length && result.headings === 1, `Empty-flight search is accessible and responsive for ${emptyMode} at ${width}px`);
        assert(await evaluate(`!!document.querySelector('[data-upcoming-empty]') && !document.querySelector('[data-upcoming-flight]')`), 'Empty state replaces flight cards');
    }
    }
    await visit('/flights');
    await evaluate(`document.querySelector('#from_airport_id').value = '${fixture.from}'; document.querySelector('#from_airport_id').dispatchEvent(new Event('change', {bubbles:true})); true`);
    await pause(450);
    assert(await evaluate(`location.search === '' && !document.querySelector('[aria-invalid="true"]')`), 'Incomplete route filters wait for required fields without submitting');
    await evaluate(`document.querySelector('#to_airport_id').value = '${fixture.to}'; document.querySelector('#travel_date').value = '${fixture.date}'; document.querySelector('#travel_date').dispatchEvent(new Event('change', {bubbles:true})); true`);
    await pause(650); await ready('/flights');
    assert(await evaluate(`new URL(location.href).searchParams.get('from_airport_id') === '${fixture.from}' && document.querySelector('.flight-results') !== null`), 'Valid flight filters refresh automatically without pressing Search');
    assert(await evaluate(`!document.querySelector('#upcoming-title') && !document.querySelector('[data-upcoming-flight]')`), 'Submitted search displays results instead of upcoming flights');
    await command('Emulation.setDeviceMetricsOverride', { width: 375, height: 900, deviceScaleFactor: 1, mobile: false });
    await visit('/login');
    assert(await evaluate(`getComputedStyle(document.querySelector('.main-nav')).display === 'none'`), 'Mobile menu starts collapsed');
    assert(await evaluate(`document.querySelector('.menu-toggle').click(); document.querySelector('.menu-toggle').getAttribute('aria-expanded') === 'true' && getComputedStyle(document.querySelector('.main-nav')).display !== 'none'`), 'Mobile menu opens');
    assert(await evaluate(`document.dispatchEvent(new KeyboardEvent('keydown', {key:'Escape', bubbles:true})); document.querySelector('.menu-toggle').getAttribute('aria-expanded') === 'false'`), 'Escape closes menu');
    assert(await evaluate(`document.querySelector('.account-form').requestSubmit(); !!document.querySelector('[aria-invalid="true"]') && document.querySelector('.form-feedback').textContent.includes('highlighted')`), 'Native validation gives accessible feedback');
    await signIn('customer');
    await evaluate(`document.querySelector('.logout-form').requestSubmit(); true`);
    await respondToConfirmation(false);
    assert(await evaluate(`!!document.querySelector('.logout-form') && location.pathname === '/'`), 'Dismissing logout confirmation keeps the customer signed in on home');
    await checkPages([['home-customer', '/']], 'customer');
    assert(await evaluate(`!document.querySelector('#upcoming-title')`), 'Customer Home omits upcoming flights');
    await checkPages([['customer-search', '/flights'], ['customer-search-results', search]], 'customer');
    await visit('/flights');
    assert(await evaluate(`!!document.querySelector('#upcoming-title') && !document.querySelector('.search-form button')`), 'Customer search initially shows upcoming flights and filters automatically');
    await visit('/');
    const journeyDestinations = ['/flights', '/bookings', '/bookings?section=seats', '/bookings?section=payments', '/bookings?section=payments&status=payment_submitted', '/bookings?section=tickets'];
    for (const [index, destination] of journeyDestinations.entries()) {
        await visit('/');
        await evaluate(`document.querySelectorAll('.booking-journey li > a')[${index}].click(); true`);
        await ready(destination);
        assert(await evaluate(`location.pathname + location.search === ${JSON.stringify(destination)} && !!document.querySelector('h1')`), `Booking journey step ${index + 1} opens its corresponding feature`);
    }
    await visit('/');
    assert(await evaluate(`document.querySelectorAll('.customer-nav a').length === 7 && !document.querySelector('.site-header .logout-form') && !!document.querySelector('.customer-sidebar .logout-form[action="/logout"]') && !!document.querySelector('.site-header .header-account .avatar') && !document.querySelector('.site-header .main-nav > a')`), 'Customer navigation moves to sidebar with logout and header identity');
    await command('Emulation.setDeviceMetricsOverride', { width: 375, height: 900, deviceScaleFactor: 1, mobile: false });
    assert(await evaluate(`document.querySelector('.menu-toggle').click(); getComputedStyle(document.querySelector('.customer-sidebar')).display !== 'none' && document.querySelector('.menu-toggle').getAttribute('aria-expanded') === 'true'`), 'Mobile customer menu opens the sidebar');
    assert(await evaluate(`(() => { const sidebar = document.querySelector('.customer-sidebar'); const rect = sidebar.getBoundingClientRect(); return rect.top === 0 && rect.left === 0 && Math.abs(rect.bottom - innerHeight) < 1 && Math.abs(rect.right - innerWidth) < 1 && getComputedStyle(document.body).overflow === 'hidden' && document.querySelector('.app-main').inert && document.querySelector('.site-header').inert && document.activeElement === document.querySelector('.sidebar-close'); })()`), 'Customer menu covers full viewport, locks page scrolling and focuses its close control');
    assert(await evaluate(`document.querySelector('.sidebar-close').click(); !document.querySelector('.app-main').inert && document.activeElement === document.querySelector('.menu-toggle') && getComputedStyle(document.querySelector('.customer-sidebar')).display === 'none'`), 'Close icon restores background interaction and hamburger focus');
    await evaluate(`document.querySelector('.menu-toggle').click(); true`);
    const menuScreenshot = await command('Page.captureScreenshot', { captureBeyondViewport: false });
    writeFileSync(join(artifacts, 'customer-fullscreen-menu-375.png'), Buffer.from(menuScreenshot.data, 'base64'));
    assert(await evaluate(`document.dispatchEvent(new KeyboardEvent('keydown', {key:'Escape', bubbles:true})); getComputedStyle(document.querySelector('.customer-sidebar')).display === 'none'`), 'Escape closes the mobile customer sidebar');
    await checkPages([['customer-seats', '/bookings?section=seats'], ['customer-payments', '/bookings?section=payments'], ['customer-tickets', '/bookings?section=tickets']], 'customer');
    for (const section of ['seats', 'payments', 'tickets']) {
        await visit('/bookings?section=' + section);
        assert(await evaluate(`document.querySelector('.customer-nav a[aria-current="page"]').href.endsWith('section=${section}') && [...document.querySelectorAll('.filter-tabs a')].every(link => new URL(link.href).searchParams.get('section') === '${section}')`), `Customer ${section} section remains selected when filtering`);
    }
    await evaluate(`document.querySelector('.mobile-quick-nav a[href="/profile"]').click(); true`);
    await ready('/profile');
    assert(await evaluate(`document.querySelector('.mobile-quick-nav a[aria-current="page"]').getAttribute('href') === '/profile'`), 'Customer quick Profile link reaches the existing profile route');
    await evaluate(`document.querySelector('.mobile-quick-nav a[href="/bookings"]').click(); true`);
    await ready('/bookings');
    assert(await evaluate(`document.querySelector('.mobile-quick-nav a[aria-current="page"]').getAttribute('href') === '/bookings'`), 'Customer quick Bookings link reaches its existing bookings route');
    await visit('/');
    assert(await evaluate(`!!document.querySelector('.home-final-cta a[href="/bookings"]')`), 'Customer homepage links to My Bookings');
    await checkPages([['profile', '/profile'], ['booking-form', `/bookings/create?flight_id=${fixture.flightId}`], ['booking-summary', `/bookings/show?id=${fixture.bookingId}`], ['seat-map', `/bookings/seats?booking_id=${fixture.bookingId}`], ['payment-form', `/bookings/payment?booking_id=${fixture.bookingId}`]], 'customer');
    await visit(`/bookings/seats?booking_id=${fixture.bookingId}`);
    await evaluate(`document.querySelector('#passenger_id').selectedIndex = 1; document.querySelector('input[name="seat_id"][value="${fixture.seatId}"]').checked = true; document.querySelector('.account-form').requestSubmit(); true`);
    await respondToConfirmation();
    await ready('/bookings/show'); await pause(100);
    assert(await evaluate(`document.body.textContent.includes('2A')`), 'Native seat selection prepares passenger for ticket');
    await visit(`/bookings/payment?booking_id=${fixture.bookingId}`);
    assert(await evaluate(`document.querySelector('.account-form').dataset.confirm?.includes('payment')`), 'Payment submission has a confirmation handler');
    await evaluate(`document.getElementById('method').value = 'bank_transfer'; document.getElementById('transaction_reference').value = 'UI-DEMO-REFERENCE'; document.querySelector('.account-form').requestSubmit(); true`);
    await respondToConfirmation();
    await ready('/bookings/show');
    assert(await evaluate(`document.body.textContent.includes('Awaiting Verification') && document.body.textContent.includes('UI-DEMO-REFERENCE')`), 'Native payment form works with shared loading interactions');
    await checkPages([['payment-summary', `/bookings/show?id=${fixture.bookingId}`]], 'customer');
    await visit('/profile');
    await signOut('customer');
    await signIn('admin');
    await checkPages([['home-admin', '/']], 'admin');
    assert(await evaluate(`document.querySelectorAll('.mobile-quick-nav a').length === 7 && [...document.querySelectorAll('.mobile-quick-nav a')].every(link => new URL(link.href).pathname.startsWith('/admin'))`), 'Admin quick navigation contains every admin feature and no general destinations');
    assert(await evaluate(`!!document.querySelector('.home-final-cta a[href="/admin"]') && !document.querySelector('.home-final-cta a[href="/bookings"]')`), 'Admin homepage links to its authorized dashboard');
    await command('Emulation.setDeviceMetricsOverride', { width: 1440, height: 800, deviceScaleFactor: 1, mobile: false });
    await visit('/admin');
    assert(await evaluate(`document.querySelectorAll('.admin-nav svg[aria-hidden="true"]').length === 7`), 'All admin sidebar actions have decorative SVG icons');
    const sidebarTop = await evaluate(`document.querySelector('.admin-sidebar').getBoundingClientRect().top`);
    await evaluate(`window.scrollTo({top: 500, behavior: 'instant'}); true`); await pause(100);
    assert(await evaluate(`scrollY > 0 && Math.abs(document.querySelector('.site-header').getBoundingClientRect().top) < 1`), 'Navbar remains at viewport top after scrolling');
    assert(await evaluate(`getComputedStyle(document.querySelector('.admin-sidebar')).position === 'fixed' && Math.abs(document.querySelector('.admin-sidebar').getBoundingClientRect().top - ${sidebarTop}) < 1`), 'Desktop sidebar remains fixed after page scrolling');
    assert(await evaluate(`!document.querySelector('.site-footer') && document.querySelector('.back-to-top').getAttribute('href') === '#page-top' && document.querySelector('.back-to-top').getAttribute('aria-label') === 'Back to top' && !!document.querySelector('.back-to-top svg')`), 'Admin panel omits footer and provides an accessible back-to-top icon');
    await command('Emulation.setEmulatedMedia', { features: [{ name: 'prefers-reduced-motion', value: 'reduce' }] });
    await evaluate(`document.querySelector('.back-to-top').click(); true`); await pause(100);
    assert(await evaluate(`scrollY === 0`), 'Back-to-top link returns to page start with reduced motion');
    await command('Emulation.setEmulatedMedia', { features: [] });
    await command('Emulation.setDeviceMetricsOverride', { width: 375, height: 800, deviceScaleFactor: 1, mobile: false });
    assert(await evaluate(`getComputedStyle(document.querySelector('.admin-sidebar')).display === 'none' && getComputedStyle(document.querySelector('.header-account')).display !== 'none' && getComputedStyle(document.querySelector('.header-account-name')).display === 'none' && Math.abs(document.querySelector('.brand').getBoundingClientRect().left + document.querySelector('.brand').getBoundingClientRect().width / 2 - innerWidth / 2) < 5`), 'Admin mobile header centers brand and keeps avatar visible with menu collapsed');
    await evaluate(`document.querySelector('.menu-toggle').click(); true`);
    assert(await evaluate(`document.querySelector('.admin-sidebar').getBoundingClientRect().height === innerHeight && document.querySelector('.admin-sidebar .sidebar-logout').getBoundingClientRect().height > 0`), 'Admin mobile hamburger opens full-screen features and logout');
    const adminMenuScreenshot = await command('Page.captureScreenshot', { captureBeyondViewport: false });
    writeFileSync(join(artifacts, 'admin-fullscreen-menu-375.png'), Buffer.from(adminMenuScreenshot.data, 'base64'));
    await evaluate(`document.querySelector('.admin-sidebar .sidebar-close').click(); true`);
    await command('Emulation.setDeviceMetricsOverride', { width: 1440, height: 1000, deviceScaleFactor: 1, mobile: false });
    await checkPages([
        ['dashboard', '/admin'], ['airports', '/admin/airports'], ['airport-add', '/admin/airports/create'], ['airport-edit', `/admin/airports/edit?id=${fixture.from}`],
        ['aircraft', '/admin/aircraft'], ['aircraft-add', '/admin/aircraft/create'], ['aircraft-edit', `/admin/aircraft/edit?id=${fixture.aircraftId}`],
        ['seats', `/admin/seats?aircraft_id=${fixture.aircraftId}`], ['seat-add', `/admin/seats/create?aircraft_id=${fixture.aircraftId}`], ['seat-edit', `/admin/seats/edit?aircraft_id=${fixture.aircraftId}&id=${fixture.seatId}`],
        ['admin-flights', '/admin/flights'], ['flight-add', '/admin/flights/create'], ['flight-edit', `/admin/flights/edit?id=${fixture.flightId}`], ['admin-flight-details', `/admin/flights/show?id=${fixture.flightId}`],
    ], 'admin');
    await visit('/admin/flights/create');
    assert(await evaluate(`document.querySelector('a.return-link').classList.contains('button-secondary')`), 'Cancel and return is styled as an interactive button');
    await evaluate(`document.querySelector('#flight_number').value = 'UNSAVED-TEST'; document.querySelector('.back-link').click(); true`);
    assert(await evaluate(`document.querySelector('#confirmation-title').textContent === 'Leave without saving?' && document.activeElement.matches('[data-dialog-cancel]')`), 'Unsaved navigation asks permission and focuses the safe action');
    await respondToConfirmation(false);
    assert(await evaluate(`location.pathname === '/admin/flights/create' && document.querySelector('#flight_number').value === 'UNSAVED-TEST'`), 'Keep editing preserves unsaved values');
    await evaluate(`document.querySelector('.back-link').click(); true`);
    for (const width of [375, 1440]) {
        await command('Emulation.setDeviceMetricsOverride', { width, height: 1000, deviceScaleFactor: 1, mobile: false });
        assert(await evaluate(`(() => { const rect = document.querySelector('#confirmation-dialog').getBoundingClientRect(); return rect.left >= 0 && rect.right <= innerWidth && rect.top >= 0 && rect.bottom <= innerHeight; })()`), `Confirmation dialog fits at ${width}px`);
        const screenshot = await command('Page.captureScreenshot');
        writeFileSync(join(artifacts, `confirmation-dialog-${width}.png`), Buffer.from(screenshot.data, 'base64'));
    }
    await command('Input.dispatchKeyEvent', { type: 'keyDown', key: 'Escape', code: 'Escape', windowsVirtualKeyCode: 27 });
    await command('Input.dispatchKeyEvent', { type: 'keyUp', key: 'Escape', code: 'Escape', windowsVirtualKeyCode: 27 });
    await pause(100);
    assert(await evaluate(`!document.querySelector('#confirmation-dialog').open && location.pathname === '/admin/flights/create'`), 'Escape dismisses confirmation without discarding edits');
    await evaluate(`document.querySelector('a.return-link').click(); true`);
    await respondToConfirmation();
    await ready('/admin/flights');
    await visit('/admin/flights');
    for (const width of [1280, 1440, 1920]) {
        await command('Emulation.setDeviceMetricsOverride', { width, height: 1000, deviceScaleFactor: 1, mobile: false });
        assert(await evaluate(`(() => { const region = document.querySelector('[aria-label="Flight list"]'); const sidebar = document.querySelector('.admin-sidebar').getBoundingClientRect(); return sidebar.left <= 17 && region.scrollWidth <= region.clientWidth + 1 && [...region.querySelectorAll('.row-actions a, .row-actions button')].every((action) => { const rect = action.getBoundingClientRect(); const bounds = region.getBoundingClientRect(); return rect.left >= bounds.left && rect.right <= bounds.right; }); })()`), `Flight actions fit without horizontal scrolling at ${width}px`);
    }
    await command('Emulation.setDeviceMetricsOverride', { width: 1440, height: 1000, deviceScaleFactor: 1, mobile: false });
    const wideFlightScreenshot = await command('Page.captureScreenshot', {captureBeyondViewport: true});
    writeFileSync(join(artifacts, 'admin-flights-wide-1440.png'), Buffer.from(wideFlightScreenshot.data, 'base64'));
    await visit('/admin/payments');
    const paymentId = await evaluate(`(() => { const row = [...document.querySelectorAll('tbody tr')].find((row) => row.textContent.includes(${JSON.stringify(fixture.customerEmail)})); const link = row?.querySelector('a[href^="/admin/payments/show"]'); return link ? new URL(link.href).searchParams.get('id') : null; })()`);
    assert(!!paymentId, 'Submitted customer payment appears in admin list');
    await checkPages([
        ['admin-payments', '/admin/payments'], ['admin-payment-details', `/admin/payments/show?id=${paymentId}`],
        ['admin-verified-payments', '/admin/payments?status=verified'], ['admin-rejected-payments', '/admin/payments?status=rejected'],
    ], 'admin');
    assert(await evaluate(`document.querySelector('.admin-nav [aria-current="page"]').getAttribute('href') === '/admin/payments'`), 'Payment pages highlight Payments navigation');
    await visit(`/admin/payments/show?id=${paymentId}`);
    await evaluate(`document.querySelector('form[action^="/admin/payments/verify"]').requestSubmit(); true`);
    await respondToConfirmation();
    await ready('/admin/payments/show');
    await pause(100);
    assert(await evaluate(`document.querySelector('.page-heading .badge').textContent.trim() === 'Verified' && document.body.textContent.includes('Confirmed')`), 'Native admin verification works with shared confirmation and loading interactions');
    await checkPages([['admin-verified-details', `/admin/payments/show?id=${paymentId}`]], 'admin');
    await evaluate(`document.querySelector('form[action^="/admin/bookings/tickets"]').requestSubmit(); true`);
    await respondToConfirmation();
    await ready('/admin/tickets/show'); await pause(100);
    const ticketId = await evaluate(`new URL(location.href).searchParams.get('id')`);
    assert(await evaluate(`document.querySelector('.ticket-document').textContent.includes('Preview Passenger')`), 'Native ticket generation works from verified admin payment');
    await checkPages([['admin-ticket', `/admin/tickets/show?id=${ticketId}`]], 'admin');
    assert(await evaluate(`document.querySelector('.admin-nav [aria-current="page"]').getAttribute('href') === '/admin/payments'`), 'Admin ticket highlights related Payments navigation');
    await command('Emulation.setEmulatedMedia', { media: 'print' });
    assert(await evaluate(`getComputedStyle(document.querySelector('.site-header')).display === 'none' && getComputedStyle(document.querySelector('.admin-sidebar')).display === 'none' && getComputedStyle(document.querySelector('.ticket-actions')).display === 'none' && getComputedStyle(document.querySelector('.ticket-document')).display !== 'none'`), 'Print hides navigation and actions while preserving ticket');
    const printed = await command('Page.printToPDF', { printBackground: true, preferCSSPageSize: true });
    const pdf = Buffer.from(printed.data, 'base64');
    assert(pdf.subarray(0, 5).toString() === '%PDF-' && pdf.length > 1000, 'Browser renders printable ticket PDF');
    assert((pdf.toString('latin1').match(/\/Type \/Page\b/g) || []).length === 1, 'Ticket prints on one A4 page');
    writeFileSync(join(artifacts, 'ticket-print.pdf'), pdf);
    await command('Emulation.setEmulatedMedia', { media: '' });
    await checkPages([
        ['reports-index', '/admin/reports'], ['report-bookings', `/admin/reports/bookings?flight_id=${fixture.flightId}`],
        ['report-flights', `/admin/reports/flights?flight_id=${fixture.flightId}`], ['report-payments', `/admin/reports/payments?flight_id=${fixture.flightId}`],
        ['report-cancellations-empty', `/admin/reports/cancellations?flight_id=${fixture.flightId}`], ['report-passengers-select', '/admin/reports/passengers'],
        ['report-passengers', `/admin/reports/passengers?flight_id=${fixture.flightId}`], ['report-bookings-empty', `/admin/reports/bookings?flight_id=${fixture.flightId}&start_date=1900-01-01&end_date=1900-01-01`],
        ['report-invalid-filter', '/admin/reports/bookings?start_date=2025-01-02&end_date=2025-01-01'],
    ], 'admin');
    assert(await evaluate(`document.querySelector('.admin-nav [aria-current="page"]').getAttribute('href') === '/admin/reports'`), 'Reports highlight admin sidebar');
    await visit('/admin/reports/bookings');
    if (await evaluate(`CSS.supports('appearance', 'base-select')`)) {
        const filterPosition = await evaluate(`(() => { const field = document.querySelector('#booking_status'); field.scrollIntoView({block: 'center', behavior: 'instant'}); const rect = field.getBoundingClientRect(); return {x: rect.x + rect.width / 2, y: rect.y + rect.height / 2}; })()`);
        await command('Input.dispatchMouseEvent', { type: 'mousePressed', ...filterPosition, button: 'left', clickCount: 1 });
        await command('Input.dispatchMouseEvent', { type: 'mouseReleased', ...filterPosition, button: 'left', clickCount: 1 });
        assert(await evaluate(`document.querySelector('#booking_status').matches(':open') && getComputedStyle(document.querySelector('#booking_status'), '::picker(select)').borderRadius === '12px'`), 'Filter opens the styled native dropdown');
        assert(await evaluate(`(() => { const select = document.querySelector('#booking_status'); const picker = getComputedStyle(select, '::picker(select)'); const rect = select.getBoundingClientRect(); return picker.positionTryFallbacks === 'none' && Math.abs(parseFloat(picker.top) - rect.bottom - 6) < 1 && Math.abs(parseFloat(picker.left) - rect.left) < 1; })()`), 'Dropdown is positioned directly below its field without upward fallback');
        const pickerScreenshot = await command('Page.captureScreenshot');
        writeFileSync(join(artifacts, 'report-filter-open.png'), Buffer.from(pickerScreenshot.data, 'base64'));
        await command('Input.dispatchKeyEvent', { type: 'keyDown', key: 'ArrowDown', code: 'ArrowDown', windowsVirtualKeyCode: 40 });
        await command('Input.dispatchKeyEvent', { type: 'keyUp', key: 'ArrowDown', code: 'ArrowDown', windowsVirtualKeyCode: 40 });
        await command('Input.dispatchKeyEvent', { type: 'keyDown', key: 'Enter', code: 'Enter', windowsVirtualKeyCode: 13 });
        await command('Input.dispatchKeyEvent', { type: 'keyUp', key: 'Enter', code: 'Enter', windowsVirtualKeyCode: 13 });
        assert(await evaluate(`document.querySelector('#booking_status').value === 'pending' && !document.querySelector('#booking_status').matches(':open')`), 'Styled filter supports native keyboard selection');
    }
    await evaluate(`document.querySelector('#flight_id').value = '${fixture.flightId}'; document.querySelector('#booking_status').value = 'confirmed'; document.querySelector('.report-filters').requestSubmit(); true`);
    await ready('/admin/reports/bookings'); await pause(100);
    assert(await evaluate(`new URL(location.href).searchParams.get('flight_id') === '${fixture.flightId}' && !!document.querySelector('[data-report-row="${fixture.bookingId}"]') && document.querySelector('[data-report-total]').textContent.trim() === '1'`), 'Native report filters retain selection and show accurate total');
    await evaluate(`document.querySelectorAll('.report-filters input, .report-filters select').forEach(field => field.value = ''); document.querySelector('#booking_status').dispatchEvent(new Event('change', {bubbles:true})); true`);
    await waitFor(`document.readyState === 'complete' && new URL(location.href).searchParams.get('booking_status') === ''`, 'Cleared report filters finish loading');
    assert(await evaluate(`document.querySelector('#flight_id').value === '' && document.querySelector('#booking_status').value === '' && new URL(location.href).searchParams.get('booking_status') === ''`), 'Clearing fields removes report filters automatically');
    for (const width of [375, 1440]) {
        await command('Emulation.setDeviceMetricsOverride', { width, height: 1000, deviceScaleFactor: 1, mobile: false });
        assert(await evaluate(`!document.querySelector('.report-filters button[type="submit"], .report-filters a')`), `Automatic report filters have no Apply or Reset buttons at ${width}px`);
        if (width === 1440) assert(await evaluate(`(() => { const tops = [...document.querySelectorAll('.report-filters > div')].map(field => field.getBoundingClientRect().top); const heights = [...document.querySelectorAll('.report-filters input, .report-filters select')].map(field => field.getBoundingClientRect().height); return tops.every(top => Math.abs(top - tops[0]) < 1) && heights.every(height => Math.abs(height - heights[0]) < 1); })()`), 'All five report filters fit in one desktop row with equal control heights');
        assert(await evaluate(`[...document.querySelectorAll('[aria-label="Report shortcuts"] a')].every(link => link.classList.contains('button') && getComputedStyle(link).textDecorationLine === 'none')`), 'Report shortcuts are buttons without underlines');
    }
    await evaluate(`document.querySelector('#flight_id').value = '${fixture.flightId}'; document.querySelector('#booking_status').value = 'cancelled'; document.querySelector('#booking_status').dispatchEvent(new Event('change', {bubbles:true})); true`);
    await pause(650); await ready('/admin/reports/bookings');
    assert(await evaluate(`new URL(location.href).searchParams.get('booking_status') === 'cancelled' && document.querySelector('[data-report-total]').textContent.trim() === '0'`), 'Report filters refresh automatically after selection');
    await visit(`/admin/seats?aircraft_id=${fixture.aircraftId}`);
    assert(await evaluate(`document.querySelector('.admin-nav [aria-current="page"]').getAttribute('href') === '/admin/aircraft'`), 'Seat pages highlight Aircraft & seats');
    await evaluate(`document.querySelector('main form[data-confirm]').requestSubmit(); true`);
    await evaluate(`document.querySelector('main form[data-confirm]').requestSubmit(); true`);
    assert(await evaluate(`document.querySelector('#confirmation-dialog').open && !document.querySelector('main form[data-confirm]').dataset.submitting`), 'Repeated submission while awaiting permission stays blocked');
    await respondToConfirmation(false);
    assert(await evaluate(`!document.querySelector('main form[data-confirm]').dataset.submitting`), 'Cancelled delete stays on page without loading state');
    await evaluate(`(() => { const form = document.querySelector('main form[data-confirm]'); let submits = 0; const stopSave = (event) => { if (++submits === 2) { event.preventDefault(); form.removeEventListener('submit', stopSave); } }; form.addEventListener('submit', stopSave); form.requestSubmit(form.querySelector('button')); return true; })()`);
    await respondToConfirmation();
    assert(await evaluate(`document.querySelector('main form[data-confirm] button').getAttribute('aria-busy') === 'true'`), 'Confirmed action gets loading state without altering fixture');
    assert(await evaluate(`window.dispatchEvent(new Event('pageshow')); !document.querySelector('button[aria-busy]')`), 'Back/Forward restores loading buttons');
    await command('Emulation.setEmulatedMedia', { features: [{ name: 'prefers-reduced-motion', value: 'reduce' }] });
    assert(await evaluate(`getComputedStyle(document.querySelector('button')).transitionDuration === '0s'`), 'Reduced-motion preference respected');
    await visit('/admin'); await signOut('admin');
    await signIn('customer');
    await checkPages([['ticket-booking-summary', `/bookings/show?id=${fixture.bookingId}`], ['customer-ticket', `/tickets/show?id=${ticketId}`]], 'customer');
    assert(await evaluate(`window.print = () => { window.ticketPrinted = true; }; document.querySelector('[data-print-ticket]').click(); window.ticketPrinted === true`), 'Print button invokes browser printing');
    const offline = await evaluate(`(async () => { const response = await fetch('/tickets/download?id=${ticketId}'); return { status: response.status, disposition: response.headers.get('content-disposition'), html: await response.text() }; })()`);
    assert(offline.status === 200 && offline.disposition.includes('.html') && offline.html.includes('Preview Passenger') && offline.html.includes('@media print') && !offline.html.includes('<script'), 'Customer downloads a self-contained printable ticket');
    writeFileSync(join(artifacts, 'ticket-download.html'), offline.html);
    await checkPages([['my-bookings', '/bookings'], ['my-bookings-empty', '/bookings?status=cancelled'], ['cancellation-form', `/bookings/cancel?booking_id=${fixture.bookingId}`]], 'customer');
    await evaluate(`document.querySelector('main form[data-confirm]').requestSubmit(); true`);
    await respondToConfirmation(false);
    assert(await evaluate(`!document.querySelector('main form[data-confirm]').dataset.submitting`), 'Customer can dismiss cancellation confirmation');
    await evaluate(`document.querySelector('#reason').value = 'Travel plans changed'; document.querySelector('main form[data-confirm]').requestSubmit(); true`);
    await respondToConfirmation();
    await ready('/bookings/show'); await pause(100);
    assert(await evaluate(`document.body.textContent.includes('Cancellation Requested')`), 'Native cancellation request updates summary');
    await checkPages([['cancellation-requested-summary', `/bookings/show?id=${fixture.bookingId}`], ['my-bookings-requested', '/bookings?status=cancellation_requested']], 'customer');
    await visit('/profile'); await signOut('customer');
    await signIn('admin'); await visit('/admin/cancellations');
    const cancellationId = await evaluate(`(() => { const row = [...document.querySelectorAll('tbody tr')].find((row) => row.textContent.includes(${JSON.stringify(fixture.customerEmail)})); return new URL(row.querySelector('a').href).searchParams.get('id'); })()`);
    await checkPages([['admin-cancellations', '/admin/cancellations'], ['admin-cancellation-details', `/admin/cancellations/show?id=${cancellationId}`], ['admin-approved-cancellations-empty', '/admin/cancellations?status=approved'], ['admin-rejected-cancellations-empty', '/admin/cancellations?status=rejected']], 'admin');
    assert(await evaluate(`document.querySelector('.admin-nav [aria-current="page"]').getAttribute('href') === '/admin/cancellations'`), 'Cancellation pages highlight admin navigation');
    await visit(`/admin/cancellations/show?id=${cancellationId}`);
    await evaluate(`document.querySelector('#note').value = 'Retain booking'; const form = document.querySelector('.account-form'); form.requestSubmit(form.querySelector('button[formaction]')); true`);
    assert(await evaluate(`document.querySelector('#confirmation-message').textContent.includes('Reject')`), 'Rejection shows the selected action rather than approval');
    await respondToConfirmation();
    await ready('/admin/cancellations/show'); await pause(100);
    assert(await evaluate(`document.querySelector('.page-heading .badge').textContent.trim() === 'Rejected' && document.body.textContent.includes('Confirmed')`), 'Native rejection restores booking through submitter formaction');
    await checkPages([['admin-rejected-cancellation-details', `/admin/cancellations/show?id=${cancellationId}`]], 'admin');
    await visit('/admin'); await signOut('admin'); await signIn('customer');
    await visit(`/bookings/cancel?booking_id=${fixture.bookingId}`); await evaluate(`document.querySelector('main form[data-confirm]').requestSubmit(); true`); await respondToConfirmation(); await ready('/bookings/show'); await pause(100);
    await visit('/profile'); await signOut('customer'); await signIn('admin'); await visit('/admin/cancellations');
    const retryId = await evaluate(`(() => { const row = [...document.querySelectorAll('tbody tr')].find((row) => row.textContent.includes(${JSON.stringify(fixture.customerEmail)})); return new URL(row.querySelector('a').href).searchParams.get('id'); })()`);
    await visit(`/admin/cancellations/show?id=${retryId}`); await evaluate(`const form = document.querySelector('.account-form'); form.requestSubmit(form.querySelector('button[type="submit"]')); true`); await respondToConfirmation(); await ready('/admin/cancellations/show'); await pause(100);
    assert(await evaluate(`document.querySelector('.page-heading .badge').textContent.trim() === 'Approved' && document.body.textContent.includes('Cancelled') && document.body.textContent.includes('Released')`), 'Native approval cancels booking and releases seat');
    await checkPages([['admin-approved-cancellation-details', `/admin/cancellations/show?id=${retryId}`]], 'admin');
    await checkPages([['report-cancellation-history', `/admin/reports/cancellations?flight_id=${fixture.flightId}`]], 'admin');
    assert(await evaluate(`document.querySelector('[data-report-total]').textContent.trim() === '2' && document.querySelector('.report-table').textContent.includes('Approved') && document.querySelector('.report-table').textContent.includes('Rejected')`), 'Cancellation report reflects reviewed history without duplicate joins');
    await visit('/admin'); await signOut('admin'); await signIn('customer');
    await checkPages([['cancelled-booking', `/bookings/show?id=${fixture.bookingId}`], ['my-bookings-cancelled', '/bookings?status=cancelled'], ['void-ticket', `/tickets/show?id=${ticketId}`]], 'customer');
    assert(await evaluate(`document.querySelector('.ticket-invalid').textContent.includes('VOID TICKET')`), 'Cancelled ticket shows explicit invalid-for-travel warning');
    console.log(`${checks} browser UI checks passed. Screenshots: ${artifacts}`);
} finally {
    if (fixture) execFileSync(php, [fixtureScript, '--cleanup'], { input: JSON.stringify(fixture) });
    if (ws) ws.close();
    for (const request of pending.values()) { clearTimeout(request.timer); request.reject(new Error('Browser closed')); }
    chrome.kill();
    await pause(500);
    // Only delete the named Chrome profile directly inside this run's temporary directory.
    if (dirname(resolve(profile)) !== resolve(artifacts)) throw new Error('Unexpected profile cleanup path.');
    try { rmSync(profile, { recursive: true, force: true }); } catch {}
}
