// Galerie du Number Cake : boutons natifs, utilisables aussi au clavier.
(function () {
  var galerie = document.querySelector('.number-galerie');
  if (!galerie) return;
  var photo = galerie.querySelector('.number-photo');
  var boutons = galerie.querySelectorAll('[data-number-photo]');
  boutons.forEach(function (bouton) {
    bouton.addEventListener('click', function () {
      var miniature = bouton.querySelector('img');
      photo.srcset = miniature.srcset;
      photo.src = miniature.src;
      photo.width = miniature.width;
      photo.height = miniature.height;
      photo.alt = bouton.dataset.alt;
      boutons.forEach(function (autre) { autre.setAttribute('aria-pressed', String(autre === bouton)); });
    });
  });
})();

