const baseUrl = process.env.ALT_F4_BASE_URL || 'http://127.0.0.1:18080';
const minioUrl = process.env.ALT_F4_MINIO_URL || 'http://127.0.0.1:19000';
const credentials = {
  adminEmail: process.env.ALT_F4_ADMIN_EMAIL,
  adminPassword: process.env.ALT_F4_ADMIN_PASSWORD,
  studentEmail: process.env.ALT_F4_STUDENT_EMAIL,
  studentPassword: process.env.ALT_F4_STUDENT_PASSWORD,
};

for (const [name, value] of Object.entries(credentials)) {
  if (!value) {
    console.error(`Missing required environment variable for ${name}.`);
    process.exit(2);
  }
}

function session() {
  return { cookie: '' };
}

async function request(userSession, path, options = {}) {
  const headers = new Headers(options.headers || {});
  if (userSession.cookie) headers.set('Cookie', userSession.cookie);

  const response = await fetch(new URL(path, baseUrl), {
    ...options,
    headers,
    redirect: options.redirect || 'manual',
  });
  const cookie = response.headers.get('set-cookie')?.match(/PHPSESSID=[^;]+/)?.[0];
  if (cookie) userSession.cookie = cookie;
  return response;
}

async function login(email, password) {
  const userSession = session();
  const page = await request(userSession, '/login.php');
  if (!page.ok) throw new Error(`Login page returned HTTP ${page.status}.`);
  const html = await page.text();
  const csrf = html.match(/name="csrf_token" value="([^"]+)"/)?.[1];
  if (!csrf) throw new Error('Login form did not provide a CSRF token.');

  const result = await request(userSession, '/login.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: new URLSearchParams({ csrf_token: csrf, email, password }),
  });
  return { session: userSession, response: result };
}

const checks = [];
function record(name, passed, detail = '') {
  checks.push({ name, passed });
  console.log(`${passed ? 'PASS' : 'FAIL'} ${name}${detail ? ` (${detail})` : ''}`);
}

try {
  const health = await request(session(), '/login.php');
  record('App login page responds', health.status === 200, `HTTP ${health.status}`);

  const admin = await login(credentials.adminEmail, credentials.adminPassword);
  record('Admin login redirects to dashboard', admin.response.status === 302 && admin.response.headers.get('location') === '/admin/index.php');

  const dashboard = await request(admin.session, '/admin/index.php');
  record('Admin dashboard is accessible', dashboard.status === 200);

  const csrfProbe = await request(admin.session, '/admin/requests.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: new URLSearchParams({ id: '1', action: 'return', return_condition: 'good' }),
  });
  record('State change without CSRF token is rejected', csrfProbe.status === 403, `HTTP ${csrfProbe.status}`);

  const student = await login(credentials.studentEmail, credentials.studentPassword);
  record('Student login redirects to equipment page', student.response.status === 302 && student.response.headers.get('location') === '/index.php');

  const deniedAdminPage = await request(student.session, '/admin/index.php');
  record('Student cannot access admin dashboard', deniedAdminPage.status === 403, `HTTP ${deniedAdminPage.status}`);

  for (const path of ['/setup.php', '/reset_pw.php', '/check_password.php']) {
    const restricted = await request(student.session, path);
    record(`Student cannot access ${path}`, restricted.status === 403, `HTTP ${restricted.status}`);
  }

  const badPassword = await login(credentials.studentEmail, `${credentials.studentPassword}-invalid`);
  const badPasswordHtml = await badPassword.response.text();
  record('Wrong password is rejected without server error', badPassword.response.status === 200 && badPasswordHtml.includes('Sai tài khoản hoặc mật khẩu!'));

  const minio = await fetch(new URL('/minio/health/ready', minioUrl));
  record('MinIO readiness endpoint responds', minio.status === 200, `HTTP ${minio.status}`);
} catch (error) {
  console.error(`Smoke run stopped: ${error.message}`);
  process.exit(1);
}

const failed = checks.filter(check => !check.passed);
console.log(`QA smoke checks: ${checks.length - failed.length} passed, ${failed.length} failed.`);
if (failed.length) process.exit(1);
