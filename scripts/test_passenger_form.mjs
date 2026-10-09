// Browser checks against the actual passenger form; no booking is committed here.
export async function checkPassengerForm({ command, evaluate, assert, visit, respondToConfirmation, flightId }) {
    const key = async (key, code, windowsVirtualKeyCode) => {
        await command('Input.dispatchKeyEvent', { type: 'keyDown', key, code, windowsVirtualKeyCode, ...(key === 'Enter' ? {text: '\r'} : {}) });
        await command('Input.dispatchKeyEvent', { type: 'keyUp', key, code, windowsVirtualKeyCode });
    };
    const input = async (id, value) => evaluate(`(() => { const field = document.getElementById(${JSON.stringify(id)}); field.focus(); field.value = ${JSON.stringify(value)}; field.dispatchEvent(new Event('input', {bubbles: true})); return true; })()`);
    for (const width of [375, 1440]) {
        await command('Emulation.setDeviceMetricsOverride', { width, height: 1000, deviceScaleFactor: 1, mobile: false });
        await visit(`/bookings/create?flight_id=${flightId}`);
        assert(await evaluate(`!!document.querySelector('form[data-passenger-form]') && document.querySelector('#cnic').required && !document.querySelector('#passport_number').required && document.querySelector('#cnic').inputMode === 'numeric'`), `Separate required CNIC and optional passport at ${width}px`);
        await input('full_name', 'Passenger Test');
        await key('Enter', 'Enter', 13);
        assert(await evaluate(`document.activeElement.id === 'cnic' && !document.querySelector('#confirmation-dialog').open`), `Enter moves Full Name to CNIC without submitting at ${width}px`);
        for (const digit of '342021234567') await command('Input.insertText', { text: digit });
        assert(await evaluate(`document.querySelector('#cnic').value === '34202-1234567' && document.activeElement.id === 'cnic' && !document.querySelector('#cnic').validity.valid`), `Incomplete CNIC auto-formats and stays invalid at ${width}px`);
        await command('Input.insertText', { text: '1' });
        assert(await evaluate(`document.querySelector('#cnic').value === '34202-1234567-1' && document.querySelector('#cnic').validity.valid && document.activeElement.id === 'passport_number'`), `Thirteenth digit completes CNIC and focuses Passport at ${width}px`);
        await input('cnic', '34202123456719999');
        assert(await evaluate(`document.querySelector('#cnic').value === '34202-1234567-1'`), `CNIC caps input at thirteen digits at ${width}px`);
        await input('cnic', 'a34202-b1234567-c1');
        assert(await evaluate(`document.querySelector('#cnic').value === '34202-1234567-1'`), `CNIC removes nondigits and formats pasted input at ${width}px`);
        await input('cnic', '');
        await command('Input.insertText', { text: '34202-1234567-1' });
        assert(await evaluate(`document.activeElement.id === 'passport_number' && document.querySelector('#cnic').value === '34202-1234567-1'`), `Formatted CNIC paste completes correctly at ${width}px`);
        await evaluate(`document.querySelector('#cnic').focus(); true`);
        await key('Enter', 'Enter', 13);
        assert(await evaluate(`document.activeElement.id === 'passport_number'`), `Enter moves CNIC to Passport at ${width}px`);
        await input('passport_number', ' ab1234567 ');
        assert(await evaluate(`document.querySelector('#passport_number').value === 'AB1234567' && document.querySelector('#passport_number').validity.valid`), `Passport trims spaces and converts letters to uppercase at ${width}px`);
        for (const invalid of ['AB12@456', 'AB 123456', 'AB123', 'AB123456é']) {
            await input('passport_number', invalid);
            assert(await evaluate(`!document.querySelector('#passport_number').validity.valid`), `Passport rejects ${invalid} at ${width}px`);
        }
        await input('passport_number', '');
        assert(await evaluate(`document.querySelector('#passport_number').validity.valid`), `Blank passport is valid at ${width}px`);
        await key('Enter', 'Enter', 13);
        assert(await evaluate(`document.activeElement.id === 'date_of_birth'`), `Enter skips optional blank Passport to Date of Birth at ${width}px`);
        await input('date_of_birth', '1990-01-01');
        await key('Enter', 'Enter', 13);
        assert(await evaluate(`document.activeElement.id === 'gender'`), `Enter moves Date of Birth to Gender at ${width}px`);
        await evaluate(`document.querySelector('#gender').value = 'female'; true`);
        await key('Enter', 'Enter', 13);
        assert(await evaluate(`document.activeElement.id === 'phone'`), `Enter moves Gender to Phone at ${width}px`);
        await input('phone', '+923001234567');
        await key('Enter', 'Enter', 13);
        assert(await evaluate(`document.activeElement.matches('form[data-passenger-form] button[type="submit"]') && !document.querySelector('#confirmation-dialog').open && document.querySelector('form[data-passenger-form]').checkValidity()`), `Phone Enter focuses Create booking without submitting at ${width}px`);
        await evaluate(`document.querySelector('#full_name').focus(); true`);
        await key('Tab', 'Tab', 9);
        assert(await evaluate(`document.activeElement.id === 'cnic'`), `Normal Tab navigation remains intact at ${width}px`);
        // Exercise the existing submit confirmation, then cancel to keep fixtures unchanged.
        await evaluate(`document.querySelector('form[data-passenger-form] button[type="submit"]').focus(); true`);
        await key('Enter', 'Enter', 13);
        const submitState = await evaluate(`({open: document.querySelector('#confirmation-dialog').open, active: document.activeElement.outerHTML, valid: document.querySelector('form[data-passenger-form]').checkValidity(), confirm: document.querySelector('form[data-passenger-form]').dataset.confirm})`);
        assert(submitState.open, `Create booking still works from the keyboard at ${width}px: ${JSON.stringify(submitState)}`);
        await respondToConfirmation(false);
    }
}
