(function () {
  'use strict';

  if (document.getElementById('accessibility-tools')) return;

  var key = 'uyutny-bereg-accessibility';
  var settings = { font: 'normal', contrast: 'normal', spacing: false, hideImages: false };

  try {
    var saved = JSON.parse(localStorage.getItem(key) || 'null');
    if (saved && typeof saved === 'object') settings = Object.assign(settings, saved);
  } catch (e) {}

  var header = document.querySelector('.header-copy') || document.querySelector('.site-header') || document.body;
  var toolbar = document.getElementById('header-actions');

  if (!toolbar) {
    toolbar = document.createElement('div');
    toolbar.className = 'header-actions';
    toolbar.id = 'header-actions';
    var eyebrow = header.querySelector('.eyebrow');
    header.insertBefore(toolbar, eyebrow || header.firstChild);
  }

  var printButton = header.querySelector('#print-mode-toggle');
  if (printButton) toolbar.appendChild(printButton);

  var tools = document.createElement('div');
  tools.className = 'accessibility-tools';
  tools.id = 'accessibility-tools';

  var toggle = document.createElement('button');
  toggle.type = 'button';
  toggle.className = 'accessibility-toggle';
  toggle.textContent = 'Версия для слабовидящих';
  toggle.setAttribute('aria-expanded', 'false');
  toggle.setAttribute('aria-controls', 'accessibility-panel');

  var panel = document.createElement('section');
  panel.className = 'accessibility-panel';
  panel.id = 'accessibility-panel';
  panel.setAttribute('aria-label', 'Настройки доступности');
  panel.innerHTML = '<h2>Настройки отображения</h2><div class="accessibility-group"><strong>Размер шрифта:</strong><button type="button" data-setting="font" data-value="normal">Обычный</button><button type="button" data-setting="font" data-value="large">Крупный</button><button type="button" data-setting="font" data-value="xlarge">Очень крупный</button></div><div class="accessibility-group"><strong>Цветовая схема:</strong><button type="button" data-setting="contrast" data-value="normal">Обычная</button><button type="button" data-setting="contrast" data-value="light">Чёрный на белом</button><button type="button" data-setting="contrast" data-value="dark">Белый на чёрном</button></div><div class="accessibility-group"><button type="button" data-setting="spacing" data-value="true">Увеличить интервал</button><button type="button" data-setting="hideImages" data-value="true">Скрыть изображения</button><button type="button" data-action="reset">Сбросить настройки</button></div>';

  tools.append(toggle, panel);
  toolbar.appendChild(tools);

  function apply() {
    var body = document.body;
    body.classList.remove('a11y-font-large', 'a11y-font-xlarge', 'a11y-contrast-light', 'a11y-contrast-dark', 'a11y-spacing', 'a11y-hide-images');
    if (settings.font === 'large') body.classList.add('a11y-font-large');
    if (settings.font === 'xlarge') body.classList.add('a11y-font-xlarge');
    if (settings.contrast === 'light') body.classList.add('a11y-contrast-light');
    if (settings.contrast === 'dark') body.classList.add('a11y-contrast-dark');
    if (settings.spacing) body.classList.add('a11y-spacing');
    if (settings.hideImages) body.classList.add('a11y-hide-images');

    panel.querySelectorAll('[data-setting]').forEach(function (button) {
      var name = button.getAttribute('data-setting');
      var value = button.getAttribute('data-value');
      button.setAttribute('aria-pressed', String(settings[name]) === value ? 'true' : 'false');
    });

    try { localStorage.setItem(key, JSON.stringify(settings)); } catch (e) {}
  }

  toggle.addEventListener('click', function () {
    var open = panel.classList.toggle('is-open');
    toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
  });

  panel.addEventListener('click', function (event) {
    var button = event.target.closest('button');
    if (!button) return;
    if (button.dataset.action === 'reset') {
      settings = { font: 'normal', contrast: 'normal', spacing: false, hideImages: false };
    } else {
      var name = button.dataset.setting;
      var value = button.dataset.value;
      if (name === 'spacing' || name === 'hideImages') settings[name] = !settings[name];
      else if (name) settings[name] = value;
    }
    apply();
  });

  apply();
})();