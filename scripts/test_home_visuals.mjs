import {writeFileSync} from 'node:fs';
import {join} from 'node:path';

export async function checkHomeVisuals({command,evaluate,assert,visit,checkPages,artifacts,role}) {
    await checkPages([[`home-visuals-${role}`, '/']], role);
    const expected = role==='customer'
        ? ['/flights','/bookings','/bookings?section=seats','/bookings?section=payments','/bookings?section=payments&status=payment_submitted','/bookings?section=tickets']
        : role==='admin' ? ['/flights','/admin/flights','/admin/aircraft','/admin/payments','/admin/payments?status=pending','/admin/payments?status=verified']
        : ['/flights','/login','/login','/login','/login','/login'];
    assert(await evaluate(`JSON.stringify([...document.querySelectorAll('.booking-journey li>a')].map(link=>link.getAttribute('href')))===${JSON.stringify(JSON.stringify(expected))}`), `${role} journey retains existing role-specific destinations`);
    assert(await evaluate(`document.querySelectorAll('.feature-heading .home-icon').length===3 && document.querySelectorAll('.benefit-icon .home-icon').length===4 && [...document.querySelectorAll('.home-icon use,.destination-art use')].every(symbol=>!!document.querySelector(symbol.getAttribute('href')))`), `${role} reused travel symbols resolve without external assets`);
    assert(await evaluate(`JSON.stringify([...document.querySelectorAll('.destination-card h3')].map(node=>node.textContent))===JSON.stringify(['Lahore','Karachi','Islamabad','Dubai','Jeddah','Istanbul']) && [...document.querySelectorAll('.destination-card')].every(link=>link.getAttribute('href')==='/flights')`), `${role} six destination cards retain Search Flights links`);
    for(const width of [375,768,1440]) {
        await command('Emulation.setDeviceMetricsOverride',{width,height:1000,deviceScaleFactor:1,mobile:false});
        await visit('/');
        assert(await evaluate(`getComputedStyle(document.querySelector('.destination-grid')).gridTemplateColumns.split(' ').length===${width<=600?1:width<=1000?2:3}`), `${role} destination grid has correct columns at ${width}px`);
        assert(await evaluate(`Promise.all([...document.querySelectorAll('.destination-art img')].map(image=>{image.loading='eager';return image.decode().then(()=>image.naturalWidth>0 && !!image.alt && getComputedStyle(image).objectFit==='cover').catch(()=>false)})).then(results=>results.length===6 && results.every(Boolean))`), `${role} six local photos load with alt text and proportional cropping at ${width}px`);
        assert(await evaluate(`document.documentElement.scrollWidth<=innerWidth+1 && [...document.querySelectorAll('.destination-card,.booking-journey li>a')].every(link=>{const box=link.getBoundingClientRect();return box.left>=0 && box.right<=innerWidth+1 && box.height>=44})`), `${role} cards and journey stay in bounds at ${width}px`);
        assert(await evaluate(`(() => {const links=[...document.querySelectorAll('.booking-journey li>a')];return innerWidth>600 || links.every((link,index)=>!index || link.getBoundingClientRect().top>links[index-1].getBoundingClientRect().bottom)})()`), `${role} mobile journey follows a vertical reading order at ${width}px`);
        const screenshot=await command('Page.captureScreenshot',{captureBeyondViewport:true});
        writeFileSync(join(artifacts,`home-visuals-${role}-${width}.png`),Buffer.from(screenshot.data,'base64'));
    }
    await evaluate(`document.querySelector('.destination-card').focus();true`);
    await command('Input.dispatchKeyEvent',{type:'keyDown',key:'Tab',code:'Tab',windowsVirtualKeyCode:9});
    await command('Input.dispatchKeyEvent',{type:'keyUp',key:'Tab',code:'Tab',windowsVirtualKeyCode:9});
    assert(await evaluate(`document.activeElement===document.querySelectorAll('.destination-card')[1] && document.activeElement.matches(':focus-visible')`), `${role} destination cards support keyboard focus and Tab order`);
    await command('Emulation.setEmulatedMedia',{features:[{name:'prefers-reduced-motion',value:'reduce'}]});
    try {
        assert(await evaluate(`[...document.querySelectorAll('.journey-arrow .home-icon,.home-flight-divider path')].every(element=>getComputedStyle(element).animationName==='none')`), `${role} flight-path animation respects reduced motion`);
    } finally {await command('Emulation.setEmulatedMedia',{features:[]});}
}
