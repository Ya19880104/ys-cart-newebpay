(function () {
	'use strict';

	function post(endpoint, data) {
		if (window.YsEcApi && typeof window.YsEcApi.post === 'function') {
			return window.YsEcApi.post(endpoint, data);
		}

		var base = (window.ysEcStoreSelectorConfig && window.ysEcStoreSelectorConfig.restUrl) || '/wp-json/ys-ecommerce-headless/v1';
		return fetch(base.replace(/\/$/, '') + endpoint, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify(data || {})
		}).then(function (res) { return res.json(); });
	}

	function openMapViaForm(actionUrl, fields) {
		var popupName = 'ys_ec_newebpay_store_map';
		window.open('', popupName, 'width=1100,height=760,scrollbars=yes,resizable=yes');

		var form = document.createElement('form');
		form.method = 'POST';
		form.action = actionUrl;
		form.target = popupName;
		form.style.display = 'none';

		Object.keys(fields || {}).forEach(function (key) {
			var input = document.createElement('input');
			input.type = 'hidden';
			input.name = key;
			input.value = fields[key];
			form.appendChild(input);
		});

		document.body.appendChild(form);
		form.submit();
		window.setTimeout(function () {
			if (form.parentNode) {
				form.parentNode.removeChild(form);
			}
		}, 1000);
	}

	function handleClick(event) {
		var btn = event.target.closest('.ys-ec-select-store-btn[data-provider="newebpay"], .ys-ec-change-store[data-provider="newebpay"]');
		if (!btn) {
			return;
		}

		event.preventDefault();
		event.stopImmediatePropagation();

		var shippingId = btn.dataset.shippingId || '';
		if (!shippingId) {
			return;
		}

		var originalHTML = btn.innerHTML;
		btn.disabled = true;
		btn.textContent = '開啟門市地圖...';

		post('/stores/newebpay/map-url', { shipping_id: shippingId })
			.then(function (res) {
				btn.disabled = false;
				btn.innerHTML = originalHTML;

				var data = res && (res.data || res);
				if (!data || !data.action_url || !data.fields) {
					alert((res && res.message) || '無法取得 NewebPay 門市地圖。');
					return;
				}

				openMapViaForm(data.action_url, data.fields);
			})
			.catch(function (err) {
				btn.disabled = false;
				btn.innerHTML = originalHTML;
				alert((err && err.message) || 'NewebPay 門市地圖開啟失敗。');
			});
	}

	document.addEventListener('click', handleClick, true);
})();
