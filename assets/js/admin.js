(function () {
	document.addEventListener('submit', function (event) {
		var form = event.target;
		var message = form.getAttribute('data-wra-confirm');

		if (message && !window.confirm(message)) {
			event.preventDefault();
		}
	});
}());
