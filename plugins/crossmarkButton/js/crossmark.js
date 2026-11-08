/* Crossmark Button: pindahkan tombol ke dekat judul artikel (tema yang punya tempat khusus akan memindahkannya sendiri). */
(function () {
  function place() {
    var w = document.querySelector('.cmk-wrap');
    if (!w || w.getAttribute('data-placed') || w.getAttribute('data-position') === 'details') return;
    if (document.querySelector('.ch-art-hero')) return;          // CHEESA Theme menempatkan tombol di kepala artikel
    var h1 = document.querySelector('.obj_article_details .page_title, .page_article h1');
    if (!h1) return;
    w.setAttribute('data-placed', '1');
    w.classList.add('cmk-top');
    h1.parentNode.insertBefore(w, h1);
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', function () { setTimeout(place, 50); });
  else setTimeout(place, 50);
})();
