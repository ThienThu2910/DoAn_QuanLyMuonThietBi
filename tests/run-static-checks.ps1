$ErrorActionPreference = 'Stop'
$repoRoot = Split-Path -Parent $PSScriptRoot
Push-Location $repoRoot
$script:passed = 0
$script:failed = 0

function Assert-Pattern([string]$Path, [string]$Pattern, [string]$Name) {
    $content = Get-Content -LiteralPath $Path -Raw
    if ($content -match $Pattern) {
        $script:passed++
        Write-Host "PASS $Name"
    } else {
        $script:failed++
        Write-Host "FAIL $Name"
    }
}

function Assert-PostFormsHaveCsrf {
    $formRegex = [regex]'(?is)<form\b(?=[^>]*\bmethod\s*=\s*["'']?POST)[^>]*>(.*?)</form>'
    $csrfRegex = 'name\s*=\s*["'']csrf_token["'']'
    $missing = @()
    Get-ChildItem -LiteralPath (Join-Path $repoRoot 'src') -Recurse -Filter '*.php' | ForEach-Object {
        $file = $_
        $content = Get-Content -LiteralPath $file.FullName -Raw
        foreach ($form in $formRegex.Matches($content)) {
            if ($form.Groups[1].Value -notmatch $csrfRegex) { $missing += $file.FullName }
        }
    }
    if ($missing.Count -eq 0) {
        $script:passed++
        Write-Host 'PASS Literal POST forms include a CSRF token'
    } else {
        $script:failed++
        Write-Host "FAIL Literal POST forms missing CSRF: $($missing -join ', ')"
    }
}

function Assert-Command([string]$File, [string[]]$Arguments, [string]$Name) {
    & $File @Arguments
    if ($LASTEXITCODE -eq 0) {
        $script:passed++
        Write-Host "PASS $Name"
    } else {
        $script:failed++
        Write-Host "FAIL $Name (exit $LASTEXITCODE)"
    }
}

Assert-Command 'docker' @('compose', 'config', '--quiet') 'Docker Compose configuration parses'
Assert-Command 'node' @('--check', 'src/assets/theme.js') 'Shared frontend JavaScript parses'
Assert-Pattern 'src/config.php' 'function app_require_csrf' 'CSRF verification helper exists'
Assert-Pattern 'src/config.php' 'http_response_code\(403\)' 'Admin role violations return HTTP 403'
Assert-Pattern 'src/config.php' "'httponly'\s*=>\s*true" 'Session cookies are HttpOnly'
Assert-Pattern 'src/config.php' "'samesite'\s*=>\s*'Lax'" 'Session cookies use SameSite Lax'
Assert-Pattern 'src/login.php' 'session_regenerate_id\(true\)' 'Login rotates the session identifier'
Assert-Pattern 'src/login.php' 'unset\(\$user\[''password''' 'Login does not keep password in session'
Assert-Pattern 'src/register.php' 'app_password_hash\(\$password\)' 'Registration hashes passwords'
Assert-Pattern 'src/profile.php' 'app_password_hash\(\$new_pass\)' 'Password change hashes new password'
Assert-Pattern 'src/setup.php' 'checkAdmin\(\)' 'Setup requires admin access'
Assert-Pattern 'src/setup.php' 'app_require_csrf\(\)' 'Setup POST checks CSRF'
Assert-Pattern 'src/reset_pw.php' 'checkAdmin\(\)' 'Password reset tool requires admin access'
Assert-Pattern 'src/view_file.php' 'borrow_requests WHERE handover_doc_key' 'Borrow documents are checked against their owner'
Assert-Pattern 'src/view_file.php' 'Cache-Control: private' 'Authenticated files use private cache policy'
Assert-Pattern 'src/admin/export.php' 'safeCsvCell' 'CSV export neutralizes formula-like cells'
Assert-Pattern 'src/admin/requests.php' 'FOR UPDATE' 'Admin request transition locks the request row'
Assert-Pattern 'src/request_detail.php' 'FOR UPDATE' 'Request detail transition locks the request row'
Assert-Pattern 'src/config.php' 'newTotal\s*=\s*\$total\s*-\s*\$quantity' 'Lost returns subtract the lost quantity from total stock'
Assert-Pattern 'src/config.php' 'UPDATE devices SET quantity = \?, status = \?' 'Lost returns do not add items to available stock'
Assert-Pattern 'src/config.php' 'in_array\(\$condition, \[''damaged'', ''incomplete''\]' 'Damaged or incomplete returns require maintenance'
Assert-Pattern 'src/setup.php' 'http_response_code\(500\)' 'Setup failures return a server error without dumping database details'
Assert-Pattern 'src/admin/device_edit.php' 'checkedOut' 'Device quantity update protects checked-out stock'
Assert-Pattern 'src/admin/device_edit.php' 'newFileMetadata' 'Replaced device files are recorded in file metadata'
Assert-Pattern 'src/borrow.php' 'student-selected\[hidden\]\s*\{\s*display:\s*none' 'Unselected student card remains hidden'
Assert-Pattern 'src/borrow.php' 'student-result\[hidden\]\s*\{\s*display:\s*none' 'Student search hides nonmatching results'
Assert-Pattern 'init.sql' '\$2y\$10\$' 'Fresh account seeds store password hashes'
Assert-Pattern 'src/admin/categories.php' 'SELECT 1 FROM devices WHERE category_id' 'Category deletion checks dependent devices'
Assert-Pattern 'src/admin/devices.php' 'SELECT 1 FROM borrow_requests WHERE device_id' 'Device deletion preserves borrowing history'
Assert-Pattern 'init.sql' 'ON DELETE RESTRICT' 'Fresh database restricts destructive category/device cascades'
Assert-Pattern 'init.sql' 'CREATE TABLE IF NOT EXISTS borrow_logs' 'Fresh database includes the request audit log table'
Assert-Pattern 'init.sql' 'manual_doc_key VARCHAR\(255\) NULL' 'Fresh device schema includes document metadata fields'
Assert-Pattern 'src/minio_helper.php' 'finfo\(FILEINFO_MIME_TYPE\)' 'Uploads are checked by detected MIME type'
Assert-Pattern 'Dockerfile' 'docker-php-ext-install pdo pdo_mysql curl fileinfo' 'Image installs cURL and fileinfo extensions used by MinIO/upload validation'
Assert-PostFormsHaveCsrf

Pop-Location
Write-Host "Static checks: $script:passed passed, $script:failed failed."
if ($script:failed -gt 0) { exit 1 }
