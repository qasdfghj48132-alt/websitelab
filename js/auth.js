document.addEventListener('DOMContentLoaded', async () => {
  const header = document.querySelector('.header-copy');
  if (!header) return;
  let isAuthenticated = false;
  const button = document.createElement('button');
  button.type = 'button'; button.className = 'button button-light auth-open-button';
  button.textContent = 'Войти / Регистрация';
  header.appendChild(button);
  const overlay = document.createElement('div');
  overlay.className = 'auth-overlay';
  overlay.innerHTML = `<section class="auth-dialog" role="dialog" aria-modal="true" aria-labelledby="auth-title">
    <button type="button" class="auth-close" aria-label="Закрыть">×</button>
    <h2 id="auth-title">Личный кабинет</h2>
    <div class="auth-tabs"><button type="button" class="button" data-tab="login">Войти</button><button type="button" class="button button-light" data-tab="register">Регистрация</button></div>
    <form class="auth-form" id="auth-login"><label>Логин<input name="login" required autocomplete="username"></label><label>Пароль<input name="password" type="password" required autocomplete="current-password"></label><button class="button">Войти</button><button class="auth-link" type="button" data-tab="forgot">Забыли пароль?</button></form>
    <form class="auth-form" id="auth-register" hidden><label>Логин<input name="login" required minlength="3" maxlength="30" pattern="[A-Za-z0-9_]+" autocomplete="username"></label><label>Пароль<input name="password" type="password" required minlength="10" autocomplete="new-password"></label><label>ФИО<input name="full_name" required maxlength="150"></label><label>Электронная почта<input name="email" type="email" required></label><label>Аватар (JPG, PNG или WebP, до 2 МБ)<input name="avatar" type="file" accept="image/jpeg,image/png,image/webp" required></label><button class="button">Зарегистрироваться</button></form>
    <form class="auth-form" id="auth-forgot" hidden><p>Укажите почту, использованную при регистрации. Ссылка будет показана на экране.</p><label>Электронная почта<input name="email" type="email" required></label><button class="button">Создать ссылку</button></form>
    <p class="auth-message" id="auth-message" role="status" aria-live="polite"></p>
  </section>`;
  document.body.appendChild(overlay);
  const dialog = overlay.querySelector('.auth-dialog'), message = overlay.querySelector('#auth-message');
  let csrf = '';
  const show = name => {
    ['login','register','forgot'].forEach(n => dialog.querySelector('#auth-'+n).hidden = n !== name);
    dialog.querySelectorAll('[data-tab]').forEach(b => b.classList.toggle('button-light', b.dataset.tab !== name));
    message.textContent = '';
  };
  const open = () => { overlay.classList.add('is-open'); show('login'); dialog.querySelector('input').focus(); };
  const close = () => overlay.classList.remove('is-open');
  button.addEventListener('click', () => { if (isAuthenticated) location.href='profile.php'; else open(); });
  dialog.querySelector('.auth-close').addEventListener('click', close);
  overlay.addEventListener('click', e => { if (e.target === overlay) close(); });
  document.addEventListener('keydown', e => { if (e.key === 'Escape') close(); });
  dialog.querySelectorAll('[data-tab]').forEach(b => b.addEventListener('click', () => show(b.dataset.tab)));
  async function request(action, form) {
    const data = new FormData(form); data.append('csrf', csrf);
    const response = await fetch('auth.php?api='+action, {method:'POST', headers:{'X-CSRF-Token':csrf}, body:data});
    return response.json();
  }
  async function refresh() {
    try {
      const response = await fetch('auth.php?api=me');
      const data = await response.json(); csrf = data.csrf || '';
      if (data.user) { isAuthenticated = true; button.textContent = 'Личный кабинет'; }
    } catch (_) { /* GitHub Pages не исполняет PHP: статическая часть сайта остаётся доступной. */ }
  }
  dialog.querySelector('#auth-login').addEventListener('submit', async e => {
    e.preventDefault(); message.textContent = 'Проверяем данные…';
    try { const result = await request('login', e.currentTarget); message.textContent = result.message || 'Ошибка'; if(result.ok) location.href='profile.php'; }
    catch (_) { message.textContent = 'Сервер авторизации недоступен. Запустите сайт на PHP-сервере.'; }
  });
  dialog.querySelector('#auth-register').addEventListener('submit', async e => {
    e.preventDefault(); message.textContent = 'Создаём аккаунт…';
    try { const result = await request('register', e.currentTarget); message.textContent = result.message || 'Ошибка'; if(result.ok) location.href='profile.php'; }
    catch (_) { message.textContent = 'Сервер авторизации недоступен. Запустите сайт на PHP-сервере.'; }
  });
  dialog.querySelector('#auth-forgot').addEventListener('submit', async e => {
    e.preventDefault(); message.textContent = 'Создаём ссылку…';
    try { const result = await request('reset-request', e.currentTarget); message.textContent = result.message || 'Ошибка';
      if(result.reset_url) { const a=document.createElement('a');a.href=result.reset_url;a.textContent='Открыть ссылку восстановления пароля';a.target='_blank';a.rel='noopener';message.append(' ',a); }
    } catch (_) { message.textContent = 'Сервер авторизации недоступен.'; }
  });
  await refresh();
  if (new URLSearchParams(location.search).get('auth') === 'required') open();
});