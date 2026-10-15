// Небольшой скрипт для подготовки письма с запросом на бронирование.
document.addEventListener("DOMContentLoaded", function () {
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
