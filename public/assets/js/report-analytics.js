// Local SVG charts: no dependencies, network requests, canvas or business mutations.
(() => {
    const source = document.getElementById('analytics-data');
    if (!source) return;
    const data = JSON.parse(source.textContent);
    const colors = ['#1C768F', '#FA991C', '#032539', '#527B65', '#9C3030', '#7A6B8F'];
    const svgElement = (tag, attributes = {}, text) => {
        const element = document.createElementNS('http://www.w3.org/2000/svg', tag);
        for (const [name, value] of Object.entries(attributes)) element.setAttribute(name, String(value));
        if (text !== undefined) element.textContent = String(text);
        return element;
    };
    const number = value => new Intl.NumberFormat('en', {maximumFractionDigits: 2}).format(value);
    const compact = value => new Intl.NumberFormat('en', {notation: 'compact', maximumFractionDigits: 1}).format(value);
    const dateLabel = bucket => {
        const date = new Date((bucket.length === 7 ? bucket + '-01' : bucket) + 'T00:00:00Z');
        return new Intl.DateTimeFormat('en', {timeZone: 'UTC', month: 'short', ...(data.monthly ? {year: '2-digit'} : {day: 'numeric'})}).format(date);
    };
    const render = container => {
        const width = Math.max(220, Math.round(container.getBoundingClientRect().width));
        const height = 260;
        const svg = svgElement('svg', {viewBox: `0 0 ${width} ${height}`, width: '100%', height, focusable: 'false'});
        const label = (x, y, text, anchor = 'start', extra = {}) => svg.append(svgElement('text', {x, y, 'text-anchor': anchor, class: 'chart-axis-label', ...extra}, text));
        if (container.dataset.chart === 'statuses') {
            const total = data.statuses.reduce((sum, row) => sum + row.count, 0);
            const cx = width / 2, cy = height / 2, radius = 90, circumference = 2 * Math.PI * radius;
            let offset = 0;
            svg.append(svgElement('circle', {cx, cy, r: radius, fill: 'none', stroke: '#e8f3f5', 'stroke-width': 30}));
            data.statuses.forEach((row, index) => {
                if (!row.count) return;
                const length = row.count / total * circumference;
                const segment = svgElement('circle', {cx, cy, r: radius, fill: 'none', stroke: colors[index], 'stroke-width': 30, 'stroke-dasharray': `${length} ${circumference - length}`, 'stroke-dashoffset': -offset, transform: `rotate(-90 ${cx} ${cy})`, 'data-status': row.status});
                segment.append(svgElement('title', {}, `${row.label}: ${number(row.count)} bookings`));
                svg.append(segment);
                offset += length;
            });
            label(cx, cy, number(total), 'middle', {class: 'chart-total'});
            label(cx, cy + 26, 'Total bookings', 'middle');
        } else if (container.dataset.chart === 'routes') {
            const left = 100, right = 45, top = 20, rowHeight = 44;
            const maximum = Math.max(...data.routes.map(row => row.count), 1);
            data.routes.forEach((row, index) => {
                const y = top + index * rowHeight;
                label(0, y + 20, `${row.origin} → ${row.destination}`);
                const bar = svgElement('rect', {x: left, y, width: Math.max(1, (width - left - right) * row.count / maximum), height: 30, rx: 6, fill: colors[index % colors.length], 'data-route-count': row.count});
                bar.append(svgElement('title', {}, `${row.origin} → ${row.destination}: ${number(row.count)} bookings`));
                svg.append(bar);
                label(width - 2, y + 20, number(row.count), 'end');
            });
        } else {
            const revenue = container.dataset.chart === 'revenue';
            const series = revenue ? data.revenue[Number(container.dataset.series)] : null;
            const points = revenue ? series.points : data.bookings;
            const values = points.map(point => Number(revenue ? point.amount : point.count));
            const rawMax = Math.max(...values, 1);
            const maximum = revenue ? rawMax * 1.1 : Math.max(2, Math.ceil(rawMax / 2) * 2);
            const left = 45, right = 18, top = 20, bottom = 45;
            const plotWidth = width - left - right, plotHeight = height - top - bottom;
            const x = index => left + index * plotWidth / Math.max(1, points.length - 1);
            const y = value => top + plotHeight * (1 - value / maximum);
            for (let index = 0; index <= 2; index++) {
                const value = maximum * index / 2;
                svg.append(svgElement('line', {x1: left, x2: width - right, y1: y(value), y2: y(value), stroke: '#dce3e5', 'stroke-dasharray': '3 4'}));
                label(left - 8, y(value) + 4, compact(value), 'end');
            }
            const coordinates = values.map((value, index) => `${x(index)},${y(value)}`).join(' ');
            svg.append(svgElement('polygon', {points: `${left},${y(0)} ${coordinates} ${x(points.length - 1)},${y(0)}`, fill: revenue ? '#fff1dc' : '#e8f3f5'}));
            svg.append(svgElement('polyline', {points: coordinates, fill: 'none', stroke: revenue ? colors[1] : colors[0], 'stroke-width': 3, 'stroke-linejoin': 'round'}));
            points.forEach((point, index) => {
                const dot = svgElement('circle', {cx: x(index), cy: y(values[index]), r: 3.5, fill: revenue ? colors[1] : colors[0], 'data-value': revenue ? point.amount : point.count});
                dot.append(svgElement('title', {}, `${point.bucket}: ${revenue ? series.currency + ' ' + number(values[index]) : number(values[index]) + ' bookings'}`));
                svg.append(dot);
            });
            const tickCount = width < 400 ? 3 : 5;
            for (let tick = 0; tick < tickCount; tick++) {
                const index = Math.round(tick * (points.length - 1) / (tickCount - 1));
                label(x(index), height - 16, dateLabel(points[index].bucket), tick === 0 ? 'start' : tick === tickCount - 1 ? 'end' : 'middle');
            }
        }
        container.replaceChildren(svg);
    };
    document.querySelectorAll('.analytics-chart[data-chart]').forEach(container => {
        render(container);
        if ('ResizeObserver' in window) {
            let previousWidth = container.clientWidth;
            new ResizeObserver(() => {
                if (previousWidth === container.clientWidth) return;
                previousWidth = container.clientWidth;
                render(container);
            }).observe(container);
        }
    });
})();
