// Умный фильтр каталога. Данные хранятся в массиве объектов JavaScript.
document.addEventListener("DOMContentLoaded", function () {
	const roomGrid = document.getElementById("room-grid");
	if (!roomGrid) return;

	// Цены и характеристики соответствуют учебному прайс-листу сайта.
	const rooms = [
		{
			id: "standard",
			name: "Стандарт",
			description: "Комфортный номер для одного или двух гостей. Подходит для короткой поездки.",
			price: 2500,
			minGuests: 1,
			maxGuests: 2,
			extraBed: false,
			image: "images/room.jpg",
			imageAlt: "Светлый стандартный номер с большой кроватью"
		},
		{
			id: "comfort",
			name: "Комфорт",
			description: "Больше пространства, зона отдыха и дополнительное место по запросу.",
			price: 3400,
			minGuests: 1,
			maxGuests: 3,
			extraBed: true,
			image: "images/room.jpg",
			imageAlt: "Уютный номер повышенной комфортности"
		}
	];

	const controls = {
		type: document.getElementById("filter-type"),
		guests: document.getElementById("filter-guests"),
		price: document.getElementById("filter-price"),
		extra: document.getElementById("filter-extra")
	};
	const results = document.getElementById("filter-results");
	const resetButton = document.getElementById("reset-filters");

	// Значения цен — пороги, а не новые цены на проживание.
	const priceLimits = [3000, 3500, 4000];

	function readCurFilters() {
		return {
			type: controls.type.value,
			guests: controls.guests.value,
			price: controls.price.value,
			extra: controls.extra.checked
		};
	}

	function matchesRoom(room, filters, ignoredKey) {
		if (ignoredKey !== "type" && filters.type !== "all" && room.id !== filters.type) return false;
		if (ignoredKey !== "guests" && filters.guests !== "all") {
			const count = Number(filters.guests);
			if (count < room.minGuests || count > room.maxGuests) return false;
		}
		if (ignoredKey !== "price" && filters.price !== "all" && room.price > Number(filters.price)) return false;
		if (ignoredKey !== "extra" && filters.extra && !room.extraBed) return false;
		return true;
	}

	function roomsForOtherFilters(filters, ignoredKey) {
		return rooms.filter(function (room) {
			return matchesRoom(room, filters, ignoredKey);
		});
	}

	function fillSelect(select, values, currentValue, placeholder, formatter) {
		select.replaceChildren();
		const first = document.createElement("option");
		first.value = "all";
		first.textContent = placeholder;
		select.appendChild(first);

		values.forEach(function (value) {
			const option = document.createElement("option");
			option.value = String(value);
			option.textContent = formatter(value);
			select.appendChild(option);
		});

		const available = values.some(function (value) {
			return String(value) === currentValue;
		});
		select.value = available ? currentValue : "all";
	}

	// Для каждого поля варианты вычисляются с учётом остальных выбранных полей.
	function printFilters(filters) {
		const typeRooms = roomsForOtherFilters(filters, "type");
		const typeValues = rooms.map(room => room.id).filter(function (id) {
			return typeRooms.some(room => room.id === id);
		});
		fillSelect(controls.type, typeValues, filters.type, "Любой тип", function (id) {
			return rooms.find(room => room.id === id).name;
		});

		const guestRooms = roomsForOtherFilters(filters, "guests");
		const guestValues = Array.from(new Set(guestRooms.flatMap(function (room) {
			return Array.from({ length: room.maxGuests - room.minGuests + 1 }, function (_, index) {
				return room.minGuests + index;
			});
		}))).sort((a, b) => a - b);
		fillSelect(controls.guests, guestValues, filters.guests, "Не важно", value => value + (value === 1 ? " гость" : value < 5 ? " гостя" : " гостей"));

		const priceRooms = roomsForOtherFilters(filters, "price");
		const priceValues = priceLimits.filter(function (limit) {
			return priceRooms.some(room => room.price <= limit);
		});
		fillSelect(controls.price, priceValues, filters.price, "Без ограничения", value => "До " + value.toLocaleString("ru-RU") + " ₽");

		const extraRooms = roomsForOtherFilters(filters, "extra");
		controls.extra.disabled = !extraRooms.some(room => room.extraBed);
		if (controls.extra.disabled) controls.extra.checked = false;
	}

	function printItems(filteredRooms) {
		roomGrid.replaceChildren();

		if (filteredRooms.length === 0) {
			const empty = document.createElement("div");
			empty.className = "filter-empty";
			empty.textContent = "По выбранным параметрам номеров не найдено. Измените условия фильтрации.";
			roomGrid.appendChild(empty);
			results.textContent = "Подходящих вариантов: 0";
			return;
		}

		filteredRooms.forEach(function (room) {
			const card = document.createElement("article");
			card.className = "room-card";
			const image = document.createElement("img");
			image.src = room.image;
			image.alt = room.imageAlt;

			const copy = document.createElement("div");
			copy.className = "room-copy";
			const heading = document.createElement("h3");
			heading.textContent = room.name;
			const description = document.createElement("p");
			description.textContent = room.description;
			const capacity = document.createElement("p");
			capacity.className = "room-feature";
			capacity.textContent = "Вместимость: до " + room.maxGuests + (room.maxGuests === 1 ? " гостя" : " гостей");
			const price = document.createElement("p");
			price.className = "room-price";
			price.textContent = "от " + room.price.toLocaleString("ru-RU") + " ₽ / ночь";
			const features = document.createElement("p");
			features.className = "room-feature";
			features.textContent = room.extraBed ? "Дополнительное место доступно по запросу (+700 ₽ / ночь)." : "Дополнительное место не предусмотрено.";

			const link = document.createElement("a");
			link.href = "price.html";
			link.textContent = "Условия и цены →";

			copy.append(heading, description, capacity, price, features, link);
			card.append(image, copy);
			roomGrid.appendChild(card);
		});

		results.textContent = "Подходящих вариантов: " + filteredRooms.length + " из " + rooms.length;
	}

	function applyFilters() {
		let filters = readCurFilters();
		// Повторный проход нужен, если изменение одного поля сбросило другое
		// (например, выбранное число гостей стало недоступно для нового типа номера).
		for (let pass = 0; pass < 3; pass++) {
			printFilters(filters);
			filters = readCurFilters();
		}
		const filteredRooms = rooms.filter(room => matchesRoom(room, filters));
		printItems(filteredRooms);
	}

	controls.type.addEventListener("change", applyFilters);
	controls.guests.addEventListener("change", applyFilters);
	controls.price.addEventListener("change", applyFilters);
	controls.extra.addEventListener("change", applyFilters);

	resetButton.addEventListener("click", function () {
		controls.type.value = "all";
		controls.guests.value = "all";
		controls.price.value = "all";
		controls.extra.checked = false;
		applyFilters();
	});

	applyFilters();
});
