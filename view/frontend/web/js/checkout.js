require(['jquery'], function ($) {

    /* =================================================
       INIT
    ================================================= */
    toggleCompanyFields();
    togglePaymentMethods();
    validateCheckoutState();

    /* =================================================
       COMPANY DATA
    ================================================= */
    function saveCustomerData() {
        const companyType = $('input[name="company_type"]:checked').val() || 'privat';

        $.post('/softcode/index/index', {
            company_type: companyType,
            company_name: $('#sc-company-name').val(),
            company_cvr: $('#sc-cvr').val(),
            company_ean: $('#sc-ean').val()
        });
    }

    $('input[name="company_type"]').on('change', saveCustomerData);
    $('#sc-company-name, #sc-cvr, #sc-ean').on('blur change', saveCustomerData);

    /* =================================================
       COMPANY TYPE TOGGLE
    ================================================= */
    function toggleCompanyFields() {
        const type = $('input[name="company_type"]:checked').val();

        const $fields = $('#sc-company-fields');
        const $ean = $('#sc-ean');
        const $cvr = $('#sc-cvr');
        const $name = $('#sc-company-name');
        const $error = $('#sc-company-error');

        $error.hide().text('');
        $fields.hide();
        $ean.hide().val('');
        $cvr.hide().val('');

        if (type === 'ean') {
            $fields.show();
            $ean.show();
        }

        if (type === 'cvr') {
            $fields.show();
            $cvr.show();
        }

        if (type === 'privat') {
            $name.val('');
        }
    }

    $('input[name="company_type"]').on('change', function () {
        toggleCompanyFields();
        togglePaymentMethods();

        // Clear payment on company change
        $('input[name="payment_method"]').prop('checked', false);
        $('#sc-payment-card').hide();

        validateCheckoutState();
    });

    /* =================================================
       PAYMENT METHODS
    ================================================= */
    function loadPaymentMethods() {
        const companyType = $('input[name="company_type"]:checked').val() || 'privat';

        $.getJSON('/softcode/index/paymentMethods', function (resp) {
            if (!resp.success || !resp.methods.length) {
                $('#sc-payment-mount').empty();
                return;
            }

            const wrapper = $('<div class="sc-payment-methods"/>');

            resp.methods.forEach(method => {

                /* ===============================
                   FILTER RULES
                =============================== */

                // Privat + CVR → ePay only
                if ((companyType === 'privat' || companyType === 'cvr') && method.code !== 'epay') {
                    return;
                }

                // EAN → ePay + Invoice
                if (companyType === 'ean' && !['epay', 'purchaseorder'].includes(method.code)) {
                    return;
                }

                wrapper.append(`
                    <label class="sc-payment-method">
                        <input type="radio" name="payment_method" value="${method.code}">
                        ${method.title}
                    </label><br/>
                `);
            });

            $('#sc-payment-mount').html(wrapper);
            window.softcode.showPayment();
        });
    }

    function togglePaymentMethods() {
        $('#sc-payment-mount').show();
        loadPaymentMethods();
    }

    $(document).on('change', 'input[name="payment_method"]', function () {
        $.post('/softcode/index/savePayment', {
            method: $(this).val()
        });
        validateCheckoutState();
    });

    window.softcode = window.softcode || {};
    window.softcode.showPayment = function () {
        if ($('#sc-payment-mount').children().length) {
            $('#sc-payment-card').slideDown(200);
        }
    };

    /* =================================================
       ALTERNATIVE DELIVERY ADDRESS
    ================================================= */
    $('#sc-use-alt-address').on('change', function () {
        $('#sc-alt-delivery-card').toggle(this.checked);
        validateCheckoutState();
    });

    /* =================================================
       ADDRESS SAVE
    ================================================= */
    function saveAddress() {
        const useAlt = $('#sc-use-alt-address').is(':checked');

        const data = {
            email: $('#sc-email').val(),
            firstname: $('#sc-firstname').val(),
            lastname: $('#sc-lastname').val(),
            telephone: $('#sc-phone').val(),

            street: $('#sc-address-street').val(),
            housenumber: $('#sc-address-housenumber').val(),
            postcode: $('#sc-address-postcode').val(),
            city: $('#sc-address-city').val(),

            use_alt: useAlt ? 1 : 0
        };

        if (useAlt) {
            data.alt_company = $('#sc-alt-company').val();
            data.alt_receiver = $('#sc-alt-receiver').val();
            data.alt_street = $('#sc-alt-street').val();
            data.alt_housenumber = $('#sc-alt-housenumber').val();
            data.alt_postcode = $('#sc-alt-postcode').val();
            data.alt_city = $('#sc-alt-city').val();
        }

        $.post('/softcode/index/saveAddress', data);
    }

    $(
        '#sc-address-street, #sc-address-housenumber, #sc-address-postcode, #sc-address-city,' +
        '#sc-alt-street, #sc-alt-housenumber, #sc-alt-postcode, #sc-alt-city'
    ).on('blur change', function () {
        saveAddress();
        validateCheckoutState();
    });

    /* =================================================
       VALIDATION (FINAL)
    ================================================= */
    function validateCheckoutState() {
        let isValid = true;

        const companyType = $('input[name="company_type"]:checked').val();
        const deliveryMethod = $('input[name="delivery_method"]:checked').val();
        const useAlt = $('#sc-use-alt-address').is(':checked');

        /* Company */
        if (!companyType) isValid = false;
        if (companyType === 'cvr' && !$('#sc-cvr').val()) isValid = false;
        if (companyType === 'ean' && !$('#sc-ean').val()) isValid = false;

        /* Delivery */
        if (!deliveryMethod) isValid = false;
        if (deliveryMethod === 'gls_shop' && !$('#sc-gls-shop-select').val()) {
            isValid = false;
        }

        /* Payment (ALL company types) */
        if (!$('input[name="payment_method"]:checked').val()) {
            isValid = false;
        }

        /* Address */
        if (useAlt) {
            if (!$('#sc-alt-street').val() || !$('#sc-alt-postcode').val()) {
                isValid = false;
            }
        } else {
            if (!$('#sc-address-street').val() || !$('#sc-address-postcode').val()) {
                isValid = false;
            }
        }

        $('#sc-place-order').prop('disabled', !isValid);
    }

    $(document).on('change input', `
        input[name="company_type"],
        #sc-cvr,
        #sc-ean,
        input[name="delivery_method"],
        #sc-gls-shop-select,
        input[name="payment_method"],
        #sc-use-alt-address,
        #sc-address-street,
        #sc-address-postcode,
        #sc-alt-street,
        #sc-alt-postcode
    `, validateCheckoutState);

    /* =================================================
       PLACE ORDER
    ================================================= */
    $('#sc-place-order').on('click', function () {
        $.post('/softcode/index/placeOrder')
            .done(function (resp) {
                if (resp.success) {
                    window.location.href = '/checkout/onepage/success';
                } else {
                    alert(resp.error);
                }
            });
    });

    /* =================================================
       COUPON
    ================================================= */
    $('#sc-apply-coupon').on('click', function () {
        const code = $('#sc-coupon-code').val();

        $.post('/softcode/cart/applyCoupon', { code: code }, function (resp) {
            const $msg = $('#sc-coupon-message');

            $msg
                .text(resp.message)
                .removeClass('text-danger text-success')
                .addClass(resp.success ? 'text-success' : 'text-danger')
                .show();

            if (resp.success && window.softcode.reloadCart) {
                window.softcode.reloadCart();
            }
        });
    });

});
