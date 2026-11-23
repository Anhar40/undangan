<?php

namespace App\Http\Controllers\Site;

use App\Enums\InvitationStatus;
use App\Http\Controllers\Controller;
use App\Models\Invitation;
use App\Models\Template;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

class SitemapController extends Controller
{
    public function __invoke(): Response
    {
        $xml = Cache::remember('sitemap.xml', now()->addMinutes(60), function (): string {
            $base = rtrim((string) config('app.url', url('/')), '/');
            $urls = [];

            $urls[] = [
                'loc' => $base.'/',
                'lastmod' => now()->toAtomString(),
                'changefreq' => 'weekly',
                'priority' => '1.0',
            ];
            $urls[] = [
                'loc' => $base.'/templates',
                'lastmod' => now()->toAtomString(),
                'changefreq' => 'weekly',
                'priority' => '0.8',
            ];

            Template::query()->where('status', 'active')->orderBy('id')->get(['slug', 'updated_at'])
                ->each(function (Template $template) use ($base, &$urls): void {
                    $urls[] = [
                        'loc' => $base.'/templates/'.$template->slug.'/sample',
                        'lastmod' => optional($template->updated_at)->toAtomString(),
                        'changefreq' => 'monthly',
                        'priority' => '0.5',
                    ];
                });

            Invitation::query()
                ->where('status', InvitationStatus::Active->value)
                ->where(function ($q) {
                    $q->whereNull('active_expires_at')->orWhere('active_expires_at', '>', now());
                })
                ->orderBy('id')
                ->get(['slug', 'updated_at'])
                ->each(function (Invitation $invitation) use ($base, &$urls): void {
                    $urls[] = [
                        'loc' => $base.'/u/'.$invitation->slug,
                        'lastmod' => optional($invitation->updated_at)->toAtomString(),
                        'changefreq' => 'weekly',
                        'priority' => '0.7',
                    ];
                });

            $items = collect($urls)->unique('loc')->map(function (array $u): string {
                $loc = htmlspecialchars($u['loc'], ENT_XML1);
                $lastmod = isset($u['lastmod']) ? '<lastmod>'.htmlspecialchars((string) $u['lastmod'], ENT_XML1).'</lastmod>' : '';
                $changefreq = isset($u['changefreq']) ? '<changefreq>'.htmlspecialchars((string) $u['changefreq'], ENT_XML1).'</changefreq>' : '';
                $priority = isset($u['priority']) ? '<priority>'.htmlspecialchars((string) $u['priority'], ENT_XML1).'</priority>' : '';

                return "<url><loc>{$loc}</loc>{$lastmod}{$changefreq}{$priority}</url>";
            })->implode('');

            return '<?xml version="1.0" encoding="UTF-8"?>'
                .'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'
                .$items
                .'</urlset>';
        });

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }
}
