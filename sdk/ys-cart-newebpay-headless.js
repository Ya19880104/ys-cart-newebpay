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
