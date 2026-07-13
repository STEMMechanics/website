<?php

namespace App\Support;

use App\Models\SiteOption;

class StemcraftFaqs
{
    public const OPTION = 'stemcraft.faqs.items';

    /**
     * @return list<array{question: string, answer: string, show_on_index: bool}>
     */
    public static function all(): array
    {
        $raw = SiteOption::value(self::OPTION, static::defaultJson());
        $decoded = json_decode((string) $raw, true);

        if (! is_array($decoded)) {
            $decoded = static::defaultItems();
        }

        return collect($decoded)
            ->map(function ($item): array {
                $item = is_array($item) ? $item : [];

                return [
                    'question' => trim((string) ($item['question'] ?? '')),
                    'answer' => trim((string) ($item['answer'] ?? '')),
                    'show_on_index' => filter_var($item['show_on_index'] ?? false, FILTER_VALIDATE_BOOL),
                ];
            })
            ->filter(fn (array $item): bool => $item['question'] !== '' && $item['answer'] !== '')
            ->values()
            ->all();
    }

    /**
     * @return list<array{question: string, answer: string, show_on_index: bool}>
     */
    public static function indexItems(): array
    {
        return collect(static::all())
            ->filter(fn (array $item): bool => $item['show_on_index'])
            ->values()
            ->all();
    }

    /**
     * @return non-empty-list<array{question: string, answer: string, show_on_index: bool}>
     */
    public static function defaultItems(): array
    {
        return [
            [
                'question' => 'What is STEMCraft?',
                'answer' => 'STEMCraft is an online creative building space connected to STEMMechanics workshops and programs.',
                'show_on_index' => true,
            ],
            [
                'question' => 'Who can join?',
                'answer' => 'STEMCraft is designed for young makers, families, schools and libraries interested in creative building and STEM learning.',
                'show_on_index' => true,
            ],
            [
                'question' => 'What age is it suitable for?',
                'answer' => 'It is best suited to school-aged participants who can use Minecraft with suitable parent, carer, teacher or facilitator support.',
                'show_on_index' => false,
            ],
            [
                'question' => 'Is it free?',
                'answer' => 'STEMCraft is included when it forms part of a STEMMechanics workshop or program. Any program-specific costs are explained with that workshop or activity.',
                'show_on_index' => true,
            ],
            [
                'question' => 'What software is required?',
                'answer' => 'Participants need Minecraft Java or Bedrock, depending on their device, and a Minecraft account that can connect to multiplayer worlds.',
                'show_on_index' => true,
            ],
            [
                'question' => 'How do I join?',
                'answer' => 'Use the join guide for the server address, setup notes and support details.',
                'show_on_index' => true,
            ],
            [
                'question' => 'Is it moderated?',
                'answer' => 'STEMCraft is supported by STEMMechanics. Participants follow clear community expectations and can ask for help when needed.',
                'show_on_index' => true,
            ],
            [
                'question' => 'Can schools or libraries participate?',
                'answer' => 'Yes. Schools and libraries can contact STEMMechanics to discuss how STEMCraft can support a workshop or program.',
                'show_on_index' => false,
            ],
            [
                'question' => 'What happens if I need help?',
                'answer' => 'Contact STEMMechanics with the participant name, device type and Minecraft username so we can help troubleshoot.',
                'show_on_index' => false,
            ],
            [
                'question' => 'How is participant safety managed?',
                'answer' => 'We keep expectations clear, encourage respectful participation, limit personal information sharing, and respond to support requests or moderation concerns.',
                'show_on_index' => false,
            ],
        ];
    }

    public static function defaultJson(): string
    {
        return json_encode(static::defaultItems(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '[]';
    }
}
