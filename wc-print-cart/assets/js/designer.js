/**
 * WC Print Cart — canvas photo editor.
 *
 * Crop coordinates are exported in the source image's pixel space after
 * applying `rotation` quarter-turns clockwise, matching what the server-side
 * renderer (WCPC_Image::render) expects.
 */
(function () {
	'use strict';

	var data = window.wcpcData;
	var root = document.getElementById('wcpc-designer');
	if (!data || !root) {
		return;
	}

	var els = {
		file: document.getElementById('wcpc-file'),
		uploadLabel: root.querySelector('.wcpc-upload-label'),
		progress: root.querySelector('.wcpc-progress'),
		progressBar: root.querySelector('.wcpc-progress-bar'),
		editor: root.querySelector('.wcpc-editor'),
		canvas: document.getElementById('wcpc-canvas'),
		zoom: document.getElementById('wcpc-zoom'),
		rotate: document.getElementById('wcpc-rotate'),
		orient: document.getElementById('wcpc-orient'),
		sizeSelect: document.getElementById('wcpc-size-select'),
		paperSelect: document.getElementById('wcpc-paper-select'),
		price: document.getElementById('wcpc-price'),
		dpi: document.getElementById('wcpc-dpi'),
		inToken: document.getElementById('wcpc_token'),
		inCrop: document.getElementById('wcpc_crop'),
		inSize: document.getElementById('wcpc_size'),
		inPaper: document.getElementById('wcpc_paper'),
		inOrient: document.getElementById('wcpc_orientation')
	};

	var form = root.closest('form.cart');
	var addBtn = form ? form.querySelector('.single_add_to_cart_button') : null;
	var ctx = els.canvas.getContext('2d');

	var st = {
		img: null,
		token: null,
		rot: 0,               // quarter turns clockwise
		scale: 1,
		minScale: 1,
		maxScale: 1,
		off: { x: 0, y: 0 },  // image center offset from frame center, canvas px
		sizeIdx: 0,
		paperIdx: 0,
		orientation: 'portrait',
		cssW: 0,
		cssH: 0,
		frame: { x: 0, y: 0, w: 0, h: 0 }
	};

	/* ---------------------------------------------------------------- utils */

	function rotatedDims() {
		var w = st.img.naturalWidth;
		var h = st.img.naturalHeight;
		return (st.rot % 2) ? { w: h, h: w } : { w: w, h: h };
	}

	function printDims() {
		var s = data.sizes[st.sizeIdx];
		var lo = Math.min(s.w, s.h);
		var hi = Math.max(s.w, s.h);
		return st.orientation === 'landscape' ? { w: hi, h: lo } : { w: lo, h: hi };
	}

	function formatPrice(n) {
		var c = data.currency;
		var fixed = n.toFixed(c.decimals);
		var parts = fixed.split('.');
		parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, c.thousandSep);
		var num = parts.join(c.decimalSep);
		switch (c.position) {
			case 'right': return num + c.symbol;
			case 'left_space': return c.symbol + ' ' + num;
			case 'right_space': return num + ' ' + c.symbol;
			default: return c.symbol + num;
		}
	}

	/* -------------------------------------------------------------- layout */

	function layoutCanvas() {
		var w = els.canvas.parentElement.clientWidth || 480;
		st.cssW = w;
		st.cssH = Math.round(Math.min(440, w * 0.85));

		var dpr = window.devicePixelRatio || 1;
		els.canvas.width = Math.round(st.cssW * dpr);
		els.canvas.height = Math.round(st.cssH * dpr);
		els.canvas.style.width = st.cssW + 'px';
		els.canvas.style.height = st.cssH + 'px';
		ctx.setTransform(dpr, 0, 0, dpr, 0, 0);

		computeFrame();
	}

	function computeFrame() {
		var p = printDims();
		var aspect = p.w / p.h;
		var maxW = st.cssW * 0.86;
		var maxH = st.cssH * 0.86;
		var fw = maxW;
		var fh = fw / aspect;
		if (fh > maxH) {
			fh = maxH;
			fw = fh * aspect;
		}
		st.frame = {
			x: (st.cssW - fw) / 2,
			y: (st.cssH - fh) / 2,
			w: fw,
			h: fh
		};
	}

	function fit() {
		computeFrame();
		var d = rotatedDims();
		st.minScale = Math.max(st.frame.w / d.w, st.frame.h / d.h);
		st.maxScale = st.minScale * 5;
		st.scale = st.minScale;
		st.off.x = 0;
		st.off.y = 0;
		els.zoom.value = 0;
		clampOffsets();
	}

	function clampOffsets() {
		var d = rotatedDims();
		var maxX = Math.max(0, (d.w * st.scale - st.frame.w) / 2);
		var maxY = Math.max(0, (d.h * st.scale - st.frame.h) / 2);
		st.off.x = Math.max(-maxX, Math.min(maxX, st.off.x));
		st.off.y = Math.max(-maxY, Math.min(maxY, st.off.y));
	}

	/* ---------------------------------------------------------------- draw */

	function draw() {
		if (!st.img) {
			return;
		}
		ctx.clearRect(0, 0, st.cssW, st.cssH);

		var cx = st.cssW / 2 + st.off.x;
		var cy = st.cssH / 2 + st.off.y;

		ctx.save();
		ctx.translate(cx, cy);
		ctx.rotate(st.rot * Math.PI / 2);
		ctx.scale(st.scale, st.scale);
		ctx.drawImage(st.img, -st.img.naturalWidth / 2, -st.img.naturalHeight / 2);
		ctx.restore();

		// Dim everything outside the crop frame.
		var f = st.frame;
		ctx.fillStyle = 'rgba(20,20,20,0.55)';
		ctx.fillRect(0, 0, st.cssW, f.y);
		ctx.fillRect(0, f.y + f.h, st.cssW, st.cssH - f.y - f.h);
		ctx.fillRect(0, f.y, f.x, f.h);
		ctx.fillRect(f.x + f.w, f.y, st.cssW - f.x - f.w, f.h);

		ctx.strokeStyle = '#ffffff';
		ctx.lineWidth = 2;
		ctx.strokeRect(f.x + 1, f.y + 1, f.w - 2, f.h - 2);

		// Rule-of-thirds guides.
		ctx.strokeStyle = 'rgba(255,255,255,0.35)';
		ctx.lineWidth = 1;
		ctx.beginPath();
		for (var i = 1; i <= 2; i++) {
			ctx.moveTo(f.x + (f.w * i) / 3, f.y);
			ctx.lineTo(f.x + (f.w * i) / 3, f.y + f.h);
			ctx.moveTo(f.x, f.y + (f.h * i) / 3);
			ctx.lineTo(f.x + f.w, f.y + (f.h * i) / 3);
		}
		ctx.stroke();
	}

	/* -------------------------------------------------------------- export */

	function exportCrop() {
		var d = rotatedDims();
		var f = st.frame;
		var sx = (d.w * st.scale / 2 - f.w / 2 - st.off.x) / st.scale;
		var sy = (d.h * st.scale / 2 - f.h / 2 - st.off.y) / st.scale;
		var sw = f.w / st.scale;
		var sh = f.h / st.scale;

		sx = Math.max(0, Math.min(sx, d.w - 1));
		sy = Math.max(0, Math.min(sy, d.h - 1));
		sw = Math.min(sw, d.w - sx);
		sh = Math.min(sh, d.h - sy);

		return {
			x: Math.round(sx * 100) / 100,
			y: Math.round(sy * 100) / 100,
			w: Math.round(sw * 100) / 100,
			h: Math.round(sh * 100) / 100,
			rotation: st.rot
		};
	}

	function updateDpi(crop) {
		if (!st.img) {
			els.dpi.hidden = true;
			return;
		}
		var p = printDims();
		var ppi = Math.min(crop.w / p.w, crop.h / p.h);

		els.dpi.hidden = false;
		els.dpi.classList.remove('wcpc-dpi-good', 'wcpc-dpi-ok', 'wcpc-dpi-low');
		if (ppi >= 300) {
			els.dpi.textContent = data.i18n.dpiGood + ' · ' + Math.round(ppi) + ' DPI';
			els.dpi.classList.add('wcpc-dpi-good');
		} else if (ppi >= 150) {
			els.dpi.textContent = data.i18n.dpiOk + ' · ' + Math.round(ppi) + ' DPI';
			els.dpi.classList.add('wcpc-dpi-ok');
		} else {
			els.dpi.textContent = data.i18n.dpiLow + ' · ' + Math.round(ppi) + ' DPI';
			els.dpi.classList.add('wcpc-dpi-low');
		}
	}

	function updatePrice() {
		var size = data.sizes[st.sizeIdx];
		var paper = data.papers[st.paperIdx];
		var base = size.price > 0 ? size.price : data.basePrice;
		var total = base + paper.surcharge;
		els.price.textContent = formatPrice(total) + ' ' + data.i18n.each;
	}

	function sync() {
		els.inSize.value = String(st.sizeIdx);
		els.inPaper.value = String(st.paperIdx);
		els.inOrient.value = st.orientation;
		if (st.img) {
			var crop = exportCrop();
			els.inCrop.value = JSON.stringify(crop);
			updateDpi(crop);
		}
		updatePrice();
	}

	function setAddEnabled(enabled) {
		if (addBtn) {
			addBtn.disabled = !enabled;
			addBtn.classList.toggle('disabled', !enabled);
		}
	}

	/* -------------------------------------------------------------- upload */

	function upload(file) {
		if (!file) {
			return;
		}
		if (file.size > 41943040) {
			window.alert(data.i18n.uploadError);
			return;
		}

		els.progress.hidden = false;
		els.progressBar.style.width = '0%';
		els.uploadLabel.textContent = data.i18n.uploading;

		var fd = new FormData();
		fd.append('action', 'wcpc_upload');
		fd.append('nonce', data.nonce);
		fd.append('file', file);

		var xhr = new XMLHttpRequest();
		xhr.open('POST', data.ajaxUrl);
		xhr.upload.onprogress = function (e) {
			if (e.lengthComputable) {
				els.progressBar.style.width = Math.round((e.loaded / e.total) * 100) + '%';
			}
		};
		xhr.onload = function () {
			els.progress.hidden = true;
			var resp = null;
			try {
				resp = JSON.parse(xhr.responseText);
			} catch (err) { /* fall through */ }

			if (!resp || !resp.success) {
				els.uploadLabel.textContent = data.i18n.uploadError;
				window.alert((resp && resp.data && resp.data.message) || data.i18n.uploadError);
				return;
			}

			var img = new Image();
			img.onload = function () {
				st.img = img;
				st.token = resp.data.token;
				st.rot = 0;
				els.inToken.value = st.token;
				els.uploadLabel.textContent = data.i18n.replace;
				els.editor.hidden = false;

				// Default the frame orientation to match the photo.
				st.orientation = img.naturalWidth >= img.naturalHeight ? 'landscape' : 'portrait';

				layoutCanvas();
				fit();
				draw();
				sync();
				setAddEnabled(true);
			};
			img.onerror = function () {
				els.uploadLabel.textContent = data.i18n.uploadError;
			};
			img.src = resp.data.url;
		};
		xhr.onerror = function () {
			els.progress.hidden = true;
			els.uploadLabel.textContent = data.i18n.uploadError;
		};
		xhr.send(fd);
	}

	/* -------------------------------------------------------------- events */

	els.file.addEventListener('change', function () {
		upload(this.files && this.files[0]);
		this.value = '';
	});

	// Drag to reposition.
	var dragging = false;
	var last = { x: 0, y: 0 };

	els.canvas.addEventListener('pointerdown', function (e) {
		if (!st.img) {
			return;
		}
		dragging = true;
		last.x = e.clientX;
		last.y = e.clientY;
		els.canvas.setPointerCapture(e.pointerId);
		e.preventDefault();
	});
	els.canvas.addEventListener('pointermove', function (e) {
		if (!dragging) {
			return;
		}
		st.off.x += e.clientX - last.x;
		st.off.y += e.clientY - last.y;
		last.x = e.clientX;
		last.y = e.clientY;
		clampOffsets();
		draw();
		sync();
	});
	els.canvas.addEventListener('pointerup', function () {
		dragging = false;
	});
	els.canvas.addEventListener('pointercancel', function () {
		dragging = false;
	});

	// Wheel zoom.
	els.canvas.addEventListener('wheel', function (e) {
		if (!st.img) {
			return;
		}
		e.preventDefault();
		var factor = Math.pow(1.0015, -e.deltaY);
		st.scale = Math.max(st.minScale, Math.min(st.maxScale, st.scale * factor));
		els.zoom.value = String(Math.round(((st.scale - st.minScale) / (st.maxScale - st.minScale)) * 100));
		clampOffsets();
		draw();
		sync();
	}, { passive: false });

	// Zoom slider.
	els.zoom.addEventListener('input', function () {
		if (!st.img) {
			return;
		}
		var t = Number(this.value) / 100;
		st.scale = st.minScale + (st.maxScale - st.minScale) * t;
		clampOffsets();
		draw();
		sync();
	});

	// Rotate 90° clockwise.
	els.rotate.addEventListener('click', function () {
		if (!st.img) {
			return;
		}
		st.rot = (st.rot + 1) % 4;
		fit();
		draw();
		sync();
	});

	// Portrait / landscape toggle.
	els.orient.addEventListener('click', function () {
		st.orientation = st.orientation === 'portrait' ? 'landscape' : 'portrait';
		if (st.img) {
			fit();
			draw();
		}
		sync();
	});

	els.sizeSelect.addEventListener('change', function () {
		st.sizeIdx = Number(this.value) || 0;
		if (st.img) {
			fit();
			draw();
		}
		sync();
	});

	els.paperSelect.addEventListener('change', function () {
		st.paperIdx = Number(this.value) || 0;
		sync();
	});

	window.addEventListener('resize', function () {
		if (!st.img) {
			return;
		}
		var crop = exportCrop();
		layoutCanvas();
		// Restore roughly the same view after relayout.
		var d = rotatedDims();
		st.minScale = Math.max(st.frame.w / d.w, st.frame.h / d.h);
		st.maxScale = st.minScale * 5;
		st.scale = Math.max(st.minScale, Math.min(st.maxScale, st.frame.w / crop.w));
		st.off.x = (d.w / 2 - (crop.x + crop.w / 2)) * st.scale;
		st.off.y = (d.h / 2 - (crop.y + crop.h / 2)) * st.scale;
		clampOffsets();
		draw();
		sync();
	});

	if (form) {
		form.addEventListener('submit', sync);
	}

	/* ---------------------------------------------------------------- init */

	setAddEnabled(false);
	layoutCanvas();
	sync();
})();
