/**
 * Loads and renders the cart summary for the checkout, matching the JSON shape
 * returned by Cart\Index (items: name/qty/row_total, totals: subtotal/discount/
 * shipping/tax/grand_total). Read-only GET, so no form key is required.
 */
require(['jquery'], function ($) {
    'use strict';

    var $cart = $('#sc-cart');
    var xhr = null;

    function render(resp) {
        if (!resp || !resp.success) {
            return;
        }
        var t = resp.totals;
        var rows = resp.items.map(function (item) {
            return '<tr><td>' + $('<div/>').text(item.name).html() + '</td>' +
                '<td>' + item.qty + '</td>' +
                '<td>' + $('<div/>').text(item.row_total).html() + '</td></tr>';
        }).join('');

        $cart.html(
            '<table class="sc-cart-table"><tbody>' + rows + '</tbody></table>' +
            '<dl class="sc-cart-totals">' +
            (t.has_discount ? '<dt>' + $.mage.__('Discount') + '</dt><dd>-' + $('<div/>').text(t.discount).html() + '</dd>' : '') +
            '<dt>' + $.mage.__('Shipping') + '</dt><dd>' + $('<div/>').text(t.shipping).html() + '</dd>' +
            '<dt>' + $.mage.__('Tax') + '</dt><dd>' + $('<div/>').text(t.tax).html() + '</dd>' +
            '<dt>' + $.mage.__('Total') + '</dt><dd>' + $('<div/>').text(t.grand_total).html() + '</dd>' +
            '</dl>'
        );
    }

    function loadCart() {
        if (xhr) {
            xhr.abort();
        }
        xhr = $.getJSON('/softcode/cart/index', render);
    }

    // Let other scripts refresh the summary (e.g. after a coupon is applied).
    window.softcode = window.softcode || {};
    window.softcode.reloadCart = loadCart;

    loadCart();
});
