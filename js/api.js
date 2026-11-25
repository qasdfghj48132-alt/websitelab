/**
 * Единый клиент запросов к PHP API сайта «Уютный берег».
 * Повторные запросы сессии объединяются, повторная отправка одной формы блокируется.
 */
(function (window) {
  'use strict';

  const sessionState = {
    promise: null,
    data: null
  };

  const pendingRequests = new Map();
  const formIds = new WeakMap();
  let nextFormId = 1;

  async function readJson(response) {
    let data;

    try {
      data = await response.json();
    } catch (error) {
      throw new Error('Сервер вернул некорректный ответ.');
    }

    if (!response.ok) {
      throw new Error(data.message || 'Ошибка запроса к серверу.');
    }

    return data;
  }

  function getFormKey(form) {
    if (!formIds.has(form)) {
      formIds.set(form, nextFormId++);
    }

    return 'form:' + formIds.get(form);
  }

  /**
   * Получает текущую сессию и CSRF-токен.
   * Одновременные вызовы используют один и тот же Promise.
   */
  function getSession() {
    if (sessionState.data) {
      return Promise.resolve(sessionState.data);
    }

    if (!sessionState.promise) {
      sessionState.promise = fetch('auth.php?api=me', {
        method: 'GET',
        credentials: 'same-origin',
        headers: {
          'Accept': 'application/json'
        }
      })
        .then(readJson)
        .then(function (data) {
          sessionState.data = data;
          return data;
        })
        .catch(function (error) {
          sessionState.promise = null;
          throw error;
        });
    }

    return sessionState.promise;
  }

  /**
   * Отправляет POST-запрос.
   * formOrData может быть HTML-формой или объектом FormData.
   */
  function post(action, formOrData, options) {
    const settings = options || {};
    const isForm = formOrData instanceof HTMLFormElement;
    const formData = isForm
      ? new FormData(formOrData)
      : (formOrData instanceof FormData ? formOrData : new FormData());

    const requestKey = settings.requestKey
      || (isForm ? getFormKey(formOrData) : 'action:' + action);

    if (pendingRequests.has(requestKey)) {
      return pendingRequests.get(requestKey);
    }

    const requestPromise = (async function () {
      let csrfToken = settings.csrf || formData.get('csrf');

      if (!csrfToken) {
        const session = await getSession();
        csrfToken = session.csrf || '';
      }

      if (csrfToken && !formData.has('csrf')) {
        formData.append('csrf', csrfToken);
      }

      const response = await fetch('auth.php?api=' + encodeURIComponent(action), {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Accept': 'application/json',
          'X-CSRF-Token': csrfToken
        },
        body: formData
      });

      return readJson(response);
    })().finally(function () {
      pendingRequests.delete(requestKey);
    });

    pendingRequests.set(requestKey, requestPromise);
    return requestPromise;
  }

  window.SiteAPI = {
    getSession: getSession,
    post: post
  };
})(window);
