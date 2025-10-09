<?php
/**
 * static_audit.php
 * Recursively scans a PHP project for high-risk errors & security issues.
 * Usage: php static_audit.php /path/to/project > audit_report.json
 *
 * Output: JSON array with findings.
 */
 
if (php_sapi_name() !== 'cli') {
  fwrite(STDERR, "Run from CLI: php static_audit.php /path/to/project\n");
  exit(1);
}

$root = $argv[1] ?? '.';
if (!is_dir($root)) {
  fwrite(STDERR, "Not a directory: $root\n");
  exit(1);
}

$SUSPECT_JSON_ENDPOINTS = ['delete.php','toggle_protect.php']; // expected to return JSON only
$CRITICAL_POST_HANDLERS = ['create.php','edit_ride.php','delete.php','toggle_protect.php']; // should verify CSRF

$rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
$findings = [];

function addFinding(&$findings, $file, $lineNo, $severity, $code, $message, $evidence='') {
  $findings[] = [
    'file' => $file,
    'line' => $lineNo,
    'severity' => $severity, // error | high | medium | low | info
    'code' => $code,
    'message' => $message,
    'evidence' => trim($evidence),
  ];
}

foreach ($rii as $file) {
  if (!$file->isFile()) continue;
  $path = $file->getPathname();
  if (!preg_match('/\.php$/i', $path)) continue;

  $content = file_get_contents($path);
  $lines = explode("\n", $content);
  $basename = basename($path);

  // 1) BOM / whitespace before opening tag
  if (strlen($content) > 0) {
    // If first non-whitespace char isn't '<' or there's BOM
    $bom = substr($content, 0, 3) === "\xEF\xBB\xBF";
    $firstNonWsPos = strspn($content, " \t\r\n");
    if ($bom || ($firstNonWsPos < strlen($content) && $content[$firstNonWsPos] !== '<')) {
      addFinding($findings, $path, 1, 'medium', 'OUTPUT_BEFORE_HEADER', 'Possible BOM or whitespace output before opening <?php tag; may break header() calls.');
    }
  }

  // 2) Use of deprecated mysql_* functions
  if (preg_match('/\bmysql_(query|connect|pconnect|fetch|real_escape_string)\b/i', $content)) {
    addFinding($findings, $path, 0, 'high', 'DEPRECATED_MYSQL', 'Use of deprecated mysql_* APIs. Use PDO or mysqli with prepared statements.');
  }

  // 3) Raw SQL interpolation of superglobals
  $sqlPattern = '/\b(SELECT|INSERT|UPDATE|DELETE)\b.*\$_(GET|POST|REQUEST|COOKIE)/i';
  foreach ($lines as $i => $line) {
    if (preg_match($sqlPattern, $line)) {
      addFinding($findings, $path, $i+1, 'high', 'SQL_INTERPOLATION', 'SQL query appears to directly include superglobal input; use prepared statements.', $line);
    }
  }

  // 4) mysqli_query / PDO->query with concatenated values
  $concatSqlPattern = '/\b(mysqli_query|->query)\s*\(.*\.(?:\s*\$|["\'])/i';
  foreach ($lines as $i => $line) {
    if (preg_match($concatSqlPattern, $line)) {
      addFinding($findings, $path, $i+1, 'high', 'SQL_CONCAT', 'Concatenated SQL detected; use prepared statements.', $line);
    }
  }

  // 5) Direct echo of superglobals without escaping
  foreach ($lines as $i => $line) {
    if (preg_match('/\becho\b.*\$_(GET|POST|REQUEST|COOKIE)/i', $line) && !preg_match('/htmlspecialchars|strip_tags/i', $line)) {
      addFinding($findings, $path, $i+1, 'high', 'XSS_UNESCAPED_ECHO', 'Direct echo of user input without escaping.', $line);
    }
  }

  // 6) Files using $_SESSION but missing session_start()
  if (preg_match('/\$_SESSION\s*\[/', $content) && !preg_match('/\bsession_start\s*\(/i', $content)) {
    addFinding($findings, $path, 0, 'high', 'SESSION_MISSING_START', 'Uses $_SESSION but no session_start() found in this file. Ensure session_start() is called before usage.');
  }

  // 7) Login/signup password handling
  if (preg_match('/login\.php$|signup\.php$/i', $path)) {
    $hasHash = preg_match('/password_hash\s*\(/i', $content);
    $hasVerify = preg_match('/password_verify\s*\(/i', $content);
    if (!$hasHash && !$hasVerify) {
      addFinding($findings, $path, 0, 'high', 'PASSWORD_PLAINTEXT_OR_WEAK', 'No password_hash()/password_verify() detected in login/signup. Ensure strong password handling.');
    }
    if (!preg_match('/session_regenerate_id\s*\(\s*true?\s*\)/i', $content)) {
      addFinding($findings, $path, 0, 'medium', 'SESSION_FIXATION_RISK', 'Missing session_regenerate_id(true) after successful login.');
    }
  }

  // 8) JSON endpoints should not redirect; and should set Content-Type
  if (in_array($basename, $SUSPECT_JSON_ENDPOINTS)) {
    if (!preg_match('/header\s*\(\s*[\'"]Content-Type:\s*application\/json/i', $content)) {
      addFinding($findings, $path, 0, 'medium', 'JSON_CT_MISSING', 'Expected JSON endpoint without Content-Type: application/json header.');
    }
    if (preg_match('/header\s*\(\s*[\'"]Location:/i', $content)) {
      addFinding($findings, $path, 0, 'high', 'JSON_REDIRECT', 'JSON endpoint appears to redirect. Should return JSON only.');
    }
  }

  // 9) CSRF checks on critical POST handlers
  if (in_array($basename, $CRITICAL_POST_HANDLERS)) {
    $hasCsrf = preg_match('/csrf/i', $content);
    if (!$hasCsrf) {
      addFinding($findings, $path, 0, 'high', 'CSRF_MISSING', 'Likely state-changing endpoint without CSRF validation.');
    }
  }

  // 10) Rides SELECT without soft-delete filter
  if (preg_match('/\bFROM\s+rides\b/i', $content) && preg_match('/\bSELECT\b/i', $content)) {
    // crude check: contains "deleted = 0"
    if (!preg_match('/deleted\s*=\s*0/i', $content)) {
      addFinding($findings, $path, 0, 'medium', 'SOFT_DELETE_FILTER_MISSING', 'Query on rides may be missing "deleted = 0" filter.');
    }
  }

  // 11) Prevent delete logic in delete.php
  if ($basename === 'delete.php') {
    if (!preg_match('/prevent_delete/i', $content)) {
      addFinding($findings, $path, 0, 'high', 'PREVENT_DELETE_IGNORED', 'delete.php does not appear to enforce prevent_delete flag.');
    }
    if (!preg_match('/user_id/i', $content)) {
      addFinding($findings, $path, 0, 'high', 'OWNERSHIP_CHECK_MISSING', 'delete.php may not be checking ride ownership.');
    }
  }
}

echo json_encode($findings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), PHP_EOL;
