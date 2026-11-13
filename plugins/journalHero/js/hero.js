/**
 * Journal Hero — cover carousel (arrows, dots, autoplay that stops on hover).
 */
(function () {
	'use strict';
	/* Boxed layout: match the width of the site's main content column */
	function matchWidth() {
		document.querySelectorAll('.jh-root.jh-boxed:not([data-jh-fixed])').forEach(function (root) {
			var content = document.querySelector('.pkp_structure_content') || document.querySelector('main, [role=main]');
			if (!content) return;
			var cs = getComputedStyle(content);
			var w = content.getBoundingClientRect().width - parseFloat(cs.paddingLeft || 0) - parseFloat(cs.paddingRight || 0);
			if (w > 320) root.style.setProperty('--jh-boxw', Math.round(w) + 'px');
		});
	}

	function init() {
		matchWidth();
		window.addEventListener('resize', matchWidth);
		document.querySelectorAll('[data-jh-carousel]').forEach(function (car) {
			var track = car.querySelector('.jh-covers__track');
			var dots = car.querySelector('[data-jh-dots]');
			if (!track || car._jhReady) return;
			car._jhReady = true;

			var width = function () { return track.clientWidth; };
			var pages = function () { return Math.max(1, Math.ceil(track.scrollWidth / track.clientWidth - 0.05)); };
			var go = function (dir) {
				var max = track.scrollWidth - track.clientWidth;
				var next = track.scrollLeft + dir * width();
				if (next > max + 5) next = 0;
				if (next < -5) next = max;
				track.scrollTo({ left: next, behavior: 'smooth' });
			};
			var renderDots = function () {
				if (!dots) return;
				var n = pages(), cur = Math.round(track.scrollLeft / width());
				dots.innerHTML = '';
				for (var i = 0; i < n; i++) {
					var d = document.createElement('span');
					if (i === cur) d.className = 'is-active';
					d.addEventListener('click', (function (idx) {
						return function () { track.scrollTo({ left: idx * width(), behavior: 'smooth' }); };
					})(i));
					dots.appendChild(d);
				}
			};

			var prev = car.querySelector('[data-jh-prev]'), next = car.querySelector('[data-jh-next]');
			if (prev) prev.addEventListener('click', function () { go(-1); });
			if (next) next.addEventListener('click', function () { go(1); });
			track.addEventListener('scroll', function () { clearTimeout(track._t); track._t = setTimeout(renderDots, 80); });
			window.addEventListener('resize', renderDots);
			renderDots();

			if (pages() > 1) {
				var timer = setInterval(function () { go(1); }, 6000);
				car.addEventListener('mouseenter', function () { clearInterval(timer); });
				car.addEventListener('touchstart', function () { clearInterval(timer); }, { passive: true });
			}
		});
	}
	if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
