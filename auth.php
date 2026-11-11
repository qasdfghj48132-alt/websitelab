<?php
declare(strict_types=1);

$configFile = __DIR__ . '/config.php';
$config = require (is_file($configFile) ? $configFile : __DIR__ . '/config.example.php');

ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
session_set_cookie_params([
    'lifetime' => 0, 'path' => '/', 'secure' => (bool)$config['secure_cookie'],
    'httponly' => true, 'samesite' => 'Lax'
]);
session_start();

header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header('X-Frame-Options: DENY');

try {
    $pdo = new PDO(
        'mysql:host=' . $config['db_host'] . ';dbname=' . $config['db_name'] . ';charset=utf8mb4',
        $config['db_user'],
        $config['db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
         PDO::ATTR_EMULATE_PREPARES => false]
    );
} catch (Throwable $e) {
    if (isset($_GET['api'])) { json_response(['ok' => false, 'message' => 'Не удалось подключиться к базе данных. Проверьте config.php и импорт database.sql.'], 503); }
    http_response_code(503);
    exit('Сервис авторизации пока не настроен. Проверьте config.php и базу данных.');
}

function json_response(array $data, int $status = 200): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
function clean_text(string $value): string { return trim($value); }
function current_user(PDO $pdo): ?array {
    if (!empty($_SESSION['user_id'])) {
        $q = $pdo->prepare('SELECT id, login, full_name, email, avatar FROM users WHERE id = ?');
        $q->execute([(int)$_SESSION['user_id']]);
        return $q->fetch() ?: null;
    }
    $cookie = $_COOKIE['ub_remember'] ?? '';
    if (!preg_match('/^([a-f0-9]{24}):([a-f0-9]{64})$/', $cookie, $m)) return null;
    $q = $pdo->prepare('SELECT r.id AS token_id, r.user_id, r.validator_hash, r.expires_at, u.id, u.login, u.full_name, u.email, u.avatar
                        FROM remember_tokens r JOIN users u ON u.id = r.user_id
                        WHERE r.selector = ? AND r.expires_at > NOW() LIMIT 1');
    $q->execute([$m[1]]);
    $row = $q->fetch();
    if (!$row || !hash_equals($row['validator_hash'], hash('sha256', $m[2]))) return null;
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int)$row['user_id'];
    return ['id' => (int)$row['id'], 'login' => $row['login'], 'full_name' => $row['full_name'],
            'email' => $row['email'], 'avatar' => $row['avatar']];
}
function csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function require_csrf(): void {
    $sent = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf'] ?? '');
    if (!is_string($sent) || empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $sent)) {
        json_response(['ok' => false, 'message' => 'Срок действия формы истёк. Обновите страницу и повторите попытку.'], 419);
    }
}
function save_avatar(array $file, array $config): string {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) throw new RuntimeException('Загрузите изображение аватара.');
    if (($file['size'] ?? 0) > 2 * 1024 * 1024) throw new RuntimeException('Размер аватара не должен превышать 2 МБ.');
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!isset($extensions[$mime])) throw new RuntimeException('Допустимы только изображения JPG, PNG или WebP.');
    $dir = $config['upload_dir'];
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) throw new RuntimeException('Не удалось создать папку для аватаров.');
    $name = bin2hex(random_bytes(16)) . '.' . $extensions[$mime];
    if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) throw new RuntimeException('Не удалось сохранить аватар.');
    return $config['upload_url'] . '/' . $name;
}
function set_remember(PDO $pdo, int $userId): void {
    $selector = bin2hex(random_bytes(12));
    $validator = bin2hex(random_bytes(32));
    $expires = time() + 60 * 60 * 24 * 30;
    $q = $pdo->prepare('INSERT INTO remember_tokens (user_id, selector, validator_hash, expires_at) VALUES (?, ?, ?, FROM_UNIXTIME(?))');
    $q->execute([$userId, $selector, hash('sha256', $validator), $expires]);
    setcookie('ub_remember', $selector . ':' . $validator, [
        'expires' => $expires, 'path' => '/', 'secure' => (bool)$GLOBALS['config']['secure_cookie'],
        'httponly' => true, 'samesite' => 'Lax'
    ]);
}
function user_data(PDO $pdo): ?array { return current_user($pdo); }

if (isset($_GET['api'])) {
    $action = (string)$_GET['api'];
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'me') {
        json_response(['ok' => true, 'user' => user_data($pdo), 'csrf' => csrf_token()]);
    }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['ok' => false, 'message' => 'Метод не поддерживается.'], 405);
    require_csrf();

    try {
        if ($action === 'register') {
            $login = clean_text((string)($_POST['login'] ?? ''));
            $password = (string)($_POST['password'] ?? '');
            $fullName = clean_text((string)($_POST['full_name'] ?? ''));
            $email = trim((string)($_POST['email'] ?? ''));
            if (!preg_match('/^[A-Za-z0-9_]{3,30}$/', $login)) throw new RuntimeException('Логин: 3–30 символов, латинские буквы, цифры и подчёркивание.');
            if (strlen($password) < 10) throw new RuntimeException('Пароль должен содержать не менее 10 символов.');
            if (mb_strlen($fullName) < 3 || mb_strlen($fullName) > 150) throw new RuntimeException('Укажите ФИО (до 150 символов).');
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Укажите корректный адрес электронной почты.');
            $avatar = save_avatar($_FILES['avatar'] ?? [], $config);
            $q = $pdo->prepare('INSERT INTO users (login, password_hash, full_name, email, avatar) VALUES (?, ?, ?, ?, ?)');
            $q->execute([$login, password_hash($password, PASSWORD_DEFAULT), $fullName, $email, $avatar]);
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int)$pdo->lastInsertId();
            set_remember($pdo, (int)$_SESSION['user_id']);
            json_response(['ok' => true, 'message' => 'Регистрация завершена.', 'user' => user_data($pdo)]);
        }

        if ($action === 'login') {
            $login = trim((string)($_POST['login'] ?? ''));
            $password = (string)($_POST['password'] ?? '');
            $q = $pdo->prepare('SELECT id, password_hash FROM users WHERE login = ? LIMIT 1');
            $q->execute([$login]);
            $row = $q->fetch();
            if (!$row || !password_verify($password, $row['password_hash'])) throw new RuntimeException('Неверный логин или пароль.');
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int)$row['id'];
            set_remember($pdo, (int)$row['id']);
            json_response(['ok' => true, 'message' => 'Вы успешно вошли.', 'user' => user_data($pdo)]);
        }

        if ($action === 'logout') {
            $cookie = $_COOKIE['ub_remember'] ?? '';
            if (preg_match('/^([a-f0-9]{24}):/', $cookie, $m)) {
                $q = $pdo->prepare('DELETE FROM remember_tokens WHERE selector = ?');
                $q->execute([$m[1]]);
            }
            setcookie('ub_remember', '', ['expires' => time() - 3600, 'path' => '/', 'secure' => (bool)$config['secure_cookie'], 'httponly' => true, 'samesite' => 'Lax']);
            $_SESSION = [];
            session_regenerate_id(true);
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
            json_response(['ok' => true, 'message' => 'Вы вышли из аккаунта.']);
        }

        if ($action === 'profile') {
            $user = user_data($pdo);
            if (!$user) json_response(['ok' => false, 'message' => 'Сначала войдите в аккаунт.'], 401);
            $fullName = clean_text((string)($_POST['full_name'] ?? ''));
            $email = trim((string)($_POST['email'] ?? ''));
            if (mb_strlen($fullName) < 3 || mb_strlen($fullName) > 150) throw new RuntimeException('Проверьте ФИО.');
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Проверьте адрес электронной почты.');
            $avatar = $user['avatar'];
            if (!empty($_FILES['avatar']['name'])) $avatar = save_avatar($_FILES['avatar'], $config);
            $password = (string)($_POST['password'] ?? '');
            if ($password !== '' && strlen($password) < 10) throw new RuntimeException('Новый пароль должен содержать не менее 10 символов.');
            if ($password !== '') {
                $q = $pdo->prepare('UPDATE users SET full_name=?, email=?, avatar=?, password_hash=? WHERE id=?');
                $q->execute([$fullName, $email, $avatar, password_hash($password, PASSWORD_DEFAULT), $user['id']]);
            } else {
                $q = $pdo->prepare('UPDATE users SET full_name=?, email=?, avatar=? WHERE id=?');
                $q->execute([$fullName, $email, $avatar, $user['id']]);
            }
            json_response(['ok' => true, 'message' => 'Данные профиля обновлены.', 'user' => user_data($pdo)]);
        }

        if ($action === 'reset-request') {
            $email = trim((string)($_POST['email'] ?? ''));
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Укажите корректную почту.');
            $q = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
            $q->execute([$email]);
            $row = $q->fetch();
            $result = ['ok' => true, 'message' => 'Если аккаунт с такой почтой существует, ссылка восстановления создана.'];
            if ($row) {
                $token = bin2hex(random_bytes(32));
                $q = $pdo->prepare('DELETE FROM password_resets WHERE user_id = ?');
                $q->execute([(int)$row['id']]);
                $q = $pdo->prepare('INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR))');
                $q->execute([(int)$row['id'], hash('sha256', $token)]);
                $base = rtrim((string)$config['base_url'], '/');
                if ($base === '') $base = (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? 'localhost') . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
                $result['reset_url'] = $base . '/reset.php?token=' . urlencode($token);
            }
            json_response($result);
        }

        if ($action === 'reset-complete') {
            $token = (string)($_POST['token'] ?? '');
            $password = (string)($_POST['password'] ?? '');
            if (!preg_match('/^[a-f0-9]{64}$/', $token)) throw new RuntimeException('Ссылка восстановления недействительна.');
            if (strlen($password) < 10) throw new RuntimeException('Пароль должен содержать не менее 10 символов.');
            $q = $pdo->prepare('SELECT id, user_id FROM password_resets WHERE token_hash = ? AND expires_at > NOW() LIMIT 1');
            $q->execute([hash('sha256', $token)]);
            $reset = $q->fetch();
            if (!$reset) throw new RuntimeException('Ссылка истекла или уже использована. Запросите новую.');
            $pdo->beginTransaction();
            $q = $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
            $q->execute([password_hash($password, PASSWORD_DEFAULT), (int)$reset['user_id']]);
            $q = $pdo->prepare('DELETE FROM password_resets WHERE user_id = ?');
            $q->execute([(int)$reset['user_id']]);
            $q = $pdo->prepare('DELETE FROM remember_tokens WHERE user_id = ?');
            $q->execute([(int)$reset['user_id']]);
            $pdo->commit();
            json_response(['ok' => true, 'message' => 'Пароль изменён. Теперь можно войти.']);
        }
        json_response(['ok' => false, 'message' => 'Неизвестная операция.'], 404);
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($e->getCode() === '23000') json_response(['ok' => false, 'message' => 'Такой логин или адрес почты уже зарегистрирован.'], 409);
        json_response(['ok' => false, 'message' => 'Ошибка обработки запроса. Проверьте введённые данные.'], 400);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        json_response(['ok' => false, 'message' => $e->getMessage()], 400);
    }
}
