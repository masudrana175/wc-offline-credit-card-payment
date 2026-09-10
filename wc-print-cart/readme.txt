=== WC Print Cart — Photo Print Designer ===
Contributors: masudrana175
Tags: woocommerce, photo prints, print shop, image upload, product designer
Requires at least: 5.8
Tested up to: 6.6
Requires PHP: 7.2
Stable tag: 1.0.0
License: GPLv2 or later

Colorplak-style photo print ordering for WooCommerce: upload, crop, choose size & paper, live pricing, and print-ready files on every order.

== Description ==

Turns any WooCommerce simple product into a photo print product with an
in-page design editor, similar to online print shops such as Colorplak:

* **Photo upload** — JPEG / PNG / WebP up to 40 MB, with an upload progress
  bar. EXIF rotation from phone cameras is normalized automatically.
* **Crop editor** — canvas-based editor with drag to reposition, mouse-wheel
  and slider zoom, 90° rotation, portrait/landscape toggle, and
  rule-of-thirds guides. The crop frame always matches the selected print
  size's aspect ratio, so what the customer sees is exactly what prints.
* **Print sizes** — configurable per product (label, physical inches,
  price). Defaults: 4×6, 5×7, 8×10, 11×14, 16×20.
* **Paper / finish options** — configurable per product with per-paper
  surcharges. Defaults: Glossy, Luster, Pearl, Fine Art.
* **Live pricing** — the displayed price updates instantly as the customer
  changes size and paper; the cart charges size price + paper surcharge.
* **Print-quality (DPI) check** — a live badge warns the customer when their
  photo is too small for the chosen print size (below 150 DPI).
* **Cart integration** — the cart line shows a thumbnail of the actual
  cropped photo plus the chosen size and paper. Each customized print is a
  separate cart line.
* **Print-ready files** — when the order is placed, a flattened JPEG is
  generated at 300 DPI (capped at the source resolution) with the exact
  crop the customer chose. Shop managers get "Download print-ready file"
  and "Original upload" buttons on the admin order screen.

== Installation ==

1. Copy the `wc-print-cart` folder into `wp-content/plugins/`.
2. Activate **WC Print Cart — Photo Print Designer** in wp-admin → Plugins.
   WooCommerce must be active.
3. Edit (or create) a **simple product**, open the **Print Designer** tab in
   the Product data box, tick **Enable print designer**, and optionally
   configure sizes and papers. Give the product a regular price (used as a
   fallback when a size has price 0).
4. View the product — the upload/crop designer appears above the
   Add to cart button.

Size lines use the format `Label|width inches|height inches|price`,
one per line, e.g. `8×10|8|10|9.99`.
Paper lines use `Label|surcharge`, e.g. `Pearl|1.50`.

== Notes & limitations ==

* Requires the PHP GD extension (bundled with almost every host).
* Simple products only in this version (no variations).
* Customer uploads are stored in `wp-content/uploads/wcpc-prints/` with
  random, unguessable file names; upload references expire after 7 days if
  the customer never completes an order.
* Print-ready output is flattened JPEG (quality 92); transparent PNG areas
  are rendered on white.

== Changelog ==

= 1.0.0 =
* Initial release: upload, crop/zoom/rotate editor, size & paper options,
  live pricing, DPI warning, cart preview thumbnails, order meta, and
  300 DPI print-ready file generation.
