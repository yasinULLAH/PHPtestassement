<?php
define('DB_HOST', 'localhost');
define('DB_NAME', 'ticktock_db');
define('DB_USER', 'root');
define('DB_PASS', 'root');
define('DB_CHARSET', 'utf8mb4');
define('SESSION_IDLE_TIMEOUT', 2400);
define('SESSION_ABSOLUTE_TIMEOUT', 28800);
ini_set('session.cookie_httponly', 1);
ini_set('session.use_strict_mode', 1);
date_default_timezone_set('UTC');
session_start();

function getDB(): PDO
{
  static $pdo = null;
  if ($pdo === null) {
    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
    $options = [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      PDO::ATTR_EMULATE_PREPARES => false,
    ];
    try {
      $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    } catch (PDOException $e) {
      jsonError('Database connection failed.', 500);
    }
  }
  return $pdo;
}

function jsonOut(array $data, int $code = 200): void
{
  http_response_code($code);
  header('Content-Type: application/json');
  echo json_encode($data);
  exit;
}

function jsonError(string $message, int $code = 400): void
{
  jsonOut(['success' => false, 'error' => $message], $code);
}

function requireAuth(): void
{
  if (empty($_SESSION['user_id'])) {
    jsonError('Unauthenticated', 401);
  }
  $now = time();
  if (!empty($_SESSION['last_activity']) && ($now - $_SESSION['last_activity']) > SESSION_IDLE_TIMEOUT) {
    session_destroy();
    jsonError('Session expired due to inactivity', 401);
  }
  if (!empty($_SESSION['session_start']) && ($now - $_SESSION['session_start']) > SESSION_ABSOLUTE_TIMEOUT) {
    session_destroy();
    jsonError('Session expired', 401);
  }
  $_SESSION['last_activity'] = $now;
}

function requireAdmin(): void
{
  requireAuth();
  if ($_SESSION['user_role'] !== 'admin') {
    jsonError('Access denied. Admin only.', 403);
  }
}

function sanitizeStr(string $val): string
{
  return htmlspecialchars(trim($val), ENT_QUOTES, 'UTF-8');
}

function csrfToken(): string
{
  if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
  }
  return $_SESSION['csrf_token'];
}

function verifyCsrf(): void
{
  $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? '');
  if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
    jsonError('Invalid CSRF token', 403);
  }
}

function generateCaptchaImage(int $answer, string $question): string
{
  $width = 200;
  $height = 60;
  $img = imagecreatetruecolor($width, $height);
  $bg = imagecolorallocate($img, 248, 249, 250);
  imagefilledrectangle($img, 0, 0, $width, $height, $bg);
  for ($i = 0; $i < 6; $i++) {
    $lc = imagecolorallocate($img, rand(180, 220), rand(180, 220), rand(180, 220));
    imageline($img, rand(0, $width), rand(0, $height), rand(0, $width), rand(0, $height), $lc);
  }
  for ($i = 0; $i < 120; $i++) {
    $nc = imagecolorallocate($img, rand(150, 210), rand(150, 210), rand(150, 210));
    imagesetpixel($img, rand(0, $width), rand(0, $height), $nc);
  }
  $textColor = imagecolorallocate($img, 30, 40, 80);
  $fontSize = 5;
  $textWidth = imagefontwidth($fontSize) * strlen($question);
  $x = (int) (($width - $textWidth) / 2);
  $y = (int) (($height - imagefontheight($fontSize)) / 2);
  imagestring($img, $fontSize, $x, $y, $question, $textColor);
  ob_start();
  imagepng($img);
  $imgData = ob_get_clean();
  imagedestroy($img);
  return base64_encode($imgData);
}

function cleanExpiredCaptchas(): void
{
  try {
    $db = getDB();
    $db->exec('DELETE FROM captcha_store WHERE expires_at < NOW()');
  } catch (Exception $e) {
  }
}

function getWeekRange(int $year, int $week): array
{
  $dt = new DateTime();
  $dt->setISODate($year, $week, 1);
  $start = clone $dt;
  $dt->setISODate($year, $week, 7);
  $end = clone $dt;
  return ['start' => $start->format('Y-m-d'), 'end' => $end->format('Y-m-d')];
}

function getWeeksInRange(string $startDate, string $endDate): array
{
  $start = new DateTime($startDate);
  $end = new DateTime($endDate);
  $weeks = [];
  $current = clone $start;
  $current->modify('Monday this week');
  while ($current <= $end) {
    $weekEnd = clone $current;
    $weekEnd->modify('+6 days');
    $weeks[] = [
      'year' => (int) $current->format('o'),
      'week' => (int) $current->format('W'),
      'start' => $current->format('Y-m-d'),
      'end' => $weekEnd->format('Y-m-d'),
    ];
    $current->modify('+7 days');
  }
  return $weeks;
}

function checkRateLimit(): void
{
  $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
  try {
    $db = getDB();
    $db->exec('DELETE FROM login_attempts WHERE attempt_time <= DATE_SUB(NOW(), INTERVAL 15 MINUTE)');
    $db->prepare('INSERT INTO login_attempts (ip_address) VALUES (?)')->execute([$ip]);
    $stmt = $db->prepare('SELECT COUNT(*) FROM login_attempts WHERE ip_address = ? AND attempt_time > DATE_SUB(NOW(), INTERVAL 15 MINUTE)');
    $stmt->execute([$ip]);
    if ((int) $stmt->fetchColumn() > 10) {
      jsonError('Too many login attempts. Please try again in 15 minutes.', 429);
    }
  } catch (Exception $e) {
  }
}

function logEmail(string $to, string $subject, string $body): void
{
  $entry = '[' . date('Y-m-d H:i:s') . "] TO: $to | SUBJECT: $subject\nBODY: $body\n" . str_repeat('-', 40) . "\n";
  file_put_contents('email.txt', $entry, FILE_APPEND);

  $headers = 'From: noreply@' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . "\r\n"
    . 'Reply-To: noreply@' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . "\r\n"
    . 'X-Mailer: PHP/' . phpversion();
  @mail($to, $subject, $body, $headers);
}

$api = $_GET['api'] ?? '';
if ($api !== '') {
  switch ($api) {
    case 'captcha':
      cleanExpiredCaptchas();
      $a = rand(1, 9);
      $b = rand(1, 9);
      $answer = $a + $b;
      $question = "$a + $b = ?";
      $token = bin2hex(random_bytes(16));
      try {
        $db = getDB();
        $stmt = $db->prepare('INSERT INTO captcha_store (token, answer, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE))');
        $stmt->execute([$token, (string) $answer]);
      } catch (Exception $e) {
        $token = '';
      }
      $imgB64 = generateCaptchaImage($answer, $question);
      jsonOut(['token' => $token, 'image' => 'data:image/png;base64,' . $imgB64]);
      break;
    case 'login':
      if ($_SERVER['REQUEST_METHOD'] !== 'POST')
        jsonError('Method not allowed', 405);
      checkRateLimit();
      $body = json_decode(file_get_contents('php://input'), true) ?? [];
      $email = trim($body['email'] ?? '');
      $password = $body['password'] ?? '';
      $captchaToken = $body['captcha_token'] ?? '';
      $captchaAnswer = trim($body['captcha_answer'] ?? '');
      if (empty($email) || empty($password))
        jsonError('Email and password are required.');
      if (!filter_var($email, FILTER_VALIDATE_EMAIL))
        jsonError('Invalid email format.');
      if (strlen($password) < 8)
        jsonError('Password must be at least 8 characters.');
      if (empty($captchaToken) || empty($captchaAnswer))
        jsonError('Please complete the CAPTCHA.');
      try {
        $db = getDB();
        $stmt = $db->prepare('SELECT answer FROM captcha_store WHERE token = ? AND expires_at > NOW()');
        $stmt->execute([$captchaToken]);
        $captchaRow = $stmt->fetch();
        if (!$captchaRow)
          jsonError('CAPTCHA expired. Please refresh and try again.');
        if ((string) $captchaRow['answer'] !== $captchaAnswer) {
          $db->prepare('DELETE FROM captcha_store WHERE token = ?')->execute([$captchaToken]);
          jsonError('Incorrect CAPTCHA answer.');
        }
        $db->prepare('DELETE FROM captcha_store WHERE token = ?')->execute([$captchaToken]);
        $stmt = $db->prepare('SELECT id, email, password_hash, name, role, is_approved FROM users WHERE email = ? AND is_active = 1 AND is_deleted = 0');
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        if (!$user || !password_verify($password, $user['password_hash'])) {
          jsonError('Invalid email or password.');
        }
        if ($user['is_approved'] == 0) {
          jsonError('Your account is pending approval by an admin.');
        }
        $db->prepare('DELETE FROM login_attempts WHERE ip_address = ?')->execute([$_SERVER['REMOTE_ADDR'] ?? 'unknown']);
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['last_activity'] = time();
        $_SESSION['session_start'] = time();
        csrfToken();
        jsonOut(['success' => true, 'user' => ['id' => $user['id'], 'name' => $user['name'], 'email' => $user['email'], 'role' => $user['role']], 'csrf' => $_SESSION['csrf_token']]);
      } catch (PDOException $e) {
        jsonError('Login failed. Please try again.', 500);
      }
      break;
    case 'register':
      if ($_SERVER['REQUEST_METHOD'] !== 'POST')
        jsonError('Method not allowed', 405);
      checkRateLimit();
      $body = json_decode(file_get_contents('php://input'), true) ?? [];
      $name = sanitizeStr($body['name'] ?? '');
      $email = trim($body['email'] ?? '');
      $password = $body['password'] ?? '';
      $confirm = $body['confirm_password'] ?? '';
      $captchaToken = $body['captcha_token'] ?? '';
      $captchaAnswer = trim($body['captcha_answer'] ?? '');
      if (empty($name) || empty($email) || empty($password))
        jsonError('All fields are required.');
      if (!filter_var($email, FILTER_VALIDATE_EMAIL))
        jsonError('Invalid email format.');
      if (strlen($password) < 8)
        jsonError('Password must be at least 8 characters.');
      if ($password !== $confirm)
        jsonError('Passwords do not match.');
      if (empty($captchaToken) || empty($captchaAnswer))
        jsonError('Please complete the CAPTCHA.');
      try {
        $db = getDB();
        $stmt = $db->prepare('SELECT answer FROM captcha_store WHERE token = ? AND expires_at > NOW()');
        $stmt->execute([$captchaToken]);
        $captchaRow = $stmt->fetch();
        if (!$captchaRow)
          jsonError('CAPTCHA expired.');
        if ((string) $captchaRow['answer'] !== $captchaAnswer) {
          $db->prepare('DELETE FROM captcha_store WHERE token = ?')->execute([$captchaToken]);
          jsonError('Incorrect CAPTCHA answer.');
        }
        $db->prepare('DELETE FROM captcha_store WHERE token = ?')->execute([$captchaToken]);
        $stmt = $db->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([$email]);
        if ($stmt->fetch())
          jsonError('Email already registered.');
        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        $sStmt = $db->query("SELECT setting_value FROM system_settings WHERE setting_key = 'default_standard_hours'");
        $stdHrs = (float) ($sStmt->fetchColumn() ?: 40.0);
        $stmt = $db->prepare("INSERT INTO users (name, email, password_hash, role, is_approved, standard_hours) VALUES (?, ?, ?, 'user', 0, ?)");
        $stmt->execute([$name, $email, $hash, $stdHrs]);
        jsonOut(['success' => true, 'message' => 'Registration successful! Your account is pending admin approval.']);
      } catch (PDOException $e) {
        jsonError('Registration failed.', 500);
      }
      break;
    case 'logout':
      session_destroy();
      jsonOut(['success' => true]);
      break;
    case 'me':
      if (empty($_SESSION['user_id'])) {
        jsonOut(['success' => false, 'error' => 'Unauthenticated'], 200);
      }
      requireAuth();
      jsonOut(['success' => true, 'user' => ['id' => $_SESSION['user_id'], 'name' => $_SESSION['user_name'], 'email' => $_SESSION['user_email'], 'role' => $_SESSION['user_role']], 'csrf' => csrfToken()]);
      break;
    case 'timesheets':
      requireAuth();
      $userId = (int) $_SESSION['user_id'];
      $startDate = $_GET['start'] ?? date('Y-01-01');
      $endDate = $_GET['end'] ?? date('Y-12-31');
      if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
        jsonError('Invalid date format.');
      }
      try {
        $db = getDB();
        $uStmt = $db->prepare('SELECT standard_hours FROM users WHERE id = ?');
        $uStmt->execute([$userId]);
        $stdHours = (float) ($uStmt->fetchColumn() ?: 40.0);
        $stmt = $db->prepare('SELECT date, SUM(hours) as total_hours FROM timesheet_entries WHERE user_id = ? AND date BETWEEN ? AND ? GROUP BY date');
        $stmt->execute([$userId, $startDate, $endDate]);
        $entriesByDate = [];
        while ($row = $stmt->fetch()) {
          $entriesByDate[$row['date']] = (float) $row['total_hours'];
        }
        $sStmt = $db->prepare('SELECT year, week, status, rejection_reason FROM timesheet_submissions WHERE user_id = ?');
        $sStmt->execute([$userId]);
        $subs = [];
        while ($srow = $sStmt->fetch()) {
          $subs[$srow['year'] . '-' . $srow['week']] = [
            'status' => $srow['status'],
            'reason' => $srow['rejection_reason']
          ];
        }
        $weeks = getWeeksInRange($startDate, $endDate);
        $result = [];
        $weekNum = 1;
        foreach ($weeks as $w) {
          $totalHours = 0.0;
          $d = new DateTime($w['start']);
          $dEnd = new DateTime($w['end']);
          while ($d <= $dEnd) {
            $ds = $d->format('Y-m-d');
            $totalHours += $entriesByDate[$ds] ?? 0.0;
            $d->modify('+1 day');
          }
          $subInfo = $subs[$w['year'] . '-' . $w['week']] ?? null;
          $status = 'missing';
          $reason = '';
          if ($subInfo) {
            $status = $subInfo['status'];
            $reason = $subInfo['reason'];
          } elseif ($totalHours >= $stdHours)
            $status = 'completed';
          elseif ($totalHours > 0)
            $status = 'incomplete';
          $startDt = new DateTime($w['start']);
          $endDt = new DateTime($w['end']);
          $dateLabel = $startDt->format('j') . ' - ' . $endDt->format('j F, Y');
          $result[] = [
            'week_num' => $weekNum++,
            'year' => $w['year'],
            'week' => $w['week'],
            'date_start' => $w['start'],
            'date_end' => $w['end'],
            'date_label' => $dateLabel,
            'total_hours' => $totalHours,
            'std_hours' => $stdHours,
            'status' => $status,
            'rejection_reason' => $reason
          ];
        }
        jsonOut(['success' => true, 'timesheets' => $result]);
      } catch (PDOException $e) {
        jsonError('Failed to load timesheets.', 500);
      }
      break;
    case 'week_entries':
      requireAuth();
      $targetUserId = (int)($_GET['user_id'] ?? 0);
      $userId = ($targetUserId > 0 && $_SESSION['user_role'] === 'admin') ? $targetUserId : (int) $_SESSION['user_id'];
      $startDate = $_GET['start'] ?? '';
      $endDate = $_GET['end'] ?? '';
      if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
        jsonError('Invalid date format.');
      }
      try {
        $db = getDB();
        $stmt = $db->prepare('SELECT te.id, te.date, te.description, te.hours, p.name as project_name, p.id as project_id, wt.name as work_type_name, wt.id as work_type_id FROM timesheet_entries te JOIN projects p ON p.id = te.project_id JOIN work_types wt ON wt.id = te.work_type_id WHERE te.user_id = ? AND te.date BETWEEN ? AND ? ORDER BY te.date ASC, te.id ASC');
        $stmt->execute([$userId, $startDate, $endDate]);
        $entries = $stmt->fetchAll();
        $totalHours = array_sum(array_column($entries, 'hours'));
        jsonOut(['success' => true, 'entries' => $entries, 'total_hours' => (float) $totalHours, 'start' => $startDate, 'end' => $endDate]);
      } catch (PDOException $e) {
        jsonError('Failed to load entries.', 500);
      }
      break;
    case 'entry':
      requireAuth();
      $userId = (int) $_SESSION['user_id'];
      if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verifyCsrf();
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $id = isset($body['id']) ? (int) $body['id'] : 0;
        $date = sanitizeStr($body['date'] ?? '');
        $projectId = (int) ($body['project_id'] ?? 0);
        $workTypeId = (int) ($body['work_type_id'] ?? 0);
        $description = sanitizeStr($body['description'] ?? '');
        $hours = round((float) ($body['hours'] ?? 0), 2);
        if (empty($date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date))
          jsonError('Invalid date.');
        if ($projectId <= 0)
          jsonError('Please select a project.');
        if ($workTypeId <= 0)
          jsonError('Please select a work type.');
        if (empty($description))
          jsonError('Task description is required.');
        if ($hours <= 0 || $hours > 24)
          jsonError('Hours must be between 0.5 and 24.');
        try {
          $db = getDB();
          $isAdmin = ($_SESSION['user_role'] === 'admin');
          if ($id > 0 && $isAdmin) {
            $uStmt = $db->prepare('SELECT user_id FROM timesheet_entries WHERE id = ?');
            $uStmt->execute([$id]);
            $targetUserId = $uStmt->fetchColumn();
            if ($targetUserId)
              $userId = $targetUserId;
          }
          $dt = new DateTime($date);
          $year = (int) $dt->format('o');
          $week = (int) $dt->format('W');
          $sStmt = $db->prepare('SELECT status FROM timesheet_submissions WHERE user_id = ? AND year = ? AND week = ?');
          $sStmt->execute([$userId, $year, $week]);
          $subStatus = $sStmt->fetchColumn();
          if (!$isAdmin && ($subStatus === 'pending' || $subStatus === 'approved')) {
            jsonError('This week is ' . $subStatus . ' and locked for editing.');
          }
          $stmt = $db->prepare('SELECT id FROM projects WHERE id = ? AND is_active = 1');
          $stmt->execute([$projectId]);
          if (!$stmt->fetch())
            jsonError('Invalid project.');
          $stmt = $db->prepare('SELECT id FROM work_types WHERE id = ? AND is_active = 1');
          $stmt->execute([$workTypeId]);
          if (!$stmt->fetch())
            jsonError('Invalid work type.');
          if ($id > 0) {
            $stmt = $db->prepare('SELECT id FROM timesheet_entries WHERE id = ? AND user_id = ?');
            $stmt->execute([$id, $userId]);
            if (!$stmt->fetch())
              jsonError('Entry not found or access denied.', 403);
            $stmt = $db->prepare('UPDATE timesheet_entries SET date=?, project_id=?, work_type_id=?, description=?, hours=? WHERE id=? AND user_id=?');
            $stmt->execute([$date, $projectId, $workTypeId, $description, $hours, $id, $userId]);
            jsonOut(['success' => true, 'message' => 'Entry updated successfully.']);
          } else {
            $stmt = $db->prepare('INSERT INTO timesheet_entries (user_id, date, project_id, work_type_id, description, hours) VALUES (?, ?, ?, ?, ?, ?)');
            $stmt->execute([$userId, $date, $projectId, $workTypeId, $description, $hours]);
            jsonOut(['success' => true, 'message' => 'Entry added successfully.', 'id' => (int) $db->lastInsertId()]);
          }
        } catch (PDOException $e) {
          jsonError('Failed to save entry.', 500);
        }
      } elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
        verifyCsrf();
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $id = (int) ($body['id'] ?? 0);
        if ($id <= 0)
          jsonError('Invalid entry ID.');
        try {
          $db = getDB();
          $isAdmin = ($_SESSION['user_role'] === 'admin');
          if ($isAdmin) {
            $uStmt = $db->prepare('SELECT user_id FROM timesheet_entries WHERE id = ?');
            $uStmt->execute([$id]);
            $targetUserId = $uStmt->fetchColumn();
            if ($targetUserId)
              $userId = $targetUserId;
          }
          $stmt = $db->prepare('SELECT date FROM timesheet_entries WHERE id = ? AND user_id = ?');
          $stmt->execute([$id, $userId]);
          $entryDate = $stmt->fetchColumn();
          if (!$entryDate)
            jsonError('Entry not found or access denied.', 403);
          $dt = new DateTime($entryDate);
          $year = (int) $dt->format('o');
          $week = (int) $dt->format('W');
          $sStmt = $db->prepare('SELECT status FROM timesheet_submissions WHERE user_id = ? AND year = ? AND week = ?');
          $sStmt->execute([$userId, $year, $week]);
          $subStatus = $sStmt->fetchColumn();
          if (!$isAdmin && ($subStatus === 'pending' || $subStatus === 'approved')) {
            jsonError('This week is ' . $subStatus . ' and locked.');
          }
          $db->prepare('DELETE FROM timesheet_entries WHERE id = ? AND user_id = ?')->execute([$id, $userId]);
          jsonOut(['success' => true, 'message' => 'Entry deleted.']);
        } catch (PDOException $e) {
          jsonError('Failed to delete entry.', 500);
        }
      } else {
        jsonError('Method not allowed.', 405);
      }
      break;
    case 'projects':
      requireAuth();
      try {
        $db = getDB();
        $where = ($_SESSION['user_role'] === 'admin') ? '' : ' WHERE is_active = 1';
        $projects = $db->query("SELECT id, name, is_active FROM projects $where ORDER BY name ASC")->fetchAll();
        jsonOut(['success' => true, 'projects' => $projects]);
      } catch (PDOException $e) {
        jsonError('Failed to load projects.', 500);
      }
      break;
    case 'admin_project_save':
      requireAdmin();
      verifyCsrf();
      if ($_SERVER['REQUEST_METHOD'] !== 'POST')
        jsonError('Method not allowed', 405);
      $body = json_decode(file_get_contents('php://input'), true) ?? [];
      $id = (int) ($body['id'] ?? 0);
      $name = sanitizeStr($body['name'] ?? '');
      $isActive = isset($body['is_active']) ? (int) $body['is_active'] : 1;
      if (empty($name))
        jsonError('Project name is required.');
      try {
        $db = getDB();
        if ($id > 0) {
          $stmt = $db->prepare('UPDATE projects SET name = ?, is_active = ? WHERE id = ?');
          $stmt->execute([$name, $isActive, $id]);
          jsonOut(['success' => true, 'message' => 'Project updated successfully.']);
        } else {
          $stmt = $db->prepare('INSERT INTO projects (name, is_active) VALUES (?, ?)');
          $stmt->execute([$name, $isActive]);
          jsonOut(['success' => true, 'message' => 'Project added successfully.', 'id' => (int) $db->lastInsertId()]);
        }
      } catch (PDOException $e) {
        jsonError('Failed to save project.', 500);
      }
      break;
    case 'work_types':
      requireAuth();
      try {
        $db = getDB();
        $where = ($_SESSION['user_role'] === 'admin') ? '' : ' WHERE is_active = 1';
        $types = $db->query("SELECT id, name, is_active FROM work_types $where ORDER BY name ASC")->fetchAll();
        jsonOut(['success' => true, 'work_types' => $types]);
      } catch (PDOException $e) {
        jsonError('Failed to load work types.', 500);
      }
      break;
    case 'admin_work_type_save':
      requireAdmin();
      verifyCsrf();
      if ($_SERVER['REQUEST_METHOD'] !== 'POST')
        jsonError('Method not allowed', 405);
      $body = json_decode(file_get_contents('php://input'), true) ?? [];
      $id = (int) ($body['id'] ?? 0);
      $name = sanitizeStr($body['name'] ?? '');
      $isActive = isset($body['is_active']) ? (int) $body['is_active'] : 1;
      if (empty($name))
        jsonError('Work type name is required.');
      try {
        $db = getDB();
        if ($id > 0) {
          $stmt = $db->prepare('UPDATE work_types SET name = ?, is_active = ? WHERE id = ?');
          $stmt->execute([$name, $isActive, $id]);
          jsonOut(['success' => true, 'message' => 'Work type updated successfully.']);
        } else {
          $stmt = $db->prepare('INSERT INTO work_types (name, is_active) VALUES (?, ?)');
          $stmt->execute([$name, $isActive]);
          jsonOut(['success' => true, 'message' => 'Work type added successfully.', 'id' => (int) $db->lastInsertId()]);
        }
      } catch (PDOException $e) {
        jsonError('Failed to save work type.', 500);
      }
      break;
    case 'admin_users':
      requireAdmin();
      try {
        $db = getDB();
        $users = $db->query('SELECT id, name, email, role, standard_hours, is_approved, is_active, created_at, is_deleted FROM users ORDER BY is_deleted ASC, created_at DESC')->fetchAll();
        jsonOut(['success' => true, 'users' => $users]);
      } catch (PDOException $e) {
        jsonError('Failed to load users.', 500);
      }
      break;
    case 'admin_user_update':
      requireAdmin();
      verifyCsrf();
      if ($_SERVER['REQUEST_METHOD'] !== 'POST')
        jsonError('Method not allowed', 405);
      $body = json_decode(file_get_contents('php://input'), true) ?? [];
      $id = (int) ($body['id'] ?? 0);
      $role = sanitizeStr($body['role'] ?? 'user');
      $isApproved = isset($body['is_approved']) ? (int) $body['is_approved'] : 0;
      $isActive = isset($body['is_active']) ? (int) $body['is_active'] : 1;
      $standardHours = isset($body['standard_hours']) ? round((float) $body['standard_hours'], 2) : 40.0;
      if ($id <= 0)
        jsonError('Invalid user ID.');
      if (!in_array($role, ['admin', 'user']))
        jsonError('Invalid role.');
      if ($standardHours < 0 || $standardHours > 168)
        jsonError('Invalid standard hours.');
      try {
        $db = getDB();
        $stmt = $db->prepare('UPDATE users SET role = ?, is_approved = ?, is_active = ?, standard_hours = ? WHERE id = ?');
        $stmt->execute([$role, $isApproved, $isActive, $standardHours, $id]);
        jsonOut(['success' => true, 'message' => 'User updated successfully.']);
      } catch (PDOException $e) {
        jsonError('Failed to update user.', 500);
      }
      break;
    case 'admin_user_delete':
      requireAdmin();
      verifyCsrf();
      if ($_SERVER['REQUEST_METHOD'] !== 'DELETE')
        jsonError('Method not allowed', 405);
      $body = json_decode(file_get_contents('php://input'), true) ?? [];
      $id = (int) ($body['id'] ?? 0);
      if ($id <= 0)
        jsonError('Invalid user ID.');
      if ($id == $_SESSION['user_id'])
        jsonError('You cannot delete yourself.');
      try {
        $db = getDB();
        $db->prepare('UPDATE users SET is_deleted = 1, is_active = 0 WHERE id = ?')->execute([$id]);
        jsonOut(['success' => true, 'message' => 'User account deactivated (Soft Delete).']);
      } catch (PDOException $e) {
        jsonError('Failed to delete user.', 500);
      }
      break;
    case 'admin_user_restore':
      requireAdmin();
      verifyCsrf();
      if ($_SERVER['REQUEST_METHOD'] !== 'POST')
        jsonError('Method not allowed', 405);
      $body = json_decode(file_get_contents('php://input'), true) ?? [];
      $id = (int) ($body['id'] ?? 0);
      if ($id <= 0)
        jsonError('Invalid user ID.');
      try {
        $db = getDB();
        $db->prepare('UPDATE users SET is_deleted = 0, is_active = 1 WHERE id = ?')->execute([$id]);
        jsonOut(['success' => true, 'message' => 'User restored successfully.']);
      } catch (PDOException $e) {
        jsonError('Failed to restore user.', 500);
      }
      break;
    case 'admin_user_purge':
      requireAdmin();
      verifyCsrf();
      if ($_SERVER['REQUEST_METHOD'] !== 'DELETE')
        jsonError('Method not allowed', 405);
      $body = json_decode(file_get_contents('php://input'), true) ?? [];
      $id = (int) ($body['id'] ?? 0);
      if ($id <= 0)
        jsonError('Invalid user ID.');
      try {
        $db = getDB();
        $db->prepare('DELETE FROM timesheet_entries WHERE user_id = ?')->execute([$id]);
        $db->prepare('DELETE FROM timesheet_submissions WHERE user_id = ?')->execute([$id]);
        $db->prepare('DELETE FROM users WHERE id = ? AND is_deleted = 1')->execute([$id]);
        jsonOut(['success' => true, 'message' => 'User permanently deleted.']);
      } catch (PDOException $e) {
        jsonError('Failed to purge user.', 500);
      }
      break;
    case 'submit_week':
      requireAuth();
      verifyCsrf();
      if ($_SERVER['REQUEST_METHOD'] !== 'POST')
        jsonError('Method not allowed', 405);
      $body = json_decode(file_get_contents('php://input'), true) ?? [];
      $year = (int) ($body['year'] ?? 0);
      $week = (int) ($body['week'] ?? 0);
      $userId = $_SESSION['user_id'];
      if ($year <= 0 || $week <= 0)
        jsonError('Invalid year/week.');
      try {
        $db = getDB();
        $range = getWeekRange($year, $week);
        $stmt = $db->prepare('SELECT COUNT(*) FROM timesheet_entries WHERE user_id = ? AND date BETWEEN ? AND ?');
        $stmt->execute([$userId, $range['start'], $range['end']]);
        if ($stmt->fetchColumn() == 0)
          jsonError('No entries found for this week. Cannot submit empty timesheet.');
        $stmt = $db->prepare("INSERT INTO timesheet_submissions (user_id, year, week, status) VALUES (?, ?, ?, 'pending') ON DUPLICATE KEY UPDATE status = 'pending', reviewed_at = NULL, reviewed_by = NULL");
        $stmt->execute([$userId, $year, $week]);
        $submissionId = $db->lastInsertId() ?: $db->query("SELECT id FROM timesheet_submissions WHERE user_id=$userId AND year=$year AND week=$week")->fetchColumn();
        $db->prepare('UPDATE timesheet_entries SET submission_id = ? WHERE user_id = ? AND date BETWEEN ? AND ?')->execute([$submissionId, $userId, $range['start'], $range['end']]);
        jsonOut(['success' => true, 'message' => 'Timesheet submitted for approval.']);
      } catch (PDOException $e) {
        jsonError('Submission failed.', 500);
      }
      break;
    case 'admin_overview':
      requireAdmin();
      $start = $_GET['start'] ?? date('Y-m-d', strtotime('Monday this week'));
      $end = $_GET['end'] ?? date('Y-m-d', strtotime('Sunday this week'));
      try {
        $db = getDB();
        $users = $db->query("SELECT id, name, standard_hours FROM users WHERE is_active = 1 AND is_deleted = 0 ORDER BY name ASC")->fetchAll();
        $stmt = $db->prepare('SELECT user_id, date, hours FROM timesheet_entries WHERE date BETWEEN ? AND ?');
        $stmt->execute([$start, $end]);
        $entries = $stmt->fetchAll();
        $weeks = getWeeksInRange($start, $end);
        $subsList = [];
        foreach($weeks as $w) {
            $sStmt = $db->prepare("SELECT user_id, status FROM timesheet_submissions WHERE year = ? AND week = ?");
            $sStmt->execute([$w['year'], $w['week']]);
            while($row = $sStmt->fetch()) {
                $subsList[$row['user_id'] . '-' . $w['year'] . '-' . $w['week']] = $row['status'];
            }
        }
        $result = [];
        foreach ($users as $u) {
            foreach ($weeks as $w) {
                $hrs = 0;
                foreach($entries as $e) {
                    if ($e['user_id'] == $u['id'] && $e['date'] >= $w['start'] && $e['date'] <= $w['end']) {
                        $hrs += (float)$e['hours'];
                    }
                }
                $subKey = $u['id'] . '-' . $w['year'] . '-' . $w['week'];
                $status = $subsList[$subKey] ?? null;
                if (!$status) {
                    if ($hrs >= $u['standard_hours']) $status = 'completed';
                    elseif ($hrs > 0) $status = 'incomplete';
                    else $status = 'missing';
                }
                $result[] = [
                    'user_id' => $u['id'],
                    'user_name' => $u['name'],
                    'date_label' => date('M j', strtotime($w['start'])) . ' - ' . date('M j, Y', strtotime($w['end'])),
                    'date_start' => $w['start'],
                    'date_end' => $w['end'],
                    'total_hours' => $hrs,
                    'std_hours' => $u['standard_hours'],
                    'status' => $status
                ];
            }
        }
        jsonOut(['success' => true, 'overview' => $result]);
      } catch (PDOException $e) {
        jsonError('Failed to load overview.', 500);
      }
      break;
    case 'admin_submissions':
      requireAdmin();
      try {
        $db = getDB();
        $subs = $db->query("SELECT ts.*, u.name as user_name, u.email as user_email FROM timesheet_submissions ts JOIN users u ON u.id = ts.user_id WHERE ts.status = 'pending' ORDER BY ts.submitted_at DESC")->fetchAll();
        jsonOut(['success' => true, 'submissions' => $subs]);
      } catch (PDOException $e) {
        jsonError('Failed to load submissions.', 500);
      }
      break;
    case 'admin_submission_entries':
      requireAdmin();
      $subId = (int) ($_GET['id'] ?? 0);
      try {
        $db = getDB();
        $stmt = $db->prepare('SELECT te.id, te.date, te.description, te.hours, te.project_id, te.work_type_id, p.name as project_name, wt.name as work_type_name FROM timesheet_entries te JOIN projects p ON p.id = te.project_id JOIN work_types wt ON wt.id = te.work_type_id WHERE te.submission_id = ? ORDER BY te.date ASC');
        $stmt->execute([$subId]);
        $entries = $stmt->fetchAll();
        jsonOut(['success' => true, 'entries' => $entries]);
      } catch (PDOException $e) {
        jsonError('Failed to load entries.', 500);
      }
      break;
    case 'admin_submission_review':
      requireAdmin();
      verifyCsrf();
      if ($_SERVER['REQUEST_METHOD'] !== 'POST')
        jsonError('Method not allowed', 405);
      $body = json_decode(file_get_contents('php://input'), true) ?? [];
      $id = (int) ($body['id'] ?? 0);
      $status = sanitizeStr($body['status'] ?? '');
      $reason = sanitizeStr($body['reason'] ?? '');
      if (!in_array($status, ['approved', 'rejected']))
        jsonError('Invalid status.');
      try {
        $db = getDB();
        $stmt = $db->prepare('UPDATE timesheet_submissions SET status = ?, rejection_reason = ?, reviewed_at = NOW(), reviewed_by = ? WHERE id = ?');
        $stmt->execute([$status, ($status === 'rejected' ? $reason : null), $_SESSION['user_id'], $id]);
        jsonOut(['success' => true, 'message' => 'Submission ' . $status . '.']);
      } catch (PDOException $e) {
        jsonError('Failed to review submission.', 500);
      }
      break;
    case 'update_profile':
      requireAuth();
      verifyCsrf();
      if ($_SERVER['REQUEST_METHOD'] !== 'POST')
        jsonError('Method not allowed', 405);
      $body = json_decode(file_get_contents('php://input'), true) ?? [];
      $name = sanitizeStr($body['name'] ?? '');
      $email = trim($body['email'] ?? '');
      if (empty($name) || empty($email))
        jsonError('Name and email are required.');
      if (!filter_var($email, FILTER_VALIDATE_EMAIL))
        jsonError('Invalid email.');
      try {
        $db = getDB();
        $stmt = $db->prepare('SELECT id FROM users WHERE email = ? AND id != ?');
        $stmt->execute([$email, $_SESSION['user_id']]);
        if ($stmt->fetch())
          jsonError('Email already in use.');
        $stmt = $db->prepare('UPDATE users SET name = ?, email = ? WHERE id = ?');
        $stmt->execute([$name, $email, $_SESSION['user_id']]);
        $_SESSION['user_name'] = $name;
        $_SESSION['user_email'] = $email;
        jsonOut(['success' => true, 'message' => 'Profile updated.']);
      } catch (PDOException $e) {
        jsonError('Failed to update profile.', 500);
      }
      break;
    case 'upload_avatar':
      requireAuth();
      verifyCsrf();
      if ($_SERVER['REQUEST_METHOD'] !== 'POST')
        jsonError('Method not allowed', 405);
      if (!isset($_FILES['avatar']))
        jsonError('No file uploaded.');
      $file = $_FILES['avatar'];
      if ($file['error'] !== UPLOAD_ERR_OK)
        jsonError('Upload failed.');
      $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
      if (!array_key_exists($file['type'], $allowed))
        jsonError('Invalid file type. Only JPG, PNG, and WebP allowed.');
      if ($file['size'] > 2 * 1024 * 1024)
        jsonError('File too large. Max 2MB.');
      if (!@getimagesize($file['tmp_name']))
        jsonError('Invalid image content. File may be corrupted or malicious.');
      $ext = $allowed[$file['type']];
      $filename = 'avatar_' . $_SESSION['user_id'] . '_' . time() . '.' . $ext;
      $uploadDir = 'uploads/avatars/';
      if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
      }
      if (!file_exists($uploadDir . '.htaccess')) {
        file_put_contents($uploadDir . '.htaccess', "php_flag engine off\nOptions -Indexes\n<FilesMatch \"\.(?i:php|php[0-9]|phtml|cgi|pl|fcgi|inc)\$\">\nOrder allow,deny\nDeny from all\n</FilesMatch>");
      }
      $path = $uploadDir . $filename;
      if (move_uploaded_file($file['tmp_name'], $path)) {
        try {
          $db = getDB();
          $db->prepare('UPDATE users SET avatar_url = ? WHERE id = ?')->execute([$path, $_SESSION['user_id']]);
          jsonOut(['success' => true, 'avatar_url' => $path]);
        } catch (PDOException $e) {
          jsonError('Failed to update database.', 500);
        }
      } else {
        jsonError('Failed to save file.');
      }
      break;
    case 'forgot_password':
      if ($_SERVER['REQUEST_METHOD'] !== 'POST')
        jsonError('Method not allowed', 405);
      checkRateLimit();
      $body = json_decode(file_get_contents('php://input'), true) ?? [];
      $email = trim($body['email'] ?? '');
      if (empty($email))
        jsonError('Email is required.');
      try {
        $db = getDB();
        $stmt = $db->prepare('SELECT id, name FROM users WHERE email = ? AND is_deleted = 0');
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        if ($user) {
          $token = bin2hex(random_bytes(32));
          $stmt = $db->prepare('UPDATE users SET reset_token = ?, reset_expires = DATE_ADD(NOW(), INTERVAL 1 HOUR) WHERE id = ?');
          $stmt->execute([$token, $user['id']]);
          logEmail($email, 'Password Reset - ticktock', 'Hi ' . $user['name'] . ",\n\nUse this token to reset your password: $token\nIt expires in 1 hour.");
        }
        jsonOut(['success' => true, 'message' => 'If that email exists, a reset token has been sent to your inbox.']);
      } catch (PDOException $e) {
        jsonError('Error processing request.', 500);
      }
      break;
    case 'reset_password':
      if ($_SERVER['REQUEST_METHOD'] !== 'POST')
        jsonError('Method not allowed', 405);
      checkRateLimit();
      $body = json_decode(file_get_contents('php://input'), true) ?? [];
      $token = $body['token'] ?? '';
      $newPass = $body['password'] ?? '';
      if (empty($token) || empty($newPass))
        jsonError('Token and password required.');
      if (strlen($newPass) < 8)
        jsonError('Min 8 characters required.');
      try {
        $db = getDB();
        $stmt = $db->prepare('SELECT id FROM users WHERE reset_token = ? AND reset_expires > NOW() AND is_deleted = 0');
        $stmt->execute([$token]);
        $user = $stmt->fetch();
        if (!$user)
          jsonError('Invalid or expired token.');
        $hash = password_hash($newPass, PASSWORD_BCRYPT, ['cost' => 12]);
        $stmt = $db->prepare('UPDATE users SET password_hash = ?, reset_token = NULL, reset_expires = NULL WHERE id = ?');
        $stmt->execute([$hash, $user['id']]);
        jsonOut(['success' => true, 'message' => 'Password reset successfully. You can now login.']);
      } catch (PDOException $e) {
        jsonError('Reset failed.', 500);
      }
      break;
    case 'change_password':
      requireAuth();
      verifyCsrf();
      if ($_SERVER['REQUEST_METHOD'] !== 'POST')
        jsonError('Method not allowed', 405);
      $body = json_decode(file_get_contents('php://input'), true) ?? [];
      $current = $body['current_password'] ?? '';
      $newPass = $body['new_password'] ?? '';
      $confirm = $body['confirm_password'] ?? '';
      if (empty($current) || empty($newPass) || empty($confirm))
        jsonError('All fields are required.');
      if (strlen($newPass) < 8)
        jsonError('New password must be at least 8 characters.');
      if (!preg_match('/[!@#$%^&*(),.?":{}|<>_\-+=\[\]\\\\\\/]/', $newPass))
        jsonError('Password must contain at least one special character.');
      if ($newPass !== $confirm)
        jsonError('Passwords do not match.');
      try {
        $db = getDB();
        $stmt = $db->prepare('SELECT password_hash FROM users WHERE id = ?');
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
        if (!$user || !password_verify($current, $user['password_hash']))
          jsonError('Current password is incorrect.');
        $hash = password_hash($newPass, PASSWORD_BCRYPT, ['cost' => 12]);
        $db->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([$hash, $_SESSION['user_id']]);
        jsonOut(['success' => true, 'message' => 'Password changed successfully.']);
      } catch (PDOException $e) {
        jsonError('Failed to change password.', 500);
      }
      break;
    case 'admin_settings':
      requireAdmin();
      if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        try {
          $db = getDB();
          $settings = $db->query('SELECT setting_key, setting_value FROM system_settings')->fetchAll(PDO::FETCH_KEY_PAIR);
          jsonOut(['success' => true, 'settings' => $settings]);
        } catch (PDOException $e) {
          jsonError('Failed to load settings.', 500);
        }
      } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verifyCsrf();
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        try {
          $db = getDB();
          $stmt = $db->prepare('INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?');
          foreach ($body as $key => $val) {
            $stmt->execute([$key, (string) $val, (string) $val]);
          }
          jsonOut(['success' => true, 'message' => 'Settings updated successfully.']);
        } catch (PDOException $e) {
          jsonError('Failed to save settings.', 500);
        }
      }
      break;
    case 'reports':
      requireAdmin();
      $start = $_GET['start'] ?? date('Y-m-01');
      $end = $_GET['end'] ?? date('Y-m-t');
      try {
        $db = getDB();
        $stmt = $db->prepare('SELECT p.name as project_name, SUM(te.hours) as total_hours FROM timesheet_entries te JOIN projects p ON p.id = te.project_id WHERE te.date BETWEEN ? AND ? GROUP BY p.id ORDER BY total_hours DESC');
        $stmt->execute([$start, $end]);
        $projectStats = $stmt->fetchAll();
        $stmt = $db->prepare('SELECT u.name as user_name, SUM(te.hours) as total_hours FROM timesheet_entries te JOIN users u ON u.id = te.user_id WHERE te.date BETWEEN ? AND ? GROUP BY u.id ORDER BY total_hours DESC');
        $stmt->execute([$start, $end]);
        $userStats = $stmt->fetchAll();
        jsonOut(['success' => true, 'projects' => $projectStats, 'users' => $userStats]);
      } catch (PDOException $e) {
        jsonError('Failed to load reports.', 500);
      }
      break;
    case 'export_csv':
      requireAuth();
      $userId = ($_SESSION['user_role'] === 'admin' && isset($_GET['user_id'])) ? (int) $_GET['user_id'] : $_SESSION['user_id'];
      $start = $_GET['start'] ?? date('Y-01-01');
      $end = $_GET['end'] ?? date('Y-12-31');
      try {
        $db = getDB();
        $stmt = $db->prepare('SELECT te.date, p.name as project, wt.name as type, te.hours, te.description FROM timesheet_entries te JOIN projects p ON p.id = te.project_id JOIN work_types wt ON wt.id = te.work_type_id WHERE te.user_id = ? AND te.date BETWEEN ? AND ? ORDER BY te.date ASC');
        $stmt->execute([$userId, $start, $end]);
        $rows = $stmt->fetchAll();
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="timesheet_export_' . date('Ymd') . '.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Date', 'Project', 'Work Type', 'Hours', 'Description']);
        foreach ($rows as $r) {
          fputcsv($out, $r);
        }
        fclose($out);
        exit;
      } catch (PDOException $e) {
        jsonError('Export failed.', 500);
      }
      break;
    case 'manifest':
      header('Content-Type: application/manifest+json');
      $icon = 'data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg" width="512" height="512" viewBox="0 0 512 512"><rect width="512" height="512" fill="#2563EB"/><text x="256" y="290" font-family="sans-serif" font-size="200" font-weight="bold" fill="#fff" text-anchor="middle">tt</text></svg>');
      echo json_encode(['name'=>'ticktock','short_name'=>'ticktock','start_url'=>'.','display'=>'standalone','background_color'=>'#2563EB','theme_color'=>'#2563EB','icons'=>[['src'=>$icon,'sizes'=>'512x512','type'=>'image/svg+xml']]]);
      exit;
    case 'sw':
      header('Content-Type: application/javascript');
      echo "self.addEventListener('install', e => self.skipWaiting()); self.addEventListener('activate', e => e.waitUntil(clients.claim())); self.addEventListener('fetch', e => {});";
      exit;
    default:
      jsonError('Unknown API endpoint.', 404);
  }
  exit;
}
$isLoggedIn = !empty($_SESSION['user_id']);
$csrf = csrfToken();
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ticktock — Timesheet Management</title>
<link rel="manifest" href="?api=manifest">
<meta name="theme-color" content="#2563EB">
<meta name="apple-mobile-web-app-capable" content="yes">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --blue:#2563EB;
  --blue-hover:#1D4ED8;
  --blue-light:#EFF6FF;
  --blue-mid:#BFDBFE;
  --text-primary:#111827;
  --text-secondary:#6B7280;
  --text-muted:#9CA3AF;
  --bg:#F9FAFB;
  --surface:#FFFFFF;
  --border:#E5E7EB;
  --border-light:#F3F4F6;
  --green:#059669;
  --green-bg:#D1FAE5;
  --yellow:#D97706;
  --yellow-bg:#FEF3C7;
  --pink:#DB2777;
  --pink-bg:#FCE7F3;
  --red:#DC2626;
  --shadow-sm:0 1px 2px rgba(0,0,0,.06);
  --shadow:0 1px 3px rgba(0,0,0,.1),0 1px 2px rgba(0,0,0,.06);
  --shadow-md:0 4px 6px -1px rgba(0,0,0,.1),0 2px 4px -1px rgba(0,0,0,.06);
  --shadow-lg:0 10px 15px -3px rgba(0,0,0,.1),0 4px 6px -2px rgba(0,0,0,.05);
  --radius-sm:6px;
  --radius:10px;
  --radius-lg:16px;
  --radius-xl:24px;
  --font:'Inter',sans-serif;
}
html{font-size:16px;scroll-behavior:smooth;overflow-x:hidden}
body{font-family:var(--font);background:var(--bg);color:var(--text-primary);line-height:1.5;min-height:100vh;-webkit-font-smoothing:antialiased;overflow-x:hidden}
a{color:var(--blue);text-decoration:none}
a:hover{text-decoration:underline}
button{font-family:var(--font);cursor:pointer;border:none;outline:none}
input,select,textarea{font-family:var(--font)}
#app-login{display:none;min-height:100vh;align-items:stretch}
#app-login.active{display:flex}
.login-left{flex:0 0 45%;background:#fff;display:flex;flex-direction:column;justify-content:center;align-items:center;padding:3rem 4rem;min-height:100vh}
.login-right{flex:1;background:var(--blue);display:flex;flex-direction:column;justify-content:center;align-items:flex-start;padding:4rem;position:relative;overflow:hidden}
.login-right::before{content:'';position:absolute;width:400px;height:400px;background:rgba(255,255,255,.08);border-radius:50%;top:-100px;right:-100px}
.login-right::after{content:'';position:absolute;width:300px;height:300px;background:rgba(255,255,255,.05);border-radius:50%;bottom:-80px;left:-60px}
.login-form-wrap{width:100%;max-width:380px}
.login-form-wrap h2{font-size:1.75rem;font-weight:700;color:var(--text-primary);margin-bottom:.5rem}
.login-form-wrap p{color:var(--text-secondary);font-size:.9rem;margin-bottom:2rem}
.login-brand{font-size:2.5rem;font-weight:800;color:#fff;letter-spacing:-1px;margin-bottom:1.5rem;position:relative;z-index:1}
.login-brand-tagline{color:rgba(255,255,255,.85);font-size:.95rem;line-height:1.7;max-width:340px;position:relative;z-index:1}
.form-group{margin-bottom:1.25rem}
.form-group label{display:block;font-size:.8rem;font-weight:600;color:var(--text-secondary);margin-bottom:.5rem;letter-spacing:.02em;text-transform:uppercase}
.form-control{width:100%;padding:.75rem 1rem;border:1.5px solid var(--border);border-radius:var(--radius-sm);font-size:16px;color:var(--text-primary);background:#fff;transition:border-color .2s,box-shadow .2s}
.form-control:focus{outline:none;border-color:var(--blue);box-shadow:0 0 0 3px rgba(37,99,235,.15)}
.form-control.error{border-color:var(--red)}
.check-wrap{display:flex;align-items:center;gap:.5rem;margin-bottom:1.5rem}
.check-wrap input[type=checkbox]{width:16px;height:16px;accent-color:var(--blue)}
.check-wrap label{font-size:.875rem;color:var(--text-secondary);cursor:pointer}
.btn-primary{width:100%;padding:.85rem 1.5rem;background:var(--blue);color:#fff;border-radius:var(--radius-sm);font-size:.9rem;font-weight:600;letter-spacing:.01em;transition:background .2s,transform .1s,box-shadow .2s;box-shadow:0 1px 3px rgba(37,99,235,.4)}
.btn-primary:hover{background:var(--blue-hover);box-shadow:0 4px 12px rgba(37,99,235,.4)}
.btn-primary:active{transform:scale(.98)}
.btn-primary:disabled{opacity:.6;cursor:not-allowed}
.btn-outline{padding:.75rem 1.5rem;background:#fff;color:var(--text-primary);border:1.5px solid var(--border);border-radius:var(--radius-sm);font-size:.875rem;font-weight:500;transition:all .2s}
.btn-outline:hover{border-color:var(--text-secondary);background:var(--bg)}
.btn-blue-sm{padding:.5rem 1.25rem;background:var(--blue);color:#fff;border-radius:var(--radius-sm);font-size:.8rem;font-weight:600;transition:background .2s}
.btn-blue-sm:hover{background:var(--blue-hover)}
.btn-ghost{background:transparent;color:var(--blue);font-size:.875rem;font-weight:500;padding:.25rem .5rem;border-radius:var(--radius-sm);transition:background .2s}
.btn-ghost:hover{background:var(--blue-light)}
.captcha-wrap{display:flex;align-items:center;gap:.75rem;margin-bottom:1.25rem}
.captcha-img{border-radius:var(--radius-sm);border:1.5px solid var(--border);height:48px;object-fit:contain;background:#f8f9fa;cursor:pointer}
.captcha-input{flex:1;padding:.65rem 1rem;border:1.5px solid var(--border);border-radius:var(--radius-sm);font-size:16px;min-width:0}
.captcha-input:focus{outline:none;border-color:var(--blue);box-shadow:0 0 0 3px rgba(37,99,235,.15)}
.field-error{font-size:.75rem;color:var(--red);margin-top:.35rem;display:none}
.field-error.visible{display:block}
#app-main{display:none}
#app-main.active{display:block}
.topbar{background:#fff;border-bottom:1px solid var(--border);height:64px;display:flex;align-items:center;justify-content:space-between;padding:0 2rem;position:sticky;top:0;z-index:100;box-shadow:var(--shadow-sm)}
.topbar-brand{font-size:1.35rem;font-weight:800;color:var(--text-primary);letter-spacing:-0.5px}
.topbar-section{font-size:.875rem;font-weight:500;color:var(--text-secondary);margin-left:1.5rem}
.topbar-left{display:flex;align-items:center}
.topbar-right{position:relative}
.user-btn{display:flex;align-items:center;gap:.5rem;background:none;border:none;cursor:pointer;padding:.5rem .75rem;border-radius:var(--radius-sm);transition:background .2s;color:var(--text-primary);font-size:.875rem;font-weight:500}
.user-btn:hover{background:var(--bg)}
.user-avatar{width:32px;height:32px;border-radius:50%;background:var(--blue);color:#fff;display:flex;align-items:center;justify-content:center;font-size:.75rem;font-weight:700}
.user-dropdown{position:absolute;right:0;top:calc(100% + 8px);background:#fff;border:1px solid var(--border);border-radius:var(--radius);box-shadow:var(--shadow-lg);min-width:200px;display:none;z-index:200;padding:.5rem 0;animation:fadeInDown .15s ease}
.user-dropdown.open{display:block}
.user-dropdown-item{padding:.65rem 1.25rem;font-size:.875rem;cursor:pointer;transition:background .15s;color:var(--text-primary);display:flex;align-items:center;gap:.625rem}
.user-dropdown-item:hover{background:var(--bg)}
.user-dropdown-item.danger{color:var(--red)}
.user-dropdown-divider{height:1px;background:var(--border);margin:.35rem 0}
@keyframes fadeInDown{from{opacity:0;transform:translateY(-6px)}to{opacity:1;transform:translateY(0)}}
.page{display:none;padding:2rem;max-width:1100px;margin:0 auto}
.page.active{display:block}
.page-title{font-size:1.6rem;font-weight:700;color:var(--text-primary);margin-bottom:1.5rem}
.filters-bar{display:flex;align-items:center;gap:.75rem;margin-bottom:1.5rem;flex-wrap:wrap}
.filter-select{padding:.6rem 1rem;border:1.5px solid var(--border);border-radius:var(--radius-sm);font-size:.8rem;color:var(--text-primary);background:#fff;min-width:140px;cursor:pointer}
.filter-select:focus{outline:none;border-color:var(--blue)}
.filter-date-btn{display:flex;align-items:center;gap:.5rem;padding:.6rem 1rem;border:1.5px solid var(--border);border-radius:var(--radius-sm);font-size:.8rem;color:var(--text-primary);background:#fff;cursor:pointer;min-width:180px;transition:border-color .2s}
.filter-date-btn:hover{border-color:var(--blue)}
.filter-date-btn svg{width:14px;height:14px;color:var(--text-secondary)}
.card{background:#fff;border-radius:var(--radius-lg);border:1px solid var(--border);box-shadow:var(--shadow-sm);overflow:hidden}
.table-wrap{overflow-x:auto}
table{width:100%;border-collapse:collapse}
thead th{padding:.875rem 1.25rem;text-align:left;font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--text-muted);border-bottom:1px solid var(--border);border-right:1px solid var(--border);background:#FAFAFA;white-space:nowrap}
thead th:last-child{border-right:none}
thead th.sortable{cursor:pointer;user-select:none}
thead th.sortable:hover{color:var(--text-secondary)}
thead th .sort-arrow{margin-left:.25rem;opacity:.4}
thead th.sorted .sort-arrow{opacity:1;color:var(--blue)}
tbody tr{border-bottom:1px solid var(--border-light);transition:background .15s}
tbody tr:last-child{border-bottom:none}
tbody tr:hover{background:#FAFBFF}
tbody td{padding:.875rem 1.25rem;font-size:.875rem;color:var(--text-primary);vertical-align:middle}
.badge{display:inline-flex;align-items:center;padding:.25rem .75rem;border-radius:999px;font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em}
.badge-completed{background:var(--green-bg);color:var(--green)}
.badge-incomplete{background:var(--yellow-bg);color:var(--yellow)}
.badge-missing{background:var(--pink-bg);color:var(--pink)}
.action-link{color:var(--blue);font-size:.8rem;font-weight:600;cursor:pointer;background:none;border:none;padding:.25rem .5rem;border-radius:var(--radius-sm);transition:background .15s;display:inline}
.action-link:hover{background:var(--blue-light)}
.pagination{display:flex;align-items:center;justify-content:space-between;padding:1rem 1.25rem;border-top:1px solid var(--border);flex-wrap:wrap;gap:.5rem}
.per-page{display:flex;align-items:center;gap:.5rem;font-size:.8rem;color:var(--text-secondary)}
.per-page select{padding:.35rem .6rem;border:1px solid var(--border);border-radius:var(--radius-sm);font-size:.8rem}
.page-btns{display:flex;align-items:center;gap:.25rem}
.page-btn{min-width:32px;height:32px;display:flex;align-items:center;justify-content:center;border-radius:var(--radius-sm);font-size:.8rem;font-weight:500;background:none;color:var(--text-secondary);transition:all .15s;border:1px solid transparent;cursor:pointer}
.page-btn:hover{background:var(--blue-light);color:var(--blue)}
.page-btn.active{background:var(--blue);color:#fff;border-color:var(--blue)}
.page-btn:disabled{opacity:.4;cursor:not-allowed}
.page-btn.dots{cursor:default;pointer-events:none}
.detail-header{display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:1.75rem;flex-wrap:wrap;gap:1rem}
.detail-title{font-size:1.6rem;font-weight:700;color:var(--text-primary)}
.detail-range{font-size:.9rem;color:var(--text-secondary);margin-top:.25rem}
.progress-wrap{text-align:right}
.progress-label{font-size:.875rem;font-weight:600;color:var(--text-primary);margin-bottom:.4rem}
.progress-bar-outer{width:200px;height:8px;background:var(--border);border-radius:999px;overflow:hidden}
.progress-bar-inner{height:100%;background:var(--blue);border-radius:999px;transition:width .5s ease}
.progress-bar-inner.complete{background:var(--green)}
.day-group{margin-bottom:1.25rem}
.day-label{font-size:.8rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.06em;padding:.75rem 1.25rem;border-bottom:1px solid var(--border-light);background:#FAFAFA}
.entry-row{display:flex;align-items:center;padding:.875rem 1.25rem;border-bottom:1px solid var(--border-light);gap:1rem;transition:background .15s}
.entry-row:last-of-type{border-bottom:none}
.entry-row:hover{background:#FAFBFF}
.entry-desc{flex:1;font-size:.875rem;color:var(--text-primary);line-height:1.5}
.entry-hours{font-size:.8rem;font-weight:700;color:var(--text-secondary);white-space:nowrap}
.project-chip{padding:.25rem .75rem;background:var(--blue-light);color:var(--blue);border-radius:999px;font-size:.7rem;font-weight:600;white-space:nowrap}
.entry-menu-wrap{position:relative}
.entry-menu-btn{width:28px;height:28px;display:flex;align-items:center;justify-content:center;border-radius:var(--radius-sm);background:none;color:var(--text-muted);transition:all .15s;flex-shrink:0}
.entry-menu-btn:hover{background:var(--bg);color:var(--text-secondary)}
.entry-menu-dropdown{position:absolute;right:0;top:calc(100% + 4px);background:#fff;border:1px solid var(--border);border-radius:var(--radius);box-shadow:var(--shadow-md);min-width:110px;display:none;z-index:50;overflow:hidden}
.entry-menu-dropdown.open{display:block}
.entry-menu-item{padding:.6rem 1rem;font-size:.8rem;cursor:pointer;transition:background .15s;display:flex;align-items:center;gap:.5rem}
.entry-menu-item:hover{background:var(--bg)}
.entry-menu-item.del{color:var(--red)}
.add-task-row{padding:.75rem 1.25rem}
.btn-add-task{display:flex;align-items:center;gap:.4rem;color:var(--blue);font-size:.8rem;font-weight:600;background:none;border:none;cursor:pointer;padding:.4rem .5rem;border-radius:var(--radius-sm);transition:background .15s}
.btn-add-task:hover{background:var(--blue-light)}
.btn-back{display:flex;align-items:center;gap:.4rem;color:var(--text-secondary);font-size:.8rem;font-weight:500;background:none;border:none;cursor:pointer;padding:.4rem .5rem;border-radius:var(--radius-sm);transition:all .15s;margin-bottom:1.25rem}
.btn-back:hover{color:var(--blue);background:var(--blue-light)}
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(17,24,39,.5);z-index:1000;align-items:center;justify-content:center;padding:1rem;backdrop-filter:blur(2px)}
.modal-overlay.open{display:flex}
.modal-box{background:#fff;border-radius:var(--radius-xl);box-shadow:var(--shadow-lg);width:100%;max-width:480px;overflow:hidden}
.modal-header{padding:1.5rem 1.75rem 1rem;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid var(--border)}
.modal-title{font-size:1.1rem;font-weight:700;color:var(--text-primary)}
.modal-close{width:32px;height:32px;display:flex;align-items:center;justify-content:center;border-radius:var(--radius-sm);background:none;color:var(--text-muted);transition:all .15s;cursor:pointer;font-size:1.2rem;line-height:1}
.modal-close:hover{background:var(--bg);color:var(--text-primary)}
.modal-body{padding:1.5rem 1.75rem}
.modal-footer{padding:1rem 1.75rem 1.5rem;display:flex;gap:.75rem;justify-content:flex-end}
.modal-footer .btn-primary{width:auto;flex:1}
.modal-footer .btn-outline{flex:0 0 auto}
.hours-input-wrap{display:flex;align-items:center;gap:.75rem}
.hours-stepper{display:flex;align-items:center;gap:.5rem}
.stepper-btn{width:32px;height:32px;display:flex;align-items:center;justify-content:center;border:1.5px solid var(--border);border-radius:var(--radius-sm);background:#fff;font-size:1rem;color:var(--text-secondary);cursor:pointer;transition:all .15s;flex-shrink:0;line-height:1}
.stepper-btn:hover{border-color:var(--blue);color:var(--blue);background:var(--blue-light)}
#modal-hours{width:70px;text-align:center;padding:.6rem .5rem;border:1.5px solid var(--border);border-radius:var(--radius-sm);font-size:16px;font-weight:600}
#modal-hours:focus{outline:none;border-color:var(--blue);box-shadow:0 0 0 3px rgba(37,99,235,.15)}
.cp-modal-overlay{display:none;position:fixed;inset:0;background:rgba(17,24,39,.5);z-index:2000;align-items:center;justify-content:center;padding:1rem;backdrop-filter:blur(2px)}
.cp-modal-overlay.open{display:flex}
.cp-modal-box{background:#fff;border-radius:var(--radius-xl);box-shadow:var(--shadow-lg);width:100%;max-width:420px;overflow:hidden}
.footer-bar{text-align:center;padding:2rem 1rem 1.25rem;color:var(--text-muted);font-size:.78rem}
.footer-bar a{color:var(--blue)}
.print-footer{display:none}
.loading-spinner{display:inline-block;width:18px;height:18px;border:2px solid rgba(255,255,255,.4);border-top-color:#fff;border-radius:50%;animation:spin .7s linear infinite;vertical-align:middle;margin-right:.4rem}
@keyframes spin{to{transform:rotate(360deg)}}
.skeleton{background:linear-gradient(90deg,#f0f0f0 25%,#e8e8e8 50%,#f0f0f0 75%);background-size:200% 100%;animation:shimmer 1.4s infinite;border-radius:var(--radius-sm)}
@keyframes shimmer{to{background-position:-200% 0}}
.skeleton-row td{padding:.875rem 1.25rem}
.skeleton-cell{height:14px}
.empty-state{text-align:center;padding:3rem 1rem;color:var(--text-secondary)}
.empty-icon{font-size:2.5rem;margin-bottom:.75rem;opacity:.5}
.empty-state h3{font-size:1rem;font-weight:600;color:var(--text-primary);margin-bottom:.35rem}
.empty-state p{font-size:.875rem}
@media(max-width:900px){
  .login-right{display:none}
  .login-left{flex:1;padding:2.5rem 2rem;min-height:100vh}
  .page{padding:1.25rem}
  .topbar{padding:0 1.25rem}
  .progress-bar-outer{width:130px}
  .detail-header{flex-direction:column;gap:.75rem}
  .progress-wrap{text-align:left}
}
@media(max-width:640px){
  .login-left{padding:1.5rem 1rem}
  .login-form-wrap h2{font-size:1.5rem}
  .captcha-wrap{flex-wrap:wrap;gap:0.5rem}
  .captcha-input{width:100%;flex:none}
  .topbar{padding:0 1rem}
  .topbar-section{display:none}
  .page-title{font-size:1.35rem}
  .table-wrap{-webkit-overflow-scrolling:touch}
  thead th,tbody td{padding:.75rem 1rem}
  .entry-row{flex-wrap:wrap}
  .filters-bar{flex-direction:column;align-items:stretch}
  .filter-date-btn,.filter-select{width:100%}
  .pagination{flex-direction:column;align-items:flex-start;gap:1rem}
  .modal-header{padding:1.25rem 1.25rem 1rem}
  .modal-body{padding:1.25rem}
  .modal-footer{padding:1rem 1.25rem 1.25rem;flex-direction:column;gap:0.5rem}
  .modal-footer .btn-outline{width:100%}
}
@media print{
  .topbar,.filters-bar,.pagination,.btn-back,.entry-menu-wrap,.btn-add-task,.footer-bar{display:none!important}
  body{background:#fff}
  .card{box-shadow:none;border:1px solid #ddd}
  .print-footer{display:block;text-align:center;margin-top:2rem;padding-top:1rem;border-top:1px solid #ddd;font-size:.8rem;color:#666}
}
.flatpickr-wrapper{position:relative}
</style>
</head>
<body>
<div id="app-login">
  <div class="login-left">
    <form class="login-form-wrap animate__animated animate__fadeInUp" id="login-form-wrap" onsubmit="event.preventDefault();">
      <h2>Welcome back</h2>
      <p>Sign in to your ticktock account</p>
      <div class="form-group">
        <label for="login-email">Email</label>
        <input type="email" id="login-email" class="form-control" placeholder="name@example.com" autocomplete="username">
        <div class="field-error" id="err-email"></div>
      </div>
      <div class="form-group">
        <label for="login-password">Password</label>
        <input type="password" id="login-password" class="form-control" placeholder="••••••••" autocomplete="current-password">
        <div class="field-error" id="err-password"></div>
      </div>
      <div class="form-group">
        <label>Security Check</label>
        <div class="captcha-wrap">
          <img id="captcha-img" class="captcha-img" src="" alt="CAPTCHA" title="Click to refresh" style="min-width:110px">
          <input type="number" id="captcha-answer" class="captcha-input" placeholder="Answer">
        </div>
        <div class="field-error" id="err-captcha"></div>
      </div>
      <div class="check-wrap">
        <input type="checkbox" id="remember-me">
        <label for="remember-me">Remember me</label>
        <button type="button" class="btn-ghost" id="btn-show-forgot" style="margin-left:auto">Forgot password?</button>
      </div>
      <button type="submit" class="btn-primary" id="btn-signin">Sign in</button>
      <div class="field-error visible" id="err-login" style="margin-top:.75rem;font-size:.8rem"></div>
      <div style="margin-top:1.5rem;text-align:center;font-size:.875rem;color:var(--text-secondary)">
        Don't have an account? <button type="button" class="btn-ghost" id="btn-show-register">Register here</button>
      </div>
      <div style="text-align:center;margin-top:1rem">
        <button type="button" class="btn-outline" id="btn-install-pwa" style="display:none;width:100%;font-weight:600"><svg style="width:16px;height:16px;vertical-align:middle;margin-right:6px" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg> Install App</button>
      </div>
    </form>
    <div class="login-form-wrap animate__animated animate__fadeInUp" id="register-wrap" style="display:none">
      <h2>Create account</h2>
      <p>Join ticktock to manage your timesheets</p>
      <div class="form-group">
        <label for="reg-name">Full Name</label>
        <input type="text" id="reg-name" class="form-control" placeholder="John Doe">
        <div class="field-error" id="err-reg-name"></div>
      </div>
      <div class="form-group">
        <label for="reg-email">Email</label>
        <input type="email" id="reg-email" class="form-control" placeholder="name@example.com">
        <div class="field-error" id="err-reg-email"></div>
      </div>
      <div class="form-group">
        <label for="reg-password">Password</label>
        <input type="password" id="reg-password" class="form-control" placeholder="Min 8 characters">
        <div class="field-error" id="err-reg-password"></div>
      </div>
      <div class="form-group">
        <label for="reg-confirm">Confirm Password</label>
        <input type="password" id="reg-confirm" class="form-control" placeholder="Repeat password">
        <div class="field-error" id="err-reg-confirm"></div>
      </div>
      <div class="form-group">
        <label>Security Check</label>
        <div class="captcha-wrap">
          <img class="captcha-img reg-captcha-img" src="" alt="CAPTCHA" title="Click to refresh" style="min-width:110px">
          <input type="number" id="reg-captcha-answer" class="captcha-input" placeholder="Answer">
        </div>
        <div class="field-error" id="err-reg-captcha"></div>
      </div>
      <button class="btn-primary" id="btn-register">Create Account</button>
      <div style="margin-top:1.5rem;text-align:center;font-size:.875rem;color:var(--text-secondary)">
        Already have an account? <button class="btn-ghost" id="btn-show-login">Sign in</button>
      </div>
    </div>
  </div>
  <div class="login-right">
    <div class="login-brand animate__animated animate__fadeIn">ticktock</div>
    <p class="login-brand-tagline animate__animated animate__fadeIn animate__delay-1s">Introducing ticktock, our cutting-edge timesheet web application designed to revolutionize how you manage employee work hours. With ticktock, you can effortlessly track and monitor employee attendance and productivity from anywhere, anytime, using any internet-connected device.</p>
  </div>
</div>
<div id="app-main">
  <header class="topbar">
    <div class="topbar-left">
      <span class="topbar-brand">ticktock</span>
      <span class="topbar-section">Timesheets</span>
    </div>
    <div class="topbar-right">
      <button class="user-btn" id="user-menu-btn">
        <div class="user-avatar" id="user-avatar-initials">JD</div>
        <span id="topbar-username">John Doe</span>
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg>
      </button>
      <div class="user-dropdown" id="user-dropdown">
        <div class="user-dropdown-item" id="btn-show-profile">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
          My Profile
        </div>
        <div class="user-dropdown-divider"></div>
        <div class="user-dropdown-item" id="btn-admin-users" style="display:none">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
          Manage Users
        </div>
        <div class="user-dropdown-item" id="btn-admin-projects" style="display:none">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>
          Manage Projects
        </div>
        <div class="user-dropdown-item" id="btn-admin-work-types" style="display:none">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
          Manage Work Types
        </div>
        <div class="user-dropdown-item" id="btn-admin-overview-menu" style="display:none">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="9" y1="21" x2="9" y2="9"/></svg>
          Timesheet Overview
        </div>
        <div class="user-dropdown-item" id="btn-admin-subs" style="display:none">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
          Submissions
        </div>
        <div class="user-dropdown-item" id="btn-admin-reports" style="display:none">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
          Global Reports
        </div>
        <div class="user-dropdown-item" id="btn-admin-settings" style="display:none">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
          System Settings
        </div>
        <div class="user-dropdown-divider"></div>
        <div class="user-dropdown-item" id="btn-change-password">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
          Change Password
        </div>
        <div class="user-dropdown-divider"></div>
        <div class="user-dropdown-item danger" id="btn-logout">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
          Sign Out
        </div>
      </div>
    </div>
  </header>
  <div id="page-admin" class="page">
    <button class="btn-back" id="btn-back-from-admin">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"/></svg>
      Back to Timesheets
    </button>
    <div class="page-title">User Management</div>
    <div class="card animate__animated animate__fadeInUp">
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>USER</th>
              <th>EMAIL</th>
              <th>ROLE</th>
              <th>STD HOURS</th>
              <th>STATUS</th>
              <th>ACTIONS</th>
            </tr>
          </thead>
          <tbody id="admin-users-tbody"></tbody>
        </table>
      </div>
    </div>
  </div>
  <div id="page-timesheets" class="page active">
    <div class="page-title animate__animated animate__fadeIn">Your Timesheets</div>
    <div class="filters-bar">
      <button class="filter-date-btn" id="date-range-btn">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
        <span id="date-range-label">Date Range</span>
      </button>
      <input type="text" id="date-range-picker" style="position:absolute;opacity:0;pointer-events:none;width:0;height:0">
      <select class="filter-select" id="status-filter">
        <option value="">All Statuses</option>
        <option value="completed">Completed</option>
        <option value="incomplete">Incomplete</option>
        <option value="missing">Missing</option>
      </select>
    </div>
    <div class="card animate__animated animate__fadeInUp">
      <div class="table-wrap">
        <table id="timesheets-table">
          <thead>
            <tr>
              <th class="sortable" data-col="week_num">WEEK # <span class="sort-arrow">↕</span></th>
              <th class="sortable" data-col="date_label">DATE <span class="sort-arrow">↕</span></th>
              <th>STATUS</th>
              <th>ACTIONS</th>
            </tr>
          </thead>
          <tbody id="timesheets-tbody"></tbody>
        </table>
      </div>
      <div class="pagination">
        <div class="per-page">
          <span>Show</span>
          <select id="per-page-select">
            <option value="5" selected>5 per page</option>
            <option value="10">10 per page</option>
            <option value="25">25 per page</option>
            <option value="50">50 per page</option>
            <option value="100">100 per page</option>
            <option value="999999">All</option>
          </select>
        </div>
        <div class="page-btns" id="page-btns"></div>
      </div>
    </div>
    <div class="footer-bar">
      © 2026 tentwenty. All rights reserved. &nbsp;|&nbsp;
      Created by: <strong>Yasin Ullah</strong> – Bannu Software Solutions &nbsp;|&nbsp;
      <a href="https://www.yasinbss.com" target="_blank" rel="noopener">www.yasinbss.com</a> &nbsp;|&nbsp;
      WhatsApp: <a href="https://wa.me/923361593533">03361593533</a>
    </div>
  </div>
  <div id="page-detail" class="page">
    <button class="btn-back" id="btn-back-to-list">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"/></svg>
      Back to Timesheets
    </button>
    <div class="detail-header">
      <div>
        <div class="detail-title">This week's timesheet</div>
        <div class="detail-range" id="detail-range-label"></div>
        <div style="margin-top:0.75rem;display:flex;gap:0.5rem">
            <button class="btn-blue-sm" id="btn-submit-week">Submit for Approval</button>
            <button class="btn-outline" style="padding:0.4rem 0.75rem;font-size:0.75rem" id="btn-export-week">Export CSV</button>
        </div>
      </div>
      <div class="progress-wrap">
        <div class="progress-label" id="progress-label">0/40 hrs</div>
        <div class="progress-bar-outer">
          <div class="progress-bar-inner" id="progress-bar" style="width:0%"></div>
        </div>
      </div>
    </div>
    <div class="card animate__animated animate__fadeInUp" id="entries-container"></div>
    <div class="footer-bar">
      © 2026 tentwenty. All rights reserved. &nbsp;|&nbsp;
      Created by: <strong>Yasin Ullah</strong> – Bannu Software Solutions &nbsp;|&nbsp;
      <a href="https://www.yasinbss.com" target="_blank" rel="noopener">www.yasinbss.com</a> &nbsp;|&nbsp;
      WhatsApp: <a href="https://wa.me/923361593533">03361593533</a>
    </div>
    <div class="print-footer">
      Created by: Yasin Ullah – Bannu Software Solutions | www.yasinbss.com | WhatsApp: 03361593533
    </div>
  </div>
  <div id="page-admin-projects" class="page">
    <button class="btn-back btn-back-to-admin">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"/></svg>
      Back to Admin
    </button>
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem">
        <div class="page-title" style="margin-bottom:0">Manage Projects</div>
        <button class="btn-blue-sm" id="btn-add-project">+ Add Project</button>
    </div>
    <div class="card">
      <div class="table-wrap">
        <table>
          <thead><tr><th>NAME</th><th>STATUS</th><th>ACTIONS</th></tr></thead>
          <tbody id="projects-mgmt-tbody"></tbody>
        </table>
      </div>
    </div>
  </div>
  <div id="page-admin-work-types" class="page">
    <button class="btn-back btn-back-to-admin">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"/></svg>
      Back to Admin
    </button>
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem">
        <div class="page-title" style="margin-bottom:0">Manage Work Types</div>
        <button class="btn-blue-sm" id="btn-add-work-type">+ Add Type</button>
    </div>
    <div class="card">
      <div class="table-wrap">
        <table>
          <thead><tr><th>NAME</th><th>STATUS</th><th>ACTIONS</th></tr></thead>
          <tbody id="work-types-mgmt-tbody"></tbody>
        </table>
      </div>
    </div>
  </div>
  <div id="page-admin-overview" class="page">
    <button class="btn-back btn-back-to-admin">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"/></svg>
      Back to Admin
    </button>
    <div class="page-title">Timesheet Overview</div>
    <div class="filters-bar">
        <input type="text" id="overview-date-picker" class="filter-select" placeholder="Select Date Range" style="min-width:220px">
        <select class="filter-select" id="overview-user-filter"><option value="">All Users</option></select>
        <select class="filter-select" id="overview-status-filter">
            <option value="">All Statuses</option>
            <option value="approved">Approved</option>
            <option value="pending">Pending</option>
            <option value="completed">Completed</option>
            <option value="incomplete">Incomplete</option>
            <option value="missing">Missing</option>
            <option value="rejected">Rejected</option>
        </select>
    </div>
    <div class="card">
      <div class="table-wrap">
        <table>
          <thead><tr><th>USER</th><th>WEEK</th><th>HOURS</th><th>STATUS</th><th>ACTIONS</th></tr></thead>
          <tbody id="overview-mgmt-tbody"></tbody>
        </table>
      </div>
    </div>
  </div>
  <div id="page-admin-submissions" class="page">
    <button class="btn-back btn-back-to-admin">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"/></svg>
      Back to Admin
    </button>
    <div class="page-title">Pending Submissions</div>
    <div class="card">
      <div class="table-wrap">
        <table>
          <thead><tr><th>USER</th><th>WEEK</th><th>SUBMITTED AT</th><th>ACTIONS</th></tr></thead>
          <tbody id="submissions-mgmt-tbody"></tbody>
        </table>
      </div>
    </div>
  </div>
  <div id="page-admin-reports" class="page">
    <button class="btn-back btn-back-to-admin">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"/></svg>
      Back to Admin
    </button>
    <div class="page-title">Global Reports</div>
    <div class="filters-bar">
        <input type="date" id="report-start" class="filter-select">
        <input type="date" id="report-end" class="filter-select">
        <button class="btn-blue-sm" id="btn-run-report">Run Report</button>
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem">
        <div class="card">
            <div style="padding:1rem;font-weight:700;border-bottom:1px solid var(--border)">Hours by Project</div>
            <div class="table-wrap">
                <table><thead><tr><th>PROJECT</th><th>HOURS</th></tr></thead><tbody id="report-projects-tbody"></tbody></table>
            </div>
        </div>
        <div class="card">
            <div style="padding:1rem;font-weight:700;border-bottom:1px solid var(--border)">Hours by User</div>
            <div class="table-wrap">
                <table><thead><tr><th>USER</th><th>HOURS</th></tr></thead><tbody id="report-users-tbody"></tbody></table>
            </div>
        </div>
    </div>
  </div>
  <div id="page-admin-settings" class="page">
    <button class="btn-back btn-back-to-admin">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"/></svg>
      Back to Admin
    </button>
    <div class="page-title">System Settings</div>
    <div class="card" style="max-width:500px">
        <div class="modal-body">
            <div class="form-group">
                <label>Company Name</label>
                <input type="text" id="set-company-name" class="form-control" placeholder="Acme Corp">
            </div>
            <div class="form-group">
                <label>Default Standard Hours (Per Week)</label>
                <input type="number" id="set-default-hours" class="form-control" step="0.5" placeholder="40.0">
            </div>
            <button class="btn-primary" id="btn-save-settings">Save Settings</button>
        </div>
    </div>
  </div>
  <div id="page-profile" class="page">
    <button class="btn-back" id="btn-back-from-profile">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"/></svg>
      Back to Timesheets
    </button>
    <div class="page-title">My Profile</div>
    <div class="card" style="max-width:500px">
        <div class="modal-body">
            <div style="display:flex;flex-direction:column;align-items:center;margin-bottom:1.5rem">
                <div class="user-avatar" id="profile-avatar-preview" style="width:80px;height:80px;font-size:1.75rem;margin-bottom:1rem;background-size:cover;background-position:center"></div>
                <input type="file" id="prof-avatar-file" style="display:none" accept="image/*">
                <button class="btn-ghost" id="btn-change-avatar" style="font-size:0.8rem">Change Photo</button>
            </div>
            <div class="form-group">
                <label>Full Name</label>
                <input type="text" id="prof-name" class="form-control">
            </div>
            <div class="form-group">
                <label>Email Address</label>
                <input type="email" id="prof-email" class="form-control">
            </div>
            <button class="btn-primary" id="btn-save-profile">Update Profile</button>
        </div>
    </div>
  </div>
</div>
<div class="modal-overlay" id="admin-view-modal">
  <div class="modal-box animate__animated animate__zoomIn animate__faster" style="max-width:700px">
    <div class="modal-header"><span class="modal-title" id="av-modal-title">Submission Details</span><button class="modal-close av-modal-close">&times;</button></div>
    <div class="modal-body" style="max-height:60vh;overflow-y:auto;padding:0">
      <div class="table-wrap">
        <table>
          <thead><tr><th>DATE</th><th>PROJECT</th><th>WORK TYPE</th><th>HOURS</th><th>DESCRIPTION</th><th>ACTIONS</th></tr></thead>
          <tbody id="av-modal-tbody"></tbody>
        </table>
      </div>
    </div>
    <div class="modal-footer"><button class="btn-outline av-modal-close">Close</button></div>
  </div>
</div>
<div class="modal-overlay" id="project-modal">
  <div class="modal-box animate__animated animate__zoomIn animate__faster">
    <div class="modal-header"><span class="modal-title" id="pmodal-title">Add Project</span><button class="modal-close pmodal-close">&times;</button></div>
    <div class="modal-body">
      <input type="hidden" id="pmodal-id">
      <div class="form-group"><label>Project Name</label><input type="text" id="pmodal-name" class="form-control"></div>
      <div class="check-wrap"><input type="checkbox" id="pmodal-active" checked><label for="pmodal-active">Is Active</label></div>
    </div>
    <div class="modal-footer"><button class="btn-outline pmodal-close">Cancel</button><button class="btn-primary" id="btn-pmodal-save">Save Project</button></div>
  </div>
</div>
<div class="modal-overlay" id="work-type-modal">
  <div class="modal-box animate__animated animate__zoomIn animate__faster">
    <div class="modal-header"><span class="modal-title" id="wtmodal-title">Add Work Type</span><button class="modal-close wtmodal-close">&times;</button></div>
    <div class="modal-body">
      <input type="hidden" id="wtmodal-id">
      <div class="form-group"><label>Type Name</label><input type="text" id="wtmodal-name" class="form-control"></div>
      <div class="check-wrap"><input type="checkbox" id="wtmodal-active" checked><label for="wtmodal-active">Is Active</label></div>
    </div>
    <div class="modal-footer"><button class="btn-outline wtmodal-close">Cancel</button><button class="btn-primary" id="btn-wtmodal-save">Save Type</button></div>
  </div>
</div>
<div class="cp-modal-overlay" id="forgot-modal">
  <div class="cp-modal-box animate__animated animate__zoomIn animate__faster">
    <div class="modal-header"><span class="modal-title">Forgot Password</span><button class="modal-close fmodal-close">&times;</button></div>
    <div class="modal-body">
        <p style="font-size:.875rem;color:var(--text-secondary);margin-bottom:1rem">Enter your email and we will generate a reset token.</p>
        <div class="form-group"><label>Email Address</label><input type="email" id="f-email" class="form-control" placeholder="name@example.com"></div>
    </div>
    <div class="modal-footer"><button class="btn-outline fmodal-close">Cancel</button><button class="btn-primary" id="btn-f-submit">Get Token</button></div>
  </div>
</div>
<div class="cp-modal-overlay" id="reset-modal">
  <div class="cp-modal-box animate__animated animate__zoomIn animate__faster">
    <div class="modal-header"><span class="modal-title">Reset Password</span><button class="modal-close rmodal-close">&times;</button></div>
    <div class="modal-body">
        <div class="form-group"><label>Reset Token</label><input type="text" id="r-token" class="form-control"></div>
        <div class="form-group"><label>New Password</label><input type="password" id="r-pass" class="form-control"></div>
    </div>
    <div class="modal-footer"><button class="btn-outline rmodal-close">Cancel</button><button class="btn-primary" id="btn-r-submit">Reset Password</button></div>
  </div>
</div>
<div class="modal-overlay" id="entry-modal">
  <div class="modal-box animate__animated animate__zoomIn animate__faster">
    <div class="modal-header">
      <span class="modal-title" id="modal-title-text">Add New Entry</span>
      <button class="modal-close" id="modal-close-btn">&times;</button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="modal-entry-id" value="">
      <input type="hidden" id="modal-entry-date" value="">
      <div class="form-group">
        <label for="modal-project">Select Project <span style="color:var(--red)">*</span></label>
        <select id="modal-project" class="form-control">
          <option value="">Project Name</option>
        </select>
        <div class="field-error" id="err-modal-project"></div>
      </div>
      <div class="form-group">
        <label for="modal-work-type">Type of Work <span style="color:var(--red)">*</span></label>
        <select id="modal-work-type" class="form-control">
          <option value="">Bug Type</option>
        </select>
        <div class="field-error" id="err-modal-work-type"></div>
      </div>
      <div class="form-group">
        <label for="modal-desc">Task description <span style="color:var(--red)">*</span></label>
        <textarea id="modal-desc" class="form-control" rows="3" placeholder="Write task here..."></textarea>
        <div class="field-error" id="err-modal-desc"></div>
      </div>
      <div class="form-group">
        <label>Hours <span style="color:var(--red)">*</span></label>
        <div class="hours-stepper">
          <button class="stepper-btn" id="hours-minus">−</button>
          <input type="number" id="modal-hours" value="1" min="0.5" max="24" step="0.5">
          <button class="stepper-btn" id="hours-plus">+</button>
        </div>
        <div class="field-error" id="err-modal-hours"></div>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn-outline" id="modal-cancel-btn">Cancel</button>
      <button class="btn-primary" id="modal-save-btn">Add entry</button>
    </div>
  </div>
</div>
<div class="cp-modal-overlay" id="cp-modal">
  <div class="cp-modal-box animate__animated animate__zoomIn animate__faster">
    <div class="modal-header">
      <span class="modal-title">Change Password</span>
      <button class="modal-close" id="cp-modal-close">&times;</button>
    </div>
    <div class="modal-body">
      <div class="form-group">
        <label for="cp-current">Current Password</label>
        <input type="password" id="cp-current" class="form-control" placeholder="••••••••">
        <div class="field-error" id="err-cp-current"></div>
      </div>
      <div class="form-group">
        <label for="cp-new">New Password</label>
        <input type="password" id="cp-new" class="form-control" placeholder="Min 8 chars + special char">
        <div class="field-error" id="err-cp-new"></div>
      </div>
      <div class="form-group">
        <label for="cp-confirm">Confirm New Password</label>
        <input type="password" id="cp-confirm" class="form-control" placeholder="Repeat new password">
        <div class="field-error" id="err-cp-confirm"></div>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn-outline" id="cp-cancel-btn">Cancel</button>
      <button class="btn-primary" id="cp-save-btn">Update Password</button>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
(function(){
'use strict';
var STATE = {
  user: null,
  csrf: '',
  timesheets: [],
  filteredTimesheets: [],
  currentPage: 1,
  perPage: 5,
  sortCol: 'week_num',
  sortDir: 'asc',
  statusFilter: '',
  dateStart: '',
  dateEnd: '',
  currentWeekStart: '',
  currentWeekEnd: '',
  weekEntries: [],
  projects: [],
  workTypes: [],
  captchaToken: '',
  editingEntryId: null,
  activeOpenMenu: null,
  adminSubmissions: [],
  reportProjects: [],
  reportUsers: [],
};
function el(id){ return document.getElementById(id); }
function showErr(id, msg){
  var e = el(id);
  if(!e) return;
  e.textContent = msg || '';
  if(msg) e.classList.add('visible'); else e.classList.remove('visible');
}
function clearErrors(){
  document.querySelectorAll('.field-error').forEach(function(e){ e.textContent=''; e.classList.remove('visible'); });
}
function saveTableState(){
  localStorage.setItem('tt_tbl_state', JSON.stringify({
    currentPage: STATE.currentPage, perPage: STATE.perPage,
    sortCol: STATE.sortCol, sortDir: STATE.sortDir,
    statusFilter: STATE.statusFilter, dateStart: STATE.dateStart, dateEnd: STATE.dateEnd
  }));
}
function loadTableState(){
  try{
    var s = JSON.parse(localStorage.getItem('tt_tbl_state'));
    if(s){
      STATE.currentPage = s.currentPage || 1;
      STATE.perPage = s.perPage || 5;
      STATE.sortCol = s.sortCol || 'week_num';
      STATE.sortDir = s.sortDir || 'asc';
      STATE.statusFilter = s.statusFilter || '';
      STATE.dateStart = s.dateStart || '';
      STATE.dateEnd = s.dateEnd || '';
      if(el('status-filter')) el('status-filter').value = STATE.statusFilter;
      if(el('per-page-select')) el('per-page-select').value = STATE.perPage;
      if(STATE.dateStart && STATE.dateEnd && typeof fp !== 'undefined'){
        fp.setDate([STATE.dateStart, STATE.dateEnd], false);
        var d1 = new Date(STATE.dateStart+'T00:00:00'), d2 = new Date(STATE.dateEnd+'T00:00:00');
        el('date-range-label').textContent = d1.toLocaleDateString('en-GB',{day:'numeric',month:'short',year:'numeric'}) + ' – ' + d2.toLocaleDateString('en-GB',{day:'numeric',month:'short',year:'numeric'});
      }
      document.querySelectorAll('thead th.sortable').forEach(function(t){
        t.classList.remove('sorted');
        if(t.dataset.col === STATE.sortCol) t.classList.add('sorted');
      });
    }
  }catch(e){}
}
function apiCall(endpoint, method, body){
  var opts = { method: method || 'GET', headers: { 'X-CSRF-TOKEN': STATE.csrf } };
  if(body){ opts.headers['Content-Type'] = 'application/json'; opts.body = JSON.stringify(body); }
  return fetch('?api=' + endpoint, opts).then(function(r){
    if(r.status === 401){
      if(STATE.user){ showLogin(); }
      return r.json();
    }
    return r.json();
  });
}
function showLogin(){
  STATE.user = null;
  el('app-main').classList.remove('active');
  el('app-login').classList.add('active');
  loadCaptcha();
}
function showMain(){
  el('app-login').classList.remove('active');
  el('app-main').classList.add('active');
  showPage('page-timesheets');
  loadTableState();
  loadTimesheets();
  prefetchProjectsAndTypes();
}
function showPage(pageId){
  document.querySelectorAll('.page').forEach(function(p){ p.classList.remove('active'); });
  var p = el(pageId);
  if(p){
    p.classList.add('active');
    p.classList.remove('animate__fadeInUp','animate__fadeIn');
    void p.offsetWidth;
    p.classList.add('animate__animated','animate__fadeIn');
  }
}
function getInitials(name){
  return name.split(' ').map(function(w){ return w[0]; }).join('').substring(0,2).toUpperCase();
}
function setUserUI(user){
  STATE.user = user;
  el('topbar-username').textContent = user.name;
  var av = el('user-avatar-initials');
  if(user.avatar_url){
    av.textContent = '';
    av.style.backgroundImage = 'url('+user.avatar_url+')';
    av.style.backgroundSize = 'cover';
  } else {
    av.textContent = getInitials(user.name);
    av.style.backgroundImage = 'none';
  }
  if(user.role === 'admin'){
    document.querySelectorAll('[id^="btn-admin-"]').forEach(function(b){ b.style.display = 'flex'; });
  } else {
    document.querySelectorAll('[id^="btn-admin-"]').forEach(function(b){ b.style.display = 'none'; });
  }
}
function loadCaptcha(){
  document.querySelectorAll('.captcha-img').forEach(function(img){ img.src = ''; });
  el('captcha-answer').value = '';
  if(el('reg-captcha-answer')) el('reg-captcha-answer').value = '';
  STATE.captchaToken = '';
  fetch('?api=captcha').then(function(r){ return r.json(); }).then(function(d){
    document.querySelectorAll('.captcha-img').forEach(function(img){ img.src = d.image; });
    STATE.captchaToken = d.token;
  }).catch(function(){});
}
document.querySelectorAll('.captcha-img').forEach(function(img){ img.addEventListener('click', loadCaptcha); });
el('btn-show-register').addEventListener('click', function(){
  el('login-form-wrap').style.display = 'none';
  el('register-wrap').style.display = 'block';
  loadCaptcha();
});
el('btn-show-login').addEventListener('click', function(){
  el('register-wrap').style.display = 'none';
  el('login-form-wrap').style.display = 'block';
  loadCaptcha();
});
el('btn-register').addEventListener('click', function(){
  clearErrors();
  var name = el('reg-name').value.trim();
  var email = el('reg-email').value.trim();
  var password = el('reg-password').value;
  var confirm = el('reg-confirm').value;
  var captchaAnswer = el('reg-captcha-answer').value.trim();
  var valid = true;
  if(!name){ showErr('err-reg-name','Name is required.'); valid=false; }
  if(!email){ showErr('err-reg-email','Email is required.'); valid=false; }
  else if(!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)){ showErr('err-reg-email','Enter a valid email.'); valid=false; }
  if(!password){ showErr('err-reg-password','Password is required.'); valid=false; }
  else if(password.length < 8){ showErr('err-reg-password','Password must be at least 8 characters.'); valid=false; }
  if(password !== confirm){ showErr('err-reg-confirm','Passwords do not match.'); valid=false; }
  if(!captchaAnswer){ showErr('err-reg-captcha','Please solve the CAPTCHA.'); valid=false; }
  if(!valid) return;
  var btn = el('btn-register');
  btn.disabled = true;
  btn.innerHTML = '<span class="loading-spinner"></span>Creating...';
  apiCall('register','POST',{ name:name, email:email, password:password, confirm_password:confirm, captcha_token:STATE.captchaToken, captcha_answer:captchaAnswer }).then(function(d){
    btn.disabled=false; btn.textContent='Create Account';
    if(d.success){
      Swal.fire({ icon:'success', title:'Success!', text:d.message });
      el('btn-show-login').click();
    } else {
      showErr('err-reg-captcha', d.error || 'Registration failed.');
      loadCaptcha();
    }
  }).catch(function(){
    btn.disabled=false; btn.textContent='Create Account';
    loadCaptcha();
  });
});
el('btn-signin').addEventListener('click', function(){
  clearErrors();
  var email = el('login-email').value.trim();
  var password = el('login-password').value;
  var captchaAnswer = el('captcha-answer').value.trim();
  var valid = true;
  if(!email){ showErr('err-email','Email is required.'); valid=false; }
  else if(!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)){ showErr('err-email','Enter a valid email.'); valid=false; }
  if(!password){ showErr('err-password','Password is required.'); valid=false; }
  else if(password.length < 8){ showErr('err-password','Password must be at least 8 characters.'); valid=false; }
  if(!captchaAnswer){ showErr('err-captcha','Please solve the CAPTCHA.'); valid=false; }
  if(!valid) return;
  var btn = el('btn-signin');
  btn.disabled = true;
  btn.innerHTML = '<span class="loading-spinner"></span>Signing in...';
  apiCall('login','POST',{ email:email, password:password, captcha_token:STATE.captchaToken, captcha_answer:captchaAnswer }).then(function(d){
    btn.disabled=false; btn.textContent='Sign in';
    if(d.success){
      STATE.csrf = d.csrf;
      setUserUI(d.user);
      showMain();
    } else {
      showErr('err-login', d.error || 'Login failed.');
      loadCaptcha();
    }
  }).catch(function(){
    btn.disabled=false; btn.textContent='Sign in';
    showErr('err-login','Network error. Please try again.');
    loadCaptcha();
  });
});
el('login-email').addEventListener('keydown', function(e){ if(e.key==='Enter') el('btn-signin').click(); });
el('login-password').addEventListener('keydown', function(e){ if(e.key==='Enter') el('btn-signin').click(); });
el('captcha-answer').addEventListener('keydown', function(e){ if(e.key==='Enter') el('btn-signin').click(); });
el('user-menu-btn').addEventListener('click', function(e){
  e.stopPropagation();
  el('user-dropdown').classList.toggle('open');
});
document.addEventListener('click', function(){
  el('user-dropdown').classList.remove('open');
  if(STATE.activeOpenMenu){ STATE.activeOpenMenu.classList.remove('open'); STATE.activeOpenMenu = null; }
});
el('btn-logout').addEventListener('click', function(){
  Swal.fire({ title:'Sign out?', text:'You will be returned to the login screen.', icon:'question', showCancelButton:true, confirmButtonText:'Sign out', confirmButtonColor:'#DC2626', cancelButtonText:'Cancel' }).then(function(r){
    if(r.isConfirmed){
      apiCall('logout','POST',{}).then(function(){ showLogin(); });
    }
  });
});
el('btn-back-to-list').addEventListener('click', function(){
  showPage('page-timesheets');
});
el('btn-show-profile').addEventListener('click', function(){
  el('user-dropdown').classList.remove('open');
  el('prof-name').value = STATE.user.name;
  el('prof-email').value = STATE.user.email;
  var av = el('profile-avatar-preview');
  if(STATE.user.avatar_url){
    av.textContent = '';
    av.style.backgroundImage = 'url('+STATE.user.avatar_url+')';
  } else {
    av.textContent = getInitials(STATE.user.name);
    av.style.backgroundImage = 'none';
  }
  showPage('page-profile');
});
el('btn-change-avatar').addEventListener('click', function(){ el('prof-avatar-file').click(); });
el('prof-avatar-file').addEventListener('change', function(){
    if(!this.files.length) return;
    var fd = new FormData();
    fd.append('avatar', this.files[0]);
    fetch('?api=upload_avatar', { method:'POST', headers:{'X-CSRF-TOKEN':STATE.csrf}, body:fd }).then(function(r){ return r.json(); }).then(function(d){
        if(d.success){
            STATE.user.avatar_url = d.avatar_url;
            setUserUI(STATE.user);
            var av = el('profile-avatar-preview');
            av.textContent = ''; av.style.backgroundImage = 'url('+d.avatar_url+')';
            Swal.fire({icon:'success', title:'Photo Updated'});
        } else { Swal.fire({icon:'error', title:'Error', text:d.error}); }
    });
});
el('btn-save-profile').addEventListener('click', function(){
  var name = el('prof-name').value.trim();
  var email = el('prof-email').value.trim();
  if(!name || !email) return;
  apiCall('update_profile','POST',{name:name, email:email}).then(function(d){
    if(d.success){
        STATE.user.name = name; STATE.user.email = email;
        setUserUI(STATE.user);
        Swal.fire({icon:'success', title:'Updated', text:d.message});
    } else { Swal.fire({icon:'error', title:'Error', text:d.error}); }
  });
});
el('btn-back-from-profile').addEventListener('click', function(){ showPage('page-timesheets'); });
el('btn-show-forgot').addEventListener('click', function(){ openModal('forgot-modal'); });
el('btn-f-submit').addEventListener('click', function(){
  var email = el('f-email').value.trim();
  if(!email) return;
  apiCall('forgot_password','POST',{email:email}).then(function(d){
    closeModal('forgot-modal');
    Swal.fire({title:'Token Generated', text:d.message}).then(function(){ openModal('reset-modal'); });
  });
});
el('btn-r-submit').addEventListener('click', function(){
    var token = el('r-token').value.trim();
    var pass = el('r-pass').value;
    if(!token || !pass) return;
    apiCall('reset_password','POST',{token:token, password:pass}).then(function(d){
        if(d.success){ closeModal('reset-modal'); Swal.fire({icon:'success', title:'Success', text:d.message}); }
        else { Swal.fire({icon:'error', title:'Error', text:d.error}); }
    });
});
document.querySelectorAll('.fmodal-close').forEach(function(b){ b.addEventListener('click', function(){ closeModal('forgot-modal'); }); });
document.querySelectorAll('.rmodal-close').forEach(function(b){ b.addEventListener('click', function(){ closeModal('reset-modal'); }); });
el('btn-admin-users').addEventListener('click', function(){
  el('user-dropdown').classList.remove('open');
  showPage('page-admin');
  loadAdminUsers();
});
el('btn-admin-projects').addEventListener('click', function(){
  el('user-dropdown').classList.remove('open');
  showPage('page-admin-projects');
  loadAdminProjects();
});
el('btn-admin-work-types').addEventListener('click', function(){
  el('user-dropdown').classList.remove('open');
  showPage('page-admin-work-types');
  loadAdminWorkTypes();
});
var overviewFp = null;
var RAW_OVERVIEW_DATA = [];
el('btn-admin-overview-menu').addEventListener('click', function(){
  el('user-dropdown').classList.remove('open');
  showPage('page-admin-overview');
  if(!overviewFp) {
    overviewFp = flatpickr(el('overview-date-picker'), {
      mode: 'range', dateFormat: 'Y-m-d',
      defaultDate: [new Date(new Date().setDate(new Date().getDate() - new Date().getDay() + 1)), new Date(new Date().setDate(new Date().getDate() - new Date().getDay() + 7))],
      onClose: function(selectedDates) { if(selectedDates.length === 2) loadAdminOverview(); }
    });
  }
  apiCall('admin_users').then(function(d){
    if(d.success){
      var sel = el('overview-user-filter');
      sel.innerHTML = '<option value="">All Users</option>';
      d.users.forEach(function(u){ if(u.is_deleted==0) sel.innerHTML += '<option value="'+u.id+'">'+escHtml(u.name)+'</option>'; });
    }
  });
  loadAdminOverview();
});
el('overview-user-filter').addEventListener('change', renderOverview);
el('overview-status-filter').addEventListener('change', renderOverview);
function loadAdminOverview(){
  var dates = overviewFp.selectedDates;
  if(dates.length !== 2) return;
  var s = formatFP(dates[0]), e = formatFP(dates[1]);
  el('overview-mgmt-tbody').innerHTML = '<tr><td colspan="5" style="text-align:center">Loading...</td></tr>';
  apiCall('admin_overview&start='+s+'&end='+e).then(function(d){
    if(d.success){ RAW_OVERVIEW_DATA = d.overview; renderOverview(); }
  });
}
function renderOverview(){
  var uFilter = el('overview-user-filter').value;
  var sFilter = el('overview-status-filter').value;
  var tbody = el('overview-mgmt-tbody');
  var html = '';
  RAW_OVERVIEW_DATA.forEach(function(r){
    if(uFilter && r.user_id != uFilter) return;
    if(sFilter && r.status !== sFilter) return;
    var badge = '';
    if(r.status === 'approved') badge = '<span class="badge badge-completed">APPROVED</span>';
    else if(r.status === 'pending') badge = '<span class="badge badge-incomplete">PENDING</span>';
    else if(r.status === 'rejected') badge = '<span class="badge badge-missing">REJECTED</span>';
    else if(r.status === 'completed') badge = '<span class="badge badge-completed">COMPLETED</span>';
    else if(r.status === 'incomplete') badge = '<span class="badge badge-incomplete">INCOMPLETE</span>';
    else badge = '<span class="badge badge-missing">MISSING</span>';
    html += '<tr class="animate__animated animate__fadeIn"><td>'+escHtml(r.user_name)+'</td><td>'+r.date_label+'</td><td>'+r.total_hours+' / '+r.std_hours+'h</td><td>'+badge+'</td>';
    html += '<td><button class="action-link view-ov-sub" data-uid="'+r.user_id+'" data-uname="'+escHtml(r.user_name)+'" data-start="'+r.date_start+'" data-end="'+r.date_end+'">View Details</button></td></tr>';
  });
  tbody.innerHTML = html || '<tr><td colspan="5" style="text-align:center">No records match your filters.</td></tr>';
  tbody.querySelectorAll('.view-ov-sub').forEach(function(b){
    b.addEventListener('click', function(){
      var uid = this.dataset.uid, uname = this.dataset.uname, start = this.dataset.start, end = this.dataset.end;
      el('av-modal-title').textContent = uname + ' - ' + start + ' to ' + end;
      el('av-modal-tbody').innerHTML = '<tr><td colspan="6" style="text-align:center">Loading...</td></tr>';
      openModal('admin-view-modal');
      apiCall('week_entries&user_id='+uid+'&start='+start+'&end='+end).then(function(d){
        if(d.success){
            var h = '', total = 0;
            d.entries.forEach(function(e){
                var dFormatted = new Date(e.date+'T00:00:00').toLocaleDateString('en-US',{month:'short',day:'numeric'});
                h += '<tr><td>'+dFormatted+'</td><td>'+escHtml(e.project_name)+'</td><td>'+escHtml(e.work_type_name)+'</td><td>'+parseFloat(e.hours)+'h</td><td>'+escHtml(e.description)+'</td>';
                h += '<td><button class="action-link edit-sub-entry" data-entry=\''+JSON.stringify(e).replace(/'/g, "&#39;")+'\'>Edit</button></td></tr>';
                total += parseFloat(e.hours);
            });
            if(!h) h = '<tr><td colspan="6" style="text-align:center">No entries found.</td></tr>';
            else h += '<tr><td colspan="3" style="text-align:right;font-weight:bold">Total Hours:</td><td colspan="3" style="font-weight:bold;color:var(--blue)">'+total+'h</td></tr>';
            el('av-modal-tbody').innerHTML = h;
            el('av-modal-tbody').querySelectorAll('.edit-sub-entry').forEach(function(btn) {
              btn.addEventListener('click', function() {
                var entry = JSON.parse(this.dataset.entry);
                openEditModal(entry);
                closeModal('admin-view-modal');
              });
            });
        }
      });
    });
  });
}
el('btn-admin-subs').addEventListener('click', function(){
  el('user-dropdown').classList.remove('open');
  showPage('page-admin-submissions');
  loadAdminSubmissions();
});
el('btn-admin-reports').addEventListener('click', function(){
  el('user-dropdown').classList.remove('open');
  showPage('page-admin-reports');
  var d = new Date(), y = d.getFullYear(), m = d.getMonth();
  el('report-start').value = y + '-' + String(m+1).padStart(2,'0') + '-01';
  el('report-end').value = y + '-' + String(m+1).padStart(2,'0') + '-' + String(new Date(y, m+1, 0).getDate()).padStart(2,'0');
  el('btn-run-report').click();
});
el('btn-admin-settings').addEventListener('click', function(){
  el('user-dropdown').classList.remove('open');
  showPage('page-admin-settings');
  apiCall('admin_settings').then(function(d){
    if(d.success){
      el('set-company-name').value = d.settings['company_name'] || 'ticktock';
      el('set-default-hours').value = d.settings['default_standard_hours'] || '40.0';
    }
  });
});
el('btn-save-settings').addEventListener('click', function(){
  var company = el('set-company-name').value.trim();
  var hours = parseFloat(el('set-default-hours').value) || 40.0;
  apiCall('admin_settings', 'POST', { company_name: company, default_standard_hours: hours }).then(function(d){
    if(d.success){ Swal.fire({icon:'success', title:'Saved', text:d.message, timer:1500, showConfirmButton:false, toast:true, position:'top-end'}); }
  });
});
document.querySelectorAll('.btn-back-to-admin').forEach(function(b){
  b.addEventListener('click', function(){ showPage('page-admin'); });
});
el('btn-back-from-admin').addEventListener('click', function(){
  showPage('page-timesheets');
});
function loadAdminUsers(){
  apiCall('admin_users').then(function(d){
    if(d.success){
      renderAdminUsers(d.users);
    }
  });
}
function renderAdminUsers(users){
  var tbody = el('admin-users-tbody');
  var html = '';
  users.forEach(function(u){
    var status = u.is_deleted == 1 ? '<span class="badge badge-missing" style="background:#fce7f3;color:#db2777">Deleted</span>' : (u.is_approved == 1 ? '<span class="badge badge-completed">Approved</span>' : '<span class="badge badge-missing">Pending</span>');
    html += '<tr style="'+(u.is_deleted == 1 ? 'opacity:0.6' : '')+'">';
    html += '<td>'+escHtml(u.name)+'</td>';
    html += '<td>'+escHtml(u.email)+'</td>';
    html += '<td><select class="admin-role-sel" data-id="'+u.id+'" '+(u.is_deleted == 1 ? 'disabled' : '')+'><option value="user" '+(u.role==='user'?'selected':'')+'>User</option><option value="admin" '+(u.role==='admin'?'selected':'')+'>Admin</option></select></td>';
    html += '<td><input type="number" class="admin-hours-inp" data-id="'+u.id+'" value="'+u.standard_hours+'" style="width:60px" '+(u.is_deleted == 1 ? 'disabled' : '')+'></td>';
    html += '<td>'+status+'</td>';
    if(u.is_deleted == 1) {
      html += '<td><button class="action-link restore-user" data-id="'+u.id+'" style="color:var(--green)">Restore</button> <button class="action-link purge-user danger" data-id="'+u.id+'" style="color:var(--red)">Purge</button></td>';
    } else {
      html += '<td><button class="action-link approve-toggle" data-id="'+u.id+'" data-approved="'+u.is_approved+'">'+(u.is_approved==1?'Disapprove':'Approve')+'</button> <button class="action-link delete-user danger" data-id="'+u.id+'" style="color:var(--red)">Delete</button></td>';
    }
    html += '</tr>';
  });
  tbody.innerHTML = html;
  tbody.querySelectorAll('.admin-role-sel').forEach(function(sel){
    sel.addEventListener('change', function(){
      updateUser(this.dataset.id, { role: this.value });
    });
  });
  tbody.querySelectorAll('.admin-hours-inp').forEach(function(inp){
    inp.addEventListener('change', function(){
      updateUser(this.dataset.id, { standard_hours: this.value });
    });
  });
  tbody.querySelectorAll('.approve-toggle').forEach(function(btn){
    btn.addEventListener('click', function(){
      var isApp = this.dataset.approved == 1 ? 0 : 1;
      updateUser(this.dataset.id, { is_approved: isApp });
    });
  });
  tbody.querySelectorAll('.delete-user').forEach(function(btn){
    btn.addEventListener('click', function(){
      deleteUser(this.dataset.id);
    });
  });
  tbody.querySelectorAll('.restore-user').forEach(function(btn){
    btn.addEventListener('click', function(){
      apiCall('admin_user_restore', 'POST', { id: this.dataset.id }).then(function(d){
        if(d.success){ Swal.fire({ icon:'success', title:'Restored', timer:1500, showConfirmButton:false, toast:true, position:'top-end' }); loadAdminUsers(); }
      });
    });
  });
  tbody.querySelectorAll('.purge-user').forEach(function(btn){
    btn.addEventListener('click', function(){
      var id = this.dataset.id;
      Swal.fire({ title:'Permanently Delete?', text:'This removes the user and ALL their timesheets forever.', icon:'warning', showCancelButton:true, confirmButtonText:'Purge', confirmButtonColor:'#DC2626' }).then(function(r){
        if(r.isConfirmed){
          apiCall('admin_user_purge', 'DELETE', { id: id }).then(function(d){
            if(d.success){ Swal.fire({ icon:'success', title:'Purged', timer:1500, showConfirmButton:false, toast:true, position:'top-end' }); loadAdminUsers(); }
          });
        }
      });
    });
  });
}
function updateUser(id, data){
  data.id = id;
  apiCall('admin_user_update', 'POST', data).then(function(d){
    if(d.success){
      Swal.fire({ icon:'success', title:'Updated', text:d.message, timer:1500, showConfirmButton:false, toast:true, position:'top-end' });
      loadAdminUsers();
    } else {
      Swal.fire({ icon:'error', title:'Error', text:d.error });
    }
  });
}
function deleteUser(id){
  Swal.fire({ title:'Delete user?', text:'This will remove all their timesheets too.', icon:'warning', showCancelButton:true, confirmButtonText:'Delete', confirmButtonColor:'#DC2626' }).then(function(r){
    if(r.isConfirmed){
      apiCall('admin_user_delete', 'DELETE', { id: id }).then(function(d){
        if(d.success){
          Swal.fire({ icon:'success', title:'Deleted', text:d.message, timer:1500, showConfirmButton:false, toast:true, position:'top-end' });
          loadAdminUsers();
        } else {
          Swal.fire({ icon:'error', title:'Error', text:d.error });
        }
      });
    }
  });
}
function formatDateLabel(dateStr){
  var d = new Date(dateStr + 'T00:00:00');
  return d.toLocaleDateString('en-GB',{ day:'numeric', month:'long', year:'numeric' });
}
function formatDayLabel(dateStr){
  var d = new Date(dateStr + 'T00:00:00');
  return d.toLocaleDateString('en-US',{ month:'short', day:'numeric' });
}
var fp = flatpickr(el('date-range-picker'), {
  mode:'range',
  dateFormat:'Y-m-d',
  onClose: function(selectedDates){
    if(selectedDates.length === 2){
      STATE.dateStart = formatFP(selectedDates[0]);
      STATE.dateEnd = formatFP(selectedDates[1]);
      var lbl = selectedDates[0].toLocaleDateString('en-GB',{day:'numeric',month:'short',year:'numeric'}) + ' – ' + selectedDates[1].toLocaleDateString('en-GB',{day:'numeric',month:'short',year:'numeric'});
      el('date-range-label').textContent = lbl;
      STATE.currentPage = 1;
      loadTimesheets();
    } else if(selectedDates.length === 0){
      STATE.dateStart = ''; STATE.dateEnd = '';
      el('date-range-label').textContent = 'Date Range';
      STATE.currentPage = 1;
      loadTimesheets();
    }
  }
});
function formatFP(d){ return d.getFullYear()+'-'+(''+(d.getMonth()+1)).padStart(2,'0')+'-'+(''+d.getDate()).padStart(2,'0'); }
el('date-range-btn').addEventListener('click', function(e){ e.stopPropagation(); fp.open(); });
el('status-filter').addEventListener('change', function(){
  STATE.statusFilter = this.value;
  STATE.currentPage = 1;
  applyFiltersAndRender();
});
el('per-page-select').addEventListener('change', function(){
  STATE.perPage = parseInt(this.value);
  STATE.currentPage = 1;
  applyFiltersAndRender();
});
function loadTimesheets(){
  var params = '';
  if(STATE.dateStart && STATE.dateEnd){
    params = '&start=' + STATE.dateStart + '&end=' + STATE.dateEnd;
  } else {
    var y = new Date().getFullYear();
    params = '&start=' + y + '-01-01&end=' + y + '-12-31';
  }
  renderSkeletonRows();
  apiCall('timesheets','GET').then(function(){ return fetch('?api=timesheets' + params, { headers:{'X-CSRF-TOKEN':STATE.csrf} }); })
  .then(function(r){ return r.json(); }).then(function(d){
    if(d.success){
      STATE.timesheets = d.timesheets;
      applyFiltersAndRender();
    }
  }).catch(function(){});
}
function renderSkeletonRows(){
  var tbody = el('timesheets-tbody');
  var html = '';
  for(var i=0;i<5;i++){
    html += '<tr class="skeleton-row"><td><div class="skeleton skeleton-cell" style="width:40px"></div></td><td><div class="skeleton skeleton-cell" style="width:180px"></div></td><td><div class="skeleton skeleton-cell" style="width:80px"></div></td><td><div class="skeleton skeleton-cell" style="width:60px"></div></td></tr>';
  }
  tbody.innerHTML = html;
}
function applyFiltersAndRender(){
  var data = STATE.timesheets.slice();
  if(STATE.statusFilter){
    data = data.filter(function(r){ return r.status === STATE.statusFilter; });
  }
  data.sort(function(a,b){
    var av = a[STATE.sortCol], bv = b[STATE.sortCol];
    if(typeof av === 'string') av = av.toLowerCase();
    if(typeof bv === 'string') bv = bv.toLowerCase();
    if(av < bv) return STATE.sortDir === 'asc' ? -1 : 1;
    if(av > bv) return STATE.sortDir === 'asc' ? 1 : -1;
    return 0;
  });
  STATE.filteredTimesheets = data;
  renderTimesheetTable();
  renderPagination();
}
function loadAdminProjects(){
  apiCall('projects').then(function(d){ if(d.success) renderAdminProjects(d.projects); });
}
function renderAdminProjects(projects){
  var tbody = el('projects-mgmt-tbody');
  var html = '';
  projects.forEach(function(p){
    html += '<tr><td>'+escHtml(p.name)+'</td><td>'+(p.is_active==1?'Active':'Inactive')+'</td><td><button class="action-link edit-p" data-id="'+p.id+'">Edit</button></td></tr>';
  });
  tbody.innerHTML = html;
  tbody.querySelectorAll('.edit-p').forEach(function(b){ b.addEventListener('click', function(){
    var p = projects.find(function(x){ return x.id == b.dataset.id; });
    el('pmodal-id').value = p.id; el('pmodal-name').value = p.name; el('pmodal-active').checked = p.is_active==1;
    el('pmodal-title').textContent = 'Edit Project'; openModal('project-modal');
  });});
}
el('btn-add-project').addEventListener('click', function(){
  el('pmodal-id').value = ''; el('pmodal-name').value = ''; el('pmodal-active').checked = true;
  el('pmodal-title').textContent = 'Add Project'; openModal('project-modal');
});
el('btn-pmodal-save').addEventListener('click', function(){
  var id = el('pmodal-id').value, name = el('pmodal-name').value.trim(), active = el('pmodal-active').checked?1:0;
  if(!name) return;
  apiCall('admin_project_save','POST',{id:id, name:name, is_active:active}).then(function(d){
    if(d.success){ closeModal('project-modal'); loadAdminProjects(); STATE.projects=[]; prefetchProjectsAndTypes(); }
  });
});
document.querySelectorAll('.pmodal-close').forEach(function(b){ b.addEventListener('click', function(){ closeModal('project-modal'); }); });
function loadAdminWorkTypes(){
  apiCall('work_types').then(function(d){ if(d.success) renderAdminWorkTypes(d.work_types); });
}
function renderAdminWorkTypes(types){
  var tbody = el('work-types-mgmt-tbody');
  var html = '';
  types.forEach(function(t){
    html += '<tr><td>'+escHtml(t.name)+'</td><td>'+(t.is_active==1?'Active':'Inactive')+'</td><td><button class="action-link edit-wt" data-id="'+t.id+'">Edit</button></td></tr>';
  });
  tbody.innerHTML = html;
  tbody.querySelectorAll('.edit-wt').forEach(function(b){ b.addEventListener('click', function(){
    var t = types.find(function(x){ return x.id == b.dataset.id; });
    el('wtmodal-id').value = t.id; el('wtmodal-name').value = t.name; el('wtmodal-active').checked = t.is_active==1;
    el('wtmodal-title').textContent = 'Edit Work Type'; openModal('work-type-modal');
  });});
}
el('btn-add-work-type').addEventListener('click', function(){
  el('wtmodal-id').value = ''; el('wtmodal-name').value = ''; el('wtmodal-active').checked = true;
  el('wtmodal-title').textContent = 'Add Work Type'; openModal('work-type-modal');
});
el('btn-wtmodal-save').addEventListener('click', function(){
  var id = el('wtmodal-id').value, name = el('wtmodal-name').value.trim(), active = el('wtmodal-active').checked?1:0;
  if(!name) return;
  apiCall('admin_work_type_save','POST',{id:id, name:name, is_active:active}).then(function(d){
    if(d.success){ closeModal('work-type-modal'); loadAdminWorkTypes(); STATE.workTypes=[]; prefetchProjectsAndTypes(); }
  });
});
document.querySelectorAll('.wtmodal-close').forEach(function(b){ b.addEventListener('click', function(){ closeModal('work-type-modal'); }); });
function loadAdminSubmissions(){
  apiCall('admin_submissions').then(function(d){ if(d.success) renderAdminSubmissions(d.submissions); });
}
function renderAdminSubmissions(subs){
  var tbody = el('submissions-mgmt-tbody');
  var html = '';
  subs.forEach(function(s){
    html += '<tr><td>'+escHtml(s.user_name)+'</td><td>Week '+s.week+', '+s.year+'</td><td>'+s.submitted_at+'</td><td>';
    html += '<button class="action-link view-sub" data-id="'+s.id+'" data-user="'+escHtml(s.user_name)+'" data-week="'+s.week+'" data-year="'+s.year+'">View</button>';
    html += '<button class="action-link review-sub" data-id="'+s.id+'" data-status="approved">Approve</button>';
    html += '<button class="action-link review-sub danger" data-id="'+s.id+'" data-status="rejected" style="color:var(--red)">Reject</button>';
    html += '</td></tr>';
  });
  tbody.innerHTML = html || '<tr><td colspan="4">No pending submissions.</td></tr>';
  tbody.querySelectorAll('.view-sub').forEach(function(b){ b.addEventListener('click', function(){
    var id = b.dataset.id, user = b.dataset.user, week = b.dataset.week, year = b.dataset.year;
    el('av-modal-title').textContent = user + ' - Week ' + week + ', ' + year;
    el('av-modal-tbody').innerHTML = '<tr><td colspan="5" style="text-align:center">Loading...</td></tr>';
    openModal('admin-view-modal');
    apiCall('admin_submission_entries&id='+id, 'GET').then(function(d){
        if(d.success){
            var h = '', total = 0;
            d.entries.forEach(function(e){
                var dFormatted = new Date(e.date+'T00:00:00').toLocaleDateString('en-US',{month:'short',day:'numeric'});
                h += '<tr><td>'+dFormatted+'</td><td>'+escHtml(e.project_name)+'</td><td>'+escHtml(e.work_type_name)+'</td><td>'+parseFloat(e.hours)+'h</td><td>'+escHtml(e.description)+'</td>';
                h += '<td><button class="action-link edit-sub-entry" data-entry=\''+JSON.stringify(e).replace(/'/g, "&#39;")+'\'>Edit</button></td></tr>';
                total += parseFloat(e.hours);
            });
            if(!h) h = '<tr><td colspan="6" style="text-align:center">No entries found.</td></tr>';
            else h += '<tr><td colspan="3" style="text-align:right;font-weight:bold">Total Hours:</td><td colspan="3" style="font-weight:bold;color:var(--blue)">'+total+'h</td></tr>';
            el('av-modal-tbody').innerHTML = h;
            el('av-modal-tbody').querySelectorAll('.edit-sub-entry').forEach(function(btn) {
              btn.addEventListener('click', function() {
                var entry = JSON.parse(this.dataset.entry);
                openEditModal(entry);
                closeModal('admin-view-modal');
              });
            });
        }
    });
  });});
  tbody.querySelectorAll('.review-sub').forEach(function(b){ b.addEventListener('click', function(){
    var id = b.dataset.id, status = b.dataset.status;
    if(status==='rejected'){
        Swal.fire({title:'Rejection Reason', input:'text', showCancelButton:true}).then(function(r){
            if(r.isConfirmed) reviewSub(id, status, r.value);
        });
    } else { reviewSub(id, status); }
  });});
}
document.querySelectorAll('.av-modal-close').forEach(function(b){ b.addEventListener('click', function(){ closeModal('admin-view-modal'); }); });
function reviewSub(id, status, reason){
    apiCall('admin_submission_review','POST',{id:id, status:status, reason:reason}).then(function(d){
        if(d.success){ Swal.fire({icon:'success', title:'Success', text:d.message}); loadAdminSubmissions(); }
    });
}
el('btn-run-report').addEventListener('click', function(){
  var s = el('report-start').value, e = el('report-end').value;
  if(!s || !e) return;
  el('report-projects-tbody').innerHTML = '<tr><td colspan="2" style="text-align:center">Loading...</td></tr>';
  el('report-users-tbody').innerHTML = '<tr><td colspan="2" style="text-align:center">Loading...</td></tr>';
  apiCall('reports&start='+s+'&end='+e).then(function(d){
    if(d.success){
        var ph = '', uh = '';
        d.projects.forEach(function(p){ ph += '<tr><td>'+escHtml(p.project_name)+'</td><td>'+p.total_hours+'h</td></tr>'; });
        d.users.forEach(function(u){ uh += '<tr><td>'+escHtml(u.user_name)+'</td><td>'+u.total_hours+'h</td></tr>'; });
        el('report-projects-tbody').innerHTML = ph || '<tr><td colspan="2">No data</td></tr>';
        el('report-users-tbody').innerHTML = uh || '<tr><td colspan="2">No data</td></tr>';
    }
  });
});
el('btn-submit-week').addEventListener('click', function(){
    Swal.fire({title:'Submit for approval?', text:'This will lock your timesheet for this week.', icon:'question', showCancelButton:true}).then(function(r){
        if(r.isConfirmed){
            var wInfo = STATE.timesheets.find(function(w){ return w.date_start === STATE.currentWeekStart; });
            var dt = new Date(STATE.currentWeekStart+'T00:00:00');
            var year = wInfo ? wInfo.year : dt.getFullYear();
            var week = wInfo ? wInfo.week : getWeekNumber(dt);
            apiCall('submit_week','POST', {year: year, week: week}).then(function(d){
                if(d.success){ Swal.fire({icon:'success', title:'Submitted', text:d.message}); loadTimesheets(); openWeekDetail(STATE.currentWeekStart, STATE.currentWeekEnd); }
                else { Swal.fire({icon:'error', title:'Error', text:d.error}); }
            });
        }
    });
});
el('btn-export-week').addEventListener('click', function(){
    window.location.href = '?api=export_csv&start='+STATE.currentWeekStart+'&end='+STATE.currentWeekEnd;
});
function getWeekNumber(d) {
    d = new Date(Date.UTC(d.getFullYear(), d.getMonth(), d.getDate()));
    d.setUTCDate(d.getUTCDate() + 4 - (d.getUTCDay()||7));
    var yearStart = new Date(Date.UTC(d.getUTCFullYear(),0,1));
    var weekNo = Math.ceil(( ( (d - yearStart) / 86400000) + 1)/7);
    return weekNo;
}
function renderTimesheetTable(){
  saveTableState();
  var tbody = el('timesheets-tbody');
  var start = (STATE.currentPage - 1) * STATE.perPage;
  var page = STATE.filteredTimesheets.slice(start, start + STATE.perPage);
  if(!page.length){
    tbody.innerHTML = '<tr><td colspan="4"><div class="empty-state"><div class="empty-icon">📋</div><h3>No timesheets found</h3><p>Try adjusting your filters or date range.</p></div></td></tr>';
    return;
  }
  var html = '';
  page.forEach(function(row){
    var badge = '';
    if(row.status === 'approved') badge = '<span class="badge badge-completed">APPROVED</span>';
    else if(row.status === 'pending') badge = '<span class="badge badge-incomplete">PENDING</span>';
    else if(row.status === 'rejected') badge = '<span class="badge badge-missing">REJECTED</span>';
    else if(row.status === 'completed') badge = '<span class="badge badge-completed">COMPLETED</span>';
    else if(row.status === 'incomplete') badge = '<span class="badge badge-incomplete">INCOMPLETE</span>';
    else badge = '<span class="badge badge-missing">MISSING</span>';
    var action = '';
    var actionText = 'View';
    if(row.status === 'missing') actionText = 'Create';
    else if(row.status === 'incomplete' || row.status === 'rejected') actionText = 'Update';
    action = '<button class="action-link" data-start="'+row.date_start+'" data-end="'+row.date_end+'">'+actionText+'</button>';
    html += '<tr class="animate__animated animate__fadeIn"><td>'+row.week_num+'</td><td>'+escHtml(row.date_label)+'</td><td>'+badge+'</td><td>'+action+'</td></tr>';
  });
  tbody.innerHTML = html;
  tbody.querySelectorAll('.action-link').forEach(function(btn){
    btn.addEventListener('click', function(){
      openWeekDetail(this.dataset.start, this.dataset.end);
    });
  });
}
function renderPagination(){
  var total = STATE.filteredTimesheets.length;
  var pages = Math.ceil(total / STATE.perPage);
  var wrap = el('page-btns');
  var html = '';
  if(pages <= 1){ wrap.innerHTML = ''; return; }
  html += '<button class="page-btn" id="pg-prev" '+(STATE.currentPage===1?'disabled':'')+'>&lsaquo;</button>';
  var pageNums = buildPageNums(STATE.currentPage, pages);
  pageNums.forEach(function(p){
    if(p === '...') html += '<button class="page-btn dots">…</button>';
    else html += '<button class="page-btn'+(p===STATE.currentPage?' active':'')+'">'+p+'</button>';
  });
  html += '<button class="page-btn" id="pg-next" '+(STATE.currentPage===pages?'disabled':'')+'>&rsaquo;</button>';
  wrap.innerHTML = html;
  wrap.querySelectorAll('.page-btn').forEach(function(btn){
    if(btn.id==='pg-prev') btn.addEventListener('click',function(){ if(STATE.currentPage>1){STATE.currentPage--;renderTimesheetTable();renderPagination();} });
    else if(btn.id==='pg-next') btn.addEventListener('click',function(){ if(STATE.currentPage<pages){STATE.currentPage++;renderTimesheetTable();renderPagination();} });
    else if(!btn.classList.contains('dots')) btn.addEventListener('click',function(){ STATE.currentPage=parseInt(this.textContent);renderTimesheetTable();renderPagination(); });
  });
}
function buildPageNums(cur, total){
  var res = [];
  if(total <= 7){ for(var i=1;i<=total;i++) res.push(i); return res; }
  res.push(1);
  if(cur > 3) res.push('...');
  var start = Math.max(2, cur-1), end = Math.min(total-1, cur+1);
  for(var j=start;j<=end;j++) res.push(j);
  if(cur < total-2) res.push('...');
  res.push(total);
  return res;
}
document.querySelectorAll('thead th.sortable').forEach(function(th){
  th.addEventListener('click', function(){
    var col = this.dataset.col;
    if(STATE.sortCol === col){ STATE.sortDir = STATE.sortDir==='asc'?'desc':'asc'; }
    else { STATE.sortCol = col; STATE.sortDir = 'asc'; }
    document.querySelectorAll('thead th.sortable').forEach(function(t){ t.classList.remove('sorted'); });
    this.classList.add('sorted');
    applyFiltersAndRender();
  });
});
function openWeekDetail(start, end){
  STATE.currentWeekStart = start;
  STATE.currentWeekEnd = end;
  var s = new Date(start+'T00:00:00'), e = new Date(end+'T00:00:00');
  el('detail-range-label').textContent = s.toLocaleDateString('en-US',{day:'numeric',month:'short'}) + ' - ' + e.toLocaleDateString('en-US',{day:'numeric',month:'short',year:'numeric'});
  var wInfo = STATE.timesheets.find(function(w){ return w.date_start === start; });
  var isLocked = wInfo && (wInfo.status === 'pending' || wInfo.status === 'approved');
  el('btn-submit-week').disabled = isLocked;
  el('btn-submit-week').textContent = isLocked ? (wInfo.status === 'approved' ? 'Approved' : 'Pending Review') : 'Submit for Approval';
  showPage('page-detail');
  loadWeekEntries();
}
function loadWeekEntries(){
  el('entries-container').innerHTML = '<div style="padding:2rem;text-align:center;color:var(--text-muted)"><span class="skeleton" style="display:inline-block;width:200px;height:16px"></span></div>';
  el('progress-label').textContent = 'Loading...';
  el('progress-bar').style.width = '0%';
  fetch('?api=week_entries&start='+STATE.currentWeekStart+'&end='+STATE.currentWeekEnd, { headers:{'X-CSRF-TOKEN':STATE.csrf} })
  .then(function(r){ return r.json(); }).then(function(d){
    if(d.success){
      STATE.weekEntries = d.entries;
      var wInfo = STATE.timesheets.find(function(w){ return w.date_start === STATE.currentWeekStart; });
      renderWeekDetail(d.total_hours, wInfo ? wInfo.std_hours : 40, wInfo ? wInfo.status : '', wInfo ? wInfo.rejection_reason : '');
    }
  }).catch(function(){});
}
function renderWeekDetail(totalHours, stdHours, status, reason){
  var pct = Math.min((totalHours/stdHours)*100, 100);
  el('progress-label').textContent = totalHours.toFixed(1) + '/' + stdHours + ' hrs';
  var bar = el('progress-bar');
  bar.style.width = pct + '%';
  if(totalHours >= stdHours) bar.classList.add('complete'); else bar.classList.remove('complete');
  var isLocked = (status === 'pending' || status === 'approved');
  var html = '';
  if(status === 'rejected'){
      html += '<div style="padding:1rem;background:var(--pink-bg);color:var(--pink);border-radius:var(--radius);font-size:0.875rem;margin-bottom:1rem;border:1px solid var(--pink)"><strong>Rejected:</strong> '+escHtml(reason)+'</div>';
  }
  var grouped = {};
  var order = [];
  STATE.weekEntries.forEach(function(entry){
    if(!grouped[entry.date]){ grouped[entry.date] = []; order.push(entry.date); }
    grouped[entry.date].push(entry);
  });
  var d = new Date(STATE.currentWeekStart+'T00:00:00');
  var dEnd = new Date(STATE.currentWeekEnd+'T00:00:00');
  var allDays = [];
  while(d <= dEnd){
    allDays.push(formatFP(d));
    d.setDate(d.getDate()+1);
  }
  var html = '';
  allDays.forEach(function(dateStr){
    var dayLabel = formatDayLabel(dateStr);
    var entries = grouped[dateStr] || [];
    html += '<div class="day-group"><div class="day-label">'+dayLabel+'</div>';
    if(entries.length){
      entries.forEach(function(en){
        html += '<div class="entry-row animate__animated animate__fadeIn" data-id="'+en.id+'">';
        html += '<div class="entry-desc">'+escHtml(en.description)+'</div>';
        html += '<div class="entry-hours">'+parseFloat(en.hours)+'h</div>';
        html += '<span class="project-chip">'+escHtml(en.project_name)+'</span>';
        if(!isLocked){
            html += '<div class="entry-menu-wrap">';
            html += '<button class="entry-menu-btn" data-id="'+en.id+'">⋯</button>';
            html += '<div class="entry-menu-dropdown" id="menu-'+en.id+'">';
            html += '<div class="entry-menu-item edit-entry" data-id="'+en.id+'"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>Edit</div>';
            html += '<div class="entry-menu-item del delete-entry" data-id="'+en.id+'"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>Delete</div>';
            html += '</div></div>';
        }
        html += '</div>';
      });
    }
    if(!isLocked){
        html += '<div class="add-task-row"><button class="btn-add-task" data-date="'+dateStr+'"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg> Add new task</button></div>';
    }
    html += '</div>';
  });
  el('entries-container').innerHTML = html;
  el('entries-container').querySelectorAll('.entry-menu-btn').forEach(function(btn){
    btn.addEventListener('click', function(e){
      e.stopPropagation();
      var menu = el('menu-'+this.dataset.id);
      if(STATE.activeOpenMenu && STATE.activeOpenMenu !== menu){ STATE.activeOpenMenu.classList.remove('open'); }
      menu.classList.toggle('open');
      STATE.activeOpenMenu = menu.classList.contains('open') ? menu : null;
    });
  });
  el('entries-container').querySelectorAll('.edit-entry').forEach(function(btn){
    btn.addEventListener('click', function(){
      var id = parseInt(this.dataset.id);
      var entry = STATE.weekEntries.find(function(e){ return e.id == id; });
      if(entry) openEditModal(entry);
    });
  });
  el('entries-container').querySelectorAll('.delete-entry').forEach(function(btn){
    btn.addEventListener('click', function(){
      var id = parseInt(this.dataset.id);
      confirmDeleteEntry(id);
    });
  });
  el('entries-container').querySelectorAll('.btn-add-task').forEach(function(btn){
    btn.addEventListener('click', function(){
      openAddModal(this.dataset.date);
    });
  });
}
function prefetchProjectsAndTypes(){
  if(!STATE.projects.length){
    fetch('?api=projects',{headers:{'X-CSRF-TOKEN':STATE.csrf}}).then(function(r){return r.json();}).then(function(d){ if(d.success) STATE.projects = d.projects; });
  }
  if(!STATE.workTypes.length){
    fetch('?api=work_types',{headers:{'X-CSRF-TOKEN':STATE.csrf}}).then(function(r){return r.json();}).then(function(d){ if(d.success) STATE.workTypes = d.work_types; });
  }
}
function populateModalSelects(projectId, workTypeId){
  var ps = el('modal-project');
  ps.innerHTML = '<option value="">— Select Project —</option>';
  STATE.projects.forEach(function(p){ ps.innerHTML += '<option value="'+p.id+'"'+(p.id==projectId?' selected':'')+'>'+escHtml(p.name)+'</option>'; });
  var ws = el('modal-work-type');
  ws.innerHTML = '<option value="">— Select Work Type —</option>';
  STATE.workTypes.forEach(function(w){ ws.innerHTML += '<option value="'+w.id+'"'+(w.id==workTypeId?' selected':'')+'>'+escHtml(w.name)+'</option>'; });
}
function openAddModal(dateStr){
  clearErrors();
  STATE.editingEntryId = null;
  el('modal-title-text').textContent = 'Add New Entry';
  el('modal-entry-id').value = '';
  el('modal-entry-date').value = dateStr;
  el('modal-desc').value = '';
  el('modal-hours').value = '1';
  el('modal-save-btn').textContent = 'Add entry';
  populateModalSelects(null, null);
  openModal('entry-modal');
}
function openEditModal(entry){
  clearErrors();
  STATE.editingEntryId = entry.id;
  el('modal-title-text').textContent = 'Edit Entry';
  el('modal-entry-id').value = entry.id;
  el('modal-entry-date').value = entry.date;
  el('modal-desc').value = entry.description;
  el('modal-hours').value = entry.hours;
  el('modal-save-btn').textContent = 'Save changes';
  populateModalSelects(entry.project_id, entry.work_type_id);
  openModal('entry-modal');
}
function openModal(id){
  var m = el(id);
  m.classList.add('open');
  var box = m.querySelector('.modal-box,.cp-modal-box');
  if(box){ box.classList.remove('animate__zoomIn'); void box.offsetWidth; box.classList.add('animate__zoomIn'); }
}
function closeModal(id){ el(id).classList.remove('open'); }
el('modal-close-btn').addEventListener('click', function(){ closeModal('entry-modal'); });
el('modal-cancel-btn').addEventListener('click', function(){ closeModal('entry-modal'); });
el('entry-modal').addEventListener('click', function(e){ if(e.target === this) closeModal('entry-modal'); });
el('hours-minus').addEventListener('click', function(){
  var v = parseFloat(el('modal-hours').value)||1;
  if(v > 0.5) el('modal-hours').value = Math.round((v-0.5)*10)/10;
});
el('hours-plus').addEventListener('click', function(){
  var v = parseFloat(el('modal-hours').value)||1;
  if(v < 24) el('modal-hours').value = Math.round((v+0.5)*10)/10;
});
el('modal-save-btn').addEventListener('click', function(){
  clearErrors();
  var projectId = parseInt(el('modal-project').value)||0;
  var workTypeId = parseInt(el('modal-work-type').value)||0;
  var desc = el('modal-desc').value.trim();
  var hours = parseFloat(el('modal-hours').value)||0;
  var date = el('modal-entry-date').value;
  var entryId = parseInt(el('modal-entry-id').value)||0;
  var valid = true;
  if(!projectId){ showErr('err-modal-project','Please select a project.'); valid=false; }
  if(!workTypeId){ showErr('err-modal-work-type','Please select a work type.'); valid=false; }
  if(!desc){ showErr('err-modal-desc','Task description is required.'); valid=false; }
  if(!hours || hours < 0.5 || hours > 24){ showErr('err-modal-hours','Enter hours between 0.5 and 24.'); valid=false; }
  if(!valid) return;
  var btn = el('modal-save-btn');
  btn.disabled=true; btn.innerHTML='<span class="loading-spinner"></span>Saving...';
  var body = { date:date, project_id:projectId, work_type_id:workTypeId, description:desc, hours:hours };
  if(entryId) body.id = entryId;
  apiCall('entry','POST',body).then(function(d){
    btn.disabled=false; btn.textContent = entryId ? 'Save changes' : 'Add entry';
    if(d.success){
      closeModal('entry-modal');
      Swal.fire({ icon:'success', title:'Saved!', text:d.message, timer:1800, showConfirmButton:false, toast:true, position:'top-end' });
      loadWeekEntries();
      loadTimesheets();
    } else {
      Swal.fire({ icon:'error', title:'Error', text:d.error||'Failed to save entry.' });
    }
  }).catch(function(){
    btn.disabled=false; btn.textContent = entryId ? 'Save changes' : 'Add entry';
    Swal.fire({ icon:'error', title:'Network Error', text:'Please try again.' });
  });
});
function confirmDeleteEntry(id){
  Swal.fire({ title:'Delete this entry?', text:'This action cannot be undone.', icon:'warning', showCancelButton:true, confirmButtonText:'Yes, delete it', confirmButtonColor:'#DC2626', cancelButtonText:'Cancel' }).then(function(r){
    if(r.isConfirmed){
      apiCall('entry','DELETE',{id:id}).then(function(d){
        if(d.success){
          Swal.fire({ icon:'success', title:'Deleted', text:'Entry removed.', timer:1500, showConfirmButton:false, toast:true, position:'top-end' });
          loadWeekEntries();
          loadTimesheets();
        } else {
          Swal.fire({ icon:'error', title:'Error', text:d.error||'Delete failed.' });
        }
      });
    }
  });
}
el('btn-change-password').addEventListener('click', function(){
  el('user-dropdown').classList.remove('open');
  el('cp-current').value=''; el('cp-new').value=''; el('cp-confirm').value='';
  clearErrors();
  openModal('cp-modal');
});
el('cp-modal-close').addEventListener('click', function(){ closeModal('cp-modal'); });
el('cp-cancel-btn').addEventListener('click', function(){ closeModal('cp-modal'); });
el('cp-modal').addEventListener('click', function(e){ if(e.target===this) closeModal('cp-modal'); });
el('cp-save-btn').addEventListener('click', function(){
  clearErrors();
  var cur = el('cp-current').value;
  var nw = el('cp-new').value;
  var conf = el('cp-confirm').value;
  var valid = true;
  if(!cur){ showErr('err-cp-current','Current password is required.'); valid=false; }
  if(!nw){ showErr('err-cp-new','New password is required.'); valid=false; }
  else if(nw.length < 8){ showErr('err-cp-new','Minimum 8 characters required.'); valid=false; }
  else if(!/[!@#$%^&*()\-_=+\[\]{}|;:,.<>?]/.test(nw)){ showErr('err-cp-new','Must contain at least one special character.'); valid=false; }
  if(nw !== conf){ showErr('err-cp-confirm','Passwords do not match.'); valid=false; }
  if(!valid) return;
  var btn = el('cp-save-btn');
  btn.disabled=true; btn.innerHTML='<span class="loading-spinner"></span>Updating...';
  apiCall('change_password','POST',{current_password:cur,new_password:nw,confirm_password:conf}).then(function(d){
    btn.disabled=false; btn.textContent='Update Password';
    if(d.success){
      closeModal('cp-modal');
      Swal.fire({ icon:'success', title:'Password Updated', text:d.message, timer:2000, showConfirmButton:false, toast:true, position:'top-end' });
    } else {
      Swal.fire({ icon:'error', title:'Error', text:d.error||'Failed to update password.' });
    }
  }).catch(function(){
    btn.disabled=false; btn.textContent='Update Password';
    Swal.fire({ icon:'error', title:'Network Error', text:'Please try again.' });
  });
});
function escHtml(str){
  if(!str) return '';
  return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
}
function init(){
  loadCaptcha();
  fetch('?api=me',{headers:{'X-CSRF-TOKEN':''}}).then(function(r){return r.json();}).then(function(d){
    if(d.success){
      STATE.csrf = d.csrf;
      setUserUI(d.user);
      showMain();
    } else {
      showLogin();
    }
  }).catch(function(){ showLogin(); });
}
var _idleTimer;
function resetIdleTimer(){
  clearTimeout(_idleTimer);
  _idleTimer = setTimeout(function(){
    Swal.fire({ icon:'warning', title:'Session Expired', text:'You have been logged out due to inactivity.', confirmButtonText:'OK', confirmButtonColor:'var(--blue)' }).then(function(){
      apiCall('logout','POST',{}).then(function(){ showLogin(); });
    });
  }, 40*60*1000);
}
['mousemove','keydown','click','scroll','touchstart'].forEach(function(ev){ document.addEventListener(ev, resetIdleTimer, {passive:true}); });
resetIdleTimer();
init();

if('serviceWorker' in navigator) {
  navigator.serviceWorker.register('?api=sw');
}
var deferredPrompt;
window.addEventListener('beforeinstallprompt', function(e){
  e.preventDefault();
  deferredPrompt = e;
  var btn = el('btn-install-pwa');
  if(btn) btn.style.display = 'inline-block';
});
var installBtn = el('btn-install-pwa');
if(installBtn){
  installBtn.addEventListener('click', function(){
    if(deferredPrompt){
      deferredPrompt.prompt();
      deferredPrompt.userChoice.then(function(res){
        if(res.outcome === 'accepted'){ installBtn.style.display = 'none'; }
        deferredPrompt = null;
      });
    }
  });
}
})();
</script>
</body>
</html>
