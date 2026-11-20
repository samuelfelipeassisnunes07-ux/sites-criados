document.querySelectorAll('[data-password]').forEach(button => {
  button.addEventListener('click', () => {
    const input = document.getElementById(button.dataset.password);
    const visible = input.type === 'password';
    input.type = visible ? 'text' : 'password';
    button.textContent = visible ? 'Ocultar' : 'Mostrar';
    button.setAttribute('aria-pressed', String(visible));
    button.setAttribute('aria-label', (visible ? 'Ocultar ' : 'Mostrar ') + document.querySelector(`label[for="${input.id}"]`).textContent.toLowerCase());
  });
});
document.querySelectorAll('[data-confirm]').forEach(button => {
  button.closest('form').addEventListener('submit', event => {
    if (!window.confirm(button.dataset.confirm)) event.preventDefault();
  });
});
