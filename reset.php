<?php
$token = isset($_GET['token']) && preg_match('/^[a-f0-9]{64}$/', $_GET['token']) ? $_GET['token'] : '';
?>
<!DOCTYPE html><html lang="ru"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Восстановление пароля — Уютный берег</title><link rel="stylesheet" href="styles.css"><link rel="stylesheet" href="auth.css"></head>
<body class="profile-page"><main class="profile-card"><a href="index.html">← На главную</a><h1>Новый пароль</h1>
<?php if ($token === ''): ?><p>Ссылка восстановления некорректна.</p><?php else: ?>
<form id="reset-form" class="auth-form"><label>Новый пароль<input type="password" name="password" minlength="10" required autocomplete="new-password"></label><button class="button">Сохранить пароль</button><p id="reset-message" role="status"></p></form>
<script src="js/api.js"></script>
<script>
document.addEventListener('DOMContentLoaded', async function () {
  const form = document.getElementById('reset-form');
  const message = document.getElementById('reset-message');
  const resetToken = <?= json_encode($token) ?>;

  try {
    const session = await SiteAPI.getSession();

    form.addEventListener('submit', async function (event) {
      event.preventDefault();
      message.textContent = 'Сохраняем новый пароль…';

      const data = new FormData(form);
      data.append('token', resetToken);
      data.append('csrf', session.csrf || '');

      try {
        const result = await SiteAPI.post('reset-complete', data, {
          csrf: session.csrf || '',
          requestKey: 'reset-complete'
        });

        message.textContent = result.message || 'Пароль успешно изменён.';

        if (result.ok) {
          window.setTimeout(function () {
            location.href = 'index.html';
          }, 1800);
        }
      } catch (error) {
        message.textContent = error.message || 'Не удалось изменить пароль.';
      }
    });
  } catch (error) {
    message.textContent = 'Не удалось получить данные сессии. Обновите страницу.';
  }
});
</script>
<?php endif; ?></main></body></html>