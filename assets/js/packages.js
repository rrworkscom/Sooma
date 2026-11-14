document.addEventListener('DOMContentLoaded', () => {
    const categoryFilter = document.getElementById('categoryFilter');
    const classTypeFilter = document.getElementById('classTypeFilter');
    const packageSections = document.querySelectorAll('.package-section');
    const noPackagesMessage = document.getElementById('noPackagesMessage');
    const termsModal = document.getElementById('termsModal');
    const acceptTermsButton = document.getElementById('acceptTerms');
    const purchaseButtons = document.querySelectorAll('.purchase-button');

    let selectedPackage = null;
    let previousActiveElement = null;

    function filterPackages() {
        const selectedCategory = categoryFilter.value;
        const selectedClassType = classTypeFilter.value;

        let totalVisiblePackages = 0;

        packageSections.forEach(section => {
            const sectionCategory = section.dataset.sectionCategory;
            const packageCards = section.querySelectorAll('.package-card');

            let visibleCards = 0;

            packageCards.forEach(card => {
                const classType = card.dataset.classType;

                const categoryMatches =
                    selectedCategory === 'all' ||
                    selectedCategory === sectionCategory;

                const classTypeMatches =
                    selectedClassType === 'all' ||
                    selectedClassType === classType;

                const isVisible = categoryMatches && classTypeMatches;

                card.hidden = !isVisible;

                if (isVisible) {
                    visibleCards++;
                }
            });

            section.hidden = visibleCards === 0;
            totalVisiblePackages += visibleCards;
        });

        noPackagesMessage.hidden = totalVisiblePackages !== 0;
    }

    categoryFilter.addEventListener('change', filterPackages);
    classTypeFilter.addEventListener('change', filterPackages);

    function openTermsModal(button) {
        selectedPackage = {
            name: button.dataset.package,
            sessions: button.dataset.sessions,
            price: button.dataset.price
        };

        previousActiveElement = document.activeElement;

        termsModal.classList.add('is-visible');
        termsModal.setAttribute('aria-hidden', 'false');

        document.body.classList.add('modal-open');

        acceptTermsButton.focus();
    }

    purchaseButtons.forEach(button => {
        button.addEventListener('click', () => {
            openTermsModal(button);
        });
    });

    function closeTermsModal() {
        termsModal.classList.remove('is-visible');
        termsModal.setAttribute('aria-hidden', 'true');

        document.body.classList.remove('modal-open');

        if (previousActiveElement) {
            previousActiveElement.focus();
        }
    }

    termsModal.addEventListener('click', event => {
        if (event.target === termsModal) {
            closeTermsModal();
        }
    });

    document.addEventListener('keydown', event => {
        if (
            event.key === 'Escape' &&
            termsModal.classList.contains('is-visible')
        ) {
            closeTermsModal();
        }
    });

    acceptTermsButton.addEventListener('click', () => {
        if (!selectedPackage) {
            return;
        }

        closeTermsModal();

        alert(
            'Terms & Conditions accepted.\n\n' +
            'Package: ' + selectedPackage.name + '\n' +
            'Sessions: ' + selectedPackage.sessions + '\n' +
            'Price: ' + selectedPackage.price + '\n\n' +
            'The payment process will be connected in the next stage.'
        );

        selectedPackage = null;
    });
});