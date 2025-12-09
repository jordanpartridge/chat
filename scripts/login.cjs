const { chromium } = require('playwright');

const url = process.argv[2] || 'http://chat.test';
const email = process.argv[3] || 'dev@test.com';
const password = process.argv[4] || 'password';

(async () => {
    const browser = await chromium.launch({ headless: false });
    const page = await browser.newPage();

    await page.goto(url + '/login');
    await page.fill('input[type="email"], input[name="email"]', email);
    await page.fill('input[type="password"], input[name="password"]', password);
    await page.click('button[type="submit"]');

    await page.waitForURL('**/dashboard**');

    const finalUrl = page.url();
    console.log('Logged in as ' + email + ' - now on ' + finalUrl);
})();
