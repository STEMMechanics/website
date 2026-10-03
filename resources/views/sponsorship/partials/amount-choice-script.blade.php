<script nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">
    function sponsorshipCommunitySupportAmountChoice(config) {
        return {
            options: Array.isArray(config.options) ? config.options : [],
            selectedChoice: config.savedChoice || '',
            frequency: config.savedFrequency || 'one_time',
            optionId: config.savedOption || '',
            customAmount: config.savedCustomAmount || '',
            availableOptions() {
                return this.options.filter((option) => option.frequency === this.frequency);
            },
            selectedOption() {
                return this.availableOptions().find((option) => String(option.value) === String(this.selectedChoice)) || null;
            },
            recognitionEnabled() {
                const selected = this.availableOptions().find((option) => String(option.value) === String(this.selectedChoice));
                return selected?.recognition === true;
            },
            logoRecognition() {
                const selected = this.availableOptions().find((option) => String(option.value) === String(this.selectedChoice));
                return selected?.logoRecognition === true;
            },
            init() {
                const frequencySelect = this.$el.querySelector('select[name="frequency"]');
                const frequencyExists = Array.from(frequencySelect?.options ?? []).some((option) => option.value === this.frequency);
                if (!frequencyExists) this.frequency = 'one_time';

                if (!this.selectedChoice && this.optionId) this.selectedChoice = String(this.optionId);
                const selected = this.availableOptions().find((option) => String(option.value) === String(this.selectedChoice));
                if (!selected) {
                    this.selectedChoice = '';
                    this.optionId = '';
                    this.customAmount = '';
                    return;
                }
                this.optionId = selected.value === 'custom' ? '' : String(selected.value);
                if (selected.value !== 'custom') this.customAmount = '';
            },
            changeFrequency(event) {
                this.frequency = event.target.value;
                const selected = this.availableOptions().find((option) => String(option.value) === String(this.selectedChoice));
                if (!selected) {
                    this.selectedChoice = '';
                    this.optionId = '';
                    this.customAmount = '';
                }
            },
            changeAmount(event) {
                this.selectedChoice = event.target.value;
                const selected = this.availableOptions().find((option) => String(option.value) === String(this.selectedChoice));
                this.optionId = selected && selected.value !== 'custom' ? String(selected.value) : '';
                if (!selected || selected.value !== 'custom') this.customAmount = '';
            },
        };
    }

    function sponsorshipCommunitySupportCheckout(squareConfig, amountConfig) {
        const squareState = sponsorshipSquareCheckout(squareConfig);
        const amountState = sponsorshipCommunitySupportAmountChoice(amountConfig);

        return Object.assign({}, squareState, amountState, {
            async init() {
                amountState.init.call(this);
                await squareState.init.call(this);
            },
            submitLabel() {
                if (this.selectedChoice === 'custom') {
                    const amount = Number(this.customAmount);
                    return Number.isFinite(amount) && amount > 0 ? `Pay $${amount.toFixed(2)}` : 'Sponsor now';
                }

                const selected = this.selectedOption();
                const amount = Number(this.frequency === 'monthly' ? selected?.firstPayment : selected?.amount);
                if (!Number.isFinite(amount) || amount <= 0) return 'Sponsor now';
                return this.frequency === 'monthly' ? `Sponsor $${amount.toFixed(2)} today` : `Pay $${amount.toFixed(2)}`;
            },
        });
    }

    function sponsorshipAmountChoice(config) {
        return {
            selectedChoice: config.savedChoice || '',
            frequency: config.savedFrequency || 'one_time',
            optionId: config.savedOption || '',
            customAmount: config.savedCustomAmount || '',
            recognitionEnabled() {
                const select = this.$el.querySelector('select[name="sponsorship_choice"]');
                const selected = Array.from(select?.options ?? []).find((option) => option.value === String(this.selectedChoice));
                return selected?.dataset.recognition === 'true';
            },
            init() {
                const select = this.$el.querySelector('select[name="sponsorship_choice"]');
                const option = Array.from(select?.options ?? []).find((item) => item.value === String(this.selectedChoice));
                if (!option) {
                    this.selectedChoice = '';
                    this.frequency = 'one_time';
                    this.optionId = '';
                    return;
                }
                if (option.value === 'custom') {
                    this.frequency = 'one_time';
                    this.optionId = '';
                    return;
                }

                this.optionId = option.value;
                this.frequency = option.dataset.frequency || 'one_time';
                this.customAmount = '';
            },
            updateChoice(event) {
                const selected = event.target.selectedOptions?.[0];
                if (!selected) return;

                this.selectedChoice = selected.value;
                if (selected.value === 'custom') {
                    this.frequency = 'one_time';
                    this.optionId = '';
                    return;
                }

                this.optionId = selected.value;
                this.frequency = selected.dataset.frequency || 'one_time';
                this.customAmount = '';
            },
        };
    }

    function sponsorshipBusinessAmountChoice(config) {
        return {
            options: Array.isArray(config.options) ? config.options : [],
            frequency: config.savedFrequency || 'one_time',
            selectedChoice: config.savedChoice || '',
            optionId: config.savedOption || '',
            customAmount: config.savedCustomAmount || '',
            monthlyAvailable: config.monthlyAvailable === true,
            availableFrequencies() {
                return this.monthlyAvailable ? ['one_time', 'monthly'] : ['one_time'];
            },
            availableOptions() {
                return this.options.filter((option) => option.frequency === this.frequency);
            },
            selectedOption() {
                return this.availableOptions().find((option) => String(option.value) === String(this.selectedChoice)) || null;
            },
            customBenefitOption() {
                const amount = Number(this.customAmount);
                if (!Number.isFinite(amount) || amount <= 0) return null;

                return this.options
                    .filter((option) => option.value !== 'custom' && option.frequency === 'one_time' && Number(option.amount) <= amount)
                    .sort((a, b) => Number(b.amount) - Number(a.amount))[0] || null;
            },
            customBenefitSummary() {
                const amount = Number(this.customAmount);
                const tier = this.customBenefitOption();
                if (!tier || !Number.isFinite(amount) || amount <= 0) return '';

                const format = (value) => Number(value).toLocaleString('en-AU', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                const highestAmount = Math.max(...this.options
                    .filter((option) => option.value !== 'custom' && option.frequency === 'one_time')
                    .map((option) => Number(option.amount)));
                const packageDescription = `${tier.label} package (AUD $${format(tier.amount)})`;

                if (amount > highestAmount) {
                    return `Your AUD $${format(amount)} sponsorship includes at least the ${packageDescription} benefits. For larger sponsorships, we may also discuss additional recognition with you.`;
                }

                return `Your AUD $${format(amount)} sponsorship qualifies for the ${packageDescription}; its listed benefits are included.`;
            },
            customBenefitDetails() {
                const tier = this.customBenefitOption();
                if (!tier) return [];

                const details = [];
                if (tier.recognition) details.push('Listing on the Sponsors page');
                if (tier.homepageRecognition) details.push(tier.homepageRecognitionText);
                return details.concat(tier.benefits || []);
            },
            recognitionEnabled() {
                if (this.selectedChoice === 'custom') return this.customBenefitOption()?.recognition === true;
                return this.selectedOption()?.recognition === true;
            },
            init() {
                if (!this.availableFrequencies().includes(this.frequency)) this.frequency = 'one_time';

                if (!this.selectedChoice && this.optionId) this.selectedChoice = String(this.optionId);
                const selected = this.selectedOption();
                if (!selected) {
                    this.selectedChoice = '';
                    this.optionId = '';
                    this.customAmount = '';
                    return;
                }
                this.optionId = selected.value === 'custom' ? '' : String(selected.value);
                if (selected.value !== 'custom') this.customAmount = '';
            },
            changeFrequency(frequency) {
                const previous = this.selectedOption();
                this.frequency = this.availableFrequencies().includes(frequency) ? frequency : 'one_time';
                if (previous && previous.value !== 'custom') {
                    const matchingOption = this.availableOptions().find((option) => option.amount === previous.amount && option.label === previous.label);
                    if (matchingOption) {
                        this.selectedChoice = String(matchingOption.value);
                        this.optionId = String(matchingOption.value);
                        return;
                    }
                }
                this.selectedChoice = '';
                this.optionId = '';
                this.customAmount = '';
            },
            changeAmount(event) {
                this.selectedChoice = event.target.value;
                const selected = this.selectedOption();
                this.optionId = selected && selected.value !== 'custom' ? String(selected.value) : '';
                if (!selected || selected.value !== 'custom') this.customAmount = '';
            },
        };
    }
</script>
