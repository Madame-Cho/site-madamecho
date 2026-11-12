(function () {
  var galerie = document.querySelector('.charlotte-galerie');
  if (!galerie) return;
  var photo = galerie.querySelector('.charlotte-principale');
  var boutons = galerie.querySelectorAll('button');
  boutons.forEach(function (bouton) {
    bouton.addEventListener('click', function () {
      var miniature = bouton.querySelector('img');
      photo.srcset = miniature.srcset;
      photo.src = miniature.src;
      photo.alt = miniature.alt;
      boutons.forEach(function (autre) { autre.setAttribute('aria-pressed', String(autre === bouton)); });
    });
  });
})();
