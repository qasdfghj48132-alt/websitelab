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
<script>
fetch('auth.php?api=me').then(r=>r.json()).then(d=>{document.getElementById('profile-csrf').value=d.csrf||'';});
document.getElementById('profile-form').addEventListener('submit',async e=>{
 e.preventDefault();const f=e.currentTarget;const d=new FormData(f);
 const r=await fetch('auth.php?api=profile',{method:'POST',headers:{'X-CSRF-Token':d.get('csrf')},body:d});const x=await r.json();
 document.getElementById('profile-message').textContent=x.message||'Ошибка';
 if(x.ok) location.reload();
});
document.getElementById('profile-logout').addEventListener('click',async()=>{
 const t=document.getElementById('profile-csrf').value;
 const d=new FormData();d.append('csrf',t);
 await fetch('auth.php?api=logout',{method:'POST',headers:{'X-CSRF-Token':t},body:d});
 location.href='index.html';
});
</script>
</body></html>