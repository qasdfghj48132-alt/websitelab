// Общие динамические функции сайта «Уютный берег».
document.addEventListener("DOMContentLoaded", function () {
	// Переключение между обычным видом и упрощённым видом для печати.
	const printButton = document.getElementById("print-mode-toggle");

	if (printButton) {
		printButton.addEventListener("click", function () {
			const enabled = document.body.classList.toggle("print-mode");
			printButton.setAttribute("aria-pressed", String(enabled));
			printButton.textContent = enabled ? "Вернуться к обычной версии" : "Версия для печати";
			printButton.setAttribute("aria-label", printButton.textContent);
		});
	}
	// Существующая форма запроса на бронирование — сохранена без изменения логики.
	const form = document.getElementById("booking-form");
	const message = document.getElementById("form-message");

	if (!form || !message) {
		return;
	}

	form.addEventListener("submit", function (event) {
		event.preventDefault();

		const name = document.getElementById("guest-name").value.trim();
		const email = document.getElementById("guest-email").value.trim();
		const request = document.getElementById("guest-message").value.trim();
		const subject = encodeURIComponent("Запрос на бронирование — Уютный берег");
		const body = encodeURIComponent("Имя: " + name + "\nПочта: " + email + "\nПожелания: " + request);

		message.textContent = "Открываем почтовую программу с подготовленным запросом.";
		window.location.href = "mailto:info@uyutbereg.example?subject=" + subject + "&body=" + body;
	});
});
