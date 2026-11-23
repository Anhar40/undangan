<?php

namespace App\Http\Controllers\Site;

use App\Enums\InvitationStatus;
use App\Http\Controllers\Controller;
use App\Models\Invitation;
use App\Services\Invitation\ShareMessageService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ShareController extends Controller
{
    public function index(Request $request, ShareMessageService $messages): View
    {
        $raw = trim((string) $request->query('uri', ''));

        abort_if($raw === '', 404);

        $parts = parse_url($raw);

        abort_unless(is_array($parts), 404);

        if (str_starts_with($raw, '/')) {
            abort_unless(! isset($parts['scheme']) && ! isset($parts['host']) && ! isset($parts['user']), 404);
            $link = url($raw);
        } else {
            abort_unless(
                in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true)
                && ! empty($parts['host'])
                && ! isset($parts['user'])
                && $this->isLocalUrl($parts, $request),
                404,
            );
            $link = $raw;
        }

        $parts = parse_url($link);

        abort_unless(is_array($parts), 404);

        $path = (string) ($parts['path'] ?? '');
        $slug = null;

        if (preg_match('~^/u/([a-z0-9-]+)/?$~', $path, $matches)) {
            $slug = $matches[1];
        }

        $invitation = $slug
            ? Invitation::with(['groom', 'bride', 'invitationSettings'])
                ->where('slug', $slug)
                ->where('status', InvitationStatus::Active->value)
                ->first()
            : null;

        parse_str((string) ($parts['query'] ?? ''), $query);
        $guestName = trim((string) ($request->query('to') ?? ($query['to'] ?? '')));
        $baseLink = $this->withoutGuest($link);
        $personalLink = $guestName !== '' ? $this->withGuest($baseLink, $guestName) : $baseLink;

        $share = $invitation
            ? $messages->forInvitation($invitation, $guestName, $personalLink)
            : $messages->generic($guestName, $personalLink);

        $whatsapp = 'https://wa.me/?text=' . rawurlencode($share['message']);
        $facebook = 'https://www.facebook.com/sharer/sharer.php?u=' . rawurlencode($personalLink);

        return view('share.index', [
            'invitation' => $invitation,
            'link' => $personalLink,
            'baseLink' => $baseLink,
            'guestName' => $guestName,
            'couple' => $share['couple'],
            'messageTemplate' => $share['template'],
            'message' => $share['message'],
            'defaultMessage' => $share['default'],
            'whatsapp' => $whatsapp,
            'facebook' => $facebook,
        ]);
    }

    private function withoutGuest(string $link): string
    {
        $parts = parse_url($link);

        if (! is_array($parts)) {
            return $link;
        }

        parse_str((string) ($parts['query'] ?? ''), $query);
        unset($query['to']);

        return $this->rebuild($parts, $query);
    }

    private function withGuest(string $link, string $guestName): string
    {
        $parts = parse_url($link);

        if (! is_array($parts)) {
            return $link;
        }

        parse_str((string) ($parts['query'] ?? ''), $query);
        $query['to'] = $guestName;

        return $this->rebuild($parts, $query);
    }

    /** @param  array<string, mixed>  $parts */
    private function isLocalUrl(array $parts, Request $request): bool
    {
        $configUrl = parse_url((string) config('app.url'));
        $hosts = array_filter([
            $request->getHost(),
            is_array($configUrl) ? ($configUrl['host'] ?? null) : null,
        ]);

        if (! in_array(strtolower((string) $parts['host']), array_map('strtolower', $hosts), true)) {
            return false;
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? 'https'));
        $port = (int) ($parts['port'] ?? ($scheme === 'https' ? 443 : 80));
        $allowedPorts = [$request->getPort()];

        if (is_array($configUrl)) {
            $configPort = isset($configUrl['port'])
                ? (int) $configUrl['port']
                : (strtolower((string) ($configUrl['scheme'] ?? $scheme)) === 'https' ? 443 : 80);
            $allowedPorts[] = $configPort;
        }

        return in_array($port, $allowedPorts, true);
    }

    /**
     * @param  array<string, mixed>  $parts
     * @param  array<string, mixed>  $query
     */
    private function rebuild(array $parts, array $query): string
    {
        $url = ($parts['scheme'] ?? 'https') . '://' . ($parts['host'] ?? '');

        if (isset($parts['port'])) {
            $url .= ':' . $parts['port'];
        }

        $url .= $parts['path'] ?? '';

        if ($query !== []) {
            $url .= '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        }

        if (isset($parts['fragment'])) {
            $url .= '#' . $parts['fragment'];
        }

        return $url;
    }
}
