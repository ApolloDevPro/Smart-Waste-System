// Mobile menu toggle
const menuToggle = document.getElementById('mobile-menu');
const navLinks = document.querySelector('nav ul');

menuToggle.addEventListener('click', () => {
  navLinks.classList.toggle('show');
  menuToggle.classList.toggle('active');
});