(() => {
    const menuToggle = document.getElementById('mobile-menu-toggle');
    const mobileMenu = document.getElementById('mobile-menu');

    if (menuToggle && mobileMenu) {
        menuToggle.addEventListener('click', () => {
            const isExpanded = menuToggle.getAttribute('aria-expanded') === 'true';

            mobileMenu.classList.toggle('hidden', isExpanded);
            menuToggle.setAttribute('aria-expanded', String(!isExpanded));
        });

        menuToggle.closest('[data-customer-nav]')?.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && menuToggle.getAttribute('aria-expanded') === 'true') {
                menuToggle.click();
                menuToggle.focus();
            }
        });
    }

    const recommendationForm = document.getElementById('rasa-ai-form');
    const recommendationButton = document.getElementById('rasa-ai-submit');

    if (recommendationForm && recommendationButton) {
        recommendationForm.addEventListener('submit', () => {
            recommendationButton.disabled = true;
            recommendationButton.setAttribute('aria-busy', 'true');
            recommendationButton.textContent = 'Generating recommendations…';
        });
    }

    document.querySelectorAll('select[id^="quantity-"][name="quantity"]').forEach((quantity) => {
        quantity.addEventListener('change', () => quantity.form?.requestSubmit());
    });

    const availabilityForm = document.getElementById('availability-form');

    if (availabilityForm) {
        const date = document.getElementById('reservation_date');
        const time = document.getElementById('reservation_time');
        const status = document.getElementById('slot-status');
        const guests = document.getElementById('guest_count');
        const bookingForm = document.getElementById('reservation-form');
        let requestNumber = 0;

        function invalidateSelection() {
            if (!bookingForm) {
                return;
            }

            bookingForm.querySelectorAll('input[name="table_id"]').forEach((input) => {
                input.checked = false;
            });
            bookingForm.querySelectorAll('input, select, textarea, button').forEach((input) => {
                input.disabled = true;
            });
            bookingForm.hidden = true;
            status.textContent = 'Your visit details changed. Check availability again to select a table.';
        }

        function refreshAvailability() {
            if (bookingForm && !time.disabled && availabilityForm.checkValidity()) {
                availabilityForm.requestSubmit();
            }
        }

        async function updateSlots() {
            const currentRequest = ++requestNumber;

            if (time.tagName === 'INPUT') {
                time.min = date.value === availabilityForm.dataset.businessDate
                    ? availabilityForm.dataset.firstBookableTime
                    : '';
                return;
            }

            const selected = time.value || time.dataset.selected;
            time.replaceChildren(new Option('Choose a start time', ''));

            if (!date.value) {
                return;
            }

            time.disabled = true;
            status.textContent = 'Loading available start times…';

            try {
                const url = new URL(availabilityForm.dataset.slotsUrl, window.location.origin);
                url.searchParams.set('date', date.value);
                const response = await fetch(url, { headers: { Accept: 'application/json' } });

                if (!response.ok) {
                    throw new Error('Unable to load times');
                }

                const data = await response.json();

                if (currentRequest !== requestNumber) {
                    return;
                }

                (data.slots || []).forEach((slot) => {
                    time.add(new Option(slot, slot, false, slot === selected));
                });
                status.textContent = data.slots?.length
                    ? ''
                    : 'No start times are available for this date. Please choose another date.';
            } catch {
                if (currentRequest === requestNumber) {
                    status.textContent = 'Unable to load times. Please choose the date again.';
                }
            } finally {
                if (currentRequest === requestNumber) {
                    time.disabled = false;
                }
            }
        }

        [date, time, guests].forEach((input) => input.addEventListener('input', invalidateSelection));
        date.addEventListener('change', async () => {
            invalidateSelection();
            await updateSlots();
            refreshAvailability();
        });
        [time, guests].forEach((input) => input.addEventListener('change', () => {
            invalidateSelection();
            refreshAvailability();
        }));
        updateSlots();
    }

    const reservationForm = document.getElementById('reservation-form');
    const foodTotal = document.getElementById('food-total');

    if (reservationForm && foodTotal) {
        const items = [...reservationForm.querySelectorAll('.food-check')];
        const selectionError = document.getElementById('food-selection-error');

        function recalculateFoodTotal() {
            const total = items.reduce((sum, check) => {
                if (!check.checked) {
                    return sum;
                }

                const quantity = check.closest('[data-reservation-food]').querySelector('.food-qty');
                return sum + Number.parseFloat(check.dataset.price) * Number.parseInt(quantity.value, 10);
            }, 0);

            foodTotal.textContent = `RM ${total.toLocaleString('en-MY', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

            if (items.some((check) => check.checked)) {
                selectionError.hidden = true;
            }
        }

        items.forEach((check) => {
            const quantity = check.closest('[data-reservation-food]').querySelector('.food-qty');
            check.addEventListener('change', () => {
                quantity.disabled = !check.checked;

                if (!check.checked) {
                    quantity.value = '1';
                }

                recalculateFoodTotal();
            });
            quantity.addEventListener('change', recalculateFoodTotal);
        });

        reservationForm.addEventListener('submit', (event) => {
            if (reservationForm.dataset.foodRequired && !items.some((check) => check.checked)) {
                event.preventDefault();
                selectionError.hidden = false;
                items[0]?.focus();
            }
        });
        recalculateFoodTotal();
    }
})();
