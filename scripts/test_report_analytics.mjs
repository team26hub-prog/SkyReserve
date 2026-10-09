// Report landing-page checks against live admin data and the real empty-state view.
export async function checkReportAnalytics({ command, evaluate, assert, visit, waitFor, emptyHtml, screenshot }) {
    for (const width of [375, 768, 1440]) {
        await command('Emulation.setDeviceMetricsOverride', {width, height: 1000, deviceScaleFactor: 1, mobile: false});
        await visit('/admin/reports');
        await waitFor(`document.querySelectorAll('.analytics-chart svg').length >= 4`, 'Analytics SVG charts finish rendering');
        assert(await evaluate(`(() => { const cards = document.querySelector('.management-links'); const overview = document.querySelector('.analytics-overview'); return cards.compareDocumentPosition(overview) & Node.DOCUMENT_POSITION_FOLLOWING; })()`), `Analytics follows existing report cards at ${width}px`);
        assert(await evaluate(`(() => { const data = JSON.parse(document.querySelector('#analytics-data').textContent); return data.period === '30' && data.bookings.length === 30 && data.statuses.length === 6 && data.routes.length <= 5; })()`), `Chart payload includes statuses, 30 zero-filled dates and top routes at ${width}px`);
        assert(await evaluate(`(() => { const data = JSON.parse(document.querySelector('#analytics-data').textContent); return document.querySelectorAll('[data-chart="revenue"]').length === data.revenue.length && data.revenue.every((series,index) => document.querySelector('[data-chart="revenue"][data-series="'+index+'"]').closest('.revenue-series').querySelector('h4').textContent === series.currency); })()`), `Every revenue currency has a separate chart at ${width}px`);
        assert(await evaluate(`document.documentElement.scrollWidth <= innerWidth + 1 && [...document.querySelectorAll('.analytics-chart svg')].every(svg => { const rect = svg.getBoundingClientRect(); return rect.left >= 0 && rect.right <= innerWidth + 1 && rect.width > 200; })`), `All charts fit without horizontal page overflow at ${width}px`);
        if (width === 1440) {
            assert(await evaluate(`(() => { const cards = [...document.querySelectorAll('.analytics-card')]; return [0,2].every(index => Math.abs(cards[index].getBoundingClientRect().height - cards[index+1].getBoundingClientRect().height) < 1 && Math.abs(cards[index].querySelector('.analytics-chart').getBoundingClientRect().top - cards[index+1].querySelector('.analytics-chart').getBoundingClientRect().top) < 1); })()`), 'Desktop chart cards share row heights and aligned chart areas');
            assert(await evaluate(`(() => { const cards = [...document.querySelectorAll('.analytics-card')]; return Math.abs(cards[2].querySelector('.chart-data summary').getBoundingClientRect().bottom - cards[3].querySelector('.chart-data summary').getBoundingClientRect().bottom) < 1; })()`), 'Revenue and route data controls align at the card bottom');
        }
        assert(await evaluate(`(() => { const data = JSON.parse(document.querySelector('#analytics-data').textContent); const circles = [...document.querySelectorAll('[data-chart="bookings"] circle[data-value]')]; return circles.length === data.bookings.length && circles.every((circle,index) => Number(circle.dataset.value) === data.bookings[index].count); })()`), `Booking plot reflects actual aggregate data at ${width}px`);
        assert(await evaluate(`[...document.querySelectorAll('.analytics-chart circle[data-value]')].every(dot => !!dot.querySelector('title')) && document.querySelectorAll('.chart-data table').length >= 3 && document.querySelectorAll('.chart-legend li').length === 6`), `Readable chart legends, precise tooltips and accessible data tables at ${width}px`);
        if (width !== 768) await screenshot(`report-analytics-${width}.png`);
        await evaluate(`document.querySelector('.chart-data summary').click(); true`);
        assert(await evaluate(`document.querySelector('.chart-data').open && document.querySelector('.chart-data tbody tr').getBoundingClientRect().height > 0`), `Chart data table opens with native accessible control at ${width}px`);
    }
    for (const period of ['7', '12m']) {
        await evaluate(`document.querySelector('#analytics-period').value=${JSON.stringify(period)}; document.querySelector('#analytics-period').dispatchEvent(new Event('change',{bubbles:true})); true`);
        await waitFor(`document.readyState === 'complete' && new URL(location.href).searchParams.get('period') === ${JSON.stringify(period)} && JSON.parse(document.querySelector('#analytics-data').textContent).period === ${JSON.stringify(period)}`, 'Period selector automatically updates charts');
        assert(await evaluate(`JSON.parse(document.querySelector('#analytics-data').textContent).bookings.length === ${period === '7' ? 7 : 12}`), `Period ${period} uses expected daily/monthly buckets`);
    }
    await visit('/admin/reports?period[]=7');
    assert(await evaluate(`!!document.querySelector('.notice.error') && !document.querySelector('#analytics-data') && document.querySelectorAll('.management-links a').length === 5`), 'Invalid analytics filter keeps report cards and exposes no chart data');
    for (const width of [375, 1440]) {
        await command('Emulation.setDeviceMetricsOverride', {width, height: 1000, deviceScaleFactor: 1, mobile: false});
        await visit('/admin/reports');
        const tree = await command('Page.getFrameTree');
        await command('Page.setDocumentContent', {frameId: tree.frameTree.frame.id, html: emptyHtml});
        await waitFor(`document.querySelectorAll('.chart-empty').length === 4`, 'All empty chart states render');
        assert(await evaluate(`document.querySelectorAll('.chart-empty').length === 4 && !document.querySelector('.analytics-chart') && document.querySelectorAll('.management-links a').length === 5 && document.documentElement.scrollWidth <= innerWidth + 1`), `Professional empty states retain cards without misleading zero charts at ${width}px`);
        await screenshot(`report-analytics-empty-${width}.png`);
    }
    await command('Emulation.setScriptExecutionDisabled', {value: true});
    try {
        await visit('/admin/reports');
        assert(await evaluate(`document.querySelector('.chart-legend').textContent.trim().length > 0 && document.querySelectorAll('.chart-data table').length >= 3 && document.querySelector('.analytics-period noscript button').getBoundingClientRect().height >= 44`), 'Chart data and period submission remain available without JavaScript');
    } finally { await command('Emulation.setScriptExecutionDisabled', {value: false}); }
}
