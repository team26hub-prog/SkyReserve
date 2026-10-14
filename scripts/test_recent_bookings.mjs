export async function checkRecentBookings({ command, evaluate, assert, visit, bookingId, flightId, screenshot }) {
    await visit('/admin');
    assert(await evaluate(`(() => {
        const section = document.querySelector('.recent-bookings');
        const rows = section.querySelectorAll('[data-recent-booking]');
        const booking = section.querySelector('[data-recent-booking="${bookingId}"]');
        return rows.length > 0 && rows.length <= 10 && !!booking && booking.children.length === 6
            && !!booking.querySelector('time[datetime$="Z"]')
            && !!booking.querySelector('.recent-booking-customer .cell-note')
            && !!booking.querySelector('.recent-passenger-list')
            && booking.querySelectorAll('.badge[data-status]').length === 2
            && !!booking.querySelector('a[href="/admin/reports/passengers?flight_id=${flightId}"]')
            && !!section.querySelector('a[href="/admin/reports/bookings"]')
            && document.querySelector('.dashboard-review-summary').nextElementSibling === section;
    })()`), 'Recent bookings show requested fields, status badges and existing report links below dashboard review area');
    for (const width of [320, 375, 768, 1024, 1440]) {
        await command('Emulation.setDeviceMetricsOverride', { width, height: 1000, deviceScaleFactor: 1, mobile: false });
        assert(await evaluate(`(() => {
            const table = document.querySelector('.recent-bookings-table');
            const section = document.querySelector('.recent-bookings');
            const rect = section.getBoundingClientRect();
            const rows = [...table.querySelectorAll('tbody tr')];
            const display = getComputedStyle(rows[0]).display;
            const scroller = section.querySelector('.recent-bookings-scroll');
            const needsScroll = scroller.scrollWidth > scroller.clientWidth;
            scroller.scrollLeft = scroller.scrollWidth;
            const lastCell = rows[0].lastElementChild.getBoundingClientRect();
            const scrollBounds = scroller.getBoundingClientRect();
            const canSeeLastColumn = lastCell.right <= scrollBounds.right + 1 && lastCell.left >= scrollBounds.left;
            const scrolled = !needsScroll || scroller.scrollLeft > 0;
            scroller.scrollLeft = 0;
            return rect.left >= 0 && rect.right <= innerWidth + 1 && section.scrollWidth <= section.clientWidth + 1
                && document.documentElement.scrollWidth <= innerWidth + 1
                && display === 'table-row' && getComputedStyle(table.querySelector('thead')).position !== 'absolute'
                && scrolled && canSeeLastColumn && (${width} > 800 || needsScroll)
                && scroller.tabIndex === 0 && scroller.getAttribute('role') === 'region';
        })()`), `Recent bookings keep a table at ${width}px with horizontal scrolling to the final column and no page overflow`);
        const tree = await command('Accessibility.getFullAXTree');
        assert(tree.nodes.some(node => !node.ignored && node.role?.value === 'table')
            && tree.nodes.some(node => !node.ignored && node.role?.value === 'columnheader' && node.name?.value?.toLowerCase() === 'customer'),
        `Recent bookings preserve accessible table headers at ${width}px: ${JSON.stringify(tree.nodes.filter(node => ['table', 'columnheader'].includes(node.role?.value)).map(node => ({role: node.role?.value, name: node.name?.value, ignored: node.ignored})))}`);
        await evaluate(`document.querySelector('.recent-bookings').scrollIntoView({block:'start', behavior:'instant'}); window.scrollBy({top: -document.querySelector('.site-header').getBoundingClientRect().height - 16, behavior:'instant'}); true`);
        if (screenshot && [375, 1440].includes(width)) await screenshot(`recent-bookings-${width}`);
    }
    await command('Input.dispatchMouseEvent', { type: 'mouseMoved', x: 0, y: 0 });
    const row = await evaluate(`(() => {
        const row = document.querySelector('.recent-bookings-table tbody tr');
        row.scrollIntoView({block:'center', behavior:'instant'});
        const rect = row.getBoundingClientRect();
        return { x: rect.left + rect.width / 2, y: rect.top + Math.min(rect.height / 2, 40), background: getComputedStyle(row).backgroundColor };
    })()`);
    await command('Input.dispatchMouseEvent', { type: 'mouseMoved', x: row.x, y: row.y });
    assert(await evaluate(`getComputedStyle(document.querySelector('.recent-bookings-table tbody tr')).backgroundColor !== ${JSON.stringify(row.background)}`), 'Recent booking rows have a subtle hover highlight');
    assert(await evaluate(`(() => {
        const link = document.querySelector('.recent-booking-action');
        link.focus();
        return document.activeElement === link && link.getAttribute('aria-label').includes('booking') && link.getBoundingClientRect().height >= 44;
    })()`), 'Recent booking actions have keyboard focus, distinct accessible names and usable touch targets');
    await visit(`/admin/reports/passengers?flight_id=${flightId}`);
    assert(await evaluate(`document.querySelector('#flight_id').value === '${flightId}' && document.body.textContent.includes('Passenger List')`), 'Recent booking passenger link opens the existing filtered admin report');
}
