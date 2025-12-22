require(['jquery'], function ($) {
    $(function () {

        const $cart = $('.sc-cart');

        function loadCart() {
            $.getJSON('/softcode/cart/index', function (resp) {
                if (!resp.success) return;

                let html = '';

                /* ===========================
                   CART ITEMS
                =========================== */
                resp.items.forEach(item => {
                    html += `
                        <div class="sc-item mb-2">
                            <div>${item.name}</div>
                            <small>Antal: ${item.qty}</small>
                            <div>${item.price}</div>
                        </div>
                    `;
                });

                /* ===========================
                   TOTALS (UX FIXED ORDER)
                =========================== */
                html += `
                    <div class="sc-summary mt-4 pt-3 border-top">

                        <div class="d-flex justify-content-between">
                            <span>Subtotal (ekskl. moms)</span>
                            <strong>${resp.totals.subtotal_excl_tax}</strong>
                        </div>

                        ${resp.totals.has_discount ? `
                        <div class="d-flex justify-content-between text-success">
                            <span>Rabat</span>
                            <strong>- ${resp.totals.discount}</strong>
                        </div>
                        ` : ''}

                        <div class="d-flex justify-content-between">
                            <span>Levering (inkl. moms)</span>
                            <strong>${resp.totals.shipping}</strong>
                        </div>

                        <div class="d-flex justify-content-between">
                            <span>Moms (25%)</span>
                            <strong>${resp.totals.tax}</strong>
                        </div>

                        <hr>

                        <div class="d-flex justify-content-between fs-5">
                            <strong>Ordretotal</strong>
                            <strong>${resp.totals.grand_total}</strong>
                        </div>

                    </div>
                `;

                $cart.html(html);
            });
        }

        loadCart();

        /* Expose reload hook */
        window.softcode = window.softcode || {};
        window.softcode.reloadCart = loadCart;
    });
});
