window.SM = window.SM || {};

window.SM.userSelectorInline = (config = {}) => ({
    linkedUserLabel: config.label || '',
    linkedUserSubmissionValue: config.submissionValue || '',
    linkedUserMap: config.userMap || {},
    linkedUsers: Array.isArray(config.users) ? config.users : [],
    createUserUrl: config.createUrl || '',
    csrfToken: config.csrfToken || '',
    linkedUserOpen: false,
    linkedUserSelectedIndex: -1,
    linkedUserFiltered: [],
    createUserOpen: false,
    createUserSubmitting: false,
    createUserError: '',
    createUserTab: 'contact',
    shippingSameBilling: true,
    newUser: {
        firstname: '',
        surname: '',
        organisation_name: '',
        email: '',
        phone: '',
        billing_address: '',
        billing_address2: '',
        billing_city: '',
        billing_state: '',
        billing_postcode: '',
        billing_country: '',
        shipping_address: '',
        shipping_address2: '',
        shipping_city: '',
        shipping_state: '',
        shipping_postcode: '',
        shipping_country: '',
    },
    syncLinkedUserId() {
        const matched = this.linkedUsers.find(option => option.label === this.linkedUserLabel);
        const userId = matched?.id || this.linkedUserMap[this.linkedUserLabel] || '';
        this.$refs.linkedUserId.value = userId;
    },
    syncSubmittedLabel() {
        this.linkedUserSubmissionValue = this.linkedUserLabel;
    },
    handleLookupInput() {
        this.syncSubmittedLabel();
        this.syncLinkedUserId();
        this.refreshLinkedUsers();
    },
    handleLookupBlur(element) {
        this.syncLinkedUserId();
        window.SM.delaySet(element, 'linkedUserOpen', false, 120);
    },
    refreshLinkedUsers() {
        const needle = String(this.linkedUserLabel || '').toLowerCase().trim();
        if (needle === '') {
            this.linkedUserFiltered = [];
            this.linkedUserSelectedIndex = -1;
            this.linkedUserOpen = false;
            return;
        }
        this.linkedUserFiltered = this.linkedUsers
            .filter(option => String(option?.label || '').toLowerCase().includes(needle))
            .slice(0, 8);
        this.linkedUserSelectedIndex = this.linkedUserFiltered.length > 0 ? 0 : -1;
        this.linkedUserOpen = this.linkedUserFiltered.length > 0;
    },
    moveLinkedUser(step) {
        if (!this.linkedUserOpen) {
            this.refreshLinkedUsers();
            return;
        }
        const length = this.linkedUserFiltered.length;
        if (!length) return;
        this.linkedUserSelectedIndex = (this.linkedUserSelectedIndex + step + length) % length;
    },
    chooseLinkedUser(option) {
        this.linkedUserLabel = option?.label || '';
        this.linkedUserSubmissionValue = option?.prefill?.contact_name || option?.label || '';
        this.$refs.linkedUserId.value = option?.id || '';
        this.linkedUserOpen = false;
        this.linkedUserSelectedIndex = -1;
        window.dispatchEvent(new CustomEvent('admin-linked-user-changed', {
            detail: {
                userId: option?.id || '',
                label: option?.label || '',
                accountTermsDays: Number(option?.account_terms_days || 0),
                user: option?.prefill || null,
            },
        }));
    },
    confirmLinkedUser() {
        if (!this.linkedUserOpen) return;
        if (this.linkedUserSelectedIndex < 0 || this.linkedUserSelectedIndex >= this.linkedUserFiltered.length) return;
        this.chooseLinkedUser(this.linkedUserFiltered[this.linkedUserSelectedIndex]);
    },
    openCreateUser() {
        this.createUserOpen = true;
        this.createUserTab = 'contact';
        this.shippingSameBilling = true;
        this.createUserError = '';
        this.newUser = {
            firstname: '', surname: '', organisation_name: '', email: '', phone: '',
            billing_address: '', billing_address2: '', billing_city: '', billing_state: '', billing_postcode: '', billing_country: '',
            shipping_address: '', shipping_address2: '', shipping_city: '', shipping_state: '', shipping_postcode: '', shipping_country: '',
        };
    },
    closeCreateUser() {
        this.createUserOpen = false;
        this.createUserError = '';
    },
    syncShippingAddress() {
        this.newUser.shipping_address = this.newUser.billing_address;
        this.newUser.shipping_address2 = this.newUser.billing_address2;
        this.newUser.shipping_city = this.newUser.billing_city;
        this.newUser.shipping_state = this.newUser.billing_state;
        this.newUser.shipping_postcode = this.newUser.billing_postcode;
        this.newUser.shipping_country = this.newUser.billing_country;
    },
    syncShippingIfLinked() {
        if (this.shippingSameBilling) this.syncShippingAddress();
    },
    async submitCreateUser() {
        if (this.createUserSubmitting) return;
        this.createUserSubmitting = true;
        this.createUserError = '';
        try {
            this.syncShippingIfLinked();
            const response = await fetch(this.createUserUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': this.csrfToken,
                },
                body: JSON.stringify(this.newUser),
            });
            const payload = await response.json();
            if (!response.ok || !payload?.success || !payload?.user) {
                const firstError = payload?.errors ? Object.values(payload.errors)?.[0]?.[0] : null;
                throw new Error(firstError || payload?.message || 'Unable to create user.');
            }
            const user = payload.user;
            this.linkedUserMap[user.label] = user.id;
            this.linkedUsers = [...this.linkedUsers, { id: user.id, label: user.label, account_terms_days: 0 }]
                .filter((value, index, array) => array.findIndex(item => item.id === value.id) === index);
            this.linkedUserLabel = user.label;
            this.$refs.linkedUserId.value = user.id;
            this.refreshLinkedUsers();
            this.closeCreateUser();
            window.dispatchEvent(new CustomEvent('admin-linked-user-changed', {
                detail: { userId: user.id, label: user.label, accountTermsDays: 0, user: null },
            }));
        } catch (error) {
            this.createUserError = error?.message || 'Unable to create user.';
        } finally {
            this.createUserSubmitting = false;
        }
    },
});
