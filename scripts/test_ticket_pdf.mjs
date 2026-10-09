import {existsSync, readFileSync, renameSync, rmSync, writeFileSync} from 'node:fs';
import {join} from 'node:path';

export async function checkTicketPdf({command,evaluate,assert,visit,artifacts,ticketId,role}) {
    const prefix = role === 'admin' ? '/admin' : '';
    await command('Browser.setDownloadBehavior', {behavior: 'allow', downloadPath: artifacts});
    for (const width of [375,1440]) {
        await command('Emulation.setDeviceMetricsOverride', {width,height:1000,deviceScaleFactor:1,mobile:false});
        await visit(`${prefix}/tickets/show?id=${ticketId}`);
        assert(await evaluate(`(() => { const link=document.querySelector('.ticket-actions a[download]'); return link.textContent === 'Download PDF' && link.download.endsWith('.pdf') && !document.querySelector('.ticket-actions').textContent.includes('HTML'); })()`), `${role} PDF button and guidance at ${width}px`);
        assert(await evaluate(`document.documentElement.scrollWidth <= innerWidth+1 && [...document.querySelectorAll('.ticket-actions a,.ticket-actions button')].every(button=>{const rect=button.getBoundingClientRect();return rect.left>=0 && rect.right<=innerWidth+1 && rect.height>=44;})`), `${role} ticket buttons fit with accessible tap targets at ${width}px`);
        assert(await evaluate(`window.print=()=>{window.printInvoked=true;};document.querySelector('[data-print-ticket]').click();window.printInvoked===true`), `${role} Print ticket still opens browser printing`);
        assert(await evaluate(`Promise.all([...document.querySelectorAll('.ticket-document img')].map(image=>image.decode().then(()=>image.naturalWidth>0))).then(results=>results.length===3 && results.every(Boolean))`), `${role} logo, airplane and PNR barcode load at ${width}px`);
        assert(await evaluate(`document.querySelectorAll('.ticket-status').length===2 && document.querySelectorAll('.ticket-route-time').length===2 && document.querySelector('.ticket-footer').textContent.includes('not a boarding pass')`), `${role} route times, badges and travel reminders render at ${width}px`);
        const screenshot=await command('Page.captureScreenshot',{captureBeyondViewport:true});
        writeFileSync(join(artifacts,`${role}-pdf-actions-${width}.png`),Buffer.from(screenshot.data,'base64'));
        const filename=join(artifacts,`SkyReserve-ticket-${ticketId}.pdf`);
        if(existsSync(filename)) rmSync(filename);
        await evaluate(`document.querySelector('.ticket-actions a[download]').click();true`);
        for(let attempt=0;attempt<100 && !existsSync(filename);attempt++) await new Promise(resolve=>setTimeout(resolve,100));
        assert(existsSync(filename), `${role} clicking Download PDF saves a file to the device at ${width}px`);
        const pdf=readFileSync(filename);
        assert(pdf.subarray(0,5).toString()==='%PDF-' && pdf.length>1000 && (pdf.toString('latin1').match(/\/Type\s*\/Page\b/g)||[]).length===1, `${role} downloaded file is a complete one-page PDF at ${width}px`);
        assert(await evaluate(`location.pathname === ${JSON.stringify(prefix+'/tickets/show')}`), 'Download keeps the ticket page open');
        renameSync(filename,join(artifacts,`${role}-ticket-download-${width}.pdf`));
        await command('Emulation.setEmulatedMedia',{media:'print'});
        try {
            assert(await evaluate(`[...document.querySelectorAll('.site-header,.back-navigation,.mobile-quick-nav,.ticket-actions,.back-to-top,.toast-region')].every(element=>getComputedStyle(element).display==='none')`), `${role} print hides browser-only controls at ${width}px`);
            const printed=await command('Page.printToPDF',{preferCSSPageSize:true,printBackground:true,displayHeaderFooter:false});
            const bytes=Buffer.from(printed.data,'base64');
            assert((bytes.toString('latin1').match(/\/Type\s*\/Page\b/g)||[]).length===1, `${role} browser prints one A4 page at ${width}px`);
            writeFileSync(join(artifacts,`${role}-ticket-print-${width}.pdf`),bytes);
        } finally { await command('Emulation.setEmulatedMedia',{media:''}); }
    }
}
