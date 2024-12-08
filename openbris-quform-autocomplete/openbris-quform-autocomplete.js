jQuery(document).ready(function($) {
    const fieldIds = openbrisConfig.company_name_search_classes;

    fieldIds.forEach(fieldId => {
        const inputSelector = `.${fieldId.trim()}`;
        
        // Create suggestions box
        const suggestionsBox = $('<ul id="company-suggestions"></ul>').css({
            zIndex: 999,
            background: '#fff',
            listStyle: 'none',
            padding: 0,
            margin: '2px',
            maxHeight: '200px',
            overflowY: 'auto',
            position: 'absolute', // Ensure proper positioning
        });

        // Append suggestions box after input field
        $(inputSelector).after(suggestionsBox);

        let debounceTimer; // Timer for debounce to limit API calls

        // Handle input event
        $(inputSelector).on('input', function () {
            const query = $(this).val().trim();
            clearTimeout(debounceTimer); // Clear previous debounce

            if (query.length >= 3) {
                debounceTimer = setTimeout(() => {
                      // Retrieve the state value dynamically
            let stateValue = '';
            openbrisConfig.state_field_classes.forEach(stateClass => {
                const fieldValue = $(`.${stateClass}`).val();
                if (fieldValue) {
                    stateValue = fieldValue; // Assuming a single value is needed
                }
            });

                    suggestionsBox.hide(); // Hide suggestions box during request

                    // API request
                    $.ajax({
                        url: openbrisConfig.ajax_url,
                        method: 'POST',
                        data: {
                            action: 'fetch_company_suggestions',
                            openquery: query,
                            briscountry: stateValue // Pass the state value
                        },
                        success: function (response) {
                            suggestionsBox.empty(); // Clear existing suggestions

                            if (response.success) {
                                response.data.forEach(company => {
                                    // Exclude invalid or null companies
                                    if (company.name && company.address && company.city && company.id) {
                                        suggestionsBox.append(
                                            $('<li>', {
                                                css: { padding: '10px', cursor: 'pointer' },
                                                'data-id': company.id,
                                                'data-vat': company.vat,
                                                html: `
                                                    <strong>${company.name}</strong><br>
                                                    <small>${company.address}, ${company.city}</small><br>
                                                    <span>IČO: ${company.id}</span>
                                                `
                                            })
                                        );
                                    }
                                });
                            } else {
                                suggestionsBox.append('<li style="padding: 10px;">No results found.</li>');
                            }

                            suggestionsBox.show(); // Show suggestions box after data is loaded
                        },
                        error: function () {
                            alert('Failed to fetch company suggestions.');
                        },
                    });
                }, 300); // Debounce delay
            } else {
                suggestionsBox.empty(); // Clear suggestions for invalid input
            }
        });

        // Handle selection of a suggestion
        $(document).on('click', '#company-suggestions li', function() {
            const companyName = $(this).find('strong').text();
            const companyAddress = $(this).find('small').text();
            const companyId = $(this).data('id');
            const vatNumber = $(this).data('vat');

            // Set selected company details in inputs
            $(inputSelector).val(`${companyName} (${companyAddress}, IČO: ${companyId})`);
            
            // Update related fields
            openbrisConfig.company_name_search_classes.forEach(className => {
                $(`.${className}`).val(companyName);
            });

            openbrisConfig.company_id_classes.forEach(className => {
                $(`.${className}`).val(companyId);
            });

            openbrisConfig.vat_field_classes.forEach(className => {

    if (vatNumber && vatNumber !== 'No VAT number available') {
        
        $(`.${className}`).val('Platca DPH');

        openbrisConfig.dph_field_classes.forEach(dphClassName => {
            const extractedPart = dphClassName.split('-').pop(); // Extract the dynamic part
            const dynamicClass = `quform-element-${extractedPart}`; // Create the dynamic class
            $(`.${dynamicClass}`).show(); // Show VAT-related field
            $(`.${dphClassName}`).val(vatNumber); // Set VAT value dynamically
        });
    } else {
        $(`.${className}`).val('');
        openbrisConfig.dph_field_classes.forEach(dphClassName => {
            const extractedPart = dphClassName.split('-').pop(); // Extract the dynamic part
            const dynamicClass = `quform-element-${extractedPart}`; // Create the dynamic class
            $(`.${dynamicClass}`).hide(); // Hide VAT-related field
        });
    }
});


            suggestionsBox.empty(); // Clear suggestions
        });

        // Close suggestions when clicking outside
        $(document).click(function(event) {
            if (!$(event.target).closest(inputSelector).length && !$(event.target).is('#company-suggestions')) {
                suggestionsBox.empty();
            }
        });
    });
});
