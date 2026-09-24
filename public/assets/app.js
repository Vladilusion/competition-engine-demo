document.querySelectorAll('input[type="number"]').forEach((input) => {
  input.addEventListener('input', () => input.setCustomValidity(input.validity.valid ? '' : 'Use a whole score from 0 to 99.'));
});
