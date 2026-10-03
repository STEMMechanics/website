<?php

namespace Database\Seeders;

use App\Models\SponsorshipOption;
use App\Models\SponsorshipProject;
use App\Models\SponsorshipRecognitionLevel;
use Illuminate\Database\Seeder;

class SponsorshipProjectSeeder extends Seeder
{
    public function run(): void
    {
        $retiredProject = SponsorshipProject::query()->where('slug', 'craftarr')->first();
        if ($retiredProject) {
            $retiredProject->update(['enabled' => false, 'sponsorship_enabled' => false]);
            SponsorshipOption::query()->where('project_id', $retiredProject->id)->update(['enabled' => false]);
        }

        $supportAreas = [
            [
                'slug' => 'stemmechanics',
                'name' => 'STEMMechanics',
                'tagline' => 'STEM programs, regional delivery and online learning opportunities.',
                'description' => 'Sponsorship helps STEMMechanics offer more free and low-cost STEM experiences, reach regional communities and give young people access to equipment, materials and ongoing learning opportunities. It also supports the technology and open-source projects that help this work continue.',
                'project_url' => 'https://stemmechanics.com.au',
                'enabled' => true,
                'sponsorship_enabled' => true,
                'is_primary' => true,
                'currency' => 'AUD',
                'allow_custom_amount' => true,
                'custom_amount_min' => 3,
                'custom_amount_max' => 10000,
            ],
        ];

        foreach ($supportAreas as $attributes) {
            $project = $attributes['is_primary']
                ? (SponsorshipProject::query()->where('is_primary', true)->first()
                    ?? SponsorshipProject::query()->firstOrCreate(['slug' => $attributes['slug']], $attributes))
                : SponsorshipProject::query()->firstOrCreate(['slug' => $attributes['slug']], $attributes);
            $project->fill($attributes)->save();

            SponsorshipOption::query()
                ->where('project_id', $project->id)
                ->where('checkout_group', 'business')
                ->where('frequency', 'one_time')
                ->whereIn('amount', [50, 100, 250])
                ->update(['enabled' => false]);

            SponsorshipOption::query()
                ->where('project_id', $project->id)
                ->whereIn('checkout_group', ['business', 'both'])
                ->where('frequency', 'monthly')
                ->whereIn('amount', [50, 100, 250])
                ->update(['enabled' => false]);

            $communitySupportAmounts = [
                3 => ['label' => 'Supporter', 'checkout_group' => SponsorshipOption::CHECKOUT_GROUP_COMMUNITY_SUPPORT, 'recognition_enabled' => false],
                5 => ['label' => 'Supporter', 'checkout_group' => SponsorshipOption::CHECKOUT_GROUP_COMMUNITY_SUPPORT, 'recognition_enabled' => false],
                10 => ['label' => 'Supporter', 'checkout_group' => SponsorshipOption::CHECKOUT_GROUP_COMMUNITY_SUPPORT, 'recognition_enabled' => false],
                20 => ['label' => 'Supporter', 'checkout_group' => SponsorshipOption::CHECKOUT_GROUP_COMMUNITY_SUPPORT, 'recognition_enabled' => false],
            ];
            $businessOneTimeAmounts = [
                300 => ['label' => 'Community Supporter', 'benefits' => null],
                500 => ['label' => 'Workshop Partner', 'benefits' => 'One dedicated social media thank-you post featuring and tagging your organisation.'],
                1000 => ['label' => 'Program Partner', 'benefits' => "Public recognition across STEMMechanics programs and community events for 3 months from payment.\nOne dedicated social media thank-you post featuring and tagging your organisation."],
                2000 => ['label' => 'Major Sponsor', 'benefits' => "Public recognition across STEMMechanics programs and community events for 3 months from payment.\nOne dedicated social media thank-you post featuring and tagging your organisation.\nPrize support for our monthly STEMCraft, construction and coding challenges, with acknowledgement in award announcements."],
            ];
            $businessMonthlyAmounts = [
                300 => ['label' => 'Community Supporter', 'benefits' => null],
                500 => ['label' => 'Workshop Partner', 'benefits' => 'One dedicated social media thank-you post featuring and tagging your organisation.'],
                1000 => ['label' => 'Program Partner', 'benefits' => "Public recognition across STEMMechanics programs and community events while your monthly sponsorship is active.\nOne dedicated social media thank-you post featuring and tagging your organisation."],
                2000 => ['label' => 'Major Sponsor', 'benefits' => "Public recognition across STEMMechanics programs and community events while your monthly sponsorship is active.\nOne dedicated social media thank-you post featuring and tagging your organisation.\nPrize support for our monthly STEMCraft, construction and coding challenges while your monthly sponsorship is active, with acknowledgement in award announcements."],
            ];

            foreach ($communitySupportAmounts as $amount => $optionConfig) {
                foreach (['one_time', 'monthly'] as $frequency) {
                    SponsorshipOption::query()->updateOrCreate(
                        ['project_id' => $project->id, 'frequency' => $frequency, 'amount' => $amount],
                        [
                            'label' => $optionConfig['label'],
                            'checkout_group' => $optionConfig['checkout_group'],
                            'recognition_enabled' => $optionConfig['recognition_enabled'],
                            'enabled' => true,
                            'sort_order' => $amount,
                        ]
                    );

                }
            }

            foreach ($businessOneTimeAmounts as $amount => $optionConfig) {
                SponsorshipOption::query()->firstOrCreate(
                    ['project_id' => $project->id, 'frequency' => 'one_time', 'amount' => $amount],
                    [
                        'label' => $optionConfig['label'],
                        'additional_benefits' => $optionConfig['benefits'],
                        'checkout_group' => 'business',
                        'recognition_enabled' => true,
                        'enabled' => true,
                        'sort_order' => $amount,
                    ]
                );
            }

            foreach ($businessMonthlyAmounts as $amount => $optionConfig) {
                $attributes = [
                    'label' => $optionConfig['label'],
                    'checkout_group' => 'business',
                    'recognition_enabled' => true,
                    'enabled' => true,
                    'sort_order' => $amount,
                ];
                $monthlyOption = SponsorshipOption::query()->updateOrCreate(
                    ['project_id' => $project->id, 'frequency' => 'monthly', 'amount' => $amount],
                    $attributes
                );
                if (array_key_exists('benefits', $optionConfig) && trim((string) $monthlyOption->additional_benefits) === '') {
                    $monthlyOption->additional_benefits = $optionConfig['benefits'];
                    $monthlyOption->save();
                }
            }
        }

        foreach ([
            ['name' => 'Major Sponsors', 'minimum_total' => 2000, 'sort_order' => 0],
            ['name' => 'Sponsors', 'minimum_total' => 100, 'sort_order' => 1],
            ['name' => 'Supporters', 'minimum_total' => 0, 'sort_order' => 2],
        ] as $level) {
            SponsorshipRecognitionLevel::query()->firstOrCreate(
                ['project_id' => null, 'name' => $level['name']],
                ['minimum_total' => $level['minimum_total'], 'sort_order' => $level['sort_order'], 'enabled' => true]
            );
        }
    }
}
