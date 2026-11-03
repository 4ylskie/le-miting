(() => {
    const navigationDialog = document.getElementById('customer-nav-dialog');
    const navigationOpener = document.querySelector('[data-nav-open]');
    const navigationCloser = navigationDialog?.querySelector('[data-nav-close]');

    if (navigationDialog && navigationOpener && navigationCloser) {
        const desktop = window.matchMedia('(min-width: 1024px)');
        navigationOpener.addEventListener('click', () => {
            navigationDialog.showModal();
            navigationOpener.setAttribute('aria-expanded', 'true');
            document.body.style.overflow = 'hidden';
            navigationCloser.focus();
        });
        navigationCloser.addEventListener('click', () => navigationDialog.close());
        navigationDialog.addEventListener('keydown', (event) => {
            if (event.key !== 'Tab') {
                return;
            }
            const controls = [...navigationDialog.querySelectorAll('a[href], button:not(:disabled)')];
            const first = controls[0];
            const last = controls.at(-1);
            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        });
        navigationDialog.addEventListener('close', () => {
            navigationOpener.setAttribute('aria-expanded', 'false');
            document.body.style.overflow = '';
            if (!desktop.matches) {
                navigationOpener.focus();
            }
        });
        navigationDialog.addEventListener('click', (event) => {
            const bounds = navigationDialog.getBoundingClientRect();
            if (event.target === navigationDialog && (event.clientX < bounds.left || event.clientX > bounds.right
                || event.clientY < bounds.top || event.clientY > bounds.bottom)) {
                navigationDialog.close();
            }
        });
        desktop.addEventListener('change', () => {
            if (desktop.matches && navigationDialog.open) {
                navigationDialog.close();
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
