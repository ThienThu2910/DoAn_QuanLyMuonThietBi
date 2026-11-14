const baseUrl = process.env.ALT_F4_BASE_URL || 'http://127.0.0.1:18080';
const credentials = {
  email: process.env.ALT_F4_ADMIN_EMAIL,
  password: process.env.ALT_F4_ADMIN_PASSWORD,
};
const returnCases = [
  { name: 'damaged return moves device to maintenance', id: process.env.ALT_F4_DAMAGED_REQUEST_ID, condition: 'damaged' },
  { name: 'incomplete return moves device to maintenance', id: process.env.ALT_F4_INCOMPLETE_REQUEST_ID, condition: 'incomplete' },
];

if (!credentials.email || !credentials.password || returnCases.some(test => !test.id)) {
  console.error('Set admin credentials and both QA return request IDs in environment variables.');
  process.exit(2);
}

const userSession = { cookie: '' };
async function request(path, options = {}) {
  const headers = new Headers(options.headers || {});
  if (userSession.cookie) headers.set('Cookie', userSession.cookie);
  const response = await fetch(new URL(path, baseUrl), { ...options, headers, redirect: 'manual' });
  const cookie = response.headers.get('set-cookie')?.match(/PHPSESSID=[^;]+/)?.[0];
  if (cookie) userSession.cookie = cookie;
  return response;
}

try {
  const loginPage = await request('/login.php');
  const loginHtml = await loginPage.text();
  const loginToken = loginHtml.match(/name="csrf_token" value="([^"]+)"/)?.[1];
  if (loginPage.status !== 200 || !loginToken) throw new Error('Could not open the login form or read CSRF token.');

  const login = await request('/login.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: new URLSearchParams({ csrf_token: loginToken, email: credentials.email, password: credentials.password }),
  });
  if (login.status !== 302 || login.headers.get('location') !== '/admin/index.php') {
    throw new Error(`Admin login failed (HTTP ${login.status}).`);
  }

  for (const test of returnCases) {
    const page = await request('/admin/requests.php');
    const html = await page.text();
    const csrf = html.match(/name="csrf_token" value="([^"]+)"/)?.[1];
    if (page.status !== 200 || !csrf) throw new Error(`Cannot open request administration for ${test.name}.`);

    const response = await request('/admin/requests.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({
        csrf_token: csrf,
        id: test.id,
        action: 'return',
        return_condition: test.condition,
        return_note: `QA condition test ${test.condition}`,
      }),
    });
    const resultHtml = await response.text();
    const passed = response.status === 200 && resultHtml.includes('Đã xác nhận hoàn trả thiết bị');
    console.log(`${passed ? 'PASS' : 'FAIL'} ${test.name} (HTTP ${response.status})`);
    if (!passed) process.exitCode = 1;
  }
} catch (error) {
  console.error(`QA return test stopped: ${error.message}`);
  process.exit(1);
}
