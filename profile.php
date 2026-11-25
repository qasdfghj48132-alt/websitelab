<?php
declare(strict_types=1);
require __DIR__ . '/auth.php';
$user = current_user($pdo);
if (!$user) {
    header('Location: index.html?auth=required');
    exit;
}
function h(string $value): string { return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Личный кабинет — Уютный берег</title>
<link rel="stylesheet" href="styles.css"><link rel="stylesheet" href="auth.css">
</head>
<body class="profile-page">
<main class="profile-card">
<a href="index.html">← На главную</a>
<h1>Личный кабинет</h1>
<img class="profile-avatar" src="<?= h($user['avatar']) ?>" alt="Аватар пользователя">
<p>Логин: <strong><?= h($user['login']) ?></strong> <small>(логин нельзя изменить)</small></p>
<form id="profile-form" class="auth-form" enctype="multipart/form-data">
<input type="hidden" name="csrf" id="profile-csrf">
<label>ФИО<input name="full_name" value="<?= h($user['full_name']) ?>" required maxlength="150"></label>
<label>Электронная почта<input name="email" type="email" value="<?= h($user['email']) ?>" required></label>
<label>Новый аватар<input name="avatar" type="file" accept="image/jpeg,image/png,image/webp"></label>
<label>Новый пароль <small>(оставьте пустым, если менять не нужно)</small><input name="password" type="password" minlength="10" autocomplete="new-password"></label>
<button class="button" type="submit">Сохранить изменения</button>
<p id="profile-message" role="status"></p>
</form>
<button class="button button-light" id="profile-logout" type="button">Выйти из аккаунта</button>
</main>
<script src="js/api.js"></script>
<script>
document.addEventListener('DOMContentLoaded', async function () {
  const form = document.getElementById('profile-form');
  const message = document.getElementById('profile-message');
  const csrfInput = document.getElementById('profile-csrf');
  const logoutButton = document.getElementById('profile-logout');

  try {
    const session = await SiteAPI.getSession();
    csrfInput.value = session.csrf || '';
  } catch (error) {
    message.textContent = 'Не удалось получить данные сессии. Обновите страницу.';
  }

  form.addEventListener('submit', async function (event) {
    event.preventDefault();
    message.textContent = 'Сохраняем изменения…';

    try {
      const result = await SiteAPI.post('profile', form);
      message.textContent = result.message || 'Изменения сохранены.';

      if (result.ok) {
        location.reload();
      }
    } catch (error) {
      message.textContent = error.message || 'Не удалось сохранить профиль.';
    }
  });

  logoutButton.addEventListener('click', async function () {
    logoutButton.disabled = true;

    try {
      const data = new FormData();
      data.append('csrf', csrfInput.value);

      await SiteAPI.post('logout', data, {
        csrf: csrfInput.value,
        requestKey: 'profile-logout'
      });

      location.href = 'index.html';
    } catch (error) {
      logoutButton.disabled = false;
      message.textContent = error.message || 'Не удалось выйти из аккаунта.';
    }
  });
});
</script>
</body></html>