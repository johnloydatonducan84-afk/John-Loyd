// CareSched frontend interactions
console.log('CareSched loaded');

document.addEventListener('DOMContentLoaded', function () {
  const authCard = document.querySelector('.auth-card');
  if (authCard) {
    authCard.classList.add('is-ready');
  }

  const inputs = document.querySelectorAll('.auth-card .form-control, .auth-card .form-select');
  inputs.forEach(function (input) {
    input.addEventListener('focus', function () {
      this.parentElement.classList.add('is-focused');
    });

    input.addEventListener('blur', function () {
      this.parentElement.classList.remove('is-focused');
    });
  });

  const toggle = document.getElementById('togglePass');
  if (toggle) {
    toggle.addEventListener('click', function () {
      const p = document.getElementById('password');
      if (p.type === 'password') {
        p.type = 'text';
        toggle.classList.remove('fa-eye');
        toggle.classList.add('fa-eye-slash');
      } else {
        p.type = 'password';
        toggle.classList.remove('fa-eye-slash');
        toggle.classList.add('fa-eye');
      }
    });
  }

  const toggleReg = document.getElementById('togglePassReg');
  if (toggleReg) {
    toggleReg.addEventListener('click', function () {
      const p = document.getElementById('password_reg');
      if (p.type === 'password') {
        p.type = 'text';
        toggleReg.classList.remove('fa-eye');
        toggleReg.classList.add('fa-eye-slash');
      } else {
        p.type = 'password';
        toggleReg.classList.remove('fa-eye-slash');
        toggleReg.classList.add('fa-eye');
      }
    });
  }
});
