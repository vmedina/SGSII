document.querySelectorAll('form').forEach((form) => form.addEventListener('submit', (event) => {
  const start = form.querySelector('[name="start_time"]');
  const end = form.querySelector('[name="end_time"]');
  if (start && end && (!start.value.endsWith(':00') || !end.value.endsWith(':00') || end.value <= start.value)) {
    event.preventDefault();
    alert('Seleccione horas exactas y una hora final posterior a la inicial.');
  }
}));
