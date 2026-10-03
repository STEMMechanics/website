<?php

namespace Database\Seeders;

use App\Models\ManualSponsorSupport;
use App\Models\Sponsor;
use App\Models\SponsorshipProject;
use App\Models\SponsorshipRecognitionLevel;
use Illuminate\Database\Seeder;

class SponsorshipDemoSeeder extends Seeder
{
    /**
     * Add clearly labelled public records so the recognition layout can be previewed.
     * This seeder is intentionally separate from DatabaseSeeder and can be run again safely.
     */
    public function run(): void
    {
        $this->call(SponsorshipProjectSeeder::class);

        $project = SponsorshipProject::query()->where('is_primary', true)->firstOrFail();
        $levels = SponsorshipRecognitionLevel::query()
            ->whereNull('project_id')
            ->where('enabled', true)
            ->get()
            ->keyBy('name');

        $demoSponsors = [
            [
                'email' => 'demo.major.sponsor@stemmechanics.test',
                'contact_name' => 'Demo Major Sponsor contact',
                'sponsor_type' => 'organisation',
                'company_name' => 'Demo Major Sponsor',
                'display_name' => null,
                'level' => 'Major Sponsors',
                'value_amount' => 2500,
            ],
            [
                'email' => 'demo.business.sponsor@stemmechanics.test',
                'contact_name' => 'Demo Business Sponsor contact',
                'sponsor_type' => 'organisation',
                'company_name' => 'Demo Business Sponsor',
                'display_name' => null,
                'level' => 'Sponsors',
                'value_amount' => 500,
            ],
            [
                'email' => 'demo.community.supporter@stemmechanics.test',
                'contact_name' => 'Demo Community Supporter contact',
                'sponsor_type' => 'organisation',
                'company_name' => 'Demo Community Supporter',
                'display_name' => null,
                'level' => 'Supporters',
                'value_amount' => 75,
            ],
            [
                'email' => 'demo.individual.supporter@stemmechanics.test',
                'contact_name' => 'Demo Individual Supporter',
                'sponsor_type' => 'individual',
                'company_name' => null,
                'display_name' => 'Demo Individual Supporter',
                'level' => 'Supporters',
                'value_amount' => 25,
            ],
        ];

        foreach ($demoSponsors as $demo) {
            $level = $levels->get($demo['level']);
            if (! $level) {
                continue;
            }

            $sponsor = Sponsor::query()->updateOrCreate(
                ['email' => $demo['email']],
                [
                    'contact_name' => $demo['contact_name'],
                    'sponsor_type' => $demo['sponsor_type'],
                    'company_name' => $demo['company_name'],
                    'country' => 'Australia',
                    'recognition_public' => true,
                    'display_name' => $demo['display_name'],
                    'recognition_approved_at' => now(),
                    'recognition_approval_notified_at' => null,
                ],
            );

            ManualSponsorSupport::query()->updateOrCreate(
                [
                    'sponsor_id' => $sponsor->id,
                    'project_id' => $project->id,
                    'support_method' => 'other_benefit',
                ],
                [
                    'recognition_level_id' => $level->id,
                    'support_description' => 'Demo sponsorship seed record',
                    'value_amount' => $demo['value_amount'],
                    'currency' => 'AUD',
                    'starts_on' => now()->startOfMonth()->toDateString(),
                    'ends_on' => null,
                    'internal_note' => 'Seeded for sponsorship page preview; safe to remove after review.',
                ],
            );
        }
    }
}
