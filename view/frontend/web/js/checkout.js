/**
 * Reference frontend for the custom checkout.
 *
 * It drives the module's own AJAX endpoints and sends Magento's form key with
 * every POST so the CsrfAwareActionInterface controllers accept the request.
 * Presentation is intentionally minimal — style and shipping selection are left
 * to the integrating theme / shipping module.
 */
require(['jquery', 'mage/translate', 'Magento_Customer/js/customer-data'], function ($) {
    'use strict';

    var formKey = $('input[name="form_key"]').val() || (window.FORM_KEY || '');

    function post(url, data) {
        data = data || {};
        data.form_key = formKey;
        return $.post(url, data);
    }

    function buyerType() {
        return $('input[name="company_type"]:checked').val() || 'privat';
    }

    /* ---- buyer type ---- */
    function saveBuyerType() {
        return post('/softcode/index/index', {
            company_type: buyerType(),
            company_name: $('#sc-company-name').val(),
            company_cvr: $('#sc-cvr').val(),
            company_ean: $('#sc-ean').val()
        });
    }
    $('input[name="company_type"]').on('change', function () {
        saveBuyerType();
        loadPaymentMethods();
        validate();
    });
    $('#sc-company-name, #sc-cvr, #sc-ean').on('blur change', saveBuyerType);

    /* ---- address ---- */
    function useAlt() {
        return $('#sc-use-alt-address').is(':checked');
    }
    function saveAddress() {
        var data = {
            email: $('#sc-email').val(),
            firstname: $('#sc-firstname').val(),
            lastname: $('#sc-lastname').val(),
            telephone: $('#sc-phone').val(),
            street: $('#sc-address-street').val(),
            housenumber: $('#sc-address-housenumber').val(),
            postcode: $('#sc-address-postcode').val(),
            city: $('#sc-address-city').val(),
            use_alt: useAlt() ? 1 : 0
        };
        if (useAlt()) {
            ['company', 'receiver', 'street', 'housenumber', 'postcode', 'city'].forEach(function (field) {
                data['alt_' + field] = $('#sc-alt-' + field).val();
            });
        }
        return post('/softcode/index/saveAddress', data);
    }
    $('#sc-use-alt-address').on('change', function () {
        $('#sc-alt-address').toggle(useAlt());
    });
    $(document).on('blur change', '#sc-email, #sc-firstname, #sc-lastname, #sc-phone, '
        + '[id^="sc-address-"], [id^="sc-alt-"], #sc-use-alt-address', function () {
        saveAddress();
        validate();
    });

    /* ---- payment methods ---- */
    function loadPaymentMethods() {
        $.getJSON('/softcode/index/paymentMethods', function (resp) {
            var $mount = $('#sc-payment-mount').empty();
            if (!resp.success || !resp.methods.length) {
                return;
            }
            resp.methods.forEach(function (method) {
                $('<label class="sc-payment-method"/>')
                    .append($('<input type="radio" name="payment_method"/>').val(method.code))
                    .append(' ')
                    .append($('<span/>').text(method.title))
                    .appendTo($mount);
            });
        });
    }
    $(document).on('change', 'input[name="payment_method"]', function () {
        post('/softcode/index/savePayment', { method: $(this).val() })
            .done(function (resp) {
                if (!resp.success) {
                    $('#sc-payment-message').text(resp.error || '').show();
                }
            });
        validate();
    });

    /* ---- coupon ---- */
    $('#sc-apply-coupon').on('click', function () {
        post('/softcode/cart/applyCoupon', { code: $('#sc-coupon-code').val() })
            .done(function (resp) {
                $('#sc-coupon-message')
                    .text(resp.message || '')
                    .toggleClass('text-success', !!resp.success)
                    .toggleClass('text-danger', !resp.success)
                    .show();
            });
    });

    /* ---- validation (enables the place-order button) ---- */
    function validate() {
        var ok = true;
        var type = buyerType();
        if (type === 'cvr' && !$('#sc-cvr').val()) { ok = false; }
        if (type === 'ean' && !$('#sc-ean').val()) { ok = false; }
        if (!$('#sc-email').val() || !$('#sc-address-street').val() || !$('#sc-address-postcode').val()) { ok = false; }
        if (useAlt() && (!$('#sc-alt-street').val() || !$('#sc-alt-postcode').val())) { ok = false; }
        if (!$('input[name="payment_method"]:checked').val()) { ok = false; }
        if (!$('#sc-accept-terms').is(':checked')) { ok = false; }
        $('#sc-place-order').prop('disabled', !ok);
    }
    $(document).on('change input',
        'input[name="company_type"], #sc-cvr, #sc-ean, #sc-email, #sc-address-street, #sc-address-postcode, '
        + '#sc-use-alt-address, #sc-alt-street, #sc-alt-postcode, input[name="payment_method"], #sc-accept-terms',
        validate);

    /* ---- place order (with ePay hand-off) ---- */
    $('#sc-place-order').on('click', function () {
        var $btn = $(this).prop('disabled', true);
        post('/softcode/index/placeOrder', { newsletter: $('#sc-newsletter').is(':checked') ? 1 : 0 })
            .done(function (resp) {
                if (!resp.success) {
                    $('#sc-order-message').text(resp.error || '').show();
                    $btn.prop('disabled', false);
                    return;
                }
                if (resp.epay_start) {
                    startEpay();
                    return;
                }
                window.location.href = '/checkout/onepage/success';
            })
            .fail(function () {
                $('#sc-order-message').text($.mage.__('Something went wrong. Please try again.')).show();
                $btn.prop('disabled', false);
            });
    });

    function startEpay() {
        $.getJSON('/softcode/epay/config', function (config) {
            if (!config.success) {
                $('#sc-order-message').text(config.error || '').show();
                return;
            }
            $.getScript(config.paymentWindowJsUrl).done(function () {
                $.get(config.checkoutUrl).done(function (raw) {
                    new window.PaymentWindow(JSON.parse(raw)).open();
                });
            });
        });
    }

    /* init */
    loadPaymentMethods();
    validate();
});
