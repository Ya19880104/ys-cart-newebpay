(function () {
  function ready(fn) {
    if (document.readyState !== 'loading') {
      fn();
      return;
    }
    document.addEventListener('DOMContentLoaded', fn);
  }

  ready(function () {
    var button = document.getElementById('ys-newebpay-test-connection');
    var result = document.getElementById('ys-newebpay-test-result');
    if (!button || !result || !window.ysNewebpayTestConnection) {
      return;
    }

    button.addEventListener('click', function () {
      button.disabled = true;
      result.textContent = 'Checking...';

      fetch(window.ysNewebpayTestConnection.endpoint, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': window.ysNewebpayTestConnection.nonce
        },
        body: '{}'
      })
        .then(function (response) {
          return response.json().then(function (data) {
            return { ok: response.ok, data: data };
          });
        })
        .then(function (payload) {
          if (payload.ok && payload.data && payload.data.success) {
            result.textContent = 'Configuration present (' + (payload.data.test_mode ? 'sandbox' : 'production') + ').';
            return;
          }

          var message = payload.data && payload.data.message ? payload.data.message : 'Configuration check failed.';
          result.textContent = message;
        })
        .catch(function () {
          result.textContent = 'Configuration check failed.';
        })
        .finally(function () {
          button.disabled = false;
        });
    });
  });
}());
