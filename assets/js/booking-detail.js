document.addEventListener('DOMContentLoaded', () => {
    const paymentOptions = document.querySelectorAll('.payment-option');
    const paymentRadios = document.querySelectorAll(
        '.payment-option input[type="radio"]'
    );

    function updatePaymentSelection() {
        paymentOptions.forEach(option => {
            option.classList.remove('selected');
        });

        const checkedRadio = document.querySelector(
            '.payment-option input[type="radio"]:checked'
        );

        if (checkedRadio) {
            checkedRadio.closest('.payment-option')
                .classList.add('selected');
        }
    }

    paymentRadios.forEach(radio => {
        radio.addEventListener('change', updatePaymentSelection);
    });

    updatePaymentSelection();
});