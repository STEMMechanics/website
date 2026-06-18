<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class CairnsMinecraftController extends Controller
{
    public function index(): View
    {
        return view('cairns-minecraft', [
            'downloads' => [
                [
                    'label' => 'Full archive',
                    'filename' => '2205-cm-creative-complete.zip',
                    'description' => 'The larger final export of the creative world server complete with plugins and JARs.',
                    'size' => '6.71 GB',
                    'url' => 'https://www.stemmechanics.com.au/downloads/2205-cm-creative-complete.zip',
                    'magnet_url' => 'magnet:?xt=urn:btih:a5204ad169b365015c73f1519b3130dfbaa34fb7&xt=urn:btmh:122077728dfa1f8fd2444eddcbd9b39cfa0d0b08ba4e27fe2c7c7039c86efb5f81a0&dn=2205-cm-creative-complete.zip&xl=6712439230&tr=udp%3A%2F%2Ftracker.torrent.eu.org%3A451%2Fannounce&tr=udp%3A%2F%2Ftracker.opentrackr.org%3A1337%2Fannounce&tr=udp%3A%2F%2Fopen.stealth.si%3A80%2Fannounce&tr=udp%3A%2F%2Fexodus.desync.com%3A6969%2Fannounce',
                ],
                [
                    'label' => 'Creative archive',
                    'filename' => '2205-cm-creative.zip',
                    'description' => 'A smaller creative-only archive of just the worlds.',
                    'size' => '3.59 GB',
                    'url' => 'https://www.stemmechanics.com.au/downloads/2205-cm-creative.zip',
                    'magnet_url' => 'magnet:?xt=urn:btih:c31a0ef36de080a6e4b5c171209e96fb4deea000&xt=urn:btmh:12200a49cb3c3a6572bda5fa6add661330014ee26ef3e8013bd165541bb6d37e1103&dn=2205-cm-creative.zip&xl=3585899092&tr=udp%3A%2F%2Fexodus.desync.com%3A6969%2Fannounce&tr=udp%3A%2F%2Fopen.stealth.si%3A80%2Fannounce&tr=udp%3A%2F%2Ftracker.torrent.eu.org%3A451%2Fannounce&tr=udp%3A%2F%2Ftracker.opentrackr.org%3A1337%2Fannounce',
                ],
                [
                    'label' => 'Survival archive',
                    'filename' => '2103-cm-survival.zip',
                    'description' => 'A archive of the survival server.',
                    'size' => '6.09 GB',
                    'url' => 'https://www.stemmechanics.com.au/downloads/2103-cm-survival.zip',
                    'magnet_url' => 'magnet:?xt=urn:btih:9c2be6e4b19feabe9aa2d2be9f043921dc1110a4&xt=urn:btmh:122059a1af2b487f0ed600e62f2c6ad3baae748b4116c1c2b3728601d1bb39cb0763&dn=2103-cm-survival.zip&xl=6094337868&tr=udp%3A%2F%2Ftracker.torrent.eu.org%3A451%2Fannounce&tr=udp%3A%2F%2Fexodus.desync.com%3A6969%2Fannounce&tr=udp%3A%2F%2Fopen.stealth.si%3A80%2Fannounce&tr=udp%3A%2F%2Ftracker.opentrackr.org%3A1337%2Fannounce',
                ],
            ],
        ]);
    }
}
