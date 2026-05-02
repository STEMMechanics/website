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
                    'url' => 'https://link.storjshare.io/raw/juqc33rucmbwsdlx5lnna26e66fq/cdn/2205-cm-creative-complete.zip',
                    'magnet_url' => 'magnet:?xt=urn:btih:cb13187f823a8a6c07a367fa49bb837808600417&dn=2205-cm-creative-complete.zip&tr=udp%3A%2F%2Ftracker.opentrackr.org%3A1337%2Fannounce&tr=udp%3A%2F%2Fopen.demonii.com%3A1337%2Fannounce&tr=udp%3A%2F%2Fopen.stealth.si%3A80%2Fannounce&tr=udp%3A%2F%2Ftracker.torrent.eu.org%3A451%2Fannounce&tr=udp%3A%2F%2Ftracker.qu.ax%3A6969%2Fannounce&tr=udp%3A%2F%2Ftracker.bittor.pw%3A1337%2Fannounce&tr=udp%3A%2F%2Ftracker.004430.xyz%3A1337%2Fannounce&tr=udp%3A%2F%2Ftracker-udp.gbitt.info%3A80%2Fannounce&tr=udp%3A%2F%2Ft.overflow.biz%3A6969%2Fannounce&tr=udp%3A%2F%2Fbittorrent-tracker.e-n-c-r-y-p-t.net%3A1337%2Fannounce&tr=udp%3A%2F%2F6ahddutb1ucc3cp.ru%3A6969%2Fannounce&tr=https%3A%2F%2Ftracker.zhuqiy.com%3A443%2Fannounce&tr=https%3A%2F%2Ftracker.yemekyedim.com%3A443%2Fannounce&tr=https%3A%2F%2Ftracker.pmman.tech%3A443%2Fannounce&tr=https%3A%2F%2Ftracker.nekomi.cn%3A443%2Fannounce&tr=https%3A%2F%2Ftracker.moeblog.cn%3A443%2Fannounce&tr=https%3A%2F%2Ftracker.ghostchu-services.top%3A443%2Fannounce&tr=https%3A%2F%2Ftracker.gcrenwp.top%3A443%2Fannounce&tr=https%3A%2F%2Ftracker.bt4g.com%3A443%2Fannounce&tr=https%3A%2F%2Ftr.nyacat.pw%3A443%2Fannounce',
                ],
                [
                    'label' => 'Creative archive',
                    'filename' => '2205-cm-creative.zip',
                    'description' => 'A smaller creative-only archive of just the worlds.',
                    'size' => '3.59 GB',
                    'url' => 'https://link.storjshare.io/raw/juc3xumjlc6uueb3dqnp5hhex3aq/cdn/2205-cm-creative.zip',
                    'magnet_url' => 'magnet:?xt=urn:btih:e1366326a8e89d04af1c4b334cfd5fab0be88e0e&dn=2205-cm-creative.zip&tr=udp%3A%2F%2Ftracker.opentrackr.org%3A1337%2Fannounce&tr=udp%3A%2F%2Fopen.demonii.com%3A1337%2Fannounce&tr=udp%3A%2F%2Fopen.stealth.si%3A80%2Fannounce&tr=udp%3A%2F%2Ftracker.torrent.eu.org%3A451%2Fannounce&tr=udp%3A%2F%2Ftracker.qu.ax%3A6969%2Fannounce&tr=udp%3A%2F%2Ftracker.bittor.pw%3A1337%2Fannounce&tr=udp%3A%2F%2Ftracker.004430.xyz%3A1337%2Fannounce&tr=udp%3A%2F%2Ftracker-udp.gbitt.info%3A80%2Fannounce&tr=udp%3A%2F%2Ft.overflow.biz%3A6969%2Fannounce&tr=udp%3A%2F%2Fbittorrent-tracker.e-n-c-r-y-p-t.net%3A1337%2Fannounce&tr=udp%3A%2F%2F6ahddutb1ucc3cp.ru%3A6969%2Fannounce&tr=https%3A%2F%2Ftracker.zhuqiy.com%3A443%2Fannounce&tr=https%3A%2F%2Ftracker.yemekyedim.com%3A443%2Fannounce&tr=https%3A%2F%2Ftracker.pmman.tech%3A443%2Fannounce&tr=https%3A%2F%2Ftracker.nekomi.cn%3A443%2Fannounce&tr=https%3A%2F%2Ftracker.moeblog.cn%3A443%2Fannounce&tr=https%3A%2F%2Ftracker.ghostchu-services.top%3A443%2Fannounce&tr=https%3A%2F%2Ftracker.gcrenwp.top%3A443%2Fannounce&tr=https%3A%2F%2Ftracker.bt4g.com%3A443%2Fannounce&tr=https%3A%2F%2Ftr.nyacat.pw%3A443%2Fannounce',
                ],
                [
                    'label' => 'Survival archive',
                    'filename' => '2103-cm-survival.zip',
                    'description' => 'A archive of the survival server.',
                    'size' => '6.09 GB',
                    'url' => 'https://link.storjshare.io/raw/jvtjyq2wwdy3a2jqlnsybfqdodka/cdn/2103-cm-survival.zip',
                    'magnet_url' => 'magnet:?xt=urn:btih:e7f6b129ea7d5bd7f9df35387cbd0ef34f0d5a03&dn=2103-cm-survival.zip&tr=udp%3A%2F%2Ftracker.opentrackr.org%3A1337%2Fannounce&tr=udp%3A%2F%2Fopen.demonii.com%3A1337%2Fannounce&tr=udp%3A%2F%2Fopen.stealth.si%3A80%2Fannounce&tr=udp%3A%2F%2Ftracker.torrent.eu.org%3A451%2Fannounce&tr=udp%3A%2F%2Ftracker.qu.ax%3A6969%2Fannounce&tr=udp%3A%2F%2Ftracker.bittor.pw%3A1337%2Fannounce&tr=udp%3A%2F%2Ftracker.004430.xyz%3A1337%2Fannounce&tr=udp%3A%2F%2Ftracker-udp.gbitt.info%3A80%2Fannounce&tr=udp%3A%2F%2Ft.overflow.biz%3A6969%2Fannounce&tr=udp%3A%2F%2Fbittorrent-tracker.e-n-c-r-y-p-t.net%3A1337%2Fannounce&tr=udp%3A%2F%2F6ahddutb1ucc3cp.ru%3A6969%2Fannounce&tr=https%3A%2F%2Ftracker.zhuqiy.com%3A443%2Fannounce&tr=https%3A%2F%2Ftracker.yemekyedim.com%3A443%2Fannounce&tr=https%3A%2F%2Ftracker.pmman.tech%3A443%2Fannounce&tr=https%3A%2F%2Ftracker.nekomi.cn%3A443%2Fannounce&tr=https%3A%2F%2Ftracker.moeblog.cn%3A443%2Fannounce&tr=https%3A%2F%2Ftracker.ghostchu-services.top%3A443%2Fannounce&tr=https%3A%2F%2Ftracker.gcrenwp.top%3A443%2Fannounce&tr=https%3A%2F%2Ftracker.bt4g.com%3A443%2Fannounce&tr=https%3A%2F%2Ftr.nyacat.pw%3A443%2Fannounce',
                ],
            ],
        ]);
    }
}
