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

    function isComplimentaryMode() {
        return (window.veRegistrationMode === 'complimentary')
            || ($('#ve-form-mode').val() === 'complimentary')
            || ($('#ve-registration-form').data('mode') === 'complimentary');
    }

    function isEftMode() {
        return (window.vePaymentMode === 'eft')
            || ($('#ve-payment-mode').val() === 'eft');
    }

    function checkoutButtonLabel() {
        return isEftMode() ? 'Complete order' : 'Proceed to Payment';
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

    function getSelectedComplimentaryTier() {
        const key = $('#ve-comp-tier-select').val();
        if (!key || !window.veComplimentaryTiers || !window.veComplimentaryTiers[key]) {
            return null;
        }
        return Object.assign({ key: key }, window.veComplimentaryTiers[key]);
    }

    function complimentaryTierDisplayName() {
        const tier = getSelectedComplimentaryTier();
        return (tier && tier.name) ? String(tier.name) : '';
    }

    function complimentaryTierLabelHtml() {
        const name = complimentaryTierDisplayName();
        const label = name || 'Select a tier';
        return '<strong>' + $('<div>').text(label).html() + '</strong> <span class="ve-hint">(N$ 0.00)</span>';
    }

    function updateComplimentaryTicketLabels() {
        $('#tickets-container .ticket-accordion .ve-included-tier').html(complimentaryTierLabelHtml());
    }

    function createComplimentaryTicketHTML(index) {
        const removeBtn = index > 0
            ? `<button type="button" class="remove-ticket-btn" aria-label="Remove ticket">×</button>`
            : '';

        return `
        <div class="ticket-accordion" data-index="${index}" data-kind="complimentary">
            <div class="accordion-header">
                <strong>Ticket ${index + 1}</strong>
                ${removeBtn}
            </div>
            <div class="accordion-body">
                <p class="ve-included-tier">${complimentaryTierLabelHtml()}</p>
                ${personFieldsHTML()}
            </div>
        </div>`;
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
        const complimentary = isComplimentaryMode();

        if (isSpecialMode()) {
            const pkg = getSelectedPackage();
            if (!pkg) {
                isValid = false;
            } else if (!getSelectedIndustry().valid) {
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
        } else if (complimentary) {
            if (!getSelectedComplimentaryTier()) {
                isValid = false;
            } else {
                const $guests = $('#tickets-container .ticket-accordion');
                if (!$guests.length) {
                    isValid = false;
                } else {
                    $guests.each(function () {
                        if (!validatePersonBlock($(this), false)) {
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

        // Complimentary: no billing section
        if (isValid && !complimentary) {
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
        const disabledTip = complimentary
            ? (getSelectedComplimentaryTier() ? 'Complete guest details first' : 'Select a tier first')
            : 'Complete the form before proceeding';

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

            // Normal / complimentary: cannot remove the only remaining ticket
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

        if (isComplimentaryMode()) {
            ticketCount++;
            $('#tickets-container').append(createComplimentaryTicketHTML(ticketCount - 1));
            renumberTickets();
            updatePriceAndBreakdown();
            validateCheckoutButton();
            setAddTicketButtonState();
            return;
        }

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

        // Normal / complimentary: never remove the last remaining ticket
        if (!isSpecialMode() && $('#tickets-container .ticket-accordion').length <= 1) {
            return;
        }

        $target.remove();
        renumberTickets();

        updatePriceAndBreakdown();
        validateCheckoutButton();
        setAddTicketButtonState();
    }

    function packageHasIndustry(pkg) {
        if (!pkg) {
            return false;
        }
        const opts = Array.isArray(pkg.industries) ? pkg.industries : [];
        return opts.length > 0 || !!pkg.industry_other;
    }

    function syncIndustryField(pkg) {
        const $wrap = $('#ve-industry-wrap');
        const $select = $('#ve-industry-select');
        const $otherWrap = $('#ve-industry-other-wrap');
        const $other = $('#ve-industry-other');
        if (!$wrap.length) {
            return;
        }

        if (!packageHasIndustry(pkg)) {
            $wrap.prop('hidden', true);
            $select.empty().append('<option value="">— Select industry —</option>').val('');
            $other.val('');
            $otherWrap.prop('hidden', true);
            return;
        }

        $wrap.prop('hidden', false);
        $select.empty().append('<option value="">— Select industry —</option>');
        (pkg.industries || []).forEach(function (name) {
            if (!name) {
                return;
            }
            $select.append($('<option></option>').val(name).text(name));
        });
        if (pkg.industry_other) {
            $select.append($('<option></option>').val('__other__').text('Other'));
        }
        $select.val('');
        $other.val('');
        $otherWrap.prop('hidden', true);
    }

    function updateIndustryOtherVisibility() {
        const show = $('#ve-industry-select').val() === '__other__';
        const $otherWrap = $('#ve-industry-other-wrap');
        $otherWrap.prop('hidden', !show);
        if (!show) {
            $('#ve-industry-other').val('');
        }
    }

    function getSelectedIndustry() {
        const pkg = getSelectedPackage();
        if (!packageHasIndustry(pkg)) {
            return { required: false, valid: true, industry: '', industry_other: '' };
        }
        const val = $('#ve-industry-select').val() || '';
        if (!val) {
            return { required: true, valid: false, industry: '', industry_other: '' };
        }
        if (val === '__other__') {
            const other = ($('#ve-industry-other').val() || '').trim();
            if (!other) {
                return { required: true, valid: false, industry: 'Other', industry_other: '' };
            }
            return { required: true, valid: true, industry: '__other__', industry_other: other };
        }
        const opts = pkg.industries || [];
        if (opts.indexOf(val) === -1) {
            return { required: true, valid: false, industry: '', industry_other: '' };
        }
        return { required: true, valid: true, industry: val, industry_other: '' };
    }

    function getSelectedPackage() {
        const key = $('#ve-special-tier-select').val();
        if (!key || !window.veSpecialTiers || !window.veSpecialTiers[key]) {
            return null;
        }
        const pkg = Object.assign({ key: key }, window.veSpecialTiers[key]);
        // Sold-out options are disabled, but still guard here
        if (pkg.sold_out) {
            return null;
        }
        if (pkg.remaining !== null && pkg.remaining !== undefined && pkg.remaining !== ''
            && parseInt(pkg.remaining, 10) < 1) {
            return null;
        }
        return pkg;
    }

    function updatePackageStockHint(pkg) {
        const $hint = $('#ve-package-stock-hint');
        if (!$hint.length) {
            return;
        }
        if (!pkg) {
            $hint.prop('hidden', true).text('');
            return;
        }
        const cap = parseInt(pkg.available, 10) || 0;
        if (cap < 1) {
            $hint.prop('hidden', true).text('');
            return;
        }
        const left = (pkg.remaining === null || pkg.remaining === undefined || pkg.remaining === '')
            ? null
            : parseInt(pkg.remaining, 10);
        if (left === null) {
            $hint.prop('hidden', true).text('');
            return;
        }
        if (left < 1) {
            $hint.prop('hidden', false).text('This package is sold out.');
            return;
        }
        $hint.prop('hidden', false).text(
            left === 1 ? '1 of this package remaining.' : (left + ' of this package remaining.')
        );
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
        if (isComplimentaryMode()) {
            $('#price-amount').text('0.00');
            $('#vat-breakdown').empty();
            return;
        }

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

    function showResultBox($box, message, isError) {
        if (!$box || !$box.length) {
            return;
        }
        $box
            .prop('hidden', false)
            .toggleClass('ve-comp-result--error', !!isError)
            .toggleClass('ve-comp-result--ok', !isError)
            .text(message);
    }

    function showCompResult(message, isError) {
        showResultBox($('#ve-comp-result'), message, isError);
    }

    function showEftResult(message, isError) {
        showResultBox($('#ve-eft-result'), message, isError);
        const el = document.getElementById('ve-eft-result');
        if (el && typeof el.scrollIntoView === 'function') {
            el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
    }

    function clearResultBox($box) {
        if (!$box || !$box.length) {
            return;
        }
        $box.prop('hidden', true).removeClass('ve-comp-result--error ve-comp-result--ok').empty();
    }

    function clearCompResult() {
        clearResultBox($('#ve-comp-result'));
    }

    function clearEftResult() {
        clearResultBox($('#ve-eft-result'));
    }

    function resetEftForm() {
        if (isSpecialMode()) {
            $('#ve-special-tier-select').val('');
            showSpecialBody(false);
            updatePackageStockHint(null);
            syncIndustryField(null);
        } else {
            $('#tickets-container').empty();
            ticketCount = 0;
            addTicket(window.veTierOptions || '', { asExtra: false });
        }
        validateCheckoutButton();
        updatePriceAndBreakdown();
        setAddTicketButtonState();
    }

    function resetComplimentaryForm() {
        $('#tickets-container').empty();
        ticketCount = 0;
        addTicket('', { asExtra: false });
        validateCheckoutButton();
        updatePriceAndBreakdown();
        setAddTicketButtonState();
    }

    function postComplimentary(formData, $btn) {
        const ajaxUrl = (window.veComplimentary && veComplimentary.ajax_url)
            ? veComplimentary.ajax_url
            : ((window.veGateway && veGateway.ajax_url) ? veGateway.ajax_url : '/wp-admin/admin-ajax.php');

        const defaultLabel = 'Issue complimentary tickets';

        $.post(ajaxUrl, formData)
            .done(function (response) {
                if (response.success) {
                    const msg = (response.data && response.data.message)
                        ? response.data.message
                        : 'Complimentary tickets issued.';
                    showCompResult(msg, false);
                    $btn.prop('disabled', false).text(defaultLabel);
                    resetComplimentaryForm();
                } else {
                    const err = (response.data && response.data.message) || 'Unknown error';
                    showCompResult(err, true);
                    $btn.prop('disabled', false).text(defaultLabel);
                    validateCheckoutButton();
                }
            })
            .fail(function () {
                showCompResult('Network error – please try again.', true);
                $btn.prop('disabled', false).text(defaultLabel);
                validateCheckoutButton();
            });
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
        const eft = isEftMode();
        const ajaxUrl = eft
            ? ((window.veEft && veEft.ajax_url) || (window.veGateway && veGateway.ajax_url) || '/wp-admin/admin-ajax.php')
            : ((window.veGateway && veGateway.ajax_url) ? window.veGateway.ajax_url : '/wp-admin/admin-ajax.php');

        $.post(ajaxUrl, formData)
            .done(function (response) {
                if (eft) {
                    if (response.success) {
                        const msg = (response.data && response.data.message)
                            ? response.data.message
                            : 'Order received. Please check your email.';
                        showEftResult(msg, false);
                        $btn.prop('disabled', false).text(checkoutButtonLabel());
                        resetEftForm();
                    } else {
                        const err = (response.data && response.data.message) || 'Unknown error';
                        showEftResult(err, true);
                        $btn.prop('disabled', false).text(checkoutButtonLabel());
                        validateCheckoutButton();
                    }
                    return;
                }

                if (response.success && response.data.payment_reference) {
                    $btn.text('✅ Registrations saved – redirecting to payment...');
                    const ref = response.data.payment_reference;
                    window.location.href = window.location.pathname + '?ve_payment=start&ref=' + encodeURIComponent(ref);
                } else {
                    alert('❌ ' + ((response.data && response.data.message) || 'Unknown error'));
                    $btn.prop('disabled', false).text(checkoutButtonLabel());
                    validateCheckoutButton();
                }
            })
            .fail(function () {
                if (eft) {
                    showEftResult('Network error – please try again.', true);
                } else {
                    alert('Network error – please try again.');
                }
                $btn.prop('disabled', false).text(checkoutButtonLabel());
                validateCheckoutButton();
            });
    }

    // Main initialization
    $(document).ready(function () {
        const $form = $('#ve-registration-form');
        if (!$form.length) return;

        const special = isSpecialMode();
        const complimentary = isComplimentaryMode();
        console.log(
            '✅ Venture Events registration form initialized',
            complimentary ? '(complimentary)' : (special ? '(special)' : '(normal)'),
            isEftMode() ? '(eft)' : ''
        );

        const tierOptions = window.veTierOptions || '';

        if (special) {
            // Body stays hidden until package chosen (default option is "Select")
            showSpecialBody(false);

            $('#ve-special-tier-select').on('change', function () {
                const pkg = getSelectedPackage();
                if (!pkg) {
                    showSpecialBody(false);
                    updatePackageStockHint(null);
                    syncIndustryField(null);
                    updatePriceAndBreakdown();
                    validateCheckoutButton();
                    return;
                }

                showSpecialBody(true);
                updatePackageStockHint(pkg);
                syncIndustryField(pkg);
                renderFreeTickets(pkg);
                // Do not auto-add extra paid tickets; package + free is enough
                $('#tickets-container').empty();
                ticketCount = 0;
                setAddTicketButtonState();
                updatePriceAndBreakdown();
                validateCheckoutButton();
            });
        } else {
            // Start with first paid / complimentary ticket
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
            '.first_name, .last_name, .email, .tier-select, #billing_company, #billing_address, #accounting_email, #billing_country, #ve-special-tier-select, #ve-comp-tier-select, #ve-industry-select, #ve-industry-other',
            function () {
                if ($(this).is('#ve-comp-tier-select')) {
                    updateComplimentaryTicketLabels();
                }
                if ($(this).is('#ve-industry-select')) {
                    updateIndustryOtherVisibility();
                }
                validateCheckoutButton();
                updatePriceAndBreakdown();
            }
        );

        if (!complimentary) {
            populateCountries();
        }

        $('#ve-checkout-btn').on('click', function () {
            const $btn = $(this);
            if ($btn.prop('disabled')) {
                return;
            }

            if (complimentary) {
                clearCompResult();
                $btn.prop('disabled', true).text('Issuing tickets...');

                const tickets = [];
                $('#tickets-container .ticket-accordion').each(function () {
                    tickets.push(collectPersonFromAccordion($(this)));
                });

                const compTier = getSelectedComplimentaryTier();
                if (!compTier) {
                    showCompResult('Please select a ticket tier.', true);
                    $btn.prop('disabled', false).text('Issue complimentary tickets');
                    validateCheckoutButton();
                    return;
                }

                postComplimentary({
                    action: 've_save_complimentary_registrations',
                    nonce: (window.veComplimentary && veComplimentary.nonce) || '',
                    event_id: $('#ve-event-id').val(),
                    comp_tier: compTier.key,
                    tickets: tickets
                }, $btn);
                return;
            }

            if (isEftMode()) {
                clearEftResult();
            }

            $btn.prop('disabled', true).text(isEftMode() ? 'Submitting order...' : 'Saving registrations...');

            const eft = isEftMode();
            const base = {
                action: eft ? 've_save_eft_registrations' : 've_save_pending_registrations',
                nonce: eft
                    ? ((window.veEft && veEft.nonce) || '')
                    : ((window.veGateway && veGateway.nonce) || ''),
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
                    alert('Please select a package that is still available.');
                    $btn.prop('disabled', false).text(checkoutButtonLabel());
                    validateCheckoutButton();
                    return;
                }
                if (pkg.sold_out || (pkg.remaining !== null && pkg.remaining !== undefined
                    && pkg.remaining !== '' && parseInt(pkg.remaining, 10) < 1)) {
                    alert('Sorry, that package is sold out. Please choose another.');
                    $btn.prop('disabled', false).text(checkoutButtonLabel());
                    validateCheckoutButton();
                    return;
                }

                const industry = getSelectedIndustry();
                if (!industry.valid) {
                    alert(industry.industry === 'Other'
                        ? 'Please specify your industry.'
                        : 'Please select your industry.');
                    $btn.prop('disabled', false).text(checkoutButtonLabel());
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
                    industry: industry.industry,
                    industry_other: industry.industry_other,
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
