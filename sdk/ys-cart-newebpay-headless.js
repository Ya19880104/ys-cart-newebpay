export const YS_CART_NEWEBPAY_ROUTES = Object.freeze({
  storeMapUrl: '/wp-json/ys-ecommerce-headless/v1/stores/newebpay/map-url',
  storeCallback: '/wp-json/ys-ecommerce/v1/newebpay/store-callback',
  shippingNotify: '/wp-json/ys-ecommerce/v1/newebpay/shipping-notify',
});

export async function requestNewebPayStoreMapForm(apiBase, shippingId, fetchImpl = fetch) {
  const response = await fetchImpl(apiBase.replace(/\/$/, '') + YS_CART_NEWEBPAY_ROUTES.storeMapUrl, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    credentials: 'include',
    body: JSON.stringify({
      shipping_id: shippingId,
    }),
  });

  if (!response.ok) {
    throw new Error(`NewebPay store-map request failed: ${response.status}`);
  }

  return response.json();
}

export function submitNewebPayForm(formData, target) {
  if (!formData || !formData.action_url || !formData.fields) {
    throw new Error('Invalid NewebPay form data.');
  }

  const doc = target && target.document ? target.document : document;
  const form = doc.createElement('form');
  form.method = 'POST';
  form.action = formData.action_url;
  form.style.display = 'none';

  Object.keys(formData.fields).forEach((key) => {
    const input = doc.createElement('input');
    input.type = 'hidden';
    input.name = key;
    input.value = String(formData.fields[key]);
    form.appendChild(input);
  });

  doc.body.appendChild(form);
  form.submit();
}

export function isNewebPayGateway(gatewayId) {
  return typeof gatewayId === 'string' && gatewayId.indexOf('ys_ec_newebpay_') === 0;
}
