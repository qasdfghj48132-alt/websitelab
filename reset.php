<?php
$token = isset($_GET['token']) && preg_match('/^[a-f0-9]{64}$/', $_GET['token']) ? $_GET['token'] : '';
?>
<!DOCTYPE html><html lang="ru"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Восстановление пароля — Уютный берег</title><link rel="stylesheet" href="styles.css"><link rel="stylesheet" href="auth.css"></head>
<body class="profile-page"><main class="profile-card"><a href="index.html">← На главную</a><h1>Новый пароль</h1>
<?php if ($token === ''): ?><p>Ссылка восстановления некорректна.</p><?php else: ?>
<form id="reset-form" class="auth-form"><label>Новый пароль<input type="password" name="password" minlength="10" required autocomplete="new-password"></label><button class="button">Сохранить пароль</button><p id="reset-message" role="status"></p></form>
<script>
fetch('auth.php?api=me').then(r=>r.json()).then(d=>{
document.getElementById('reset-form').addEventListener('submit',async e=>{
e.preventDefault();const f=e.currentTarget,body=new FormData(f);body.append('token',<?= json_encode($token) ?>);body.append('csrf',d.csrf);
const r=await fetch('auth.php?api=reset-complete',{method:'POST',headers:{'X-CSRF-Token':d.csrf},body});const x=await r.json();
document.getElementById('reset-message').textContent=x.message||'Ошибка';if(x.ok)setTimeout(()=>location.href='index.html',1800);
});
});
</script>
<?php endif; ?></main></body></html>