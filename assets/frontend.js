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

    function createTicketHTML(index, tierOptions) {
        const removeBtn = index > 0
            ? `<button type="button" class="remove-ticket-btn" aria-label="Remove ticket">×</button>`
            : '';

        return `
        <div class="ticket-accordion" data-index="${index}">
            <div class="accordion-header">
                <strong>Ticket ${index + 1}</strong>
                ${removeBtn}
            </div>
            <div class="accordion-body">
                <p><label>First Name <span class="ve-required">*</span></label><br>
                   <input type="text" class="first_name" required></p>
                <p><label>Last Name <span class="ve-required">*</span></label><br>
                   <input type="text" class="last_name" required></p>
                <p><label>Organisation</label><br>
                   <input type="text" class="organisation"></p>
                <p><label>Phone</label><br>
                   <input type="text" class="phone"></p>
                <p><label>Email (for ticket) <span class="ve-required">*</span></label><br>
                   <input type="email" class="email" required></p>
                <p><label>Ticket Tier <span class="ve-required">*</span></label><br>
                   <select class="tier-select" required>
                       <option value="">— Please select a tier —</option>
                       ${tierOptions}
                   </select>
                </p>
            </div>
        </div>`;
    }

    function validateCheckoutButton() {
        let isValid = true;

        $('.ticket-accordion').each(function() {
            const firstName = $(this).find('.first_name').val().trim();
            const lastName  = $(this).find('.last_name').val().trim();
            const email     = $(this).find('.email').val().trim();
            const tier      = $(this).find('.tier-select').val();

            if (!firstName || !lastName || !email || !tier) {
                isValid = false;
                return false;
            }
        });

        const billingAddress  = $('#billing_address').val().trim();
        const accountingEmail = $('#accounting_email').val().trim();
        const billingCountry  = $('#billing_country').val();

        if (!billingAddress || !accountingEmail || !billingCountry) {
            isValid = false;
        }

        const $btn = $('#ve-checkout-btn');
        const $wrap = $btn.closest('.ve-checkout-wrap');
        const disabledTip = 'Complete the form before proceeding';

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
        const atMax = ticketCount >= MAX_TICKETS;

        $btn.prop('disabled', atMax);
        // Use .html() so dashicons markup renders ( .text() would show raw tags )
        $btn.html(atMax ? 'Maximum 30 tickets reached' : ADD_TICKET_LABEL);
    }

    function renumberTickets() {
        $('#tickets-container .ticket-accordion').each(function (i) {
            const $ticket = $(this);
            $ticket.attr('data-index', i);
            $ticket.find('.accordion-header strong').first().text('Ticket ' + (i + 1));

            const $header = $ticket.find('.accordion-header');
            $header.find('.remove-ticket-btn').remove();
            if (i > 0) {
                $header.append(
                    '<button type="button" class="remove-ticket-btn" aria-label="Remove ticket">×</button>'
                );
            }
        });
        ticketCount = $('#tickets-container .ticket-accordion').length;
    }

    function addTicket(tierOptions) {
        if (ticketCount >= MAX_TICKETS) return;

        ticketCount++;
        $('#tickets-container').append(createTicketHTML(ticketCount - 1, tierOptions));

        updatePriceAndBreakdown();
        validateCheckoutButton();
        setAddTicketButtonState();
    }

    function removeTicket(index) {
        const $target = $(`.ticket-accordion[data-index="${index}"]`);
        if (!$target.length) {
            return;
        }

        // Never remove the last remaining ticket
        if ($('#tickets-container .ticket-accordion').length <= 1) {
            return;
        }

        $target.remove();
        renumberTickets();

        updatePriceAndBreakdown();
        validateCheckoutButton();
        setAddTicketButtonState();
    }

    function updatePriceAndBreakdown() {
        let total = 0;
        $('.tier-select').each(function() {
            total += parseFloat($(this).find('option:selected').data('price')) || 0;
        });
        
        const country = $('#billing_country').val();
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

    // Main initialization
    $(document).ready(function() {
        const $form = $('#ve-registration-form');
        if (!$form.length) return;

        console.log('✅ Venture Events registration form initialized');

        const tierOptions = window.veTierOptions || '';

        // Start with first ticket
        addTicket(tierOptions);

        $('#add-ticket-btn').on('click', function() {
            addTicket(tierOptions);
        });

        $(document).on('click', '.remove-ticket-btn', function (e) {
            e.preventDefault();
            e.stopPropagation();
            const index = $(this).closest('.ticket-accordion').data('index');
            removeTicket(index);
        });

        // Real-time validation + price update
        $(document).on('input change', '.first_name, .last_name, .email, .tier-select, #billing_address, #accounting_email, #billing_country', function() {
            validateCheckoutButton();
            updatePriceAndBreakdown();
        });

        // === Populate countries dropdown ===
        const countrySelect = $('#billing_country');
        countries.forEach(c => {
            if (c.code !== 'NA') {
                countrySelect.append(`<option value="${c.code}">${c.name}</option>`);
            }
        });

        // Checkout button handler
        $('#ve-checkout-btn').on('click', function() {
            const btn = $(this);
            btn.prop('disabled', true).text('Saving registrations...');

            const tickets = [];
            $('.ticket-accordion').each(function() {
                tickets.push({
                    first_name: $(this).find('.first_name').val().trim(),
                    last_name:  $(this).find('.last_name').val().trim(),
                    organisation: $(this).find('.organisation').val().trim(),
                    phone: $(this).find('.phone').val().trim(),
                    email: $(this).find('.email').val().trim(),
                    tier: $(this).find('.tier-select').val(),
                    price: parseFloat($(this).find('.tier-select option:selected').data('price')) || 0
                });
            });

            const ajaxUrl = (window.veGateway && veGateway.ajax_url) 
                ? window.veGateway.ajax_url 
                : '/wp-admin/admin-ajax.php';

            const formData = {
                action: 've_save_pending_registrations',
                nonce: (window.veGateway && veGateway.nonce) || '',
                event_id: $('#ve-event-id').val(),
                tickets: tickets,
                billing_company: $('#billing_company').val().trim(),
                billing_address: $('#billing_address').val().trim(),
                billing_country: $('#billing_country').val(),
                accounting_email: $('#accounting_email').val().trim(),
                billing_notes: $('#billing_notes').val().trim()
            };

            $.post(ajaxUrl, formData)
                .done(function(response) {
                    if (response.success && response.data.payment_reference) {
                        btn.text('✅ Registrations saved – redirecting to payment...');
                        const ref = response.data.payment_reference;
                        window.location.href = window.location.pathname + '?ve_payment=start&ref=' + encodeURIComponent(ref);
                    } else {
                        alert('❌ ' + (response.data?.message || 'Unknown error'));
                        btn.prop('disabled', false).text('Proceed to Payment');
                    }
                })
                .fail(function() {
                    alert('Network error – please try again.');
                    btn.prop('disabled', false).text('Proceed to Payment');
                });
        });

        // Initial state
        validateCheckoutButton();
        updatePriceAndBreakdown();
    });

})(jQuery);
