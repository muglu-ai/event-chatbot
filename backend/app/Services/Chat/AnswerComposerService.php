<?php

namespace App\Services\Chat;

class AnswerComposerService
{
    public function compose(string $topicId, string $question, string $rawAnswer, array $history = []): string
    {
        $composed = match ($topicId) {
            'registration' => $this->registration($question),
            'visa' => $this->visa($question, $history),
            'login' => $this->login($question),
            'dates' => $this->dates($question),
            'venue' => $this->venue($question),
            'contact' => $this->contact($question),
            'about' => $this->about($question),
            default => $this->polish($rawAnswer, $question),
        };

        return $this->ensureComplete($composed);
    }

    private function registration(string $question): string
    {
        $q = strtolower($question);

        if (preg_match('/\b(closed|open|available|status)\b/', $q)) {
            return 'Visitor registration for SEMICON India 2026 is currently closed. '
                .'Please check semiconindia.org or portal.semiconindia.org for updates on when it reopens.';
        }

        if (preg_match('/\b(cost|free|fee|price|ticket)\b/', $q)) {
            return 'Visitor registration details, including any fees, are managed on portal.semiconindia.org. '
                .'Registration is closed at the moment — visit semiconindia.org for the latest information.';
        }

        return 'To attend as a visitor, register at portal.semiconindia.org with your organisation name, '
            .'full name, email, and mobile number. '
            .'Please note: visitor registration is currently closed, so keep an eye on semiconindia.org for reopening announcements.';
    }

    private function visa(string $question, array $history): string
    {
        $recent = $this->recentContext($history);

        if (str_contains($recent, 'register') || preg_match('/\b(after|once)\b.*\bregister/', strtolower($question))) {
            return 'After you register, log in to portal.semiconindia.org and request an invitation letter from your dashboard. '
                .'You can then apply for a Conference visa at indianvisaonline.gov.in/visa/Registration.';
        }

        return 'International visitors should first complete visitor registration, then request an invitation letter from the portal dashboard. '
            .'Apply for your Conference visa at indianvisaonline.gov.in/visa/Registration.';
    }

    private function login(string $question): string
    {
        if (preg_match('/\b(forgot|reset|password)\b/i', $question)) {
            return 'Use the Forgot Password link on portal.semiconindia.org. '
                .'If you still need help, contact support at support.mmaportal@interlinks.in or +91 9834235670.';
        }

        return 'Sign in at portal.semiconindia.org with the email and password you used when registering. '
            .'Need help? Email support.mmaportal@interlinks.in or call +91 9834235670.';
    }

    private function dates(string $question): string
    {
        if (preg_match('/\b(hour|time|open|close)\b/i', $question)) {
            return 'SEMICON India 2026 runs 17–19 September 2026 at Yashobhoomi, New Delhi. '
                .'Exact daily opening hours will be published on semiconindia.org closer to the event.';
        }

        return 'SEMICON India 2026 is on 17–19 September 2026 at Yashobhoomi (IICC), New Delhi. '
            .'The full program schedule will be posted on semiconindia.org soon.';
    }

    private function venue(string $question): string
    {
        return 'The event is at Yashobhoomi — the India International Convention & Expo Centre in New Delhi. '
            .'Travel and hotel guidance is on the Travel & Hotels section of semiconindia.org.';
    }

    private function contact(string $question): string
    {
        if (preg_match('/\b(tech|portal|login|password)\b/i', $question)) {
            return 'For portal or registration technical support, email support.mmaportal@interlinks.in or call +91 9834235670.';
        }

        return 'For event enquiries, email semiconindia@semi.org. '
            .'For registration portal support, contact support.mmaportal@interlinks.in or +91 9834235670.';
    }

    private function about(string $question): string
    {
        return 'SEMICON India 2026 brings together the global semiconductor ecosystem on 17–19 September at Yashobhoomi, New Delhi. '
            .'The theme is “Silicon to Systems: Building the Ecosystem”, organised by ISM and SEMI with 500+ exhibitors from 20+ countries.';
    }

    private function polish(string $rawAnswer, string $question): string
    {
        $answer = trim($rawAnswer);

        if (preg_match('/^(the |venue:|register at)/i', $answer)) {
            return ucfirst($answer);
        }

        if (! preg_match('/[.!?]$/', $answer)) {
            $answer .= '.';
        }

        return $answer;
    }

    private function ensureComplete(string $text): string
    {
        $text = trim(preg_replace('/\s+/', ' ', $text) ?? $text);

        if (! preg_match('/[.!?]$/', $text)) {
            $text .= '.';
        }

        return $text;
    }

    private function recentContext(array $history): string
    {
        return collect($history)
            ->take(-4)
            ->pluck('content')
            ->implode(' ');
    }
}
