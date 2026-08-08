/**
 * Venture Events - Frontend Registration Form JS
 */
(function($) {
    'use strict';

    console.log('✅ Venture Events frontend.js loaded');

    const MAX_TICKETS = 30;
    let ticketCount = 0;

    // Full countries list (for billing country dropdown)
    const countries = [
        {code: "AF", name: "Afghanistan"}, {code: "AL", name: "Albania"}, {code: "DZ", name: "Algeria"},
        {code: "AS", name: "American Samoa"}, {code: "AD", name: "Andorra"}, {code: "AO", name: "Angola"},
        {code: "AI", name: "Anguilla"}, {code: "AQ", name: "Antarctica"}, {code: "AG", name: "Antigua and Barbuda"},
        {code: "AR", name: "Argentina"}, {code: "AM", name: "Armenia"}, {code: "AW", name: "Aruba"},
        {code: "AU", name: "Australia"}, {code: "AT", name: "Austria"}, {code: "AZ", name: "Azerbaijan"},
        {code: "BS", name: "Bahamas"}, {code: "BH", name: "Bahrain"}, {code: "BD", name: "Bangladesh"},
        {code: "BB", name: "Barbados"}, {code: "BY", name: "Belarus"}, {code: "BE", name: "Belgium"},
        {code: "BZ", name: "Belize"}, {code: "BJ", name: "Benin"}, {code: "BM", name: "Bermuda"},
        {code: "BT", name: "Bhutan"}, {code: "BO", name: "Bolivia"}, {code: "BA", name: "Bosnia and Herzegovina"},
        {code: "BW", name: "Botswana"}, {code: "BR", name: "Brazil"}, {code: "IO", name: "British Indian Ocean Territory"},
        {code: "VG", name: "British Virgin Islands"}, {code: "BN", name: "Brunei"}, {code: "BG", name: "Bulgaria"},
        {code: "BF", name: "Burkina Faso"}, {code: "BI", name: "Burundi"}, {code: "KH", name: "Cambodia"},
        {code: "CM", name: "Cameroon"}, {code: "CA", name: "Canada"}, {code: "CV", name: "Cape Verde"},
        {code: "KY", name: "Cayman Islands"}, {code: "CF", name: "Central African Republic"}, {code: "TD", name: "Chad"},
        {code: "CL", name: "Chile"}, {code: "CN", name: "China"}, {code: "CO", name: "Colombia"},
        {code: "KM", name: "Comoros"}, {code: "CG", name: "Congo"}, {code: "CD", name: "Congo, Democratic Republic"},
        {code: "CK", name: "Cook Islands"}, {code: "CR", name: "Costa Rica"}, {code: "HR", name: "Croatia"},
        {code: "CU", name: "Cuba"}, {code: "CY", name: "Cyprus"}, {code: "CZ", name: "Czech Republic"},
        {code: "DK", name: "Denmark"}, {code: "DJ", name: "Djibouti"}, {code: "DM", name: "Dominica"},
        {code: "DO", name: "Dominican Republic"}, {code: "EC", name: "Ecuador"}, {code: "EG", name: "Egypt"},
        {code: "SV", name: "El Salvador"}, {code: "GQ", name: "Equatorial Guinea"}, {code: "ER", name: "Eritrea"},
        {code: "EE", name: "Estonia"}, {code: "ET", name: "Ethiopia"}, {code: "FK", name: "Falkland Islands"},
        {code: "FO", name: "Faroe Islands"}, {code: "FJ", name: "Fiji"}, {code: "FI", name: "Finland"},
        {code: "FR", name: "France"}, {code: "GF", name: "French Guiana"}, {code: "PF", name: "French Polynesia"},
        {code: "GA", name: "Gabon"}, {code: "GM", name: "Gambia"}, {code: "GE", name: "Georgia"},
        {code: "DE", name: "Germany"}, {code: "GH", name: "Ghana"}, {code: "GI", name: "Gibraltar"},
        {code: "GR", name: "Greece"}, {code: "GL", name: "Greenland"}, {code: "GD", name: "Grenada"},
        {code: "GP", name: "Guadeloupe"}, {code: "GU", name: "Guam"}, {code: "GT", name: "Guatemala"},
        {code: "GN", name: "Guinea"}, {code: "GW", name: "Guinea-Bissau"}, {code: "GY", name: "Guyana"},
        {code: "HT", name: "Haiti"}, {code: "HN", name: "Honduras"}, {code: "HK", name: "Hong Kong"},
        {code: "HU", name: "Hungary"}, {code: "IS", name: "Iceland"}, {code: "IN", name: "India"},
        {code: "ID", name: "Indonesia"}, {code: "IR", name: "Iran"}, {code: "IQ", name: "Iraq"},
        {code: "IE", name: "Ireland"}, {code: "IL", name: "Israel"}, {code: "IT", name: "Italy"},
        {code: "JM", name: "Jamaica"}, {code: "JP", name: "Japan"}, {code: "JO", name: "Jordan"},
        {code: "KZ", name: "Kazakhstan"}, {code: "KE", name: "Kenya"}, {code: "KI", name: "Kiribati"},
        {code: "KP", name: "Korea, North"}, {code: "KR", name: "Korea, South"}, {code: "KW", name: "Kuwait"},
        {code: "KG", name: "Kyrgyzstan"}, {code: "LA", name: "Laos"}, {code: "LV", name: "Latvia"},
        {code: "LB", name: "Lebanon"}, {code: "LS", name: "Lesotho"}, {code: "LR", name: "Liberia"},
        {code: "LY", name: "Libya"}, {code: "LI", name: "Liechtenstein"}, {code: "LT", name: "Lithuania"},
        {code: "LU", name: "Luxembourg"}, {code: "MO", name: "Macau"}, {code: "MK", name: "Macedonia"},
        {code: "MG", name: "Madagascar"}, {code: "MW", name: "Malawi"}, {code: "MY", name: "Malaysia"},
        {code: "MV", name: "Maldives"}, {code: "ML", name: "Mali"}, {code: "MT", name: "Malta"},
        {code: "MH", name: "Marshall Islands"}, {code: "MQ", name: "Martinique"}, {code: "MR", name: "Mauritania"},
        {code: "MU", name: "Mauritius"}, {code: "YT", name: "Mayotte"}, {code: "MX", name: "Mexico"},
        {code: "FM", name: "Micronesia"}, {code: "MD", name: "Moldova"}, {code: "MC", name: "Monaco"},
        {code: "MN", name: "Mongolia"}, {code: "ME", name: "Montenegro"}, {code: "MS", name: "Montserrat"},
        {code: "MA", name: "Morocco"}, {code: "MZ", name: "Mozambique"}, {code: "MM", name: "Myanmar"},
        {code: "NA", name: "Namibia"}, {code: "NR", name: "Nauru"}, {code: "NP", name: "Nepal"},
        {code: "NL", name: "Netherlands"}, {code: "NC", name: "New Caledonia"}, {code: "NZ", name: "New Zealand"},
        {code: "NI", name: "Nicaragua"}, {code: "NE", name: "Niger"}, {code: "NG", name: "Nigeria"},
        {code: "NU", name: "Niue"}, {code: "NF", name: "Norfolk Island"}, {code: "MP", name: "Northern Mariana Islands"},
        {code: "NO", name: "Norway"}, {code: "OM", name: "Oman"}, {code: "PK", name: "Pakistan"},
        {code: "PW", name: "Palau"}, {code: "PS", name: "Palestine"}, {code: "PA", name: "Panama"},
        {code: "PG", name: "Papua New Guinea"}, {code: "PY", name: "Paraguay"}, {code: "PE", name: "Peru"},
        {code: "PH", name: "Philippines"}, {code: "PL", name: "Poland"}, {code: "PT", name: "Portugal"},
        {code: "PR", name: "Puerto Rico"}, {code: "QA", name: "Qatar"}, {code: "RE", name: "Réunion"},
        {code: "RO", name: "Romania"}, {code: "RU", name: "Russia"}, {code: "RW", name: "Rwanda"},
        {code: "SH", name: "Saint Helena"}, {code: "KN", name: "Saint Kitts and Nevis"}, {code: "LC", name: "Saint Lucia"},
        {code: "PM", name: "Saint Pierre and Miquelon"}, {code: "VC", name: "Saint Vincent and the Grenadines"},
        {code: "WS", name: "Samoa"}, {code: "SM", name: "San Marino"}, {code: "ST", name: "São Tomé and Príncipe"},
        {code: "SA", name: "Saudi Arabia"}, {code: "SN", name: "Senegal"}, {code: "RS", name: "Serbia"},
        {code: "SC", name: "Seychelles"}, {code: "SL", name: "Sierra Leone"}, {code: "SG", name: "Singapore"},
        {code: "SK", name: "Slovakia"}, {code: "SI", name: "Slovenia"}, {code: "SB", name: "Solomon Islands"},
        {code: "SO", name: "Somalia"}, {code: "ZA", name: "South Africa"}, {code: "ES", name: "Spain"},
        {code: "LK", name: "Sri Lanka"}, {code: "SD", name: "Sudan"}, {code: "SR", name: "Suriname"},
        {code: "SZ", name: "Swaziland"}, {code: "SE", name: "Sweden"}, {code: "CH", name: "Switzerland"},
        {code: "SY", name: "Syria"}, {code: "TW", name: "Taiwan"}, {code: "TJ", name: "Tajikistan"},
        {code: "TZ", name: "Tanzania"}, {code: "TH", name: "Thailand"}, {code: "TL", name: "Timor-Leste"},
        {code: "TG", name: "Togo"}, {code: "TK", name: "Tokelau"}, {code: "TO", name: "Tonga"},
        {code: "TT", name: "Trinidad and Tobago"}, {code: "TN", name: "Tunisia"}, {code: "TR", name: "Turkey"},
        {code: "TM", name: "Turkmenistan"}, {code: "TC", name: "Turks and Caicos Islands"}, {code: "TV", name: "Tuvalu"},
        {code: "UG", name: "Uganda"}, {code: "UA", name: "Ukraine"}, {code: "AE", name: "United Arab Emirates"},
        {code: "GB", name: "United Kingdom"}, {code: "US", name: "United States"}, {code: "UY", name: "Uruguay"},
        {code: "UZ", name: "Uzbekistan"}, {code: "VU", name: "Vanuatu"}, {code: "VA", name: "Vatican City"},
        {code: "VE", name: "Venezuela"}, {code: "VN", name: "Vietnam"}, {code: "VI", name: "Virgin Islands, U.S."},
        {code: "WF", name: "Wallis and Futuna"}, {code: "YE", name: "Yemen"}, {code: "ZM", name: "Zambia"},
        {code: "ZW", name: "Zimbabwe"}
    ];

    // VAT calculation (kept exactly as verified)
    function calculateVATBreakdown(inclusivePrice, country) {
        if (!inclusivePrice || inclusivePrice <= 0) {
            return { total: '0.00', vat: '0.00', isNamibia: false };
        }
        const isNamibia = (country === 'NA');
        let vatAmount = isNamibia ? (inclusivePrice / 1.15) * 0.15 : 0;
        return { 
            total: inclusivePrice.toFixed(2), 
            vat: vatAmount.toFixed(2), 
            isNamibia: isNamibia 
        };
    }

    function isSpecialMode() {
        return (window.veRegistrationMode === 'special')
            || ($('#ve-form-mode').val() === 'special')
            || ($('#ve-registration-form').data('mode') === 'special');
    }

    function personFieldsHTML() {
        return `
            <p><label>First Name <span class="ve-required">*</span></label><br>
               <input type="text" class="first_name" required></p>
            <p><label>Last Name <span class="ve-required">*</span></label><br>
               <input type="text" class="last_name" required></p>
            <p><label>Organisation</label><br>
               <input type="text" class="organisation"></p>
            <p><label>Phone</label><br>
               <input type="text" class="phone"></p>
            <p><label>Email (for ticket) <span class="ve-required">*</span></label><br>
               <input type="email" class="email" required></p>`;
    }

    function createTicketHTML(index, tierOptions) {
        const removeBtn = index > 0
            ? `<button type="button" class="remove-ticket-btn" aria-label="Remove ticket">×</button>`
            : '';

        return `
        <div class="ticket-accordion" data-index="${index}" data-kind="paid">
            <div class="accordion-header">
                <strong>Ticket ${index + 1}</strong>
                ${removeBtn}
            </div>
            <div class="accordion-body">
                ${personFieldsHTML()}
                <p><label>Ticket Tier <span class="ve-required">*</span></label><br>
                   <select class="tier-select" required>
                       <option value="">— Please select a tier —</option>
                       ${tierOptions}
                   </select>
                </p>
            </div>
        </div>`;
    }

    /**
     * Extra paid tickets on special form may all be removed (package alone is enough).
     * On normal form, index 0 has no remove button.
     */
    function createExtraTicketHTML(index, tierOptions, allowRemove) {
        const removeBtn = allowRemove
            ? `<button type="button" class="remove-ticket-btn" aria-label="Remove ticket">×</button>`
            : '';
        const title = isSpecialMode()
            ? `Additional ticket ${index + 1}`
            : `Ticket ${index + 1}`;

        return `
        <div class="ticket-accordion" data-index="${index}" data-kind="paid">
            <div class="accordion-header">
                <strong>${title}</strong>
                ${removeBtn}
            </div>
            <div class="accordion-body">
                ${personFieldsHTML()}
                <p><label>Ticket Tier <span class="ve-required">*</span></label><br>
                   <select class="tier-select" required>
                       <option value="">— Please select a tier —</option>
                       ${tierOptions}
                   </select>
                </p>
            </div>
        </div>`;
    }

    function createFreeTicketHTML(index, freeTierName) {
        const label = freeTierName
            ? `Included free ticket ${index + 1}`
            : `Included free ticket ${index + 1}`;
        const tierLine = freeTierName
            ? `<p class="ve-included-tier"><strong>Included:</strong> ${$('<div>').text(freeTierName).html()} <span class="ve-hint">(N$ 0.00)</span></p>`
            : `<p class="ve-included-tier"><strong>Included free ticket</strong> <span class="ve-hint">(N$ 0.00)</span></p>`;

        return `
        <div class="ticket-accordion ve-free-ticket" data-index="${index}" data-kind="free">
            <div class="accordion-header">
                <strong>${label}</strong>
            </div>
            <div class="accordion-body">
                ${tierLine}
                ${personFieldsHTML()}
            </div>
        </div>`;
    }

    function validatePersonBlock($el, requireTier) {
        const firstName = ($el.find('.first_name').val() || '').trim();
        const lastName  = ($el.find('.last_name').val() || '').trim();
        const email     = ($el.find('.email').val() || '').trim();
        if (!firstName || !lastName || !email) {
            return false;
        }
        if (requireTier) {
            const tier = $el.find('.tier-select').val();
            if (!tier) {
                return false;
            }
        }
        return true;
    }

    function validateCheckoutButton() {
        let isValid = true;

        if (isSpecialMode()) {
            const packageKey = $('#ve-special-tier-select').val();
            if (!packageKey) {
                isValid = false;
            } else {
                $('#free-tickets-container .ticket-accordion').each(function () {
                    if (!validatePersonBlock($(this), false)) {
                        isValid = false;
                        return false;
                    }
                });
                // Extra paid tickets are optional, but if present must be complete
                if (isValid) {
                    $('#tickets-container .ticket-accordion').each(function () {
                        if (!validatePersonBlock($(this), true)) {
                            isValid = false;
                            return false;
                        }
                    });
                }
            }
        } else {
            const $paid = $('#tickets-container .ticket-accordion');
            if (!$paid.length) {
                isValid = false;
            } else {
                $paid.each(function () {
                    if (!validatePersonBlock($(this), true)) {
                        isValid = false;
                        return false;
                    }
                });
            }
        }

        if (isValid) {
            const billingAddress  = ($('#billing_address').val() || '').trim();
            const accountingEmail = ($('#accounting_email').val() || '').trim();
            const billingCountry  = $('#billing_country').val();
            if (!billingAddress || !accountingEmail || !billingCountry) {
                isValid = false;
            }
            // Special/package form: company is required (normal form leaves it optional)
            if (isValid && isSpecialMode()) {
                const billingCompany = ($('#billing_company').val() || '').trim();
                if (!billingCompany) {
                    isValid = false;
                }
            }
        }

        // Special form body may be hidden — no checkout until package chosen
        if (isSpecialMode() && $('#ve-special-body').prop('hidden')) {
            isValid = false;
        }

        const $btn = $('#ve-checkout-btn');
        const $wrap = $btn.closest('.ve-checkout-wrap');
        const disabledTip = 'Complete the form before proceeding';

        if (!$btn.length) {
            return;
        }

        if (isValid) {
            $btn.prop('disabled', false).removeClass('is-disabled');
            $wrap.removeClass('is-disabled').removeAttr('title');
        } else {
            $btn.prop('disabled', true).addClass('is-disabled');
            $wrap.addClass('is-disabled').attr('title', disabledTip);
        }
    }

    const ADD_TICKET_LABEL = '<span class="dashicons dashicons-insert" aria-hidden="true"></span> Add another ticket';

    function setAddTicketButtonState() {
        const $btn = $('#add-ticket-btn');
        if (!$btn.length) {
            return;
        }
        const atMax = ticketCount >= MAX_TICKETS;

        $btn.prop('disabled', atMax);
        $btn.html(atMax ? 'Maximum 30 tickets reached' : ADD_TICKET_LABEL);
    }

    function renumberTickets() {
        $('#tickets-container .ticket-accordion').each(function (i) {
            const $ticket = $(this);
            $ticket.attr('data-index', i);
            const title = isSpecialMode()
                ? ('Additional ticket ' + (i + 1))
                : ('Ticket ' + (i + 1));
            $ticket.find('.accordion-header strong').first().text(title);

            const $header = $ticket.find('.accordion-header');
            $header.find('.remove-ticket-btn').remove();

            // Normal: cannot remove the only remaining ticket
            // Special: all extra tickets are removable
            const allowRemove = isSpecialMode()
                ? true
                : (i > 0);

            if (allowRemove) {
                $header.append(
                    '<button type="button" class="remove-ticket-btn" aria-label="Remove ticket">×</button>'
                );
            }
        });
        ticketCount = $('#tickets-container .ticket-accordion').length;
    }

    function addTicket(tierOptions, options) {
        options = options || {};
        if (ticketCount >= MAX_TICKETS) return;

        const asExtra = !!options.asExtra || isSpecialMode();
        const allowRemove = asExtra
            ? (isSpecialMode() ? true : ticketCount > 0)
            : (ticketCount > 0);

        ticketCount++;
        const html = asExtra
            ? createExtraTicketHTML(ticketCount - 1, tierOptions, allowRemove || ticketCount > 1)
            : createTicketHTML(ticketCount - 1, tierOptions);

        $('#tickets-container').append(html);
        // Re-apply remove rules consistently
        renumberTickets();

        updatePriceAndBreakdown();
        validateCheckoutButton();
        setAddTicketButtonState();
    }

    function removeTicket(index) {
        const $target = $('#tickets-container .ticket-accordion').filter(function () {
            return String($(this).data('index')) === String(index);
        });
        if (!$target.length) {
            return;
        }

        // Normal form: never remove the last remaining ticket
        if (!isSpecialMode() && $('#tickets-container .ticket-accordion').length <= 1) {
            return;
        }

        $target.remove();
        renumberTickets();

        updatePriceAndBreakdown();
        validateCheckoutButton();
        setAddTicketButtonState();
    }

    function getSelectedPackage() {
        const key = $('#ve-special-tier-select').val();
        if (!key || !window.veSpecialTiers || !window.veSpecialTiers[key]) {
            return null;
        }
        return Object.assign({ key: key }, window.veSpecialTiers[key]);
    }

    function renderFreeTickets(pkg) {
        const $box = $('#free-tickets-container');
        $box.empty();
        if (!pkg) {
            return;
        }
        const count = parseInt(pkg.free_tickets, 10) || 0;
        const freeName = pkg.free_tier_name || '';
        for (let i = 0; i < count; i++) {
            $box.append(createFreeTicketHTML(i, freeName));
        }
    }

    function showSpecialBody(show) {
        const $body = $('#ve-special-body');
        if (!$body.length) {
            return;
        }
        if (show) {
            $body.prop('hidden', false).attr('aria-hidden', 'false');
        } else {
            $body.prop('hidden', true).attr('aria-hidden', 'true');
            $('#free-tickets-container').empty();
            $('#tickets-container').empty();
            ticketCount = 0;
            setAddTicketButtonState();
        }
    }

    function updatePriceAndBreakdown() {
        let total = 0;

        if (isSpecialMode()) {
            const pkg = getSelectedPackage();
            if (pkg) {
                total += parseFloat(pkg.price) || 0;
            }
        }

        // Paid ticket tier selects only (not free tickets)
        $('#tickets-container .tier-select').each(function () {
            total += parseFloat($(this).find('option:selected').data('price')) || 0;
        });

        // Normal mode: all .tier-select live under #tickets-container already;
        // keep fallback for older markup without container restriction
        if (!isSpecialMode() && total === 0) {
            $('.tier-select').each(function () {
                total += parseFloat($(this).find('option:selected').data('price')) || 0;
            });
        }

        const country = $('#billing_country').val() || 'NA';
        const breakdown = calculateVATBreakdown(total, country);

        $('#price-amount').text(breakdown.total);

        let html = '';
        if (total > 0) {
            if (breakdown.isNamibia) {
                html = `<strong>N$ ${breakdown.total}</strong> (VAT 15% included)<br><small>VAT portion: N$ ${breakdown.vat}</small>`;
            } else {
                html = `<strong>N$ ${breakdown.total}</strong> (VAT zero-rated)`;
            }
        }
        $('#vat-breakdown').html(html);
    }

    function populateCountries() {
        const countrySelect = $('#billing_country');
        if (!countrySelect.length || countrySelect.data('ve-countries-filled')) {
            return;
        }
        countries.forEach(c => {
            if (c.code !== 'NA') {
                countrySelect.append(`<option value="${c.code}">${c.name}</option>`);
            }
        });
        countrySelect.data('ve-countries-filled', true);
    }

    function collectPersonFromAccordion($el) {
        return {
            first_name: ($el.find('.first_name').val() || '').trim(),
            last_name: ($el.find('.last_name').val() || '').trim(),
            organisation: ($el.find('.organisation').val() || '').trim(),
            phone: ($el.find('.phone').val() || '').trim(),
            email: ($el.find('.email').val() || '').trim()
        };
    }

    function postCheckout(formData, $btn) {
        const ajaxUrl = (window.veGateway && veGateway.ajax_url)
            ? window.veGateway.ajax_url
            : '/wp-admin/admin-ajax.php';

        $.post(ajaxUrl, formData)
            .done(function (response) {
                if (response.success && response.data.payment_reference) {
                    $btn.text('✅ Registrations saved – redirecting to payment...');
                    const ref = response.data.payment_reference;
                    window.location.href = window.location.pathname + '?ve_payment=start&ref=' + encodeURIComponent(ref);
                } else {
                    alert('❌ ' + ((response.data && response.data.message) || 'Unknown error'));
                    $btn.prop('disabled', false).text('Proceed to Payment');
                    validateCheckoutButton();
                }
            })
            .fail(function () {
                alert('Network error – please try again.');
                $btn.prop('disabled', false).text('Proceed to Payment');
                validateCheckoutButton();
            });
    }

    // Main initialization
    $(document).ready(function () {
        const $form = $('#ve-registration-form');
        if (!$form.length) return;

        const special = isSpecialMode();
        console.log('✅ Venture Events registration form initialized', special ? '(special)' : '(normal)');

        const tierOptions = window.veTierOptions || '';

        if (special) {
            // Body stays hidden until package chosen (default option is "Select")
            showSpecialBody(false);

            $('#ve-special-tier-select').on('change', function () {
                const pkg = getSelectedPackage();
                if (!pkg) {
                    showSpecialBody(false);
                    updatePriceAndBreakdown();
                    validateCheckoutButton();
                    return;
                }

                showSpecialBody(true);
                renderFreeTickets(pkg);
                // Do not auto-add extra paid tickets; package + free is enough
                $('#tickets-container').empty();
                ticketCount = 0;
                setAddTicketButtonState();
                updatePriceAndBreakdown();
                validateCheckoutButton();
            });
        } else {
            // Start with first paid ticket
            addTicket(tierOptions, { asExtra: false });
        }

        $('#add-ticket-btn').on('click', function () {
            if (special && !getSelectedPackage()) {
                return;
            }
            addTicket(tierOptions, { asExtra: true });
        });

        $(document).on('click', '.remove-ticket-btn', function (e) {
            e.preventDefault();
            e.stopPropagation();
            const index = $(this).closest('.ticket-accordion').data('index');
            removeTicket(index);
        });

        $(document).on(
            'input change',
            '.first_name, .last_name, .email, .tier-select, #billing_company, #billing_address, #accounting_email, #billing_country, #ve-special-tier-select',
            function () {
                validateCheckoutButton();
                updatePriceAndBreakdown();
            }
        );

        populateCountries();

        $('#ve-checkout-btn').on('click', function () {
            const $btn = $(this);
            if ($btn.prop('disabled')) {
                return;
            }

            $btn.prop('disabled', true).text('Saving registrations...');

            const base = {
                action: 've_save_pending_registrations',
                nonce: (window.veGateway && veGateway.nonce) || '',
                event_id: $('#ve-event-id').val(),
                billing_company: ($('#billing_company').val() || '').trim(),
                billing_address: ($('#billing_address').val() || '').trim(),
                billing_country: $('#billing_country').val(),
                accounting_email: ($('#accounting_email').val() || '').trim(),
                billing_notes: ($('#billing_notes').val() || '').trim()
            };

            if (special) {
                const pkg = getSelectedPackage();
                if (!pkg) {
                    alert('Please select a package.');
                    $btn.prop('disabled', false).text('Proceed to Payment');
                    validateCheckoutButton();
                    return;
                }

                const free_tickets = [];
                $('#free-tickets-container .ticket-accordion').each(function () {
                    free_tickets.push(collectPersonFromAccordion($(this)));
                });

                const tickets = [];
                $('#tickets-container .ticket-accordion').each(function () {
                    const person = collectPersonFromAccordion($(this));
                    person.tier = $(this).find('.tier-select').val();
                    person.price = parseFloat($(this).find('.tier-select option:selected').data('price')) || 0;
                    tickets.push(person);
                });

                postCheckout(Object.assign({}, base, {
                    mode: 'special',
                    special_tier: pkg.key,
                    free_tickets: free_tickets,
                    tickets: tickets
                }), $btn);
            } else {
                const tickets = [];
                $('#tickets-container .ticket-accordion').each(function () {
                    const person = collectPersonFromAccordion($(this));
                    person.tier = $(this).find('.tier-select').val();
                    person.price = parseFloat($(this).find('.tier-select option:selected').data('price')) || 0;
                    tickets.push(person);
                });

                postCheckout(Object.assign({}, base, {
                    mode: 'normal',
                    tickets: tickets
                }), $btn);
            }
        });

        validateCheckoutButton();
        updatePriceAndBreakdown();
    });

})(jQuery);
