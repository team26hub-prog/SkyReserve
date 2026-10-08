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
    throw new Error(`Page did not load: ${path}`);
};
const visit = async (path) => { await command('Page.navigate', { url: new URL(path, base).href }); await ready(path); await pause(80); };
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
        for (const width of [375, 768, 1440]) {
            await command('Emulation.setDeviceMetricsOverride', { width, height: 1000, deviceScaleFactor: 1, mobile: false });
            const result = await evaluate(`(${audit.toString()})()`);
            assert(!result.overflow, `${name} overflows at ${width}px`);
            assert(!result.unlabeled.length && !result.duplicateIds && result.headings === 1 && result.landmark, `${name} semantic/label issue: ${JSON.stringify(result)}`);
            assert(!result.contrastIssues.length, `${name} contrast issues: ${JSON.stringify(result.contrastIssues)}`);
            if (['home', 'search-results', 'admin-flights', 'booking-summary', 'seat-map', 'payment-form', 'payment-summary', 'admin-payments', 'admin-payment-details', 'admin-ticket', 'customer-ticket', 'my-bookings', 'cancellation-form', 'admin-cancellations', 'admin-cancellation-details', 'cancelled-booking', 'void-ticket', 'dashboard', 'report-bookings', 'report-passengers'].includes(name) && width !== 768) {
                const screenshot = await command('Page.captureScreenshot', { captureBeyondViewport: true });
                writeFileSync(join(artifacts, `${name}-${width}.png`), Buffer.from(screenshot.data, 'base64'));
            }
        }
        console.log(`PASS: ${group}/${name}, phone/tablet/desktop, labels, landmarks, contrast`);
    }
};
const signIn = async (role) => {
    await visit(role === 'admin' ? '/admin/login' : '/login');
    await evaluate(`document.getElementById('email').value = ${JSON.stringify(fixture[role + 'Email'])}; document.getElementById('password').value = ${JSON.stringify(fixture.password)}; document.querySelector('.account-form').requestSubmit(); true`);
    await ready(role === 'admin' ? '/admin' : '/profile');
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
        if (!request) return;
        clearTimeout(request.timer); pending.delete(message.id);
        if (message.error) request.reject(new Error(message.error.message)); else request.resolve(message.result);
    });
    await command('Page.enable');
    fixture = JSON.parse(execFileSync(php, [fixtureScript, '--create'], { encoding: 'utf8' }));
    const search = `/flights?from_airport_id=${fixture.from}&to_airport_id=${fixture.to}&travel_date=${fixture.date}`;
    await checkPages([['home', '/'], ['search', '/flights'], ['search-results', search], ['login', '/login'], ['register', '/register'], ['admin-login', '/admin/login'], ['flight-details', `/flights/show?id=${fixture.flightId}`]], 'guest');
    await command('Emulation.setDeviceMetricsOverride', { width: 375, height: 900, deviceScaleFactor: 1, mobile: false });
    await visit('/login');
    assert(await evaluate(`getComputedStyle(document.querySelector('.main-nav')).display === 'none'`), 'Mobile menu starts collapsed');
    assert(await evaluate(`document.querySelector('.menu-toggle').click(); document.querySelector('.menu-toggle').getAttribute('aria-expanded') === 'true' && getComputedStyle(document.querySelector('.main-nav')).display !== 'none'`), 'Mobile menu opens');
    assert(await evaluate(`document.dispatchEvent(new KeyboardEvent('keydown', {key:'Escape', bubbles:true})); document.querySelector('.menu-toggle').getAttribute('aria-expanded') === 'false'`), 'Escape closes menu');
    assert(await evaluate(`document.querySelector('.account-form').requestSubmit(); !!document.querySelector('[aria-invalid="true"]') && document.querySelector('.form-feedback').textContent.includes('highlighted')`), 'Native validation gives accessible feedback');
    await signIn('customer');
    await checkPages([['profile', '/profile'], ['booking-form', `/bookings/create?flight_id=${fixture.flightId}`], ['booking-summary', `/bookings/show?id=${fixture.bookingId}`], ['seat-map', `/bookings/seats?booking_id=${fixture.bookingId}`], ['payment-form', `/bookings/payment?booking_id=${fixture.bookingId}`]], 'customer');
    await visit(`/bookings/seats?booking_id=${fixture.bookingId}`);
    await evaluate(`document.querySelector('#passenger_id').selectedIndex = 1; document.querySelector('input[name="seat_id"][value="${fixture.seatId}"]').checked = true; document.querySelector('.account-form').requestSubmit(); true`);
    await ready('/bookings/show'); await pause(100);
    assert(await evaluate(`document.body.textContent.includes('2A')`), 'Native seat selection prepares passenger for ticket');
    await visit(`/bookings/payment?booking_id=${fixture.bookingId}`);
    await evaluate(`document.getElementById('method').value = 'bank_transfer'; document.getElementById('transaction_reference').value = 'UI-DEMO-REFERENCE'; document.querySelector('.account-form').requestSubmit(); true`);
    await ready('/bookings/show');
    assert(await evaluate(`document.body.textContent.includes('Awaiting Verification') && document.body.textContent.includes('UI-DEMO-REFERENCE')`), 'Native payment form works with shared loading interactions');
    await checkPages([['payment-summary', `/bookings/show?id=${fixture.bookingId}`]], 'customer');
    await visit('/profile');
    await evaluate(`document.querySelector('.logout-form').requestSubmit(); true`); await ready('/login');
    await signIn('admin');
    await checkPages([
        ['dashboard', '/admin'], ['airports', '/admin/airports'], ['airport-add', '/admin/airports/create'], ['airport-edit', `/admin/airports/edit?id=${fixture.from}`],
        ['aircraft', '/admin/aircraft'], ['aircraft-add', '/admin/aircraft/create'], ['aircraft-edit', `/admin/aircraft/edit?id=${fixture.aircraftId}`],
        ['seats', `/admin/seats?aircraft_id=${fixture.aircraftId}`], ['seat-add', `/admin/seats/create?aircraft_id=${fixture.aircraftId}`], ['seat-edit', `/admin/seats/edit?aircraft_id=${fixture.aircraftId}&id=${fixture.seatId}`],
        ['admin-flights', '/admin/flights'], ['flight-add', '/admin/flights/create'], ['flight-edit', `/admin/flights/edit?id=${fixture.flightId}`], ['admin-flight-details', `/admin/flights/show?id=${fixture.flightId}`],
    ], 'admin');
    await visit('/admin/payments');
    const paymentId = await evaluate(`(() => { const row = [...document.querySelectorAll('tbody tr')].find((row) => row.textContent.includes(${JSON.stringify(fixture.customerEmail)})); const link = row?.querySelector('a[href^="/admin/payments/show"]'); return link ? new URL(link.href).searchParams.get('id') : null; })()`);
    assert(!!paymentId, 'Submitted customer payment appears in admin list');
    await checkPages([
        ['admin-payments', '/admin/payments'], ['admin-payment-details', `/admin/payments/show?id=${paymentId}`],
        ['admin-verified-payments', '/admin/payments?status=verified'], ['admin-rejected-payments', '/admin/payments?status=rejected'],
    ], 'admin');
    assert(await evaluate(`document.querySelector('.admin-nav [aria-current="page"]').getAttribute('href') === '/admin/payments'`), 'Payment pages highlight Payments navigation');
    await visit(`/admin/payments/show?id=${paymentId}`);
    await evaluate(`window.confirm = () => true; document.querySelector('form[action^="/admin/payments/verify"]').requestSubmit(); true`);
    await ready('/admin/payments/show');
    await pause(100);
    assert(await evaluate(`document.querySelector('.page-heading .badge').textContent.trim() === 'Verified' && document.body.textContent.includes('Confirmed')`), 'Native admin verification works with shared confirmation and loading interactions');
    await checkPages([['admin-verified-details', `/admin/payments/show?id=${paymentId}`]], 'admin');
    await evaluate(`document.querySelector('form[action^="/admin/bookings/tickets"]').requestSubmit(); true`);
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
    await evaluate(`document.querySelector('#flight_id').value = '${fixture.flightId}'; document.querySelector('#booking_status').value = 'confirmed'; document.querySelector('.report-filters').requestSubmit(); true`);
    await ready('/admin/reports/bookings'); await pause(100);
    assert(await evaluate(`new URL(location.href).searchParams.get('flight_id') === '${fixture.flightId}' && !!document.querySelector('[data-report-row="${fixture.bookingId}"]') && document.querySelector('[data-report-total]').textContent.trim() === '1'`), 'Native report filters retain selection and show accurate total');
    await evaluate(`document.querySelector('.report-filters a').click(); true`); await ready('/admin/reports/bookings'); await pause(100);
    assert(await evaluate(`location.search === '' && document.querySelector('#flight_id').value === '' && document.querySelector('#booking_status').value === ''`), 'Report Reset clears URL and controls');
    await visit(`/admin/seats?aircraft_id=${fixture.aircraftId}`);
    assert(await evaluate(`document.querySelector('.admin-nav [aria-current="page"]').getAttribute('href') === '/admin/aircraft'`), 'Seat pages highlight Aircraft & seats');
    assert(await evaluate(`(() => { window.confirm = () => false; const form = document.querySelector('form[data-confirm]'); const event = new Event('submit', {bubbles:true, cancelable:true}); form.dispatchEvent(event); return event.defaultPrevented && !form.dataset.submitting; })()`), 'Cancelled delete stays on page without loading state');
    assert(await evaluate(`(() => { window.confirm = () => true; const form = document.querySelector('form[data-confirm]'); const event = new SubmitEvent('submit', {bubbles:true, cancelable:true, submitter:form.querySelector('button')}); form.dispatchEvent(event); return form.querySelector('button').getAttribute('aria-busy') === 'true'; })()`), 'Confirmed action gets loading state');
    assert(await evaluate(`window.dispatchEvent(new Event('pageshow')); !document.querySelector('button[aria-busy]')`), 'Back/Forward restores loading buttons');
    await command('Emulation.setEmulatedMedia', { features: [{ name: 'prefers-reduced-motion', value: 'reduce' }] });
    assert(await evaluate(`getComputedStyle(document.querySelector('button')).transitionDuration === '0s'`), 'Reduced-motion preference respected');
    await visit('/admin'); await evaluate(`document.querySelector('.logout-form').requestSubmit(); true`); await ready('/admin/login');
    await signIn('customer');
    await checkPages([['ticket-booking-summary', `/bookings/show?id=${fixture.bookingId}`], ['customer-ticket', `/tickets/show?id=${ticketId}`]], 'customer');
    assert(await evaluate(`window.print = () => { window.ticketPrinted = true; }; document.querySelector('[data-print-ticket]').click(); window.ticketPrinted === true`), 'Print button invokes browser printing');
    const offline = await evaluate(`(async () => { const response = await fetch('/tickets/download?id=${ticketId}'); return { status: response.status, disposition: response.headers.get('content-disposition'), html: await response.text() }; })()`);
    assert(offline.status === 200 && offline.disposition.includes('.html') && offline.html.includes('Preview Passenger') && offline.html.includes('@media print') && !offline.html.includes('<script'), 'Customer downloads a self-contained printable ticket');
    writeFileSync(join(artifacts, 'ticket-download.html'), offline.html);
    await checkPages([['my-bookings', '/bookings'], ['my-bookings-empty', '/bookings?status=cancelled'], ['cancellation-form', `/bookings/cancel?booking_id=${fixture.bookingId}`]], 'customer');
    assert(await evaluate(`(() => { window.confirm = () => false; const form = document.querySelector('form[data-confirm]'); const event = new SubmitEvent('submit', { bubbles: true, cancelable: true, submitter: form.querySelector('button') }); form.dispatchEvent(event); return event.defaultPrevented && !form.dataset.submitting; })()`), 'Customer can dismiss cancellation confirmation');
    await evaluate(`window.confirm = () => true; document.querySelector('#reason').value = 'Travel plans changed'; document.querySelector('form[data-confirm]').requestSubmit(); true`);
    await ready('/bookings/show'); await pause(100);
    assert(await evaluate(`document.body.textContent.includes('Cancellation Requested')`), 'Native cancellation request updates summary');
    await checkPages([['cancellation-requested-summary', `/bookings/show?id=${fixture.bookingId}`], ['my-bookings-requested', '/bookings?status=cancellation_requested']], 'customer');
    await visit('/profile'); await evaluate(`document.querySelector('.logout-form').requestSubmit(); true`); await ready('/login');
    await signIn('admin'); await visit('/admin/cancellations');
    const cancellationId = await evaluate(`(() => { const row = [...document.querySelectorAll('tbody tr')].find((row) => row.textContent.includes(${JSON.stringify(fixture.customerEmail)})); return new URL(row.querySelector('a').href).searchParams.get('id'); })()`);
    await checkPages([['admin-cancellations', '/admin/cancellations'], ['admin-cancellation-details', `/admin/cancellations/show?id=${cancellationId}`], ['admin-approved-cancellations-empty', '/admin/cancellations?status=approved'], ['admin-rejected-cancellations-empty', '/admin/cancellations?status=rejected']], 'admin');
    assert(await evaluate(`document.querySelector('.admin-nav [aria-current="page"]').getAttribute('href') === '/admin/cancellations'`), 'Cancellation pages highlight admin navigation');
    await visit(`/admin/cancellations/show?id=${cancellationId}`);
    await evaluate(`window.confirm = () => true; document.querySelector('#note').value = 'Retain booking'; const form = document.querySelector('.account-form'); form.requestSubmit(form.querySelector('button[formaction]')); true`);
    await ready('/admin/cancellations/show'); await pause(100);
    assert(await evaluate(`document.querySelector('.page-heading .badge').textContent.trim() === 'Rejected' && document.body.textContent.includes('Confirmed')`), 'Native rejection restores booking through submitter formaction');
    await checkPages([['admin-rejected-cancellation-details', `/admin/cancellations/show?id=${cancellationId}`]], 'admin');
    await visit('/admin'); await evaluate(`document.querySelector('.logout-form').requestSubmit(); true`); await ready('/admin/login'); await signIn('customer');
    await visit(`/bookings/cancel?booking_id=${fixture.bookingId}`); await evaluate(`window.confirm = () => true; document.querySelector('form[data-confirm]').requestSubmit(); true`); await ready('/bookings/show'); await pause(100);
    await visit('/profile'); await evaluate(`document.querySelector('.logout-form').requestSubmit(); true`); await ready('/login'); await signIn('admin'); await visit('/admin/cancellations');
    const retryId = await evaluate(`(() => { const row = [...document.querySelectorAll('tbody tr')].find((row) => row.textContent.includes(${JSON.stringify(fixture.customerEmail)})); return new URL(row.querySelector('a').href).searchParams.get('id'); })()`);
    await visit(`/admin/cancellations/show?id=${retryId}`); await evaluate(`window.confirm = () => true; const form = document.querySelector('.account-form'); form.requestSubmit(form.querySelector('button[type="submit"]')); true`); await ready('/admin/cancellations/show'); await pause(100);
    assert(await evaluate(`document.querySelector('.page-heading .badge').textContent.trim() === 'Approved' && document.body.textContent.includes('Cancelled') && document.body.textContent.includes('Released')`), 'Native approval cancels booking and releases seat');
    await checkPages([['admin-approved-cancellation-details', `/admin/cancellations/show?id=${retryId}`]], 'admin');
    await checkPages([['report-cancellation-history', `/admin/reports/cancellations?flight_id=${fixture.flightId}`]], 'admin');
    assert(await evaluate(`document.querySelector('[data-report-total]').textContent.trim() === '2' && document.querySelector('.report-table').textContent.includes('Approved') && document.querySelector('.report-table').textContent.includes('Rejected')`), 'Cancellation report reflects reviewed history without duplicate joins');
    await visit('/admin'); await evaluate(`document.querySelector('.logout-form').requestSubmit(); true`); await ready('/admin/login'); await signIn('customer');
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
